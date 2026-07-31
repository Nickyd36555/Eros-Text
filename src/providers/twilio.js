import twilio from 'twilio';

// Twilio SMS provider. Prefers a Messaging Service (handles STOP/opt-out and
// number pooling); falls back to a single "from" number.
export function createTwilioProvider(cfg) {
  const client = twilio(cfg.accountSid, cfg.authToken);
  return {
    name: 'twilio',
    async send(to, body) {
      const payload = { to, body };
      if (cfg.messagingServiceSid) {
        payload.messagingServiceSid = cfg.messagingServiceSid;
      } else {
        payload.from = cfg.from;
      }
      const message = await client.messages.create(payload);
      return message.sid;
    },
  };
}
