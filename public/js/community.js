/**
 * Community Module - Handles all community page functionality
 * Exported functions: loadPosts, filterCategory, openPostModal, openComments, showUserProfile, etc.
 */

// ===== STATE MANAGEMENT =====
let currentCategory = 'all';
let currentCommentPostId = null;
let allPosts = [];

// ===== CATEGORY EMOJI MAP =====
const categoryEmoji = {
    'road_trip': '🏖️',
    'car_review': '🚗',
    'tips': '💡',
    'question': '❓'
};
const categoryLabel = {
    'road_trip': 'Road Trip',
    'car_review': 'Car Review',
    'tips': 'Tips & Advice',
    'question': 'Question'
};

// ===== LOAD POSTS =====
async function loadPosts(category = 'all') {
    currentCategory = category;
    const grid = document.getElementById('postsGrid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--gray-400);"><div style="font-size:2rem;margin-bottom:8px;">⏳</div>Loading posts...</div>';

    try {
        const url = '/api/community.php?action=list-posts' + (category !== 'all' ? '&category=' + category : '');
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--danger);">❌ ' + (data.message || 'Failed to load posts') + '</div>';
            return;
        }

        allPosts = data.posts || [];
        document.getElementById('postsCount').textContent = `Showing ${allPosts.length} post(s)`;

        if (allPosts.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--gray-400);"><div style="font-size:3rem;margin-bottom:12px;">📝</div><p style="font-size:1.1rem;font-weight:600;">No posts yet</p><p style="margin-top:4px;">Be the first to share your experience!</p></div>';
            return;
        }

        grid.innerHTML = allPosts.map(post => renderPost(post)).join('');
    } catch (err) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--danger);">❌ Network error</div>';
        console.error(err);
    }
}

