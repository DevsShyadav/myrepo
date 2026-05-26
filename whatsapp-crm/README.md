# WhatsApp CRM + Cold Outreach Operating System

A production-grade WhatsApp CRM and cold outreach platform for local business outreach. Built with PHP + Node.js split architecture deployed across Hostinger (shared hosting) and Hugging Face Spaces.

## Architecture

```
┌─────────────────────┐     ┌──────────────────────────┐
│  HOSTINGER           │     │  HUGGING FACE SPACES     │
│  ┌────────────────┐  │     │  ┌──────────────────────┐│
│  │ PHP Dashboard   │◄─────►│  │ Node.js Engine       ││
│  │ MySQL Database  │  │     │  │ WhatsApp Web.js      ││
│  │ API Endpoints   │  │     │  │ Socket.io Server     ││
│  │ Groq AI         │  │     │  │ Message Queue        ││
│  └────────────────┘  │     │  └──────────────────────┘│
└─────────────────────┘     └──────────────────────────┘
```

## Features

- CSV lead import with smart parsing
- WhatsApp number validation
- AI-powered personalized message generation (Groq)
- Automated first outreach with anti-ban pacing
- Automatic stop on lead reply
- Manual chat continuation
- Real-time dashboard with Socket.io
- Premium white + green SaaS UI
- Campaign management with daily limits
- Lead segmentation (website/no-website)
- Regional language adaptation

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | PHP + Tailwind CSS + Vanilla JS + Socket.io |
| Backend API | PHP 8.0+ with PDO/MySQL |
| AI Engine | Groq API (LLaMA 3.1) |
| WhatsApp | Node.js + whatsapp-web.js + Puppeteer |
| Realtime | Socket.io |
| Database | MySQL 8.0 |
| Hosting | Hostinger (PHP) + HF Spaces (Node.js) |

## Quick Start

See [docs/SETUP.md](docs/SETUP.md) for complete setup guide.

### 1. Database Setup
```sql
-- Import via phpMyAdmin
source sql/schema.sql;
source sql/seed.sql;
```

### 2. Hostinger Deployment
- Upload `hostinger/` files to public_html
- Update `config/app.php` and `config/db.php`
- Set cron jobs for campaign scripts

### 3. Hugging Face Deployment
- Create Docker Space
- Upload `huggingface-backend/` files
- Set environment variables
- Scan WhatsApp QR from dashboard

## Anti-Ban Strategy

- Single message per lead (no follow-up automation)
- Random delays: 120-300 seconds between messages
- Daily limit: 50 messages (configurable)
- Business hours only (9 AM - 7 PM)
- Unique AI-generated messages (no templates)
- Immediate stop on reply

## License

Private - All rights reserved.
