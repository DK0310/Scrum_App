<?php include __DIR__ . '/layout/header.html.php'; ?>

    <!-- ===== COMMUNITY ===== -->
    <section class="section" style="padding-top:100px;background:var(--gray-100);" id="community">
        <div class="section-container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">🌍 Community</h2>
                    <p class="section-subtitle">Share your road trip stories, tips, and experiences</p>
                </div>
                <button class="btn btn-primary" onclick="openPostModal()">✏️ Create Post</button>
            </div>
            <div class="community-grid">
                <?php foreach ($posts as $post): ?>
                <div class="community-post">
                    <div class="community-post-image"><?= $post['emoji'] ?></div>
                    <div class="community-post-body">
                        <div class="community-post-author">
                            <div class="community-avatar">👤</div>
                            <div>
                                <div class="community-author-name"><?= htmlspecialchars($post['author']) ?></div>
                                <div class="community-author-date"><?= $post['date'] ?></div>
                            </div>
                        </div>
                        <h3 class="community-post-title"><?= htmlspecialchars($post['title']) ?></h3>
                        <p class="community-post-excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
                        <div class="community-post-actions">
                            <button class="community-action" onclick="likePost(this)">❤️ <?= $post['likes'] ?></button>
                            <button class="community-action">💬 <?= $post['comments'] ?></button>
                            <button class="community-action">🔗 Share</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== POST MODAL ===== -->
    <div class="modal-overlay" id="postModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Create Community Post</h3>
                <button class="modal-close" onclick="closeModal('postModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Post Title</label>
                    <input type="text" class="form-input" placeholder="Give your post a great title..." id="postTitle">
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea class="form-textarea" style="min-height:150px;" placeholder="Share your experience, tips, or review..." id="postContent"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" id="postCategory">
                        <option>Road Trip Story</option>
                        <option>Car Review</option>
                        <option>Tips & Advice</option>
                        <option>Question</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('postModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitPost()">Publish Post</button>
            </div>
        </div>
    </div>

    <!-- ===== COMMUNITY PAGE JAVASCRIPT ===== -->
    <script>
        function openPostModal() {
            document.getElementById('postModal').classList.add('open');
        }

        function likePost(btn) {
            const text = btn.textContent;
            const count = parseInt(text.match(/\d+/)[0]) + 1;
            btn.textContent = '❤️ ' + count;
            btn.style.color = 'var(--danger)';
        }

        function submitPost() {
            const title = document.getElementById('postTitle').value;
            const content = document.getElementById('postContent').value;
            if (!title || !content) {
                showToast('Please fill in the title and content.', 'warning');
                return;
            }
            closeModal('postModal');
            showToast('✅ Your post has been published!', 'success');
        }
    </script>

<?php include __DIR__ . '/layout/footer.html.php'; ?>
