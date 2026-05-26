<aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <h1>
            <img src="assets/images/logo.svg" alt="Logo" width="28" height="28">
            <span>WA</span>CRM
        </h1>
        <div class="version">v1.0.0 &middot; Production</div>
    </div>

    <!-- WhatsApp Status -->
    <div id="wa-status" class="connection-badge disconnected">
        <span class="status-dot offline"></span>
        WhatsApp: Checking...
    </div>

    <!-- Realtime Status -->
    <div id="connection-status" class="connection-badge disconnected">
        <span class="status-dot offline"></span>
        Realtime: Connecting...
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <div class="nav-item active" onclick="App.loadStats(); LeadsManager.refresh();">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </div>
            <div class="nav-item" id="btn-import-csv">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import CSV
            </div>
            <div class="nav-item" id="btn-validate">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Validate Numbers
            </div>
            <div class="nav-item" id="btn-show-qr">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                WhatsApp QR
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Campaign</div>
            <?php include __DIR__ . '/campaign_controls.php'; ?>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <div class="nav-item" id="btn-settings">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                Settings
            </div>
        </div>
    </nav>

    <!-- Stats Cards -->
    <div class="sidebar-stats">
        <?php include __DIR__ . '/stats_cards.php'; ?>
    </div>
</aside>
