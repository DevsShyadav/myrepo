# API Documentation

## Hostinger PHP API Endpoints

Base URL: `https://yourdomain.com/api/`

All endpoints return JSON. POST endpoints accept JSON body.

---

### Dashboard Stats
```
GET /api/get_stats.php
```
Returns KPI data: total leads, valid, sent today, replies, reply rate, campaign status.

---

### Leads
```
GET /api/get_leads.php?page=1&limit=50&search=&whatsapp_status=&outreach_status=&pitch_type=
```
Returns paginated leads with filters and last message preview.

---

### Messages
```
GET /api/get_messages.php?lead_id=123
```
Returns conversation messages for a lead.

---

### Lead Details
```
GET /api/get_lead_details.php?lead_id=123
```
Returns complete lead profile with stats and activity.

---

### Send Manual Message
```
POST /api/send_manual.php
Body: { "lead_id": 123, "message": "Hello..." }
```
Sends message via Node.js and stores in DB.

---

### Mark Read
```
POST /api/mark_read.php
Body: { "lead_id": 123 }
```
Marks unread inbound messages as read.

---

### Import CSV
```
POST /api/import_csv.php
Body: FormData with 'csv_file'
```
Imports leads from CSV file.

---

### Start Campaign
```
POST /api/start_campaign.php
Body: { "limit": 20, "filters": { "city": "", "pitch_type": "" } }
```
Generates AI messages and queues leads for sending.

---

### Pause/Resume/Clear Campaign
```
POST /api/pause_campaign.php
Body: { "action": "pause" | "resume" | "clear" }
```

---

### Validate Numbers
```
POST /api/validate_numbers.php
Body: { "limit": 50 }
```
Starts batch WhatsApp validation.

---

### Generate AI Message
```
POST /api/generate_message.php
Body: { "lead_id": 123, "regenerate": true }
```
Generates personalized outreach message via Groq.

---

### Settings
```
GET /api/settings.php?category=api
POST /api/update_settings.php
Body: { "settings": { "key": "value", ... } }
```

---

### Activity Logs
```
GET /api/get_logs.php?type=error&category=campaign&page=1
```

---

### Sync Status
```
GET /api/refresh_sync.php
```
Syncs with Node.js backend status.

---

## Node.js API Endpoints (Hugging Face)

Base URL: `https://your-space.hf.space/api/`

All require `X-Api-Key` header.

---

### Health
```
GET /api/health
GET /api/health/ping
GET /api/health/wa-status
```

### Messages
```
POST /api/message/send - { phone, message }
POST /api/message/queue - { phone, message, leadId, businessName }
POST /api/message/queue-batch - { messages: [...] }
GET /api/message/queue-status
```

### Campaign
```
POST /api/campaign/pause
POST /api/campaign/resume
POST /api/campaign/clear
GET /api/campaign/status
POST /api/campaign/config - { minDelay, maxDelay, dailyLimit }
```

### Validation
```
POST /api/validation/check - { phone }
POST /api/validation/batch - { phones: [...] }
GET /api/validation/status
```

---

## Socket.io Events

### Server -> Client
| Event | Data | Description |
|-------|------|-------------|
| qr_code | { qr } | QR code data URL |
| wa_connected | { timestamp, info } | WhatsApp ready |
| wa_disconnected | { reason } | Session lost |
| message_received | { phone, message, timestamp } | Inbound |
| message_sent | { phone, leadId, messageId } | Outbound confirmed |
| campaign_progress | { event, sentToday, queueRemaining } | Progress |
| queue_update | { action, queueLength } | Queue change |
| validation_progress | { phone, status, validated, total } | Validation |
| heartbeat | { timestamp, waReady } | Keep-alive |

### Client -> Server
| Event | Description |
|-------|-------------|
| request_status | Request current status |
| request_qr | Request QR code |

---

## Webhook Events (HF -> Hostinger)

```
POST /webhook.php
Headers: X-Webhook-Secret, X-Webhook-Event
Body: { event, data, timestamp }
```

Events: `message_received`, `message_sent`, `message_failed`, `validation_result`, `status_update`
