<?php

declare(strict_types=1);

final class VehicleImageRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int,array{id:string,storage_path:string|null}>
     */
    public function listByVehicleId(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, storage_path FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, sort_order ASC, created_at ASC');
        $stmt->execute([$vehicleId]);
        /** @var array<int,array{id:string,storage_path:string|null}> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,string>
     */
    public function listIdsByVehicleId(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, sort_order ASC, created_at ASC');
        $stmt->execute([$vehicleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(string $imageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT storage_path, mime_type, file_name FROM vehicle_images WHERE id = ?');
        $stmt->execute([$imageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
