<?php

declare(strict_types=1);

final class EnquiryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS customer_enquiries (
                id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                customer_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                enquiry_type VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                image_storage_path TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                replied_at TIMESTAMPTZ
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS enquiry_replies (
                id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                enquiry_id UUID NOT NULL UNIQUE REFERENCES customer_enquiries(id) ON DELETE CASCADE,
                staff_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                content TEXT NOT NULL,
                image_storage_path TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )"
        );

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_customer_enquiries_customer ON customer_enquiries(customer_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_customer_enquiries_created ON customer_enquiries(created_at DESC)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_customer_enquiries_status ON customer_enquiries(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_enquiry_replies_staff ON enquiry_replies(staff_id)");
    }

    public function createEnquiry(string $customerId, string $enquiryType, string $content, ?string $imageStoragePath): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO customer_enquiries (customer_id, enquiry_type, content, image_storage_path)
             VALUES (?, ?, ?, ?)
             RETURNING id, customer_id, enquiry_type, content, image_storage_path, status, created_at, updated_at, replied_at"
        );
        $stmt->execute([$customerId, $enquiryType, $content, $imageStoragePath]);
        return (array)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listByCustomer(string $customerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.customer_id, e.enquiry_type, e.content, e.image_storage_path, e.status,
                    e.created_at, e.updated_at, e.replied_at,
                    r.id AS reply_id, r.content AS reply_content, r.image_storage_path AS reply_image_storage_path,
                    r.created_at AS reply_created_at, r.staff_id,
                    u.full_name AS staff_name
             FROM customer_enquiries e
             LEFT JOIN enquiry_replies r ON r.enquiry_id = e.id
             LEFT JOIN users u ON u.id = r.staff_id
             WHERE e.customer_id = ?
             ORDER BY e.created_at DESC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForStaff(): array
    {
        $stmt = $this->pdo->query(
            "SELECT e.id, e.customer_id, e.enquiry_type, e.content, e.image_storage_path, e.status,
                    e.created_at, e.updated_at, e.replied_at,
                    cu.full_name AS customer_name, cu.email AS customer_email, cu.phone AS customer_phone,
                    r.id AS reply_id, r.content AS reply_content, r.image_storage_path AS reply_image_storage_path,
                    r.created_at AS reply_created_at, r.staff_id,
                    su.full_name AS staff_name
             FROM customer_enquiries e
             JOIN users cu ON cu.id = e.customer_id
             LEFT JOIN enquiry_replies r ON r.enquiry_id = e.id
             LEFT JOIN users su ON su.id = r.staff_id
             ORDER BY e.created_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $enquiryId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.customer_id, e.enquiry_type, e.content, e.image_storage_path, e.status,
                    e.created_at, e.updated_at, e.replied_at,
                    r.id AS reply_id, r.content AS reply_content, r.image_storage_path AS reply_image_storage_path,
                    r.created_at AS reply_created_at, r.staff_id,
                    su.full_name AS staff_name
             FROM customer_enquiries e
             LEFT JOIN enquiry_replies r ON r.enquiry_id = e.id
             LEFT JOIN users su ON su.id = r.staff_id
             WHERE e.id = ?
             LIMIT 1"
        );
        $stmt->execute([$enquiryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (array)$row : null;
    }

    public function deleteByCustomer(string $enquiryId, string $customerId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM customer_enquiries
             WHERE id = ?
               AND customer_id = ?
               AND status = 'open'
               AND NOT EXISTS (SELECT 1 FROM enquiry_replies r WHERE r.enquiry_id = customer_enquiries.id)"
        );
        $stmt->execute([$enquiryId, $customerId]);
        return $stmt->rowCount() > 0;
    }

    public function createReply(string $enquiryId, string $staffId, string $content, ?string $imageStoragePath): array
    {
        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare(
                "SELECT id, status FROM customer_enquiries WHERE id = ? FOR UPDATE"
            );
            $lockStmt->execute([$enquiryId]);
            $enquiry = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$enquiry) {
                throw new RuntimeException('Enquiry not found.');
            }

            if ((string)$enquiry['status'] !== 'open') {
                throw new RuntimeException('This enquiry has already been replied.');
            }

            $existsStmt = $this->pdo->prepare("SELECT 1 FROM enquiry_replies WHERE enquiry_id = ? LIMIT 1");
            $existsStmt->execute([$enquiryId]);
            if ($existsStmt->fetchColumn()) {
                throw new RuntimeException('This enquiry has already been replied.');
            }

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO enquiry_replies (enquiry_id, staff_id, content, image_storage_path)
                 VALUES (?, ?, ?, ?)
                 RETURNING id, enquiry_id, staff_id, content, image_storage_path, created_at"
            );
            $insertStmt->execute([$enquiryId, $staffId, $content, $imageStoragePath]);
            $reply = (array)$insertStmt->fetch(PDO::FETCH_ASSOC);

            $updateStmt = $this->pdo->prepare(
                "UPDATE customer_enquiries
                 SET status = 'replied', replied_at = NOW(), updated_at = NOW()
                 WHERE id = ?"
            );
            $updateStmt->execute([$enquiryId]);

            $this->pdo->commit();
            return $reply;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
