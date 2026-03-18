<?php
/**
 * Community Page & API - Private Hire
 * - Page view: /api/community.php (renders template)
 * - API: /api/community.php?action=xxx (returns JSON)
 * - Upload: /api/community.php?action=get-post-image&id=xxx (redirect to Supabase)
 */

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/supabase-storage.php';
require_once __DIR__ . '/../sql/CommunityRepository.php';
require_once __DIR__ . '/../sql/UserRepository.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;
$title = "Community - Private Hire";
$currentPage = 'community';

$communityRepo = new CommunityRepository($pdo);
$userRepo = new UserRepository($pdo);

// Run migration
try {
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_storage_path TEXT");
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_data BYTEA");
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_mime VARCHAR(50)");
} catch (PDOException $e) {}

// Determine action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== SERVE POST IMAGE =====
if ($action === 'get-post-image') {
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
if (!empty($action)) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Handle both multipart and JSON
    $input = json_decode(file_get_contents('php://input'), true);

    function requireLogin() {
        global $isLoggedIn;
        if (!$isLoggedIn) {
            echo json_encode(['success' => false, 'message' => 'Please login first.']);
            exit;
        }
    }

    // LIST POSTS
    if ($action === 'list-posts') {
        $category = $input['category'] ?? $_GET['category'] ?? '';
        try {
            $posts = $communityRepo->listPosts($category);
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

    // CREATE POST
    if ($action === 'create-post') {
        requireLogin();
        if (isset($_FILES['image'])) {
            $postTitle = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $category = $_POST['category'] ?? 'road_trip';
        } else {
            $postTitle = $input['title'] ?? '';
            $content = $input['content'] ?? '';
            $category = $input['category'] ?? 'road_trip';
        }
        if (empty($postTitle) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
            exit;
        }
        $storagePath = null;
        $imageMime = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024;
            if (!in_array($file['type'], $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
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
                echo json_encode(['success' => false, 'message' => 'Storage upload failed.']);
                exit;
            }
        }
        try {
            $post = $communityRepo->createPost($userId, $postTitle, $content, $category, $storagePath, $imageMime);
            echo json_encode(['success' => true, 'message' => 'Post published!', 'post_id' => $post['id']]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // DELETE POST
    if ($action === 'delete-post') {
        requireLogin();
        $postId = $input['post_id'] ?? '';
        if (empty($postId)) {
            echo json_encode(['success' => false, 'message' => 'Post ID required.']);
            exit;
        }
        try {
            $post = $communityRepo->getPostById($postId);
            if (!$post) {
                echo json_encode(['success' => false, 'message' => 'Post not found.']);
                exit;
            }
            if ($post['user_id'] !== $userId && $userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'You do not have permission.']);
                exit;
            }
            if (!empty($post['image_storage_path'])) {
                $storage = new SupabaseStorage();
                $storage->delete($post['image_storage_path']);
            }
            $communityRepo->deletePost($postId);
            echo json_encode(['success' => true, 'message' => 'Post deleted.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // TOGGLE LIKE
    if ($action === 'toggle-like') {
        requireLogin();
        $postId = $input['post_id'] ?? '';
        if (empty($postId)) {
            echo json_encode(['success' => false, 'message' => 'Post ID required.']);
            exit;
        }
        try {
            $result = $communityRepo->toggleLike($postId, $userId);
            echo json_encode(['success' => true, 'liked' => $result['liked'], 'likes_count' => (int)$result['likes_count']]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // LIST COMMENTS
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
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // ADD COMMENT
    if ($action === 'add-comment') {
        requireLogin();
        $postId = $input['post_id'] ?? '';
        $commentContent = $input['content'] ?? '';
        if (empty($postId) || empty($commentContent)) {
            echo json_encode(['success' => false, 'message' => 'Post ID and content required.']);
            exit;
        }
        try {
            $comment = $communityRepo->addComment($postId, $userId, $commentContent);
            $user = $userRepo->findById($userId);
            echo json_encode(['success' => true, 'comment' => [
                'id' => $comment['id'],
                'content' => $commentContent,
                'created_at' => $comment['created_at'],
                'user_id' => $userId,
                'author_name' => $user['full_name'],
                'avatar_url' => $user['avatar_url'],
                'author_role' => $user['role'],
                'is_own' => true
            ]]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // DELETE COMMENT
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
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // GET USER PUBLIC PROFILE
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
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}

// ===== PAGE VIEW =====
include __DIR__ . '/../templates/community.html.php';
?>
