---
title: WhatsApp CRM Engine
emoji: 💬
colorFrom: green
colorTo: green
sdk: docker
app_port: 7860
pinned: false
license: mit
---

# WhatsApp CRM Engine

Node.js backend for WhatsApp CRM + Cold Outreach System.

## Features

- WhatsApp Web.js integration with Puppeteer
- Socket.io real-time communication
- Message queue with anti-ban pacing
- WhatsApp number validation
- Webhook delivery to PHP backend
- Session persistence with LocalAuth

## Environment Variables

| Variable | Description |
|----------|-------------|
| `API_KEY` | API authentication key |
| `WEBHOOK_URL` | Hostinger webhook endpoint |
| `WEBHOOK_SECRET` | Shared webhook secret |
| `ALLOWED_ORIGINS` | Comma-separated allowed origins |
| `NODE_ENV` | Environment (production) |
| `PORT` | Server port (7860) |

## API Endpoints

- `GET /api/health` - Health check
- `POST /api/message/send` - Send message
- `POST /api/message/queue` - Queue message
- `POST /api/validation/check` - Validate number
- `POST /api/campaign/pause` - Pause campaign
- `GET /api/campaign/status` - Campaign status
