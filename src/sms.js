import twilio from 'twilio';
import { parsePhoneNumberFromString } from 'libphonenumber-js';

// Normalize a raw phone string to E.164 (+15555550123), which is what Twilio
// requires. Returns null if the number can't be parsed/validated.
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

export function createSms(config) {
  const client = twilio(config.twilio.accountSid, config.twilio.authToken);

  return {
    async send(to, body) {
      const payload = { to, body };
      if (config.twilio.messagingServiceSid) {
        payload.messagingServiceSid = config.twilio.messagingServiceSid;
      } else {
        payload.from = config.twilio.from;
      }
      const message = await client.messages.create(payload);
      return message.sid;
    },
  };
}
