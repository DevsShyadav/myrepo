/**
 * Chat Manager
 * Handles conversation view, message display, and manual replies
 */

const ChatManager = {
    currentLeadId: null,
    currentLead: null,
    messages: [],

    init() {
        this.bindEvents();
    },

    bindEvents() {
        const sendBtn = document.getElementById('send-btn');
        const msgInput = document.getElementById('message-input');

        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }

        if (msgInput) {
            msgInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            msgInput.addEventListener('input', () => {
                msgInput.style.height = 'auto';
                msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
            });
        }

        const detailsBtn = document.getElementById('btn-lead-details');
        if (detailsBtn) {
            detailsBtn.addEventListener('click', () => this.openDetails());
        }
    },

    async loadChat(leadId) {
        this.currentLeadId = leadId;
        const chatArea = document.getElementById('chat-messages');
        const chatHeader = document.getElementById('chat-header-content');
        const chatInput = document.getElementById('chat-input-area');
        const emptyState = document.getElementById('chat-empty');

        if (emptyState) emptyState.classList.add('hidden');
        if (chatInput) chatInput.classList.remove('hidden');

        // Show loading
        if (chatArea) chatArea.innerHTML = '<div class="text-center mt-4"><div class="spinner" style="margin:auto"></div></div>';

        try {
            const data = await Utils.api(`get_messages.php?lead_id=${leadId}`);
            this.currentLead = data.lead;
            this.messages = data.messages || [];

            this.renderHeader();
            this.renderMessages();
        } catch (err) {
            Toast.error('Failed to load messages');
            if (chatArea) chatArea.innerHTML = '<div class="empty-state"><p>Failed to load</p></div>';
        }
    },

    renderHeader() {
        const el = document.getElementById('chat-header-content');
        if (!el || !this.currentLead) return;

        el.innerHTML = `
            <div class="chat-header-info">
                <div class="lead-avatar">${Utils.getInitials(this.currentLead.business_name)}</div>
                <div>
                    <h3>${Utils.escapeHtml(this.currentLead.business_name)}</h3>
                    <span class="chat-status">${this.currentLead.phone_number}</span>
                </div>
            </div>
            <div class="chat-header-actions">
                <button class="btn btn-outline btn-sm" id="btn-lead-details" onclick="ChatManager.openDetails()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    Details
                </button>
                <button class="btn btn-outline btn-sm" onclick="ChatManager.generateAIMessage()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    AI Message
                </button>
            </div>`;
    },

    renderMessages() {
        const chatArea = document.getElementById('chat-messages');
        if (!chatArea) return;

        if (this.messages.length === 0) {
            chatArea.innerHTML = '<div class="empty-state"><p class="text-muted">No messages yet</p></div>';
            return;
        }

        chatArea.innerHTML = this.messages.map(msg => {
            const isOutbound = msg.direction === 'outbound';
            const time = new Date(msg.timestamp).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
            const statusIcon = msg.status === 'failed' ? ' ✗' : '';

            return `<div class="message-bubble ${isOutbound ? 'outbound' : 'inbound'}">
                <div class="message-text">${Utils.escapeHtml(msg.message_text).replace(/\n/g, '<br>')}</div>
                <div class="message-time">${time}${statusIcon}</div>
            </div>`;
        }).join('');

        chatArea.scrollTop = chatArea.scrollHeight;
    },

    async sendMessage() {
        const input = document.getElementById('message-input');
        if (!input || !this.currentLeadId) return;

        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        input.style.height = 'auto';

        // Optimistic UI update
        this.messages.push({
            direction: 'outbound',
            message_text: message,
            timestamp: new Date().toISOString(),
            status: 'pending'
        });
        this.renderMessages();

        try {
            await Utils.api('send_manual.php', 'POST', {
                lead_id: this.currentLeadId,
                message: message
            });
            Toast.success('Message sent');
        } catch (err) {
            Toast.error('Failed to send: ' + err.message);
            this.messages[this.messages.length - 1].status = 'failed';
            this.renderMessages();
        }
    },

    async generateAIMessage() {
        if (!this.currentLeadId) return;

        Toast.info('Generating AI message...');

        try {
            const data = await Utils.api('generate_message.php', 'POST', {
                lead_id: this.currentLeadId,
                regenerate: true
            });

            if (data.message) {
                const input = document.getElementById('message-input');
                if (input) {
                    input.value = data.message;
                    input.style.height = 'auto';
                    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
                }
                Toast.success('AI message generated');
            }
        } catch (err) {
            Toast.error('AI generation failed');
        }
    },

    async openDetails() {
        if (!this.currentLeadId) return;

        const drawer = document.getElementById('lead-drawer');
        const backdrop = document.getElementById('drawer-backdrop');
        if (!drawer) return;

        try {
            const data = await Utils.api(`get_lead_details.php?lead_id=${this.currentLeadId}`);
            this.renderDetails(data);
            drawer.classList.add('active');
            if (backdrop) backdrop.classList.add('active');
        } catch (err) {
            Toast.error('Failed to load details');
        }
    },

    renderDetails(data) {
        const body = document.getElementById('drawer-body');
        if (!body) return;

        const lead = data.lead;
        const stats = data.message_stats;
        const tags = data.tags || [];

        body.innerHTML = `
            <div class="detail-section">
                <div class="detail-section-title">Business Information</div>
                <div class="detail-row"><span class="label">Name</span><span class="value">${Utils.escapeHtml(lead.business_name)}</span></div>
                <div class="detail-row"><span class="label">Phone</span><span class="value">${lead.phone_number}</span></div>
                <div class="detail-row"><span class="label">Address</span><span class="value">${Utils.escapeHtml(lead.address || 'N/A')}</span></div>
                <div class="detail-row"><span class="label">Locality</span><span class="value">${Utils.escapeHtml(lead.locality || 'N/A')}</span></div>
                <div class="detail-row"><span class="label">City</span><span class="value">${Utils.escapeHtml(lead.city || 'N/A')}</span></div>
                <div class="detail-row"><span class="label">State</span><span class="value">${Utils.escapeHtml(lead.state || 'N/A')}</span></div>
                <div class="detail-row"><span class="label">Rating</span><span class="value">${lead.rating ? lead.rating + '/5' : 'N/A'} ${lead.review_count ? '(' + lead.review_count + ' reviews)' : ''}</span></div>
                <div class="detail-row"><span class="label">Website</span><span class="value">${lead.website_status === 'has_website' ? '✓ Has Website' : '✗ No Website'}</span></div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">Outreach Status</div>
                <div class="detail-row"><span class="label">WhatsApp</span><span class="value lead-badge badge-${lead.whatsapp_status === 'valid' ? 'valid' : 'pending'}">${lead.whatsapp_status}</span></div>
                <div class="detail-row"><span class="label">Outreach</span><span class="value">${lead.outreach_status}</span></div>
                <div class="detail-row"><span class="label">Pitch Type</span><span class="value">${lead.pitch_type === 'type_a' ? 'Type A (Has Website)' : 'Type B (No Website)'}</span></div>
                <div class="detail-row"><span class="label">Language</span><span class="value">${lead.language_preference}</span></div>
                <div class="detail-row"><span class="label">Last Contacted</span><span class="value">${Utils.formatDate(lead.last_contacted_at) || 'Never'}</span></div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">Message Stats</div>
                <div class="detail-row"><span class="label">Total Messages</span><span class="value">${stats.total_messages || 0}</span></div>
                <div class="detail-row"><span class="label">Sent</span><span class="value">${stats.sent_count || 0}</span></div>
                <div class="detail-row"><span class="label">Received</span><span class="value">${stats.received_count || 0}</span></div>
                <div class="detail-row"><span class="label">First Contact</span><span class="value">${Utils.formatDate(stats.first_contact) || 'N/A'}</span></div>
            </div>

            ${lead.ai_reasoning ? `<div class="detail-section">
                <div class="detail-section-title">AI Reasoning</div>
                <p style="font-size:12px;color:var(--text-secondary);white-space:pre-line">${Utils.escapeHtml(lead.ai_reasoning)}</p>
            </div>` : ''}

            ${lead.notes ? `<div class="detail-section">
                <div class="detail-section-title">Notes</div>
                <p style="font-size:12px;color:var(--text-secondary)">${Utils.escapeHtml(lead.notes)}</p>
            </div>` : ''}

            ${tags.length > 0 ? `<div class="detail-section">
                <div class="detail-section-title">Tags</div>
                <div class="flex gap-2" style="flex-wrap:wrap">${tags.map(t => `<span class="lead-badge badge-valid">${Utils.escapeHtml(t)}</span>`).join('')}</div>
            </div>` : ''}
        `;
    },

    closeDrawer() {
        const drawer = document.getElementById('lead-drawer');
        const backdrop = document.getElementById('drawer-backdrop');
        if (drawer) drawer.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
    },

    onNewMessage(data, direction) {
        if (!this.currentLead) return;

        const phone = this.currentLead.phone_number;
        // Match phone - incoming may have or may not have country code
        const normalizedPhone = phone.replace(/^91/, '');
        const dataPhone = (data.phone || '').replace(/^91/, '');
        
        if (dataPhone === normalizedPhone || data.phone === phone) {
            this.messages.push({
                direction: direction,
                message_text: data.message || data.body || '',
                timestamp: data.timestamp || new Date().toISOString(),
                status: direction === 'outbound' ? 'sent' : 'delivered'
            });
            this.renderMessages();

            // Mark as read if it's inbound
            if (direction === 'inbound') {
                Utils.api('mark_read.php', 'POST', { lead_id: this.currentLeadId }).catch(() => {});
            }
        }

        // Always refresh leads list for unread indicators
        if (direction === 'inbound' && typeof LeadsManager !== 'undefined') {
            LeadsManager.refresh();
        }
    }
};
