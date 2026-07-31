// Telnyx SMS provider. Uses the Messages REST API directly (Node's built-in
// fetch) so there's no extra SDK dependency.
//
// You can send with either a Messaging Profile (recommended) or a plain "from"
// number — set at least one. https://developers.telnyx.com/api/messaging
export function createTelnyxProvider(cfg) {
  return {
    name: 'telnyx',
    async send(to, body) {
      const payload = { to, text: body };
      if (cfg.messagingProfileId) payload.messaging_profile_id = cfg.messagingProfileId;
      if (cfg.from) payload.from = cfg.from;

      const res = await fetch('https://api.telnyx.com/v2/messages', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${cfg.apiKey}`,
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const detail = await res.text().catch(() => '');
        throw new Error(`Telnyx send failed (HTTP ${res.status}): ${detail}`);
      }
      const data = await res.json();
      return data?.data?.id || '';
    },
  };
}
