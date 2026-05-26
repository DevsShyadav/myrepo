/**
 * PM2 Ecosystem Config (optional - for local dev)
 */
module.exports = {
    apps: [{
        name: 'whatsapp-crm-engine',
        script: 'server.js',
        instances: 1,
        autorestart: true,
        watch: false,
        max_memory_restart: '512M',
        env: {
            NODE_ENV: 'production',
            PORT: 7860
        }
    }]
};