// ===== RENDER SINGLE POST =====
function renderPost(post) {
    const avatar = post.avatar_url 
        ? `<img src="${post.avatar_url}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">`
        : `<div class="community-avatar">${post.author_name ? post.author_name.charAt(0).toUpperCase() : '👤'}</div>`;
    
    const date = new Date(post.created_at).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
    
    const imageSrc = post.image_src 
        ? post.image_src 
        : null;
    
    const imageHtml = imageSrc
        ? `<div class="community-post-image" style="background:none;overflow:hidden;cursor:pointer;" onclick="event.stopPropagation();openImageLightbox('${imageSrc}',event)"><img src="${imageSrc}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:2rem;font-weight:800;color:var(--primary);background:linear-gradient(135deg,var(--primary-50),var(--primary-100));\\'>DriveNow</div>';this.parentElement.style.cursor='default';this.parentElement.onclick=null;"></div>`
        : `<div class="community-post-image" style="background:linear-gradient(135deg,var(--primary-50),var(--primary-100));"><div style="font-size:2rem;font-weight:800;color:var(--primary);">DriveNow</div></div>`;
    
    const CURRENT_USER_ROLE = window.CURRENT_USER_ROLE || '';
    const canDelete = (post.is_own || CURRENT_USER_ROLE === 'admin');
    const deleteBtn = canDelete 
        ? `<button class="community-action" onclick="event.stopPropagation();deletePost('${post.id}')" style="margin-left:auto;color:var(--danger);">🗑️ Delete</button>` 
        : '';

    const likedClass = post.liked ? 'style="color:var(--danger);"' : '';
    const catBadge = post.category ? `<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.7rem;background:var(--primary-50);color:var(--primary);font-weight:600;margin-bottom:8px;">${categoryEmoji[post.category] || '📋'} ${categoryLabel[post.category] || post.category}</span>` : '';

    return `
    <div class="community-post" data-post-id="${post.id}">
        ${imageHtml}
        <div class="community-post-body">
            <div class="community-post-author">
                <a href="javascript:void(0)" onclick="event.stopPropagation();showUserProfile('${post.user_id}')" style="cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:10px;">
                    ${avatar}
                    <div>
                        <div class="community-author-name">${escHtml(post.author_name || 'Unknown')}</div>
                        <div class="community-author-date">${date}</div>
                    </div>
                </a>
            </div>
            ${catBadge}
            <h3 class="community-post-title">${escHtml(post.title)}</h3>
            <p class="community-post-excerpt">${escHtml(post.content ? post.content.substring(0, 200) + (post.content.length > 200 ? '...' : '') : '')}</p>
            <div class="community-post-actions">
                <button class="community-action" ${likedClass} onclick="event.stopPropagation();toggleLike('${post.id}',this)">❤️ <span>${post.likes_count || 0}</span></button>
                <button class="community-action" onclick="event.stopPropagation();openComments('${post.id}')">💬 <span>${post.comments_count || 0}</span></button>
                <button class="community-action" onclick="event.stopPropagation();sharePost('${post.id}','${escHtml(post.title)}')">🔗 Share</button>
                ${deleteBtn}
            </div>
        </div>
    </div>`;
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// ===== FILTER BY CATEGORY =====
function filterCategory(cat, btn) {
    document.querySelectorAll('.community-cat-btn').forEach(b => {
        b.classList.remove('active');
        b.className = b.className.replace('btn-primary','btn-outline');
    });
    btn.classList.add('active');
    btn.className = btn.className.replace('btn-outline','btn-primary');
    loadPosts(cat);
}

// ===== CREATE POST =====
function openPostModal() {
    const LOGGED_IN = window.LOGGED_IN || false;
    if (!LOGGED_IN) { window.location.href = 'login.php'; return; }
    document.getElementById('postTitle').value = '';
    document.getElementById('postContent').value = '';
    document.getElementById('postCategory').selectedIndex = 0;
    removeImage();
    document.getElementById('postModal').classList.add('open');
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewContainer').style.display = 'block';
            document.getElementById('imageUploadPlaceholder').style.display = 'none';
            document.getElementById('imageUploadArea').style.borderColor = 'var(--primary)';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    document.getElementById('postImage').value = '';
    document.getElementById('imagePreviewContainer').style.display = 'none';
    document.getElementById('imageUploadPlaceholder').style.display = 'block';
    document.getElementById('imageUploadArea').style.borderColor = 'var(--gray-300)';
}

async function submitPost() {
    const title = document.getElementById('postTitle').value.trim();
    const content = document.getElementById('postContent').value.trim();
    const category = document.getElementById('postCategory').value;
    const imageFile = document.getElementById('postImage').files[0];

    if (!title || !content) {
        showToast('Please fill in the title and content.', 'warning');
        return;
    }

    const btn = document.getElementById('submitPostBtn');
    btn.disabled = true;
    btn.textContent = 'Publishing...';

    try {
        const formData = new FormData();
        formData.append('action', 'create-post');
        formData.append('title', title);
        formData.append('content', content);
        formData.append('category', category);
        if (imageFile) {
            formData.append('image', imageFile);
        }

        const res = await fetch('/api/community.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            closeModal('postModal');
            showToast('✅ Your post has been published!', 'success');
            loadPosts(currentCategory);
        } else {
            showToast(data.message || 'Failed to publish post.', 'error');
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Publish Post';
    }
}

// ===== DELETE POST =====
async function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) return;
    try {
        const res = await fetch('/api/community.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete-post', post_id: postId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('🗑️ Post deleted.', 'success');
            loadPosts(currentCategory);
        } else {
            showToast(data.message || 'Failed to delete.', 'error');
        }
    } catch (err) {
        showToast('Network error.', 'error');
    }
}

// ===== TOGGLE LIKE =====
async function toggleLike(postId, btn) {
    const LOGGED_IN = window.LOGGED_IN || false;
    if (!LOGGED_IN) { window.location.href = 'login.php'; return; }
    try {
        const res = await fetch('/api/community.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'toggle-like', post_id: postId })
        });
        const data = await res.json();
        if (data.success) {
            btn.style.color = data.liked ? 'var(--danger)' : '';
            btn.querySelector('span').textContent = data.likes_count;
        }
    } catch (err) {
        console.error(err);
    }
}

