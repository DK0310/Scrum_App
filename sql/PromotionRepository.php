<?php

declare(strict_types=1);

final class PromotionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM promotions ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function codeExists(string $code): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM promotions WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find promotion by code (case-insensitive)
     * @return array<string,mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, code, title, description, discount_type, discount_value,
                   min_booking_days, max_uses, total_used, starts_at, expires_at, is_active
            FROM promotions
            WHERE UPPER(code) = ? AND is_active = true
        ");
        $stmt->execute([strtoupper($code)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @return array<string,mixed>
     */
    public function create(
        string $code,
        string $description,
        string $discountType,
        float $discountValue,
        ?string $startDate,
        ?string $endDate,
        ?int $usageLimit
    ): array {
        $stmt = $this->pdo->prepare(
            'INSERT INTO promotions (code, title, description, discount_type, discount_value, starts_at, expires_at, max_uses)\n             VALUES (?, ?, ?, ?, ?, ?::TIMESTAMPTZ, ?::TIMESTAMPTZ, ?)\n             RETURNING *'
        );
        $stmt->execute([
            strtoupper($code), strtoupper($code), $description, $discountType, $discountValue,
            $startDate ?: null, $endDate ?: null, $usageLimit
        ]);
        return (array)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update(string $promoId, array $changes): int
    {
        $fields = [];
        $params = [':id' => $promoId];

        if (array_key_exists('code', $changes)) { $fields[] = 'code = :code'; $params[':code'] = strtoupper((string)$changes['code']); }
        if (array_key_exists('description', $changes)) { $fields[] = 'description = :desc'; $params[':desc'] = $changes['description']; }
        if (array_key_exists('discount_type', $changes)) { $fields[] = 'discount_type = :dtype'; $params[':dtype'] = $changes['discount_type']; }
        if (array_key_exists('discount_value', $changes)) { $fields[] = 'discount_value = :dval'; $params[':dval'] = (float)$changes['discount_value']; }
        if (array_key_exists('start_date', $changes)) { $fields[] = 'starts_at = :sd::TIMESTAMPTZ'; $params[':sd'] = $changes['start_date']; }
        if (array_key_exists('end_date', $changes)) { $fields[] = 'expires_at = :ed::TIMESTAMPTZ'; $params[':ed'] = $changes['end_date']; }
        if (array_key_exists('usage_limit', $changes)) { $fields[] = 'max_uses = :ul'; $params[':ul'] = (int)$changes['usage_limit']; }
        if (array_key_exists('is_active', $changes)) { $fields[] = 'is_active = :active'; $params[':active'] = (bool)$changes['is_active']; }

        if (!$fields) {
            return 0;
        }

        $sql = 'UPDATE promotions SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function delete(string $promoId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM promotions WHERE id = ?');
        $stmt->execute([$promoId]);
        return $stmt->rowCount();
    }

    public function incrementUsageCount(string $promoId): void
    {
        $stmt = $this->pdo->prepare("UPDATE promotions SET total_used = total_used + 1 WHERE id = ?");
        $stmt->execute([$promoId]);
    }
}
