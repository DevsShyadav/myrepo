<div class="campaign-widget">
    <div class="campaign-status">
        <span style="font-size:12px;font-weight:600">Status</span>
        <span id="campaign-status-text" class="text-muted" style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;background:var(--bg-surface)">Idle</span>
    </div>
    <div class="campaign-progress" style="margin-top:8px">
        <div class="bar" id="campaign-progress-bar" style="width:0%"></div>
    </div>
    <div style="font-size:11px;color:var(--text-muted);margin:6px 0 10px;display:flex;justify-content:space-between">
        <span>Sent: <strong id="campaign-sent-today">0/50</strong></span>
        <span>Queue: <strong id="queue-count">0</strong></span>
    </div>
    <div class="form-group" style="margin-bottom:10px">
        <label style="font-size:11px;margin-bottom:4px">Batch Size (1-50)</label>
        <input type="number" id="campaign-batch-size" class="form-control" value="20" min="1" max="50" style="padding:6px 10px;font-size:12px">
    </div>
    <div class="campaign-actions">
        <button class="btn btn-primary btn-sm" id="btn-start-campaign" style="flex:1;font-size:11px">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Start
        </button>
        <button class="btn btn-outline btn-sm hidden" id="btn-pause-campaign" style="flex:1;font-size:11px">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            Pause
        </button>
        <button class="btn btn-primary btn-sm hidden" id="btn-resume-campaign" style="flex:1;font-size:11px">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Resume
        </button>
        <button class="btn btn-ghost btn-sm" id="btn-clear-campaign" style="font-size:11px" title="Clear Queue">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
