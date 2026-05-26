<!-- Settings Modal -->
<div class="modal-overlay" id="settings-modal">
    <div class="modal" style="max-width:640px;max-height:90vh">
        <div class="modal-header">
            <h2>Settings</h2>
            <button class="modal-close" onclick="SettingsManager.closePanel()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="settings-grid" id="settings-content">
            <div class="text-center mt-4">
                <div class="spinner" style="margin:auto"></div>
                <p class="text-muted mt-2" style="font-size:13px">Loading settings...</p>
            </div>
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:16px">
            <button class="btn btn-outline" onclick="SettingsManager.closePanel()">Cancel</button>
            <button class="btn btn-primary" id="btn-save-settings">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                Save Settings
            </button>
        </div>
    </div>
</div>