// ===== COMMENTS =====
function openComments(postId) {
    currentCommentPostId = postId;
    
    // Render post preview at top of modal
    const post = allPosts.find(p => p.id === postId);
    const previewContainer = document.getElementById('commentPostPreview');
    if (post) {
        const avatar = post.avatar_url 
            ? `<img src="${post.avatar_url}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">`
            : `<div class="community-avatar">${post.author_name ? post.author_name.charAt(0).toUpperCase() : '👤'}</div>`;
        const date = new Date(post.created_at).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
        const catBadge = post.category ? `<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.7rem;background:var(--primary-50);color:var(--primary);font-weight:600;">${categoryEmoji[post.category] || '📋'} ${categoryLabel[post.category] || post.category}</span>` : '';
        
        const imageSrc = post.image_src || null;
        const imageHtml = imageSrc
            ? `<div style="width:100%;max-height:300px;border-radius:var(--radius);margin-bottom:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;background:var(--gray-100);" onclick="openImageLightbox('${imageSrc}',event)"><img src="${imageSrc}" style="max-width:100%;max-height:300px;object-fit:contain;display:block;border-radius:var(--radius);" onerror="this.parentElement.style.display='none'"></div>`
            : '';

        previewContainer.innerHTML = `
            <div style="padding:16px 24px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    ${avatar}
                    <div>
                        <div style="font-weight:600;font-size:0.85rem;color:var(--gray-800);">${escHtml(post.author_name || 'Unknown')}</div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">${date}</div>
                    </div>
                    <div style="margin-left:auto;">${catBadge}</div>
                </div>
                ${imageHtml}
                <h3 style="font-size:1.05rem;font-weight:700;color:var(--gray-900);margin-bottom:8px;">${escHtml(post.title)}</h3>
                <p style="font-size:0.88rem;color:var(--gray-600);line-height:1.6;white-space:pre-line;word-wrap:break-word;">${escHtml(post.content)}</p>
                <div style="display:flex;align-items:center;gap:16px;margin-top:12px;padding-top:10px;border-top:1px solid var(--gray-100);font-size:0.8rem;color:var(--gray-500);">
                    <span>❤️ ${post.likes_count || 0} likes</span>
                    <span>💬 ${post.comments_count || 0} comments</span>
                </div>
            </div>`;
    } else {
        previewContainer.innerHTML = '';
    }

    document.getElementById('commentModal').classList.add('open');
    loadComments(postId);
}

async function loadComments(postId) {
    const container = document.getElementById('commentsContainer');
    container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);">Loading comments...</div>';

    try {
        const res = await fetch('/api/community.php?action=list-comments&post_id=' + postId);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--danger);">Failed to load comments.</div>';
            return;
        }

        const comments = data.comments || [];
        if (comments.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);"><div style="font-size:2rem;margin-bottom:8px;">💬</div>No comments yet. Be the first!</div>';
            return;
        }

        container.innerHTML = comments.map(c => renderComment(c)).join('');
    } catch (err) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--danger);">Network error.</div>';
    }
}

