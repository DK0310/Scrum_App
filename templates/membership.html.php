<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== MEMBERSHIP / SUBSCRIPTION ===== -->
    <section class="section" style="padding-top:100px;" id="membership">
        <div class="section-container">
            <div class="section-header" style="justify-content:center;text-align:center;">
                <div>
                    <h2 class="section-title">👑 Membership Plans</h2>
                    <p class="section-subtitle">Subscribe for exclusive benefits, discounts, and priority access</p>
                </div>
            </div>
            <div class="subscription-grid">
                <?php foreach ($plans as $plan): ?>
                <div class="subscription-card <?= $plan['popular'] ? 'popular' : '' ?>">
                    <?php if ($plan['popular']): ?>
                    <div class="subscription-popular-badge">Most Popular</div>
                    <?php endif; ?>
                    <h3 class="subscription-name"><?= $plan['name'] ?></h3>
                    <div class="subscription-price">
                        <span class="amount">$<?= $plan['price'] ?></span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="subscription-features">
                        <?php foreach ($plan['features'] as $feature): ?>
                        <li><?= htmlspecialchars($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button class="btn <?= $plan['popular'] ? 'btn-primary' : 'btn-outline' ?> btn-block" onclick="subscribePlan('<?= $plan['slug'] ?>')">
                        <?= $plan['slug'] === 'corporate' ? 'Contact Sales' : 'Choose ' . $plan['name'] ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== ENQUIRY MODAL (for Corporate) ===== -->
    <div class="modal-overlay" id="enquiryModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">📋 Corporate Enquiry</h3>
                <button class="modal-close" onclick="closeModal('enquiryModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Company Name</label>
                    <input type="text" class="form-input" placeholder="Your company name">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Email</label>
                    <input type="email" class="form-input" placeholder="your@company.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Fleet Size Needed</label>
                    <select class="form-select">
                        <option>1-5 vehicles</option>
                        <option>5-20 vehicles</option>
                        <option>20-50 vehicles</option>
                        <option>50+ vehicles</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Details</label>
                    <textarea class="form-textarea" placeholder="Tell us about your fleet needs..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('enquiryModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitCorporateEnquiry()">Submit Enquiry</button>
            </div>
        </div>
    </div>

    <!-- ===== MEMBERSHIP PAGE JAVASCRIPT ===== -->
    <script>
        function subscribePlan(plan) {
            if (plan === 'corporate') {
                document.getElementById('enquiryModal').classList.add('open');
            } else {
                showToast('🎉 Redirecting to ' + plan + ' subscription checkout...', 'info');
            }
        }

        function submitCorporateEnquiry() {
            closeModal('enquiryModal');
            showToast('✅ Corporate enquiry submitted! Our sales team will contact you within 24 hours.', 'success');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
