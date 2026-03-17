<?php
/**
 * Community API - DriveNow
 * Handles community posts CRUD, comments, likes, user public profile
 * - Posts with optional image upload to Supabase Storage
 * - Category filtering
 * - Comments system
 * - Like/unlike toggle
 * - User public profile
 */

// ===== SERVE POST IMAGE (redirect to Supabase Storage) =====
$action = $_GET['action'] ?? '';
if ($action === 'get-post-image') {
    session_start();
    require_once __DIR__ . '/../Database/db.php';
    require_once __DIR__ . '/supabase-storage.php';

    $postId = $_GET['id'] ?? '';
    if (empty($postId)) {
        http_response_code(400);
        echo 'Post ID required';
        exit;
    }

    try {
        $row = $communityRepo->getPostImageInfo($postId);

        if (!$row || empty($row['image_storage_path'])) {
            http_response_code(404);
            echo 'Image not found';
            exit;
        }

        $storage = new SupabaseStorage();
        $publicUrl = $storage->getPublicUrl($row['image_storage_path']);
        header('Location: ' . $publicUrl, true, 302);
        header('Cache-Control: public, max-age=86400');
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Server error';
    }
    exit;
}

// ===== JSON API =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../lib/repositories/CommunityRepository.php';
require_once __DIR__ . '/../lib/repositories/UserRepository.php';

$communityRepo = new CommunityRepository($pdo);
$userRepo = new UserRepository($pdo);

// Run migration: add image_storage_path if not exist
try {
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_storage_path TEXT");
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_data BYTEA");
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_mime VARCHAR(50)");
} catch (PDOException $e) {
    // columns may already exist — ignore
}

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';

// Determine action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $action = $_POST['action'] ?? 'create-post';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
}

function requireLogin() {
    global $isLoggedIn;
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please login first.']);
        exit;
    }
}