function renderComment(c) {
    const CURRENT_USER_ROLE = window.CURRENT_USER_ROLE || '';
    const avatar = c.avatar_url
        ? `<img src="${c.avatar_url}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;cursor:pointer;" onclick="showUserProfile('${c.user_id}')">`
        : `<div style="width:32px;height:32px;border-radius:50%;background:var(--primary-100);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:var(--primary);cursor:pointer;" onclick="showUserProfile('${c.user_id}')">${c.author_name ? c.author_name.charAt(0).toUpperCase() : '?'}</div>`;
    
    const date = new Date(c.created_at).toLocaleDateString('en-US', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
    const canDelete = (c.is_own || CURRENT_USER_ROLE === 'admin');
    const delBtn = canDelete ? `<button onclick="deleteComment('${c.id}')" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:0.75rem;margin-left:auto;" title="Delete comment">🗑️</button>` : '';

    return `
    <div style="display:flex;gap:10px;padding:12px 0;border-bottom:1px solid var(--gray-100);" data-comment-id="${c.id}">
        ${avatar}
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="javascript:void(0)" onclick="showUserProfile('${c.user_id}')" style="font-weight:600;font-size:0.85rem;color:var(--gray-800);text-decoration:none;cursor:pointer;">${escHtml(c.author_name)}</a>
                <span style="font-size:0.7rem;color:var(--gray-400);">${date}</span>
                ${delBtn}
            </div>
            <p style="font-size:0.85rem;color:var(--gray-600);margin-top:4px;line-height:1.5;word-wrap:break-word;">${escHtml(c.content)}</p>
        </div>
    </div>`;
}

async function submitComment() {
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    if (!content) return;

    try {
        const res = await fetch('/api/community.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'add-comment', post_id: currentCommentPostId, content: content })
        });
        const data = await res.json();
        if (data.success) {
            input.value = '';
            // Append the new comment
            const container = document.getElementById('commentsContainer');
            if (container.querySelector('[style*="No comments"]') || container.querySelector('[style*="Loading"]')) {
                container.innerHTML = '';
            }
            container.insertAdjacentHTML('beforeend', renderComment(data.comment));
            container.scrollTop = container.scrollHeight;

            // Update comment count on the post card
            const postCard = document.querySelector(`[data-post-id="${currentCommentPostId}"]`);
            if (postCard) {
                const commentBtn = postCard.querySelectorAll('.community-action')[1];
                if (commentBtn) {
                    const span = commentBtn.querySelector('span');
                    if (span) span.textContent = parseInt(span.textContent || 0) + 1;
                }
            }
        } else {
            showToast(data.message || 'Failed to post comment.', 'error');
        }
    } catch (err) {
        showToast('Network error.', 'error');
    }
}

async function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;
    try {
        const res = await fetch('/api/community.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete-comment', comment_id: commentId })
        });
        const data = await res.json();
        if (data.success) {
            const el = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (el) el.remove();
            // Update comment count
            const postCard = document.querySelector(`[data-post-id="${currentCommentPostId}"]`);
            if (postCard) {
                const commentBtn = postCard.querySelectorAll('.community-action')[1];
                if (commentBtn) {
                    const span = commentBtn.querySelector('span');
                    if (span) span.textContent = Math.max(0, parseInt(span.textContent || 0) - 1);
                }
            }
            // Check if no comments left
            const container = document.getElementById('commentsContainer');
            if (!container.querySelector('[data-comment-id]')) {
                container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);"><div style="font-size:2rem;margin-bottom:8px;">💬</div>No comments yet. Be the first!</div>';
            }
        } else {
            showToast(data.message || 'Failed to delete.', 'error');
        }
    } catch (err) {
        showToast('Network error.', 'error');
    }
}

// ===== SHARE POST =====
function sharePost(postId, title) {
    const url = window.location.origin + '/community.php#post-' + postId;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('🔗 Link copied to clipboard!', 'success');
        });
    } else {
        showToast('🔗 ' + url, 'info');
    }
}

