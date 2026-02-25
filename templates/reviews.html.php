<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== REVIEWS & RATINGS ===== -->
    <section class="section" style="padding-top:100px;">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">⭐ Customer Reviews</h2>
                    <p class="section-subtitle">See what our customers are saying about their experience</p>
                </div>
                <button class="btn btn-primary" onclick="openReviewModal()">✏️ Write a Review</button>
            </div>

            <!-- Review Stats -->
            <div style="display:grid;grid-template-columns:1fr 3fr;gap:40px;margin-bottom:40px;">
                <div style="text-align:center;background:var(--gray-50);border-radius:var(--radius-md);padding:32px;">
                    <div style="font-size:3rem;font-weight:900;color:var(--gray-900);">4.9</div>
                    <div style="font-size:1.25rem;color:var(--warning);margin:8px 0;">★★★★★</div>
                    <div style="color:var(--gray-500);font-size:0.875rem;">Based on <?= count($reviews) ?> reviews</div>
                </div>
                <div style="display:flex;flex-direction:column;justify-content:center;gap:8px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="width:60px;font-size:0.875rem;color:var(--gray-500);">5 stars</span>
                        <div style="flex:1;height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div style="width:85%;height:100%;background:var(--warning);border-radius:4px;"></div>
                        </div>
                        <span style="width:30px;font-size:0.813rem;color:var(--gray-500);">85%</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="width:60px;font-size:0.875rem;color:var(--gray-500);">4 stars</span>
                        <div style="flex:1;height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div style="width:12%;height:100%;background:var(--warning);border-radius:4px;"></div>
                        </div>
                        <span style="width:30px;font-size:0.813rem;color:var(--gray-500);">12%</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="width:60px;font-size:0.875rem;color:var(--gray-500);">3 stars</span>
                        <div style="flex:1;height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div style="width:3%;height:100%;background:var(--warning);border-radius:4px;"></div>
                        </div>
                        <span style="width:30px;font-size:0.813rem;color:var(--gray-500);">3%</span>
                    </div>
                </div>
            </div>

            <!-- Review Cards -->
            <div class="review-grid">
                <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-stars"><?= str_repeat('★', $review['stars']) . str_repeat('☆', 5 - $review['stars']) ?></div>
                    <p class="review-text">"<?= htmlspecialchars($review['text']) ?>"</p>
                    <div class="review-author">
                        <div class="review-avatar"><?= $review['initials'] ?></div>
                        <div class="review-author-info">
                            <div class="review-author-name"><?= htmlspecialchars($review['name']) ?></div>
                            <div class="review-author-trip"><?= htmlspecialchars($review['trip']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== REVIEW MODAL ===== -->
    <div class="modal-overlay" id="reviewModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">⭐ Write a Review</h3>
                <button class="modal-close" onclick="closeModal('reviewModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <div style="font-size:2rem;cursor:pointer;" id="ratingStars" onclick="setRating(event)">☆☆☆☆☆</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Car Rented</label>
                    <input type="text" class="form-input" placeholder="e.g., Toyota Camry 2025" id="reviewCar">
                </div>
                <div class="form-group">
                    <label class="form-label">Your Review</label>
                    <textarea class="form-textarea" placeholder="Tell us about your experience..." id="reviewText"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitReview()">Submit Review</button>
            </div>
        </div>
    </div>

    <!-- ===== REVIEWS PAGE JAVASCRIPT ===== -->
    <script>
        function openReviewModal() {
            document.getElementById('reviewModal').classList.add('open');
        }

        function setRating(event) {
            const stars = document.getElementById('ratingStars');
            const rect = stars.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const rating = Math.ceil((x / rect.width) * 5);
            stars.textContent = '★'.repeat(rating) + '☆'.repeat(5 - rating);
        }

        function submitReview() {
            const car = document.getElementById('reviewCar').value;
            const text = document.getElementById('reviewText').value;
            if (!car || !text) {
                showToast('Please fill in the car name and your review.', 'warning');
                return;
            }
            closeModal('reviewModal');
            showToast('✅ Thank you for your review!', 'success');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
