<?php

declare(strict_types=1);

final class HeroSlideRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureStoragePathColumnExists(): void
    {
        // safe no-op if already exists
        $this->pdo->exec('ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS storage_path TEXT');
    }

    public function hasStoragePathColumn(): bool
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'hero_slides' AND column_name = 'storage_path'");
        return $stmt->fetch() !== false;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findImageRowById(string $slideId): ?array
    {
        $select = $this->hasStoragePathColumn()
            ? 'SELECT storage_path, mime_type, file_name, image_data FROM hero_slides WHERE id = ?'
            : 'SELECT NULL as storage_path, mime_type, file_name, image_data FROM hero_slides WHERE id = ?';

        $stmt = $this->pdo->prepare($select);
        $stmt->execute([$slideId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listActiveSlidesForPublic(): array
    {
        $select = $this->hasStoragePathColumn()
            ? 'SELECT id, title, subtitle, link_url, sort_order, storage_path FROM hero_slides WHERE is_active = TRUE ORDER BY sort_order ASC, created_at DESC'
            : 'SELECT id, title, subtitle, link_url, sort_order, NULL as storage_path FROM hero_slides WHERE is_active = TRUE ORDER BY sort_order ASC, created_at DESC';

        $stmt = $this->pdo->query($select);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listSlidesForAdmin(): array
    {
        $select = $this->hasStoragePathColumn()
            ? 'SELECT id, file_name, mime_type, file_size, title, subtitle, link_url, sort_order, is_active, created_at, storage_path FROM hero_slides ORDER BY sort_order ASC, created_at DESC'
            : 'SELECT id, file_name, mime_type, file_size, title, subtitle, link_url, sort_order, is_active, created_at, NULL as storage_path FROM hero_slides ORDER BY sort_order ASC, created_at DESC';

        $stmt = $this->pdo->query($select);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSlide(
        string $storagePath,
        string $imageData,
        string $mimeType,
        string $fileName,
        int $fileSize,
        string $title,
        string $subtitle,
        string $linkUrl,
        int $sortOrder,
        bool $isActive,
        string $createdBy
    ): string {
        $stmt = $this->pdo->prepare(
            "INSERT INTO hero_slides (storage_path, image_data, mime_type, file_name, file_size, title, subtitle, link_url, sort_order, is_active, created_by)
             VALUES (:spath, :imgdata, :mime, :fname, :fsize, :title, :sub, :link, :sort, :active, :uid)
             RETURNING id"
        );
        $stmt->bindParam(':spath', $storagePath);
        $stmt->bindParam(':imgdata', $imageData, PDO::PARAM_LOB);
        $stmt->bindParam(':mime', $mimeType);
        $stmt->bindParam(':fname', $fileName);
        $stmt->bindParam(':fsize', $fileSize, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':sub', $subtitle);
        $stmt->bindParam(':link', $linkUrl);
        $stmt->bindParam(':sort', $sortOrder, PDO::PARAM_INT);
        $stmt->bindParam(':active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindParam(':uid', $createdBy);
        $stmt->execute();

        return (string)$stmt->fetchColumn();
    }

    public function updateSlideFields(string $slideId, array $fields): int
    {
        $sets = [];
        $params = [':id' => $slideId];

        if (array_key_exists('title', $fields)) { $sets[] = 'title = :title'; $params[':title'] = $fields['title']; }
        if (array_key_exists('subtitle', $fields)) { $sets[] = 'subtitle = :sub'; $params[':sub'] = $fields['subtitle']; }
        if (array_key_exists('link_url', $fields)) { $sets[] = 'link_url = :link'; $params[':link'] = $fields['link_url']; }
        if (array_key_exists('sort_order', $fields)) { $sets[] = 'sort_order = :sort'; $params[':sort'] = (int)$fields['sort_order']; }
        if (array_key_exists('is_active', $fields)) { $sets[] = 'is_active = :active'; $params[':active'] = (bool)$fields['is_active']; }

        if (!$sets) {
            return 0;
        }

        $sql = 'UPDATE hero_slides SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function findStoragePathById(string $slideId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT storage_path FROM hero_slides WHERE id = ?');
        $stmt->execute([$slideId]);
        $path = $stmt->fetchColumn();
        return $path !== false ? (string)$path : null;
    }

    public function deleteSlideById(string $slideId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM hero_slides WHERE id = ?');
        $stmt->execute([$slideId]);
        return $stmt->rowCount();
    }
}
