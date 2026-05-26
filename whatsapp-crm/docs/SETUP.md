# Setup Guide

Complete deployment guide for WhatsApp CRM + Cold Outreach System.

## Prerequisites

- Hostinger shared hosting with PHP 8.0+ and MySQL
- Hugging Face account (free tier works)
- Groq API key (free at console.groq.com)
- WhatsApp account for automation

---

## Step 1: Database Setup

1. Log into Hostinger hPanel > Databases > MySQL
2. Create database: `whatsapp_crm`
3. Create user and assign to database
4. Open phpMyAdmin
5. Import `sql/schema.sql`
6. Import `sql/seed.sql`

---

## Step 2: Hostinger Deployment

### Upload Files
1. Open File Manager or use FTP
2. Upload ALL files from `hostinger/` to `public_html/` (or subdomain root)
3. Ensure directory structure is preserved

### Configure
1. Edit `config/db.php`:
   - Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

2. Edit `config/app.php` OR use Settings panel after first load:
   - Set `NODE_API_URL` (your HF Space URL)
   - Set `NODE_API_KEY` (shared secret)
   - Set `WEBHOOK_SECRET` (shared secret)
   - Set `SOCKET_URL` (same as NODE_API_URL)
   - Set `GROQ_API_KEY`

### Set Permissions
```
chmod 755 uploads/
chmod 755 logs/
chmod 644 .htaccess
```

### Set Cron Jobs (Hostinger hPanel > Cron Jobs)
```
*/3 * * * * /usr/bin/php /home/username/public_html/scripts/campaign.php
*/10 * * * * /usr/bin/php /home/username/public_html/scripts/retry_failed.php
*/5 * * * * curl -s https://your-space.hf.space/api/health/ping > /dev/null
```

---

## Step 3: Hugging Face Spaces Deployment

### Create Space
1. Go to huggingface.co/spaces
2. Create new Space
3. Select **Docker** as SDK
4. Set visibility to Public (required for webhooks)

### Upload Files
Upload all files from `huggingface-backend/` to the Space repository.

### Set Environment Variables (Space Settings > Variables)
```
NODE_ENV=production
PORT=7860
API_KEY=your_secure_api_key_here
WEBHOOK_URL=https://yourdomain.com/webhook.php
WEBHOOK_SECRET=your_webhook_secret_here
ALLOWED_ORIGINS=https://yourdomain.com
```

### Wait for Build
- Space will auto-build from Dockerfile
- Takes 2-3 minutes
- Check logs for "WhatsApp CRM Engine running on port 7860"

---

## Step 4: Connect WhatsApp

1. Open your dashboard: `https://yourdomain.com/dashboard.php`
2. Click "WhatsApp QR" in sidebar
3. QR code appears (via Socket.io from HF Space)
4. Open WhatsApp on phone > Linked Devices > Link a Device
5. Scan QR code
6. Dashboard shows "WhatsApp: Connected"

---

## Step 5: First Use

### Import Leads
1. Click "Import CSV" in sidebar
2. Upload CSV with business leads
3. System parses, sanitizes, detects language/pitch type

### Validate Numbers
1. Click "Validate Numbers"
2. System checks each number via WhatsApp
3. Invalid numbers are filtered out

### Start Campaign
1. Set batch size (10-50)
2. Click "Start" in Campaign widget
3. AI generates personalized messages
4. Messages sent with 2-5 min random delays
5. Automation stops automatically on reply
6. Continue manually from chat interface

---

## Troubleshooting

| Issue | Solution |
|-------|---------|
| QR not showing | Check Socket URL in settings, ensure HF Space is running |
| Messages not sending | Check Node API URL, verify WhatsApp connected |
| Import fails | Check CSV format, ensure phone column exists |
| Webhook errors | Verify WEBHOOK_SECRET matches on both sides |
| Daily limit hit | Wait for next day or increase in Settings |

---

## Environment Variables Summary

### Hostinger (config/app.php)
- `NODE_API_URL` - HF Space URL
- `NODE_API_KEY` - API authentication key
- `WEBHOOK_SECRET` - Webhook verification
- `SOCKET_URL` - Socket.io server URL
- `GROQ_API_KEY` - Groq AI API key

### Hugging Face (Space Settings)
- `API_KEY` - Same as NODE_API_KEY
- `WEBHOOK_URL` - Your Hostinger webhook URL
- `WEBHOOK_SECRET` - Same as Hostinger
- `ALLOWED_ORIGINS` - Your domain
