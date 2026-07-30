// PM2 process config — keeps the bot running after you close SSH and restarts
// it on crashes or server reboots (used on Cloudways and most VPS hosts).
//
//   pm2 start ecosystem.config.cjs      # start
//   pm2 save && pm2 startup             # survive server reboots
//   pm2 logs eros-text                  # tail logs
//
// Secrets are NOT listed here — they're read from the .env file at the repo
// root (see .env.example), so nothing sensitive lives in version control.
module.exports = {
  apps: [
    {
      name: 'eros-text',
      script: 'src/index.js',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      env: {
        NODE_ENV: 'production',
      },
    },
  ],
};
