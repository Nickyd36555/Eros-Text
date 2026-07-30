import { test } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';

import {
  verifySignature,
  deriveEvent,
  extractTracking,
  extractPhone,
  orderNumber,
} from '../src/woocommerce.js';
import { buildMessage } from '../src/messages.js';
import { normalizePhone } from '../src/sms.js';

test('verifySignature accepts a correct HMAC-SHA256 base64 signature', () => {
  const secret = 'shh';
  const body = Buffer.from(JSON.stringify({ id: 1, status: 'processing' }));
  const sig = crypto.createHmac('sha256', secret).update(body).digest('base64');
  assert.equal(verifySignature(body, sig, secret), true);
});

test('verifySignature rejects a tampered body or wrong secret', () => {
  const secret = 'shh';
  const body = Buffer.from(JSON.stringify({ id: 1 }));
  const sig = crypto.createHmac('sha256', secret).update(body).digest('base64');
  assert.equal(verifySignature(Buffer.from('other'), sig, secret), false);
  assert.equal(verifySignature(body, sig, 'wrong'), false);
  assert.equal(verifySignature(body, '', secret), false);
});

test('deriveEvent maps topics + statuses to events', () => {
  const shipped = 'completed';
  assert.equal(deriveEvent('order.created', { status: 'pending' }, shipped), 'placed');
  assert.equal(deriveEvent('order.updated', { status: 'processing' }, shipped), 'processing');
  assert.equal(deriveEvent('order.updated', { status: 'completed' }, shipped), 'shipped');
  // "wc-" prefixed statuses normalize.
  assert.equal(deriveEvent('order.updated', { status: 'wc-completed' }, shipped), 'shipped');
  // Uninteresting states produce no event.
  assert.equal(deriveEvent('order.updated', { status: 'on-hold' }, shipped), null);
  assert.equal(deriveEvent('order.deleted', { status: 'completed' }, shipped), null);
});

test('deriveEvent honors a custom shipped status', () => {
  assert.equal(deriveEvent('order.updated', { status: 'shipped' }, 'shipped'), 'shipped');
  assert.equal(deriveEvent('order.updated', { status: 'completed' }, 'shipped'), null);
});

test('extractTracking reads the Shipment Tracking / AST plugin array', () => {
  const order = {
    meta_data: [
      {
        key: '_wc_shipment_tracking_items',
        value: [
          {
            tracking_provider: 'usps',
            tracking_number: '9400111899223333',
            date_shipped: '2026-07-30',
          },
        ],
      },
    ],
  };
  const t = extractTracking(order);
  assert.equal(t.number, '9400111899223333');
  assert.equal(t.provider, 'usps');
  assert.match(t.url, /usps\.com/);
});

test('extractTracking falls back to scalar meta and empty when absent', () => {
  const scalar = {
    meta_data: [
      { key: '_tracking_number', value: '1Z999AA10123456784' },
      { key: '_tracking_provider', value: 'UPS' },
    ],
  };
  const t = extractTracking(scalar);
  assert.equal(t.number, '1Z999AA10123456784');
  assert.match(t.url, /ups\.com/);

  assert.deepEqual(extractTracking({ meta_data: [] }), {
    number: '',
    provider: '',
    url: '',
  });
});

test('normalizePhone converts to E.164 and rejects junk', () => {
  assert.equal(normalizePhone('(415) 555-2671', 'US'), '+14155552671');
  assert.equal(normalizePhone('+14155552671', 'US'), '+14155552671');
  assert.equal(normalizePhone('not a phone', 'US'), null);
  assert.equal(normalizePhone('', 'US'), null);
});

test('extractPhone/orderNumber pull the right fields', () => {
  const order = {
    number: 'A-1042',
    id: 1042,
    billing: { phone: '4155552671', first_name: 'Sam' },
  };
  assert.equal(extractPhone(order), '4155552671');
  assert.equal(orderNumber(order), 'A-1042');
  assert.equal(orderNumber({ id: 7 }), '7');
});

test('messages are Eros Labs branded and never mention the product category', () => {
  const base = { storeName: 'Eros Labs', firstName: 'Sam', orderNumber: '1042' };
  const placed = buildMessage('placed', base);
  const processing = buildMessage('processing', base);
  const shipped = buildMessage('shipped', {
    ...base,
    tracking: { number: '1Z999', provider: 'UPS', url: 'https://ups.com/track?tracknum=1Z999' },
  });
  const shippedNoTrack = buildMessage('shipped', base);

  for (const msg of [placed, processing, shipped, shippedNoTrack]) {
    assert.ok(msg.startsWith('Eros Labs:'), `should be brand-prefixed: ${msg}`);
    // Guard against any product-category language leaking into customer texts.
    assert.doesNotMatch(msg, /peptide/i, `must not mention peptides: ${msg}`);
  }

  assert.match(placed, /#1042/);
  assert.match(shipped, /1Z999/);
  assert.match(shipped, /UPS/);
  assert.match(shipped, /ups\.com/);
});
