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
     * Find user by email OR username
     */
    public function findUserByEmailOrUsername($identifier) {
        $query = "SELECT id, username, email, password, full_name, phone, role, status, created_at, last_login 
                  FROM users 
                  WHERE email = :identifier OR username = :identifier 
                  LIMIT 1";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':identifier' => $identifier]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find user by ID
     */
    public function findUserById($userId) {
        $query = "SELECT id, username, email, full_name, phone, role, status, created_at, last_login 
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
        $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':email' => $email]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if username already exists
     */
    public function usernameExists($username) {
        $query = "SELECT id FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':username' => $username]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Create new user account
     */
    public function createUser($username, $email, $phone, $password, $fullName) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO users (username, email, phone, password, full_name, role, status, created_at) 
                  VALUES (:username, :email, :phone, :password, :full_name, 'user', 'active', NOW())";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':phone' => $phone,
            ':password' => $hashedPassword,
            ':full_name' => $fullName
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :id";
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
}
?>