// ==========================================================
// LIST POSTS (with optional category filter)
// ==========================================================
if ($action === 'list-posts') {
    $category = $input['category'] ?? $_GET['category'] ?? '';

    try {
        $posts = $communityRepo->listPosts($category);

        // Check if current user liked each post
        $storage = new SupabaseStorage();
        foreach ($posts as &$post) {
            $post['has_image'] = ($post['has_image'] === true || $post['has_image'] === 't');
            if ($post['has_image'] && !empty($post['image_storage_path'])) {
                $post['image_src'] = $storage->getPublicUrl($post['image_storage_path']);
            } else if ($post['has_image']) {
                $post['image_src'] = '/api/community.php?action=get-post-image&id=' . $post['id'];
            } else {
                $post['image_src'] = null;
            }
            unset($post['image_storage_path']);
            $post['is_own'] = ($userId && $post['user_id'] === $userId);
            $post['liked'] = false;

            if ($userId) {
                $post['liked'] = $communityRepo->hasUserLikedPost($post['id'], $userId);
            }
        }
        unset($post);

        echo json_encode(['success' => true, 'posts' => $posts]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CREATE POST (with optional image upload)
// ==========================================================
if ($action === 'create-post') {
    requireLogin();

    // Handle both multipart and JSON
    if (isset($_FILES['image'])) {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $category = $_POST['category'] ?? 'road_trip';
    } else {
        $title = $input['title'] ?? '';
        $content = $input['content'] ?? '';
        $category = $input['category'] ?? 'road_trip';
    }

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
        exit;
    }

    // Process image if uploaded — upload to Supabase Storage
    $storagePath = null;
    $imageMime = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image type. Only JPEG, PNG, WebP, GIF allowed.']);
            exit;
        }
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
            exit;
        }

        $imageData = file_get_contents($file['tmp_name']);
        $imageMime = $file['type'];

        $storage = new SupabaseStorage();
        $uniqueName = SupabaseStorage::uniqueName($file['name']);
        $storagePath = 'community/' . $uniqueName;

        $uploadResult = $storage->upload($storagePath, $imageData, $file['type']);
        if (!$uploadResult['success']) {
            echo json_encode(['success' => false, 'message' => 'Storage upload failed: ' . ($uploadResult['message'] ?? 'Unknown error')]);
            exit;
        }
    }

    try {
        $post = $communityRepo->createPost($userId, $title, $content, $category, $storagePath, $imageMime);

        echo json_encode(['success' => true, 'message' => 'Post published!', 'post_id' => $post['id']]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DELETE POST (author or admin only)
// ==========================================================
if ($action === 'delete-post') {
    requireLogin();

    $postId = $input['post_id'] ?? '';
    if (empty($postId)) {
        echo json_encode(['success' => false, 'message' => 'Post ID required.']);
        exit;
    }

    try {
        // Check ownership or admin
        $post = $communityRepo->getPostById($postId);

        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post not found.']);
            exit;
        }

        if ($post['user_id'] !== $userId && $userRole !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this post.']);
            exit;
        }

        // Delete image from Supabase Storage
        if (!empty($post['image_storage_path'])) {
            $storage = new SupabaseStorage();
            $storage->delete($post['image_storage_path']);
        }

        // Cascade delete handles comments and likes
        $communityRepo->deletePost($postId);

        echo json_encode(['success' => true, 'message' => 'Post deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// TOGGLE LIKE
// ==========================================================
if ($action === 'toggle-like') {
    requireLogin();

    $postId = $input['post_id'] ?? '';
    if (empty($postId)) {
        echo json_encode(['success' => false, 'message' => 'Post ID required.']);
        exit;
    }

    try {
        $result = $communityRepo->toggleLike($postId, $userId);

        echo json_encode([
            'success' => true,
            'liked' => $result['liked'],
            'likes_count' => (int)$result['likes_count']
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// LIST COMMENTS (for a post)
// ==========================================================
if ($action === 'list-comments') {
    $postId = $input['post_id'] ?? $_GET['post_id'] ?? '';
    if (empty($postId)) {
        echo json_encode(['success' => false, 'message' => 'Post ID required.']);
        exit;
    }

    try {
        $comments = $communityRepo->listComments($postId);

        foreach ($comments as &$comment) {
            $comment['is_own'] = ($userId && $comment['user_id'] === $userId);
        }
        unset($comment);

        echo json_encode(['success' => true, 'comments' => $comments]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// ADD COMMENT
// ==========================================================
if ($action === 'add-comment') {
    requireLogin();

    $postId = $input['post_id'] ?? '';
    $content = $input['content'] ?? '';

    if (empty($postId) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Post ID and content required.']);
        exit;
    }

    try {
        $comment = $communityRepo->addComment($postId, $userId, $content);

        // Get author info
        $user = $userRepo->findById($userId);

        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $comment['id'],
                'content' => $content,
                'created_at' => $comment['created_at'],
                'user_id' => $userId,
                'author_name' => $user['full_name'],
                'avatar_url' => $user['avatar_url'],
                'author_role' => $user['role'],
                'is_own' => true
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// DELETE COMMENT (author or admin)
// ==========================================================
if ($action === 'delete-comment') {
    requireLogin();

    $commentId = $input['comment_id'] ?? '';
    if (empty($commentId)) {
        echo json_encode(['success' => false, 'message' => 'Comment ID required.']);
        exit;
    }

    try {
        $comment = $communityRepo->getCommentById($commentId);

        if (!$comment) {
            echo json_encode(['success' => false, 'message' => 'Comment not found.']);
            exit;
        }

        if ($comment['user_id'] !== $userId && $userRole !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'No permission.']);
            exit;
        }

        $communityRepo->deleteComment($commentId, $comment['post_id']);

        echo json_encode(['success' => true, 'message' => 'Comment deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// GET USER PUBLIC PROFILE
// ==========================================================
if ($action === 'get-user-public') {
    $targetUserId = $input['user_id'] ?? $_GET['user_id'] ?? '';
    if (empty($targetUserId)) {
        echo json_encode(['success' => false, 'message' => 'User ID required.']);
        exit;
    }

    try {
        $user = $userRepo->getUserPublicProfile($targetUserId);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        // Recent posts
        $recentPosts = $communityRepo->listPostsByUser($targetUserId, 5);
        $storage = new SupabaseStorage();
        foreach ($recentPosts as &$rp) {
            $rp['has_image'] = ($rp['has_image'] === true || $rp['has_image'] === 't');
            if ($rp['has_image'] && !empty($rp['image_storage_path'])) {
                $rp['image_src'] = $storage->getPublicUrl($rp['image_storage_path']);
            } else if ($rp['has_image']) {
                $rp['image_src'] = '/api/community.php?action=get-post-image&id=' . $rp['id'];
            } else {
                $rp['image_src'] = null;
            }
            unset($rp['image_storage_path']);
        }
        unset($rp);

        $user['recent_posts'] = $recentPosts;

        echo json_encode(['success' => true, 'user' => $user]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