// ===== USER PUBLIC PROFILE =====
async function showUserProfile(userId) {
    document.getElementById('userProfileModal').classList.add('open');
    const container = document.getElementById('userProfileContent');
    container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);">Loading profile...</div>';

    try {
        const res = await fetch('/api/community.php?action=get-user-public&user_id=' + userId);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--danger);">' + (data.message || 'Failed to load profile.') + '</div>';
            return;
        }

        const u = data.user;
        const avatar = u.avatar_url
            ? `<img src="${u.avatar_url}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary-100);">`
            : `<div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-600));display:flex;align-items:center;justify-content:center;font-size:2rem;color:white;font-weight:700;">${u.full_name ? u.full_name.charAt(0).toUpperCase() : '?'}</div>`;

        const memberSince = new Date(u.created_at).toLocaleDateString('en-US', { year:'numeric', month:'long' });
        const roleBadge = u.role === 'admin' ? '<span style="background:#ef4444;color:white;padding:2px 10px;border-radius:12px;font-size:0.7rem;font-weight:600;">ADMIN</span>'
            : u.role === 'owner' ? '<span style="background:var(--primary);color:white;padding:2px 10px;border-radius:12px;font-size:0.7rem;font-weight:600;">OWNER</span>'
            : '<span style="background:var(--gray-200);color:var(--gray-700);padding:2px 10px;border-radius:12px;font-size:0.7rem;font-weight:600;">MEMBER</span>';

        const membershipBadge = u.membership && u.membership !== 'free'
            ? `<span style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:2px 10px;border-radius:12px;font-size:0.7rem;font-weight:600;">⭐ ${u.membership.toUpperCase()}</span>`
            : '';

        let recentPostsHtml = '';
        if (u.recent_posts && u.recent_posts.length > 0) {
            recentPostsHtml = `
                <div style="margin-top:20px;">
                    <h4 style="font-size:0.9rem;font-weight:700;color:var(--gray-800);margin-bottom:12px;">📝 Recent Posts</h4>
                    ${u.recent_posts.map(p => {
                        const pDate = new Date(p.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric'});
                        return `<div style="padding:10px 12px;background:var(--gray-50);border-radius:var(--radius);margin-bottom:8px;">
                            <div style="font-weight:600;font-size:0.85rem;color:var(--gray-800);">${escHtml(p.title)}</div>
                            <div style="font-size:0.75rem;color:var(--gray-400);margin-top:4px;">
                                ${categoryEmoji[p.category] || '📋'} ${categoryLabel[p.category] || p.category} · ${pDate} · ❤️ ${p.likes_count} · 💬 ${p.comments_count}
                            </div>
                        </div>`;
                    }).join('')}
                </div>`;
        }

        container.innerHTML = `
            <div style="text-align:center;padding-bottom:20px;border-bottom:1px solid var(--gray-100);">
                ${avatar}
                <h3 style="margin-top:12px;font-size:1.2rem;font-weight:700;color:var(--gray-900);">${escHtml(u.full_name || 'Unknown User')}</h3>
                <div style="display:flex;gap:6px;justify-content:center;margin-top:8px;">
                    ${roleBadge} ${membershipBadge}
                </div>
                ${u.city || u.country ? `<p style="font-size:0.8rem;color:var(--gray-500);margin-top:6px;">📍 ${escHtml([u.city, u.country].filter(Boolean).join(', '))}</p>` : ''}
                ${u.bio ? `<p style="font-size:0.85rem;color:var(--gray-600);margin-top:8px;line-height:1.5;font-style:italic;">"${escHtml(u.bio)}"</p>` : ''}
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:20px 0;text-align:center;">
                <div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary);">${u.posts_count}</div>
                    <div style="font-size:0.7rem;color:var(--gray-500);margin-top:2px;">Posts</div>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary);">${u.comments_count}</div>
                    <div style="font-size:0.7rem;color:var(--gray-500);margin-top:2px;">Comments</div>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary);">${u.total_likes_received}</div>
                    <div style="font-size:0.7rem;color:var(--gray-500);margin-top:2px;">Likes</div>
                </div>
                <div>
                    <div style="font-size:0.85rem;font-weight:700;color:var(--primary);">${memberSince}</div>
                    <div style="font-size:0.7rem;color:var(--gray-500);margin-top:2px;">Member since</div>
                </div>
            </div>
            ${recentPostsHtml}
        `;
    } catch (err) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--danger);">Network error.</div>';
        console.error(err);
    }
}

// ===== ENTER KEY IN COMMENT =====
document.addEventListener('DOMContentLoaded', function() {
    const commentInput = document.getElementById('commentInput');
    if (commentInput) {
        commentInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitComment();
            }
        });
    }
});

// ===== LOAD POSTS ON PAGE LOAD =====
document.addEventListener('DOMContentLoaded', () => loadPosts());

// ===== IMAGE LIGHTBOX =====
function openImageLightbox(src, evt) {
    // Don't open lightbox if triggered from behind a modal overlay
    if (evt && !evt.target.closest('.modal-overlay') && document.querySelector('.modal-overlay.open')) {
        return;
    }

    let overlay = document.getElementById('imageLightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'imageLightbox';
        overlay.innerHTML = `
            <button class="lightbox-close" onclick="closeImageLightbox()">✕</button>
            <img class="lightbox-img" id="lightboxImg" src="" alt="Full image">
        `;
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeImageLightbox();
        });
        document.body.appendChild(overlay);
    }
    document.getElementById('lightboxImg').src = src;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeImageLightbox() {
    const overlay = document.getElementById('imageLightbox');
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImageLightbox();
});
