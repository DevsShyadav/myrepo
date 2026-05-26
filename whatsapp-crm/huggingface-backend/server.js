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
const helmet = require('helmet');
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

// Middleware
app.use(helmet({
    crossOriginResourcePolicy: { policy: 'cross-origin' },
    crossOriginOpenerPolicy: false,
    crossOriginEmbedderPolicy: false
}));
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

// API Routes
app.use('/api/health', healthRoutes);
app.use('/api/message', authMiddleware, messageRoutes);
app.use('/api/campaign', authMiddleware, campaignRoutes);
app.use('/api/validation', authMiddleware, validationRoutes);

// Root endpoint
app.get('/', (req, res) => {
    res.json({
        service: 'WhatsApp CRM Engine',
        status: 'running',
        version: '1.0.0',
        timestamp: new Date().toISOString()
    });
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
