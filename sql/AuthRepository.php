<?php
/**
 * AuthRepository - Authentication Database Operations
 * Handles user login, registration, OTP management
 */

class AuthRepository {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find user by email OR phone.
     * Method name kept for compatibility with existing callers.
     */
    public function findUserByEmailOrUsername($identifier) {
        $identifier = trim((string) $identifier);

        $query = "SELECT id, email, phone, password_hash, full_name, role, is_active, created_at, last_login_at
                  FROM users
                        WHERE email = :identifier
                            OR LOWER(email) = LOWER(:identifier)
                     OR regexp_replace(COALESCE(phone, ''), '[^0-9+]', '', 'g') = regexp_replace(:identifier, '[^0-9+]', '', 'g')
                        ORDER BY
                             (email = :identifier) DESC,
                             (regexp_replace(COALESCE(phone, ''), '[^0-9+]', '', 'g') = regexp_replace(:identifier, '[^0-9+]', '', 'g')) DESC,
                             created_at DESC
                  LIMIT 1";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':identifier' => $identifier]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find user by ID
     */
    public function findUserById($userId) {
        $query = "SELECT id, email, phone, full_name, role, is_active, created_at, last_login_at
                  FROM users
                  WHERE id = :id
                  LIMIT 1";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':id' => $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if email already exists
     */
    public function emailExists($email) {
        $email = trim((string) $email);
        $query = "SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':email' => $email]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if phone already exists (normalized)
     */
    public function phoneExists($phone) {
        $phone = trim((string) $phone);
        $query = "SELECT id
                  FROM users
                  WHERE regexp_replace(COALESCE(phone, ''), '[^0-9+]', '', 'g') = regexp_replace(:phone, '[^0-9+]', '', 'g')
                  LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':phone' => $phone]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if username already exists.
     * The app uses full_name as the displayed username in register flow.
     */
    public function usernameExists($username) {
        $username = trim((string) $username);
        if ($username === '') {
            return false;
        }

        $query = "SELECT 1
                  FROM users
                  WHERE LOWER(BTRIM(COALESCE(full_name, ''))) = LOWER(BTRIM(:username))
                  LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':username' => $username]);

        return (bool)$stmt->fetch();
    }

    /**
     * Create new user account
     * Keeps legacy signature for compatibility.
     */
    public function createUser($username, $email, $phone, $password, $fullName, $dateOfBirth = null, $role = 'user') {
        $username = trim((string) $username);
        $email = strtolower(trim((string) $email));
        $phone = trim((string) $phone);
        $fullName = trim((string) $fullName);
        $dateOfBirth = $dateOfBirth ? trim((string) $dateOfBirth) : null;
        $role = trim((string) $role) ?: 'user';

        // Safety check in repository layer to prevent duplicate usernames.
        if ($this->usernameExists($username !== '' ? $username : $fullName)) {
            throw new Exception('Username already taken');
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $query = "INSERT INTO users (email, phone, password_hash, full_name, date_of_birth, auth_provider, role, is_active, created_at)
                  VALUES (:email, :phone, :password_hash, :full_name, :date_of_birth, 'email', :role, TRUE, NOW())
                  RETURNING id";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':email' => $email,
            ':phone' => $phone,
            ':password_hash' => $hashedPassword,
            ':full_name' => $fullName,
            ':date_of_birth' => $dateOfBirth,
            ':role' => $role
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':id' => $userId]);
    }

