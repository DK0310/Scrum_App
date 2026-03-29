<?php include __DIR__ . '/layout/header.html.php'; ?>

<section class="section" style="padding-top:100px;background:var(--gray-100);min-height:80vh;" id="customer-enquiry">
    <div class="section-container" style="max-width:1000px;">
        <div class="section-header" style="flex-wrap:wrap;gap:16px;">
            <div>
                <h2 class="section-title">Customer Enquiry</h2>
                <p class="section-subtitle">Send Trip or General Enquiries and track staff responses</p>
            </div>
            <?php if ($isLoggedIn): ?>
            <button class="btn btn-primary" onclick="openEnquiryModal()">Create Enquiry</button>
            <?php else: ?>
            <button class="btn btn-primary" onclick="showAuthModal('login'); return false;">Sign in to Enquire</button>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;" id="enquiryFilterTabs">
            <button class="btn btn-primary btn-sm enquiry-filter-btn active" data-type="all" onclick="filterEnquiries('all',this)">All</button>
            <button class="btn btn-outline btn-sm enquiry-filter-btn" data-type="trip" onclick="filterEnquiries('trip',this)">Trip Enquiry</button>
            <button class="btn btn-outline btn-sm enquiry-filter-btn" data-type="general" onclick="filterEnquiries('general',this)">General Enquiry</button>
        </div>

        <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:16px;" id="enquiriesCount">Loading enquiries...</p>

        <div id="enquiriesList">
            <div style="text-align:center;padding:60px 0;color:var(--gray-400);">Loading enquiries...</div>
        </div>
    </div>
</section>

<div class="modal-overlay" id="enquiryModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Enquiry</h3>
            <button class="modal-close" onclick="closeModal('enquiryModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Enquiry Type *</label>
                <select class="form-select" id="enquiryType">
                    <option value="trip">Trip Enquiry</option>
                    <option value="general">General Enquiry</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Content *</label>
                <textarea class="form-textarea" id="enquiryContent" style="min-height:140px;" placeholder="Describe your enquiry..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Image (optional)</label>
                <input type="file" id="enquiryImage" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewEnquiryImage(this)">
                <div id="enquiryImagePreviewWrap" style="margin-top:10px;display:none;">
                    <img id="enquiryImagePreview" alt="Preview" style="max-width:100%;max-height:200px;border-radius:8px;">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('enquiryModal')">Cancel</button>
            <button class="btn btn-primary" id="submitEnquiryBtn" onclick="submitEnquiry()">Submit Enquiry</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="enquiryDetailModal">
    <div class="modal" style="max-width:760px;max-height:85vh;display:flex;flex-direction:column;">
        <div class="modal-header">
            <h3 class="modal-title">Enquiry Detail</h3>
            <button class="modal-close" onclick="closeModal('enquiryDetailModal')">✕</button>
        </div>
        <div class="modal-body" id="enquiryDetailBody" style="overflow-y:auto;">
            <div style="text-align:center;padding:20px;color:var(--gray-500);">Loading...</div>
        </div>
    </div>
</div>

<script>
window.LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
window.CURRENT_USER_ID = '<?= $userId ?? '' ?>';
window.CURRENT_USER_ROLE = '<?= $userRole ?>';
</script>
<script src="/resources/js/customer-enquiry.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
