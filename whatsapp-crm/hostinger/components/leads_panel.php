<section class="leads-panel">
    <!-- Header with Search -->
    <div class="leads-header">
        <h2>Leads</h2>
        <div class="search-box">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="leads-search" placeholder="Search leads..." autocomplete="off">
        </div>
    </div>

    <!-- Filters -->
    <div class="leads-filters">
        <span class="filter-chip active" data-filter="">All</span>
        <span class="filter-chip" data-filter="valid">Valid</span>
        <span class="filter-chip" data-filter="replied">Replied</span>
        <span class="filter-chip" data-filter="sent">Sent</span>
        <span class="filter-chip" data-filter="pending">Pending</span>
        <span class="filter-chip" data-filter="invalid">Invalid</span>
        <span class="filter-chip" data-filter="has_website">Website</span>
        <span class="filter-chip" data-filter="no_website">No Web</span>
    </div>

    <!-- Leads List -->
    <div class="leads-list" id="leads-list">
        <div class="empty-state" style="padding:60px 20px">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
            <h3>No leads yet</h3>
            <p>Import a CSV file to get started</p>
        </div>
    </div>
</section>
