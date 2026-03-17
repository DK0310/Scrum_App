<?php

declare(strict_types=1);

final class CommunityRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a community post
     * @return array<string,mixed>
     */
    public function createPost(
        string $userId,
        string $title,
        string $content,
        string $category,
        ?string $storagePath = null,
        ?string $imageMime = null
    ): array {
        $stmt = $this->pdo->prepare("
            INSERT INTO community_posts (
                user_id, title, content, category, image_storage_path, image_mime, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            RETURNING id, created_at
        ");
        $stmt->execute([$userId, $title, $content, $category, $storagePath, $imageMime]);
        return (array)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * List all posts with optional category filter
     * @return array<int,array<string,mixed>>
     */
    public function listPosts(?string $category = null): array
    {
        if (!empty($category) && $category !== 'all') {
            $sql = "
                SELECT p.id, p.user_id, p.title, p.content, p.category, p.image_url,
                       p.likes_count, p.comments_count, p.created_at,
                       CASE WHEN p.image_storage_path IS NOT NULL THEN true
                            WHEN p.image_data IS NOT NULL THEN true
                            ELSE false END as has_image,
                       p.image_storage_path,
                       u.full_name as author_name, u.avatar_url, u.role as author_role
                FROM community_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.category = ?
                ORDER BY p.created_at DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$category]);
        } else {
            $sql = "
                SELECT p.id, p.user_id, p.title, p.content, p.category, p.image_url,
                       p.likes_count, p.comments_count, p.created_at,
                       CASE WHEN p.image_storage_path IS NOT NULL THEN true
                            WHEN p.image_data IS NOT NULL THEN true
                            ELSE false END as has_image,
                       p.image_storage_path,
                       u.full_name as author_name, u.avatar_url, u.role as author_role
                FROM community_posts p
                JOIN users u ON p.user_id = u.id
                ORDER BY p.created_at DESC
            ";
            $stmt = $this->pdo->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get post by ID with author details
     * @return array<string,mixed>|null
     */
    public function getPostById(string $postId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id, p.user_id, p.title, p.content, p.category,
                p.image_storage_path, p.image_mime, p.status,
                p.created_at, p.updated_at,
                u.full_name as author_name, u.avatar_url,
                (SELECT COUNT(*) FROM community_comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM community_likes WHERE post_id = p.id) as like_count
            FROM community_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ? AND p.status = 'published'
        ");
        $stmt->execute([$postId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List posts by category with pagination
     * @return array<int,array<string,mixed>>
     */
    public function listByCategory(string $category, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id, p.user_id, p.title, p.content, p.category,
                p.image_storage_path, p.created_at,
                u.full_name as author_name, u.avatar_url,
                (SELECT COUNT(*) FROM community_comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM community_likes WHERE post_id = p.id) as like_count
            FROM community_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.category = ? AND p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$category, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List all published posts
     * @return array<int,array<string,mixed>>
     */
    public function listAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id, p.user_id, p.title, p.content, p.category,
                p.image_storage_path, p.created_at,
                u.full_name as author_name, u.avatar_url,
                (SELECT COUNT(*) FROM community_comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM community_likes WHERE post_id = p.id) as like_count
            FROM community_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search posts by title/content
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $searchTerm = '%' . $query . '%';
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id, p.user_id, p.title, p.content, p.category,
                p.image_storage_path, p.created_at,
                u.full_name as author_name, u.avatar_url,
                (SELECT COUNT(*) FROM community_comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM community_likes WHERE post_id = p.id) as like_count
            FROM community_posts p
            JOIN users u ON p.user_id = u.id
            WHERE (LOWER(p.title) LIKE LOWER(?) OR LOWER(p.content) LIKE LOWER(?))
              AND p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update post
     */
    public function updatePost(string $postId, array $updates): bool
    {
        $sets = [];
        $params = [':id' => $postId];

        if (array_key_exists('title', $updates)) {
            $sets[] = 'title = :title';
            $params[':title'] = $updates['title'];
        }
        if (array_key_exists('content', $updates)) {
            $sets[] = 'content = :content';
            $params[':content'] = $updates['content'];
        }
        if (array_key_exists('category', $updates)) {
            $sets[] = 'category = :category';
            $params[':category'] = $updates['category'];
        }

        if (!$sets) {
            return false;
        }

        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE community_posts SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    /**
     * Delete post
     */
    public function deletePost(string $postId): bool
    {
        // Also delete associated comments and likes
        $this->pdo->prepare("DELETE FROM community_comments WHERE post_id = ?")->execute([$postId]);
        $this->pdo->prepare("DELETE FROM community_likes WHERE post_id = ?")->execute([$postId]);

        $stmt = $this->pdo->prepare("DELETE FROM community_posts WHERE id = ?");
        return $stmt->execute([$postId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get distinct categories
     * @return array<int,string>
     */
    public function getCategories(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT category FROM community_posts
            WHERE status = 'published'
            ORDER BY category ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'category');
    }

    /**
     * Get post comments with author details
     * @return array<int,array<string,mixed>>
     */
    public function listComments(string $postId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.content, c.created_at, c.user_id,
                   u.full_name as author_name, u.avatar_url, u.role as author_role
            FROM community_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get comment by ID
     * @return array<string,mixed>|null
     */
    public function getCommentById(string $commentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, post_id, user_id, content, created_at
            FROM community_comments WHERE id = ?
        ");
        $stmt->execute([$commentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Add comment with automatic count update
     * @return array<string,mixed>
     */
    public function addComment(string $postId, string $userId, string $content): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO community_comments (post_id, user_id, content)
            VALUES (?, ?, ?)
            RETURNING id, created_at
        ");
        $stmt->execute([$postId, $userId, $content]);
        $comment = (array)$stmt->fetch(PDO::FETCH_ASSOC);

        // Update comments count
        $this->pdo->prepare("UPDATE community_posts SET comments_count = comments_count + 1 WHERE id = ?")
            ->execute([$postId]);

        return $comment;
    }

    /**
     * Delete comment with automatic count update
     */
    public function deleteComment(string $commentId, string $postId): bool
    {
        $result = $this->pdo->prepare("DELETE FROM community_comments WHERE id = ?")
            ->execute([$commentId]);

        // Update comments count
        $this->pdo->prepare("UPDATE community_posts SET comments_count = GREATEST(comments_count - 1, 0) WHERE id = ?")
            ->execute([$postId]);

        return $result;
    }

    /**
     * Toggle like (like/unlike) for a post
     * @return array<string,mixed> ['liked' => bool, 'likes_count' => int]
     */
    public function toggleLike(string $postId, string $userId): array
    {
        // Check if already liked
        $stmt = $this->pdo->prepare("SELECT id FROM community_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Unlike
            $this->pdo->prepare("DELETE FROM community_likes WHERE post_id = ? AND user_id = ?")
                ->execute([$postId, $userId]);
            $this->pdo->prepare("UPDATE community_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")
                ->execute([$postId]);
            $liked = false;
        } else {
            // Like
            $this->pdo->prepare("INSERT INTO community_likes (post_id, user_id) VALUES (?, ?)")
                ->execute([$postId, $userId]);
            $this->pdo->prepare("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?")
                ->execute([$postId]);
            $liked = true;
        }

        // Get updated count
        $stmt = $this->pdo->prepare("SELECT likes_count FROM community_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $count = $stmt->fetchColumn();

        return ['liked' => $liked, 'likes_count' => (int)$count];
    }

    /**
     * Check if user has liked a post
     */
    public function hasUserLikedPost(string $postId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM community_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get user's posts with image info
     * @return array<int,array<string,mixed>>
     */
    public function listPostsByUser(string $userId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, category, likes_count, comments_count, created_at,
                   image_storage_path,
                   CASE WHEN image_storage_path IS NOT NULL THEN true
                        WHEN image_data IS NOT NULL THEN true
                        ELSE false END as has_image
            FROM community_posts WHERE user_id = ?
            ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user's public profile
     * @return array<string,mixed>|null
     */
    public function getUserProfile(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, full_name, avatar_url, bio,
                (SELECT COUNT(*) FROM community_posts WHERE user_id = u.id AND status = 'published') as post_count,
                (SELECT COUNT(*) FROM community_likes WHERE user_id = u.id) as like_count
            FROM users u
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get post image info
     */
    public function getPostImageInfo(string $postId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT image_storage_path, image_mime FROM community_posts WHERE id = ? LIMIT 1");
        $stmt->execute([$postId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
