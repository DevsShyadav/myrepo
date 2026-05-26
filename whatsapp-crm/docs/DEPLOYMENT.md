# Deployment Guide

## Hostinger Shared Hosting

### File Structure on Server
```
public_html/
├── index.php
├── dashboard.php
├── webhook.php
├── .htaccess
├── config/
├── includes/
├── api/
├── scripts/
├── components/
├── assets/
├── uploads/
└── logs/
```

### Cron Jobs Configuration
In Hostinger hPanel > Advanced > Cron Jobs:

1. **Campaign Processor** (every 3 minutes):
   ```
   */3 * * * * /usr/bin/php /home/u123456789/public_html/scripts/campaign.php
   ```

2. **Retry Failed** (every 10 minutes):
   ```
   */10 * * * * /usr/bin/php /home/u123456789/public_html/scripts/retry_failed.php
   ```

3. **Keep HF Space Alive** (every 5 minutes):
   ```
   */5 * * * * curl -s https://your-space.hf.space/api/health/ping > /dev/null 2>&1
   ```

### Database Configuration
- Create MySQL database via hPanel
- Import schema.sql and seed.sql
- Update config/db.php with credentials

---

## Hugging Face Spaces

### Space Configuration
- SDK: Docker
- Hardware: CPU Basic (free tier works)
- Visibility: Public (needed for webhook access)

### Required Files in Space
```
├── Dockerfile
├── package.json
├── server.js
├── services/
├── middleware/
├── routes/
├── socket/
├── utils/
└── wa_session/
```

### Environment Variables
Set in Space Settings > Repository Secrets:
- `API_KEY` - Authentication key
- `WEBHOOK_URL` - Full webhook URL
- `WEBHOOK_SECRET` - Shared secret
- `ALLOWED_ORIGINS` - Comma-separated domains
- `NODE_ENV` - production
- `PORT` - 7860

### Session Persistence
- HF Spaces have ephemeral filesystem
- WhatsApp session stored in `wa_session/`
- On container restart, QR scan needed again
- Keep-alive cron prevents most restarts
- For persistent sessions, upgrade to persistent storage tier

### Monitoring
- Space logs available in HF dashboard
- Health endpoint: `GET /api/health`
- Ping endpoint: `GET /api/health/ping`

---

## Security Checklist

- [ ] HTTPS enabled on Hostinger
- [ ] .htaccess blocking config/includes/scripts access
- [ ] Strong API_KEY set on both sides
- [ ] WEBHOOK_SECRET matching on both sides
- [ ] Database user has minimal required permissions
- [ ] File permissions: 755 dirs, 644 files
- [ ] PHP display_errors OFF in production
- [ ] ALLOWED_ORIGINS restricted to your domain

---

## Updating

### Update Hostinger Code
1. Upload changed files via FTP/File Manager
2. Clear browser cache

### Update HF Space
1. Push changes to Space repository
2. Space auto-rebuilds (2-3 min)
3. WhatsApp session will need re-scan after rebuild

---

## Performance Notes

- PHP endpoints optimized for shared hosting (low memory)
- PDO with prepared statements (no SQL injection risk)
- Indexed database queries
- Static assets cached via .htaccess
- Gzip compression enabled
- Socket.io with WebSocket transport (low latency)
- Rate limiting on Node.js API
