/**
 * Leads Manager
 * Handles leads list, search, filters, and selection
 */

const LeadsManager = {
    leads: [],
    selectedLeadId: null,
    currentFilter: '',
    searchQuery: '',
    currentPage: 1,
    totalPages: 1,

    init() {
        this.bindEvents();
        this.loadLeads();
    },

    bindEvents() {
        const searchInput = document.getElementById('leads-search');
        if (searchInput) {
            searchInput.addEventListener('input', Utils.debounce((e) => {
                this.searchQuery = e.target.value;
                this.currentPage = 1;
                this.loadLeads();
            }, 300));
        }

        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                e.target.classList.add('active');
                this.currentFilter = e.target.dataset.filter || '';
                this.currentPage = 1;
                this.loadLeads();
            });
        });
    },

    async loadLeads() {
        const listEl = document.getElementById('leads-list');
        if (!listEl) return;

        let url = `get_leads.php?page=${this.currentPage}&limit=50`;
        if (this.searchQuery) url += `&search=${encodeURIComponent(this.searchQuery)}`;
        if (this.currentFilter) {
            if (['valid', 'invalid', 'pending', 'not_on_whatsapp'].includes(this.currentFilter)) {
                url += `&whatsapp_status=${this.currentFilter}`;
            } else if (['sent', 'replied', 'queued', 'failed'].includes(this.currentFilter)) {
                url += `&outreach_status=${this.currentFilter}`;
            } else if (this.currentFilter === 'has_website') {
                url += `&pitch_type=type_a`;
            } else if (this.currentFilter === 'no_website') {
                url += `&pitch_type=type_b`;
            }
        }

        try {
            const data = await Utils.api(url);
            this.leads = data.leads || [];
            this.totalPages = data.total_pages || 1;
            this.renderLeads();
        } catch (err) {
            Toast.error('Failed to load leads');
        }
    },

    renderLeads() {
        const listEl = document.getElementById('leads-list');
        if (!listEl) return;

        if (this.leads.length === 0) {
            listEl.innerHTML = `<div class="empty-state" style="padding:40px">
                <p class="text-muted">No leads found</p>
            </div>`;
            return;
        }

        listEl.innerHTML = this.leads.map(lead => this.renderLeadItem(lead)).join('');

        listEl.querySelectorAll('.lead-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.id);
                this.selectLead(id);
            });
        });
    },

    renderLeadItem(lead) {
        const initials = Utils.getInitials(lead.business_name);
        const preview = lead.last_message ? Utils.truncate(lead.last_message, 40) : lead.city || 'No messages';
        const time = lead.last_message_time ? Utils.timeAgo(lead.last_message_time) : '';
        const unread = parseInt(lead.unread_count) > 0;
        const isActive = lead.id === this.selectedLeadId;

        let badge = '';
        if (lead.outreach_status === 'replied') badge = '<span class="lead-badge badge-replied">Replied</span>';
        else if (lead.outreach_status === 'sent') badge = '<span class="lead-badge badge-sent">Sent</span>';
        else if (lead.whatsapp_status === 'valid') badge = '<span class="lead-badge badge-valid">Valid</span>';
        else if (lead.whatsapp_status === 'invalid' || lead.whatsapp_status === 'not_on_whatsapp') badge = '<span class="lead-badge badge-invalid">Invalid</span>';
        else if (lead.whatsapp_status === 'pending') badge = '<span class="lead-badge badge-pending">Pending</span>';

        if (lead.website_status === 'has_website') badge += ' <span class="lead-badge badge-website">Web</span>';

        return `<div class="lead-item ${isActive ? 'active' : ''}" data-id="${lead.id}">
            <div class="lead-avatar">${initials}</div>
            <div class="lead-info">
                <div class="lead-name">${Utils.escapeHtml(lead.business_name)}</div>
                <div class="lead-preview">${Utils.escapeHtml(preview)}</div>
                <div class="lead-meta">${badge}</div>
            </div>
            ${time ? `<span class="lead-time">${time}</span>` : ''}
            ${unread ? '<span class="unread-dot"></span>' : ''}
        </div>`;
    },

    selectLead(leadId) {
        this.selectedLeadId = leadId;
        document.querySelectorAll('.lead-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.id) === leadId);
        });

        if (typeof ChatManager !== 'undefined') {
            ChatManager.loadChat(leadId);
        }

        // Mark as read
        Utils.api('mark_read.php', 'POST', { lead_id: leadId }).catch(() => {});
    },

    refresh() {
        this.loadLeads();
    },

    getSelectedLead() {
        return this.leads.find(l => l.id === this.selectedLeadId) || null;
    }
};
