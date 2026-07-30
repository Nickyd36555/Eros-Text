import express from 'express';
import { config } from './config.js';
import { logger } from './logger.js';
import { createStore } from './store.js';
import { createSms, normalizePhone } from './sms.js';
import { buildMessage } from './messages.js';
import {
  verifySignature,
  deriveEvent,
  extractPhone,
  extractCountry,
  extractTracking,
  orderNumber,
  firstName,
} from './woocommerce.js';

const store = createStore(config.dbPath);
const sms = createSms(config);
const app = express();

// Capture the raw body so we can verify WooCommerce's HMAC signature against
// the exact bytes it hashed, while still getting parsed JSON in req.body.
app.use(
  express.json({
    limit: '2mb',
    verify: (req, _res, buf) => {
      req.rawBody = buf;
    },
  }),
);

app.get('/health', (_req, res) => {
  res.json({ ok: true, store: config.storeName });
});

app.post('/webhooks/woocommerce', async (req, res) => {
  const signature = req.get('x-wc-webhook-signature');
  const topic = req.get('x-wc-webhook-topic') || '';

  // WooCommerce sends an unsigned ping ({ webhook_id }) when a webhook is first
  // saved. Acknowledge it so the webhook activates.
  if (!signature && req.body && req.body.webhook_id && !req.body.id) {
    logger.info('Received WooCommerce webhook ping', { webhookId: req.body.webhook_id });
    return res.status(200).json({ ok: true, ping: true });
  }

  if (!verifySignature(req.rawBody, signature, config.woocommerce.webhookSecret)) {
    logger.warn('Rejected webhook: invalid signature', { topic });
    return res.status(401).json({ ok: false, error: 'invalid signature' });
  }

  const order = req.body;
  const event = deriveEvent(topic, order, config.shippedStatus);

  // Always 200 for signed-but-uninteresting events (e.g. an update that didn't
  // change status), so WooCommerce doesn't retry them forever.
  if (!event) {
    return res.status(200).json({ ok: true, skipped: 'no matching event', topic });
  }

  const id = order.id;
  const num = orderNumber(order);

  // Idempotency: claim the (order, event) before doing any work. If we don't win
  // the claim, another delivery already handled it.
  if (!store.claim(id, event)) {
    return res.status(200).json({ ok: true, skipped: 'already sent', event, orderId: id });
  }

  const to = normalizePhone(extractPhone(order), extractCountry(order) || config.defaultCountry);
  if (!to) {
    logger.warn('No valid phone on order; skipping', { orderId: id, event });
    // Nothing to retry — treat as handled so we don't reprocess endlessly.
    return res.status(200).json({ ok: true, skipped: 'no valid phone', event, orderId: id });
  }

  const body = buildMessage(event, {
    storeName: config.storeName,
    firstName: firstName(order),
    orderNumber: num,
    tracking: event === 'shipped' ? extractTracking(order) : undefined,
  });

  try {
    const sid = await sms.send(to, body);
    store.record(id, event, to, sid);
    logger.info('Sent SMS', { orderId: id, event, sid, to });
    return res.status(200).json({ ok: true, event, orderId: id, sid });
  } catch (err) {
    // Release the claim so a WooCommerce retry (or a later status update) can
    // try again instead of being suppressed by the idempotency guard.
    store.release(id, event);
    logger.error('Failed to send SMS', { orderId: id, event, error: err.message });
    return res.status(500).json({ ok: false, error: 'send failed' });
  }
});

const server = app.listen(config.port, () => {
  logger.info('eros-text listening', { port: config.port, store: config.storeName });
});

function shutdown(signal) {
  logger.info('Shutting down', { signal });
  server.close(() => {
    store.close();
    process.exit(0);
  });
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

export { app };
