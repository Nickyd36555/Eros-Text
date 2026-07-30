# Eros Text

An SMS bot that texts your customers when their **Eros Labs** order is
**placed**, **processing**, and **shipped** — including the tracking number.

It listens for WooCommerce order webhooks and sends texts through Twilio.

> Every message is branded only as *Eros Labs*. The copy never describes or
> hints at the product category.

---

## What it sends

| Trigger (WooCommerce)                     | Text the customer gets |
|-------------------------------------------|------------------------|
| New order (`order.created`)               | "Eros Labs: thanks for your order #1042! We've got it and will text you when it's on the way. Reply STOP to opt out." |
| Status → `processing`                     | "Eros Labs: good news, Sam! Your order #1042 is being processed and prepped for shipment." |
| Status → `completed` *(= shipped)* with tracking | "Eros Labs: your order #1042 has shipped! USPS tracking: 9400… Track it: https://…" |

Each customer gets each text **once** per order — WooCommerce fires updates
repeatedly, but a small local database (SQLite) prevents duplicate texts.

---

## How it works

```
WooCommerce  --(signed webhook)-->  Eros Text (Express)  --(Twilio API)-->  Customer's phone
```

1. WooCommerce sends a signed webhook on every order create/update.
2. The bot verifies the signature (HMAC-SHA256), figures out which event it is,
   and checks it hasn't already texted the customer for that event.
3. It normalizes the phone number to E.164 and sends the text via Twilio.

---

## Setup

### 1. Install

```bash
npm install
cp .env.example .env
```

Fill in `.env` (see the comments in `.env.example`):

- **Twilio** — `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, and either a
  `TWILIO_MESSAGING_SERVICE_SID` (recommended — it handles STOP/opt-out
  automatically) or a single `TWILIO_FROM` number.
- **WooCommerce** — `WC_WEBHOOK_SECRET` (you'll set the same value in step 3).
- Optionally `SHIPPED_STATUS` if you use a custom "shipped" order status
  instead of WooCommerce's default `completed`.

### 2. Run

```bash
npm start
# health check:
curl http://localhost:3000/health
```

Deploy it anywhere that can run Node and is reachable over HTTPS
(Render, Railway, Fly.io, a VPS, etc.). WooCommerce must be able to reach the
`/webhooks/woocommerce` URL. Use a host with a **persistent disk** so the
SQLite dedupe file survives restarts (set `DB_PATH` to that disk).

### 3. Point WooCommerce at it

In WordPress admin: **WooCommerce → Settings → Advanced → Webhooks → Add webhook**.

Create **two** webhooks, both pointing at
`https://YOUR-DEPLOYED-URL/webhooks/woocommerce`:

| Webhook   | Topic           | Secret                          |
|-----------|-----------------|---------------------------------|
| Placed    | `Order created` | same as your `WC_WEBHOOK_SECRET` |
| Status    | `Order updated` | same as your `WC_WEBHOOK_SECRET` |

Set **Status: Active** and **API Version: v3** on each. WooCommerce sends a
ping when you save; the bot answers it so the webhook activates.

### Tracking numbers

The shipped text includes the tracking number when your store records one. It
reads the tracking fields written by the common shipping plugins
**WooCommerce Shipment Tracking** and **Advanced Shipment Tracking (AST)**, and
builds a tracking link for USPS / UPS / FedEx / DHL when the plugin doesn't
provide one. If an order ships without tracking recorded, the customer still
gets a "your order has shipped" text.

---

## Development

```bash
npm test      # run the unit tests
npm run dev   # run with auto-reload
```

## Notes & next steps

- **Opt-out / compliance:** using a Twilio Messaging Service means STOP/HELP are
  handled for you. Keep the "Reply STOP to opt out" line for compliance.
- Ideas for later: delivery/out-for-delivery texts, order-cancelled/refunded
  notices, review requests, and admin alerts.
