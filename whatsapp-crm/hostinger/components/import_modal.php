<!-- Import CSV Modal -->
<div class="modal-overlay" id="import-modal">
    <div class="modal">
        <div class="modal-header">
            <h2>Import CSV Leads</h2>
            <button class="modal-close" onclick="App.closeImportModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="import-form">
            <div class="form-group">
                <label>Select CSV File</label>
                <input type="file" id="csv-file" class="form-control" accept=".csv" required>
            </div>

            <div style="background:var(--bg-surface);border-radius:var(--radius-md);padding:14px;margin-bottom:16px">
                <p style="font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-primary)">Expected CSV Columns:</p>
                <ul style="font-size:12px;color:var(--text-secondary);list-style:none;line-height:1.8">
                    <li>&#8226; <strong>Business Name</strong> (or: name, company)</li>
                    <li>&#8226; <strong>Phone</strong> (or: mobile, contact, number) <span style="color:var(--primary)">&mdash; Required</span></li>
                    <li>&#8226; <strong>Address</strong> (or: location, full_address)</li>
                    <li>&#8226; <strong>Website</strong> (or: url, web)</li>
                    <li>&#8226; <strong>Rating</strong> (or: stars, google_rating)</li>
                    <li>&#8226; <strong>Reviews</strong> (or: review_count)</li>
                </ul>
            </div>

            <div style="background:#FEF3C7;border-radius:var(--radius-md);padding:12px;margin-bottom:16px;font-size:12px;color:#92400E">
                <strong>Note:</strong> Phone numbers will be auto-formatted to Indian format. Duplicates will be skipped. Maximum file size: 5MB.
            </div>

            <div class="flex gap-2" style="justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="App.closeImportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import Leads
                </button>
            </div>
        </form>
    </div>
</div>
