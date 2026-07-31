import { parsePhoneNumberFromString } from 'libphonenumber-js';
import { createTwilioProvider } from './providers/twilio.js';
import { createTelnyxProvider } from './providers/telnyx.js';

// Normalize a raw phone string to E.164 (+15555550123), which is what every SMS
// provider requires. Returns null if the number can't be parsed/validated.
export function normalizePhone(raw, defaultCountry) {
  if (!raw) return null;
  try {
    const parsed = parsePhoneNumberFromString(raw, defaultCountry || undefined);
    if (parsed && parsed.isValid()) {
      return parsed.number; // E.164
    }
  } catch {
    // fall through
  }
  return null;
}

// Pick the SMS provider based on config. Each provider exposes the same
// interface: { name, async send(to, body) -> messageId }. To add another
// provider, drop a file in src/providers/ and add a branch here.
export function createSms(config) {
  const provider = config.sms.provider;
  if (provider === 'telnyx') return createTelnyxProvider(config.sms.telnyx);
  if (provider === 'twilio') return createTwilioProvider(config.sms.twilio);
  throw new Error(`Unknown SMS_PROVIDER "${provider}". Use "twilio" or "telnyx".`);
}
