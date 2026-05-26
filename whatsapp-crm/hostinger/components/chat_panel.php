<section class="chat-panel">
    <!-- Chat Header -->
    <div class="chat-header" id="chat-header">
        <div id="chat-header-content">
            <div class="chat-header-info">
                <h3 style="color:var(--text-muted);font-weight:400;font-size:14px">Select a lead to start chatting</h3>
            </div>
        </div>
    </div>

    <!-- Chat Messages -->
    <div class="chat-messages" id="chat-messages">
        <div class="empty-state" id="chat-empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
            <h3>WhatsApp CRM</h3>
            <p>Select a lead from the list to view conversation</p>
        </div>
    </div>

    <!-- Chat Input -->
    <div class="chat-input-area hidden" id="chat-input-area">
        <textarea id="message-input" placeholder="Type a message..." rows="1"></textarea>
        <button class="btn btn-primary" id="send-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        </button>
    </div>
</section>

<!-- Lead Details Drawer -->
<div class="drawer-backdrop" id="drawer-backdrop"></div>
<div class="drawer-overlay" id="lead-drawer">
    <div class="drawer-header">
        <h2 style="font-size:16px;font-weight:700">Lead Details</h2>
        <button class="modal-close" id="drawer-close">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="drawer-body" id="drawer-body">
        <div class="text-center mt-4"><div class="spinner" style="margin:auto"></div></div>
    </div>
</div>
