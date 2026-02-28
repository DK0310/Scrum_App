<?php
/**
 * Community API - DriveNow
 * Handles community posts CRUD, comments, likes, user public profile
 * - Posts with optional BLOB image upload
 * - Category filtering
 * - Comments system
 * - Like/unlike toggle
 * - User public profile
 */

// ===== SERVE POST IMAGE (before JSON headers) =====
$action = $_GET['action'] ?? '';
if ($action === 'get-post-image') {
    session_start();
    require_once __DIR__ . '/../Database/db.php';

    $postId = $_GET['id'] ?? '';
    if (empty($postId)) {
        http_response_code(400);
        echo 'Post ID required';
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT image_data, image_mime FROM community_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['image_data']) {
            http_response_code(404);
            echo 'Image not found';
            exit;
        }

        $imageData = $row['image_data'];
        if (is_resource($imageData)) {
            $imageData = stream_get_contents($imageData);
        }
        if (is_string($imageData) && substr($imageData, 0, 2) === '\\x') {
            $imageData = hex2bin(substr($imageData, 2));
        }

        header('Content-Type: ' . ($row['image_mime'] ?? 'image/jpeg'));
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: public, max-age=86400');
        echo $imageData;
    } catch (PDOException $e) {
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

// Run migration: add image_data/image_mime if not exist
try {
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_data BYTEA");
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_mime VARCHAR(50)");
} catch (PDOException $e) {
    // columns may already exist — ignore
}

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'renter';

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
        $sql = "
            SELECT p.id, p.user_id, p.title, p.content, p.category, p.image_url,
                   p.likes_count, p.comments_count, p.created_at,
                   CASE WHEN p.image_data IS NOT NULL THEN true ELSE false END as has_image,
                   u.full_name as author_name, u.avatar_url, u.role as author_role
            FROM community_posts p
            JOIN users u ON p.user_id = u.id
        ";
        $params = [];

        if (!empty($category) && $category !== 'all') {
            $sql .= " WHERE p.category = :category";
            $params[':category'] = $category;
        }

        $sql .= " ORDER BY p.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if current user liked each post
        foreach ($posts as &$post) {
            $post['has_image'] = ($post['has_image'] === true || $post['has_image'] === 't');
            $post['image_src'] = $post['has_image']
                ? '/api/community.php?action=get-post-image&id=' . $post['id']
                : null;
            $post['is_own'] = ($userId && $post['user_id'] === $userId);
            $post['liked'] = false;

            if ($userId) {
                $likeStmt = $pdo->prepare("SELECT id FROM community_likes WHERE post_id = ? AND user_id = ?");
                $likeStmt->execute([$post['id'], $userId]);
                $post['liked'] = $likeStmt->fetch() ? true : false;
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

    // Process image if uploaded
    $imageData = null;
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
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO community_posts (user_id, title, content, category, image_data, image_mime)
            VALUES (:uid, :title, :content, :category, :imgdata, :imgmime)
            RETURNING id
        ");
        $stmt->bindParam(':uid', $userId);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':imgdata', $imageData, $imageData ? PDO::PARAM_LOB : PDO::PARAM_NULL);
        $stmt->bindParam(':imgmime', $imageMime);
        $stmt->execute();
        $postId = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'message' => 'Post published!', 'post_id' => $postId]);
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
        $stmt = $pdo->prepare("SELECT user_id FROM community_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post not found.']);
            exit;
        }

        if ($post['user_id'] !== $userId && $userRole !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this post.']);
            exit;
        }

        // Cascade delete handles comments and likes
        $stmt = $pdo->prepare("DELETE FROM community_posts WHERE id = ?");
        $stmt->execute([$postId]);

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
        // Check if already liked
        $stmt = $pdo->prepare("SELECT id FROM community_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Unlike
            $pdo->prepare("DELETE FROM community_likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
            $pdo->prepare("UPDATE community_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")->execute([$postId]);
            $liked = false;
        } else {
            // Like
            $pdo->prepare("INSERT INTO community_likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);
            $pdo->prepare("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$postId]);
            $liked = true;
        }

        // Get updated count
        $stmt = $pdo->prepare("SELECT likes_count FROM community_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $count = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'liked' => $liked, 'likes_count' => (int)$count]);
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
        $stmt = $pdo->prepare("
            SELECT c.id, c.content, c.created_at, c.user_id,
                   u.full_name as author_name, u.avatar_url, u.role as author_role
            FROM community_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$postId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $stmt = $pdo->prepare("
            INSERT INTO community_comments (post_id, user_id, content)
            VALUES (?, ?, ?)
            RETURNING id, created_at
        ");
        $stmt->execute([$postId, $userId, $content]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update comments count
        $pdo->prepare("UPDATE community_posts SET comments_count = comments_count + 1 WHERE id = ?")->execute([$postId]);

        // Get author info
        $uStmt = $pdo->prepare("SELECT full_name, avatar_url, role FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $row['id'],
                'content' => $content,
                'created_at' => $row['created_at'],
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
        $stmt = $pdo->prepare("SELECT user_id, post_id FROM community_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            echo json_encode(['success' => false, 'message' => 'Comment not found.']);
            exit;
        }

        if ($comment['user_id'] !== $userId && $userRole !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'No permission.']);
            exit;
        }

        $pdo->prepare("DELETE FROM community_comments WHERE id = ?")->execute([$commentId]);
        $pdo->prepare("UPDATE community_posts SET comments_count = GREATEST(comments_count - 1, 0) WHERE id = ?")->execute([$comment['post_id']]);

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
        $stmt = $pdo->prepare("
            SELECT id, full_name, avatar_url, role, bio, city, country, membership, created_at
            FROM users WHERE id = ?
        ");
        $stmt->execute([$targetUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        // Count posts
        $pStmt = $pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ?");
        $pStmt->execute([$targetUserId]);
        $user['posts_count'] = (int)$pStmt->fetchColumn();

        // Count comments
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM community_comments WHERE user_id = ?");
        $cStmt->execute([$targetUserId]);
        $user['comments_count'] = (int)$cStmt->fetchColumn();

        // Count total likes received
        $lStmt = $pdo->prepare("
            SELECT COALESCE(SUM(p.likes_count), 0) 
            FROM community_posts p WHERE p.user_id = ?
        ");
        $lStmt->execute([$targetUserId]);
        $user['total_likes_received'] = (int)$lStmt->fetchColumn();

        // Recent posts
        $rpStmt = $pdo->prepare("
            SELECT id, title, category, likes_count, comments_count, created_at,
                   CASE WHEN image_data IS NOT NULL THEN true ELSE false END as has_image
            FROM community_posts WHERE user_id = ?
            ORDER BY created_at DESC LIMIT 5
        ");
        $rpStmt->execute([$targetUserId]);
        $user['recent_posts'] = $rpStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($user['recent_posts'] as &$rp) {
            $rp['has_image'] = ($rp['has_image'] === true || $rp['has_image'] === 't');
            $rp['image_src'] = $rp['has_image']
                ? '/api/community.php?action=get-post-image&id=' . $rp['id']
                : null;
        }
        unset($rp);

        echo json_encode(['success' => true, 'user' => $user]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
