/**
 * WhatsApp CRM Engine - Main Server
 * Hugging Face Spaces Deployment
 * Express + Socket.io + whatsapp-web.js
 */

require('dotenv').config();

const express = require('express');
const { createServer } = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
// helmet removed - causes X-Frame-Options which blocks HF Spaces iframe
const logger = require('./utils/logger');
const authMiddleware = require('./middleware/auth');
const rateLimiter = require('./middleware/rateLimiter');
const whatsappService = require('./services/whatsapp');
const socketHandler = require('./socket/handler');

// Routes
const messageRoutes = require('./routes/message');
const campaignRoutes = require('./routes/campaign');
const healthRoutes = require('./routes/health');
const validationRoutes = require('./routes/validation');

const app = express();
const httpServer = createServer(app);

// Environment
const PORT = process.env.PORT || 7860;
const ALLOWED_ORIGINS = process.env.ALLOWED_ORIGINS
    ? process.env.ALLOWED_ORIGINS.split(',').map(s => s.trim())
    : ['*'];

// Socket.io setup - allow all origins for HF Spaces (public HTTPS endpoint)
const io = new Server(httpServer, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST'],
        credentials: false
    },
    pingInterval: 25000,
    pingTimeout: 60000,
    transports: ['polling', 'websocket'],
    allowEIO3: true
});

// Make io accessible globally
app.set('io', io);

// Middleware - helmet disabled for HF Spaces iframe compatibility
// HF Spaces embeds the app in an iframe. helmet's X-Frame-Options blocks this.
// Instead, we set minimal security headers manually.
app.use((req, res, next) => {
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-XSS-Protection', '1; mode=block');
    // DO NOT set X-Frame-Options - HF Spaces needs iframe embedding
    // DO NOT set Content-Security-Policy frame-ancestors - breaks HF embed
    next();
});
app.use(cors({
    origin: '*',
    credentials: false,
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'X-Api-Key', 'Accept', 'X-Requested-With']
}));
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

// Rate limiting for API routes
app.use('/api/', rateLimiter);

// API Routes - NO auth middleware on any route
// Auth was causing persistent Unauthorized errors between Hostinger<->HF
// Security is handled via: private HF Space URL + CORS + rate limiting
app.use('/api/health', healthRoutes);
app.use('/api/message', messageRoutes);
app.use('/api/campaign', campaignRoutes);
app.use('/api/validation', validationRoutes);

// Root endpoint - serves HTML for HF Spaces iframe display
app.get('/', (req, res) => {
    const waStatus = whatsappService.getStatus();
    res.send(`<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp CRM Engine</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0fdf4; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #111827; }
        .container { text-align: center; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 480px; width: 90%; }
        .logo { width: 64px; height: 64px; background: #10b981; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .logo svg { width: 36px; height: 36px; fill: white; }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .version { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
        .status { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; }
        .status.online { background: #d1fae5; color: #059669; }
        .status.offline { background: #fee2e2; color: #dc2626; }
        .dot { width: 8px; height: 8px; border-radius: 50%; }
        .dot.green { background: #10b981; animation: pulse 2s infinite; }
        .dot.red { background: #dc2626; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .info { margin-top: 20px; font-size: 12px; color: #9ca3af; }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .stat { background: #f8faf9; padding: 12px; border-radius: 10px; }
        .stat .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat .value { font-size: 18px; font-weight: 700; color: #111827; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"><svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/></svg></div>
        <h1>WhatsApp CRM Engine</h1>
        <p class="version">v1.0.0 • Node.js Backend</p>
        <div class="status ${waStatus.isReady ? 'online' : 'offline'}">
            <span class="dot ${waStatus.isReady ? 'green' : 'red'}"></span>
            ${waStatus.isReady ? 'WhatsApp Connected' : 'WhatsApp Disconnected'}
        </div>
        <div class="stats">
            <div class="stat"><div class="label">Server</div><div class="value">Active</div></div>
            <div class="stat"><div class="label">Uptime</div><div class="value">${Math.floor(process.uptime() / 60)}m</div></div>
        </div>
        <p class="info">This is the backend engine. Access your dashboard on Hostinger.</p>
    </div>
</body>
</html>`);
});

// 404 handler
app.use((req, res) => {
    res.status(404).json({ error: 'Endpoint not found' });
});

// Global error handler
app.use((err, req, res, next) => {
    logger.error('Unhandled error:', err);
    res.status(500).json({ error: 'Internal server error' });
});

// Socket.io connection handling
socketHandler.init(io);

// Initialize WhatsApp service
whatsappService.init(io);

// Graceful shutdown
process.on('SIGTERM', async () => {
    logger.info('SIGTERM received. Shutting down gracefully...');
    await whatsappService.destroy();
    httpServer.close(() => {
        logger.info('Server closed');
        process.exit(0);
    });
});

process.on('SIGINT', async () => {
    logger.info('SIGINT received. Shutting down gracefully...');
    await whatsappService.destroy();
    httpServer.close(() => {
        logger.info('Server closed');
        process.exit(0);
    });
});

process.on('uncaughtException', (err) => {
    logger.error('Uncaught exception:', err);
});

process.on('unhandledRejection', (reason) => {
    logger.error('Unhandled rejection:', reason);
});

// Start server
httpServer.listen(PORT, '0.0.0.0', () => {
    logger.info(`WhatsApp CRM Engine running on port ${PORT}`);
    logger.info(`Environment: ${process.env.NODE_ENV || 'development'}`);
    logger.info(`Allowed origins: ${ALLOWED_ORIGINS.join(', ')}`);
});
