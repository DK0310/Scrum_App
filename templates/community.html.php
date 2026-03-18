<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== COMMUNITY ===== -->
    <section class="section" style="padding-top:100px;background:var(--gray-100);min-height:80vh;" id="community">
        <div class="section-container">
            <div class="section-header" style="flex-wrap:wrap;gap:16px;">
                <div>
                    <h2 class="section-title">🌍 Community</h2>
                    <p class="section-subtitle">Share your road trip stories, tips, and experiences</p>
                </div>
                <?php if ($isLoggedIn): ?>
                <button class="btn btn-primary" onclick="openPostModal()">✏️ Create Post</button>
                <?php else: ?>
                <button class="btn btn-primary" onclick="showAuthModal('login'); return false;">✏️ Sign in to Post</button>
                <?php endif; ?>
            </div>

            <!-- Category Tabs -->
            <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;" id="categoryTabs">
                <button class="btn btn-primary btn-sm community-cat-btn active" data-cat="all" onclick="filterCategory('all',this)">📋 All Posts</button>
                <button class="btn btn-outline btn-sm community-cat-btn" data-cat="road_trip" onclick="filterCategory('road_trip',this)">🏖️ Road Trip</button>
                <button class="btn btn-outline btn-sm community-cat-btn" data-cat="car_review" onclick="filterCategory('car_review',this)">🚗 Car Review</button>
                <button class="btn btn-outline btn-sm community-cat-btn" data-cat="tips" onclick="filterCategory('tips',this)">💡 Tips & Advice</button>
                <button class="btn btn-outline btn-sm community-cat-btn" data-cat="question" onclick="filterCategory('question',this)">❓ Question</button>
            </div>

            <!-- Posts Count -->
            <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:16px;" id="postsCount">Loading posts...</p>

            <!-- Posts Grid -->
            <div class="community-grid" id="postsGrid">
                <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--gray-400);">
                    <div style="font-size:2rem;margin-bottom:8px;">⏳</div>
                    Loading posts...
                </div>
            </div>
        </div>
    </section>

    <!-- ===== CREATE POST MODAL ===== -->
    <div class="modal-overlay" id="postModal">
        <div class="modal" style="max-width:600px;">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Create Community Post</h3>
                <button class="modal-close" onclick="closeModal('postModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Post Title *</label>
                    <input type="text" class="form-input" placeholder="Give your post a great title..." id="postTitle" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Content *</label>
                    <textarea class="form-textarea" style="min-height:150px;" placeholder="Share your experience, tips, or review..." id="postContent"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" id="postCategory">
                        <option value="road_trip">🏖️ Road Trip Story</option>
                        <option value="car_review">🚗 Car Review</option>
                        <option value="tips">💡 Tips & Advice</option>
                        <option value="question">❓ Question</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Image (optional)</label>
                    <div id="imageUploadArea" style="border:2px dashed var(--gray-300);border-radius:var(--radius);padding:30px;text-align:center;cursor:pointer;transition:var(--transition);position:relative;" onclick="document.getElementById('postImage').click()">
                        <div id="imagePreviewContainer" style="display:none;position:relative;">
                            <img id="imagePreview" style="max-width:100%;max-height:200px;border-radius:var(--radius);" />
                            <button type="button" onclick="event.stopPropagation();removeImage()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:white;border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:14px;">✕</button>
                        </div>
                        <div id="imageUploadPlaceholder">
                            <div style="font-size:2rem;margin-bottom:8px;">📷</div>
                            <p style="font-size:0.875rem;color:var(--gray-500);">Click to upload image (JPEG, PNG, WebP, GIF — Max 5MB)</p>
                        </div>
                        <input type="file" id="postImage" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="previewImage(this)">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('postModal')">Cancel</button>
                <button class="btn btn-primary" id="submitPostBtn" onclick="submitPost()">Publish Post</button>
            </div>
        </div>
    </div>

    <!-- ===== COMMENT MODAL ===== -->
    <div class="modal-overlay" id="commentModal">
        <div class="modal" style="max-width:650px;max-height:85vh;display:flex;flex-direction:column;">
            <div class="modal-header">
                <h3 class="modal-title">💬 Comments</h3>
                <button class="modal-close" onclick="closeModal('commentModal')">✕</button>
            </div>
            <!-- Post Preview at top -->
            <div id="commentPostPreview" style="padding:0;border-bottom:1px solid var(--gray-200);max-height:45vh;overflow-y:auto;"></div>
            <!-- Comments list -->
            <div class="modal-body" style="flex:1;overflow-y:auto;max-height:300px;" id="commentsContainer">
                <div style="text-align:center;padding:30px;color:var(--gray-400);">Loading comments...</div>
            </div>
            <?php if ($isLoggedIn): ?>
            <div style="padding:16px 24px;border-top:1px solid var(--gray-200);display:flex;gap:10px;align-items:flex-end;">
                <textarea class="form-textarea" id="commentInput" placeholder="Write a comment..." style="min-height:44px;max-height:100px;resize:none;flex:1;margin:0;"></textarea>
                <button class="btn btn-primary btn-sm" onclick="submitComment()" style="white-space:nowrap;">Send</button>
            </div>
            <?php else: ?>
            <div style="padding:16px 24px;border-top:1px solid var(--gray-200);text-align:center;">
                <button onclick="showAuthModal('login'); return false;" style="background:none;border:none;color:var(--primary);font-weight:600;cursor:pointer;">Sign in to comment</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== USER PROFILE MODAL ===== -->
    <div class="modal-overlay" id="userProfileModal">
        <div class="modal" style="max-width:550px;">
            <div class="modal-header">
                <h3 class="modal-title">👤 User Profile</h3>
                <button class="modal-close" onclick="closeModal('userProfileModal')">✕</button>
            </div>
            <div class="modal-body" id="userProfileContent">
                <div style="text-align:center;padding:30px;color:var(--gray-400);">Loading profile...</div>
            </div>
        </div>
    </div>

    <!-- ===== COMMUNITY JAVASCRIPT ===== -->
    <script>
        // Pass PHP variables to community.js module
        window.LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
        window.CURRENT_USER_ID = '<?= $userId ?? '' ?>';
        window.CURRENT_USER_ROLE = '<?= $userRole ?>';
    </script>
    <script src="/resources/js/community.js"></script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
