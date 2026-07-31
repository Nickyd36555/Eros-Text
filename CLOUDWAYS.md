# Deploying Eros Text on Cloudways

This runs the SMS bot on the same Cloudways account as your WooCommerce store.
Cloudways is a PHP/Apache host, so the plan is: create an app to hold the code,
run the Node bot with **PM2**, and put a small **reverse proxy** in front so a
real HTTPS URL reaches it. Takes about 15 minutes.

You'll do this on the **same server** your store already runs on — just as a
separate application, ideally on its own subdomain like `bot.yourstore.com`.

---

## 1. Create an application to hold the bot

In the Cloudways dashboard:

1. Open your server → **Applications** → **Add Application**.
2. Choose **PHP** (any recent version — it's only the container; the bot runs on
   Node). Name it something like `eros-text`.
3. After it's created, go to the app's **Domain Management** and add a subdomain
   you control, e.g. `bot.yourstore.com`, and point that subdomain's DNS
   (an `A` record) at your server's IP.
4. Go to the app's **SSL Certificate**, choose **Let's Encrypt**, enter that
   subdomain, and install it. This gives you `https://bot.yourstore.com`.

> Using a subdomain keeps the bot completely separate from your storefront.

---

## 2. Get the code onto the server

Grab your SSH/SFTP credentials from **Server Management → Master Credentials**
(or create an app-specific SSH user), then SSH in:

```bash
ssh <master-user>@<your-server-ip>

# Go to your app's web root (name shown in the dashboard under the app):
cd applications/<your-app-folder>/public_html
rm -f index.php          # remove the placeholder PHP file

# Pull the bot in (use your repo URL, or upload via SFTP instead):
git clone <your-repo-url> .
```

### Install Node + PM2

Cloudways servers may not have a recent Node by default. Use `nvm`:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install       # picks up the version in .nvmrc (Node 22)

npm install       # install the bot's dependencies
npm install -g pm2
```

---

## 3. Configure secrets

```bash
cp .env.example .env
nano .env          # fill in Twilio + WooCommerce values
```

At minimum set `SMS_PROVIDER`, your provider's credentials, and
`WC_WEBHOOK_SECRET`:

- **Telnyx** (`SMS_PROVIDER=telnyx`): `TELNYX_API_KEY` + a sender
  (`TELNYX_MESSAGING_PROFILE_ID` or `TELNYX_FROM`).
- **Twilio** (`SMS_PROVIDER=twilio`): `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`
  + a sender (`TWILIO_MESSAGING_SERVICE_SID` or `TWILIO_FROM`).

Leave `PORT=3000` unless that port is already used on the server. The SQLite
dedupe file lives in `data/` inside this folder, so it persists across restarts.

---

## 4. Start the bot with PM2

```bash
pm2 start ecosystem.config.cjs
pm2 save            # remember this process...
pm2 startup         # ...and run the command it prints, so it survives reboots

# verify it's up:
curl http://127.0.0.1:3000/health
# -> {"ok":true,"store":"Eros Labs"}

pm2 logs eros-text  # watch live logs
```

---

## 5. Put the reverse proxy in front

Right now the bot only answers on `127.0.0.1:3000`. To expose it at
`https://bot.yourstore.com`, copy the provided proxy config into place:

```bash
cp deploy/cloudways/htaccess.example public_html/.htaccess
# (run from the app folder; adjust if you're already inside public_html)
```

This forwards all traffic to the Node app. It needs Apache's `mod_proxy` — if
you get a 500 or 403, open a **Cloudways support ticket** and ask them to
**enable `mod_proxy` and `mod_proxy_http`** for the app (a routine request).

Test it:

```bash
curl https://bot.yourstore.com/health
# -> {"ok":true,"store":"Eros Labs"}
```

---

## 6. Point WooCommerce at it

In WordPress admin: **WooCommerce → Settings → Advanced → Webhooks**. Add two
webhooks, both with **Delivery URL** `https://bot.yourstore.com/webhooks/woocommerce`
and the **Secret** matching your `WC_WEBHOOK_SECRET`:

| Webhook | Topic         |
|---------|---------------|
| Placed  | Order created |
| Status  | Order updated |

Set each to **Active**, **API Version v3**, and Save. Place a test order and
watch `pm2 logs eros-text` — you should see the text go out.

---

## Updating later

```bash
cd applications/<your-app-folder>/public_html
git pull
npm install
pm2 restart eros-text
```

## Troubleshooting

- **`curl .../health` works locally but not via the domain** → the reverse
  proxy isn't active; check `.htaccess` is in `public_html` and ask support to
  enable `mod_proxy`.
- **Webhooks return 401** → the `WC_WEBHOOK_SECRET` in `.env` doesn't match the
  secret on the WooCommerce webhook.
- **No text sent, logs say "no valid phone"** → the order's billing phone was
  missing or unparseable; set `DEFAULT_COUNTRY` in `.env` to your main market.
- **Nothing in logs at all** → confirm the WooCommerce webhook status is
  "Active" and the Delivery URL is exactly `.../webhooks/woocommerce`.
