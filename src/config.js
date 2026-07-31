import 'dotenv/config';

// Load and validate configuration once at startup. Failing fast here beats
// discovering a missing credential when the first order comes in.
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

// Which SMS provider to use. Only that provider's credentials are required, so
// you don't need Twilio keys to run on Telnyx (or vice versa).
const provider = optional('SMS_PROVIDER', 'twilio').toLowerCase();
const sms = { provider };

if (provider === 'twilio') {
  sms.twilio = {
    accountSid: required('TWILIO_ACCOUNT_SID'),
    authToken: required('TWILIO_AUTH_TOKEN'),
    // Prefer a Messaging Service (handles STOP/opt-out); fall back to a number.
    messagingServiceSid: optional('TWILIO_MESSAGING_SERVICE_SID', ''),
    from: optional('TWILIO_FROM', ''),
  };
  if (!sms.twilio.messagingServiceSid && !sms.twilio.from) {
    throw new Error(
      'Set either TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM so texts have a sender.',
    );
  }
} else if (provider === 'telnyx') {
  sms.telnyx = {
    apiKey: required('TELNYX_API_KEY'),
    from: optional('TELNYX_FROM', ''),
    messagingProfileId: optional('TELNYX_MESSAGING_PROFILE_ID', ''),
  };
  if (!sms.telnyx.from && !sms.telnyx.messagingProfileId) {
    throw new Error(
      'Set either TELNYX_FROM or TELNYX_MESSAGING_PROFILE_ID so texts have a sender.',
    );
  }
} else {
  throw new Error(`Unknown SMS_PROVIDER "${provider}". Use "twilio" or "telnyx".`);
}

export const config = {
  port: Number(optional('PORT', '3000')),
  storeName: optional('STORE_NAME', 'Eros Labs'),

  sms,

  woocommerce: {
    webhookSecret: required('WC_WEBHOOK_SECRET'),
  },

  defaultCountry: optional('DEFAULT_COUNTRY', 'US'),
  // WooCommerce order status that means "shipped". Statuses arrive without the
  // "wc-" prefix in webhook payloads, so store it that way.
  shippedStatus: optional('SHIPPED_STATUS', 'completed').replace(/^wc-/, ''),
  dbPath: optional('DB_PATH', 'data/eros-text.sqlite'),
};
