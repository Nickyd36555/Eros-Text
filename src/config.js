import 'dotenv/config';

// Load and validate configuration once at startup. Failing fast here beats
// discovering a missing Twilio credential when the first order comes in.
function required(name) {
  const value = process.env[name];
  if (!value || !value.trim()) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value.trim();
}

function optional(name, fallback) {
  const value = process.env[name];
  return value && value.trim() ? value.trim() : fallback;
}

export const config = {
  port: Number(optional('PORT', '3000')),
  storeName: optional('STORE_NAME', 'Eros Labs'),

  twilio: {
    accountSid: required('TWILIO_ACCOUNT_SID'),
    authToken: required('TWILIO_AUTH_TOKEN'),
    // Prefer a Messaging Service (handles opt-out/STOP and number pooling);
    // fall back to a single from-number. At least one must be present.
    messagingServiceSid: optional('TWILIO_MESSAGING_SERVICE_SID', ''),
    from: optional('TWILIO_FROM', ''),
  },

  woocommerce: {
    webhookSecret: required('WC_WEBHOOK_SECRET'),
  },

  defaultCountry: optional('DEFAULT_COUNTRY', 'US'),
  // WooCommerce order status that means "shipped". Statuses arrive without the
  // "wc-" prefix in webhook payloads, so store it that way.
  shippedStatus: optional('SHIPPED_STATUS', 'completed').replace(/^wc-/, ''),
  dbPath: optional('DB_PATH', 'data/eros-text.sqlite'),
};

if (!config.twilio.messagingServiceSid && !config.twilio.from) {
  throw new Error(
    'Set either TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM so texts have a sender.',
  );
}
