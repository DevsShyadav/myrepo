<!-- QR Code Modal -->
<div class="modal-overlay" id="qr-modal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h2>Connect WhatsApp</h2>
            <button class="modal-close">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="qr-container">
            <img id="qr-image" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==" alt="QR Code">
            <div class="qr-instructions">
                <p style="font-weight:600;margin-bottom:6px">Scan with WhatsApp</p>
                <p>Open WhatsApp on your phone &gt; Settings &gt; Linked Devices &gt; Link a Device</p>
            </div>
        </div>

        <div style="margin-top:12px;text-align:center">
            <button class="btn btn-outline btn-sm" onclick="SocketManager.requestQR()">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh QR
            </button>
        </div>
    </div>
</div>