    /**
     * Store OTP in session (can be extended to database if needed)
     * Currently uses PHP sessions
     */
    public function storeOtp($email, $otp, $expiryMinutes = 5) {
        $expiry = date('Y-m-d H:i:s', strtotime("+$expiryMinutes minutes"));
        
        $_SESSION['otp_' . $email] = [
            'code' => $otp,
            'expiry' => $expiry,
            'attempts' => 0
        ];

        return true;
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp($email, $otp) {
        if (!isset($_SESSION['otp_' . $email])) {
            return ['valid' => false, 'message' => 'OTP not found. Please request a new one'];
        }

        $otpData = $_SESSION['otp_' . $email];

        // Check expiry
        if (strtotime($otpData['expiry']) < time()) {
            unset($_SESSION['otp_' . $email]);
            return ['valid' => false, 'message' => 'OTP has expired. Please request a new one'];
        }

        // Check attempts
        if ($otpData['attempts'] >= 5) {
            unset($_SESSION['otp_' . $email]);
            return ['valid' => false, 'message' => 'Too many attempts. Please request a new OTP'];
        }

        // Verify OTP
        if ($otpData['code'] !== $otp) {
            $_SESSION['otp_' . $email]['attempts']++;
            return ['valid' => false, 'message' => 'Invalid OTP. Please try again'];
        }

        // OTP verified - clean up
        unset($_SESSION['otp_' . $email]);
        return ['valid' => true, 'message' => 'OTP verified successfully'];
    }

    /**
     * Delete OTP
     */
    public function deleteOtp($email) {
        if (isset($_SESSION['otp_' . $email])) {
            unset($_SESSION['otp_' . $email]);
        }
    }

    /**
     * Validate email format
     */
    public function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password
     */
    public function isValidPassword($password) {
        return strlen($password) >= 6;
    }

    /**
     * Verify password against hash
     */
    public function verifyPassword($plainPassword, $hashedPassword) {
        return password_verify($plainPassword, $hashedPassword);
    }

    /**
     * Calculate age from DOB
     */
    public function calculateAge($dobString) {
        $dob = new DateTime($dobString);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        
        // Adjust for month/day
        $monthDiff = $today->format('m') - $dob->format('m');
        if ($monthDiff < 0 || ($monthDiff === 0 && $today->format('d') < $dob->format('d'))) {
            $age--;
        }

        return $age;
    }

    /**
     * Check if user is 18+
     */
    public function isAdult($dobString) {
        return $this->calculateAge($dobString) >= 18;
    }

    /**
     * Update user role by email
     */
    public function updateUserRoleByEmail($email, $role) {
        $email = strtolower(trim((string) $email));
        $role = trim((string) $role) ?: 'user';

        $query = "UPDATE users SET role = :role WHERE LOWER(email) = LOWER(:email)";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([':role' => $role, ':email' => $email]);
    }

    /**
     * Update user role by ID
     */
    public function updateUserRoleById($userId, $role) {
        $role = trim((string) $role) ?: 'user';

        $query = "UPDATE users SET role = :role WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([':role' => $role, ':id' => $userId]);
    }

    /**
     * Find user by email only.
     */
    public function findUserByEmail($email) {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $query = "SELECT id, email, full_name, password_hash, is_active
                  FROM users
                  WHERE LOWER(email) = LOWER(:email)
                  LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':email' => $email]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ensure reset token table exists for password reset workflow.
     */
    public function ensurePasswordResetTableExists() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash VARCHAR(255) NOT NULL UNIQUE,
            expires_at TIMESTAMPTZ NOT NULL,
            is_used BOOLEAN NOT NULL DEFAULT FALSE,
            used_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reset_tokens_user ON password_reset_tokens(user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reset_tokens_expires ON password_reset_tokens(expires_at)");
    }

    /**
     * Create a 5-minute reset token and return plaintext token for email link.
     */
    public function createPasswordResetToken($userId, $expiryMinutes = 5) {
        $this->ensurePasswordResetTableExists();

        // Keep only one active token per user to reduce replay risk.
        $this->revokeActivePasswordResetTokens($userId);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $query = "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                  VALUES (:user_id, :token_hash, NOW() + (:expiry_minutes || ' minutes')::INTERVAL)";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expiry_minutes' => (int)$expiryMinutes,
        ]);

        return $rawToken;
    }

    /**
     * Validate token and return token row with user data if valid.
     */
    public function findValidPasswordResetToken($rawToken) {
        $this->ensurePasswordResetTableExists();

        $rawToken = trim((string)$rawToken);
        if ($rawToken === '') {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);

        $query = "SELECT prt.id, prt.user_id, prt.expires_at, prt.is_used,
                         u.email, u.full_name, u.password_hash
                  FROM password_reset_tokens prt
                  JOIN users u ON u.id = prt.user_id
                  WHERE prt.token_hash = :token_hash
                    AND prt.is_used = FALSE
                    AND prt.expires_at >= NOW()
                    AND u.is_active = TRUE
                  LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':token_hash' => $tokenHash]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark a reset token as used.
     */
    public function consumePasswordResetToken($rawToken) {
        $this->ensurePasswordResetTableExists();

        $tokenHash = hash('sha256', trim((string)$rawToken));
        $query = "UPDATE password_reset_tokens
                  SET is_used = TRUE,
                      used_at = NOW()
                  WHERE token_hash = :token_hash
                    AND is_used = FALSE";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':token_hash' => $tokenHash]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke all active tokens for a user (used after successful reset).
     */
    public function revokeActivePasswordResetTokens($userId) {
        $this->ensurePasswordResetTableExists();

        $query = "UPDATE password_reset_tokens
                  SET is_used = TRUE,
                      used_at = NOW()
                  WHERE user_id = :user_id
                    AND is_used = FALSE";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':user_id' => $userId]);

        return true;
    }

    /**
     * Update user's password hash.
     */
    public function updatePasswordHash($userId, $plainPassword) {
        $hashedPassword = password_hash((string)$plainPassword, PASSWORD_BCRYPT);
        $query = "UPDATE users
                  SET password_hash = :password_hash,
                      auth_provider = 'email',
                      updated_at = NOW()
                  WHERE id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':password_hash' => $hashedPassword,
            ':id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
?>
