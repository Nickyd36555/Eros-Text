import crypto from 'node:crypto';

// WooCommerce signs each webhook with
//   base64( HMAC-SHA256( raw_request_body, webhook_secret ) )
// delivered in the `x-wc-webhook-signature` header. We must hash the exact raw
// bytes, so index.js captures req.rawBody before JSON parsing.
export function verifySignature(rawBody, signature, secret) {
  if (!signature || !rawBody) return false;
  const expected = crypto
    .createHmac('sha256', secret)
    .update(rawBody)
    .digest('base64');

  const a = Buffer.from(expected);
  const b = Buffer.from(signature);
  // Length check first: timingSafeEqual throws on length mismatch.
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

// Map a WooCommerce webhook to one of our notification events, or null if the
// order state isn't one we text about.
//
//   order.created                       -> "placed"
//   order.updated (status processing)   -> "processing"
//   order.updated (status = shipped)    -> "shipped"
//
// Note: an order can be created already in "processing" (instant payment). We
// still only emit "placed" on create; the processing/shipped texts come from
// subsequent updates. Idempotency in the store guards against repeats.
export function deriveEvent(topic, order, shippedStatus) {
  const status = (order?.status || '').replace(/^wc-/, '');

  if (topic === 'order.created') {
    return 'placed';
  }

  if (topic === 'order.updated') {
    if (status === shippedStatus) return 'shipped';
    if (status === 'processing') return 'processing';
  }

  return null;
}

// Pull the customer's phone from the order. Billing phone is the reliable field;
// shipping phone is a fallback some plugins populate.
export function extractPhone(order) {
  return (
    order?.billing?.phone?.trim() ||
    order?.shipping?.phone?.trim() ||
    ''
  );
}

export function extractCountry(order) {
  return (
    order?.billing?.country?.trim() ||
    order?.shipping?.country?.trim() ||
    ''
  );
}

// Human-friendly order number for messages. WooCommerce exposes a display
// `number` (may differ from `id` with sequential-order plugins); fall back to id.
export function orderNumber(order) {
  return String(order?.number || order?.id || '').trim();
}

export function firstName(order) {
  return (order?.billing?.first_name || order?.shipping?.first_name || '').trim();
}

function metaValue(order, key) {
  const meta = Array.isArray(order?.meta_data) ? order.meta_data : [];
  const found = meta.find((m) => m?.key === key);
  return found ? found.value : undefined;
}

// Best-effort tracking-URL builder for common carriers when the shipping plugin
// stored a number + provider but no explicit link.
function carrierUrl(provider, number) {
  if (!number) return '';
  const p = String(provider || '').toLowerCase();
  const n = encodeURIComponent(number);
  if (p.includes('usps')) return `https://tools.usps.com/go/TrackConfirmAction?tLabels=${n}`;
  if (p.includes('ups')) return `https://www.ups.com/track?tracknum=${n}`;
  if (p.includes('fedex')) return `https://www.fedex.com/fedextrack/?trknbr=${n}`;
  if (p.includes('dhl')) return `https://www.dhl.com/us-en/home/tracking.html?tracking-id=${n}`;
  return '';
}

// Extract tracking info from the order. Supports the two most common
// WooCommerce shipping plugins:
//   - "WooCommerce Shipment Tracking" / "Advanced Shipment Tracking (AST)"
//     store an array under `_wc_shipment_tracking_items`.
//   - Some setups store scalar meta: `_tracking_number` / `_tracking_provider`.
// Returns { number, provider, url } with empty strings when nothing is found.
export function extractTracking(order) {
  const items = metaValue(order, '_wc_shipment_tracking_items');
  if (Array.isArray(items) && items.length > 0) {
    // Use the most recent entry (last pushed).
    const item = items[items.length - 1] || {};
    const provider =
      item.formatted_tracking_provider ||
      item.custom_tracking_provider ||
      item.tracking_provider ||
      '';
    const number = item.tracking_number || '';
    const url =
      item.formatted_tracking_link ||
      item.custom_tracking_link ||
      carrierUrl(provider, number);
    return { number, provider, url };
  }

  const number = metaValue(order, '_tracking_number') || '';
  const provider = metaValue(order, '_tracking_provider') || '';
  if (number) {
    return { number, provider, url: carrierUrl(provider, number) };
  }

  return { number: '', provider: '', url: '' };
}
