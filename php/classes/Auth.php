<?php
/**
 * Authentication Class
 * Handles user login, registration, and session management
 */

require_once __DIR__ . '/../config/Database.php';

class Auth {
    private $conn;
    private $table = 'users';

    /**
     * Start session once per request with a cookie visible site-wide (path /).
     * Without this, some servers set a narrow cookie path so the next page never sends PHPSESSID.
     */
    private static function ensureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    /**
     * Register a new barangay official
     */
    public function register($username, $email, $password, $full_name, $barangay_name, $phone_number, $address) {
        // Validate inputs
        if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($barangay_name)) {
            return ['success' => false, 'message' => 'All required fields must be filled'];
        }

        if (strtolower(trim((string) $barangay_name)) === 'mdrrmo') {
            return ['success' => false, 'message' => 'MDRRMO accounts cannot be created through public registration'];
        }

        // Check if user already exists
        $check_query = "SELECT id FROM {$this->table} WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($check_query);
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Insert new user
        $insert_query = "INSERT INTO {$this->table} (username, email, password_hash, full_name, barangay_name, phone_number, address, user_type) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $user_type = 'barangay_official'; // Default registration is for barangay officials
        
        $stmt = $this->conn->prepare($insert_query);
        $stmt->bind_param('ssssssss', $username, $email, $password_hash, $full_name, $barangay_name, $phone_number, $address, $user_type);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registration successful. Please login.', 'user_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Registration failed: ' . $this->conn->error];
        }
    }

    /**
     * Login user
     */
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $query = "SELECT id, username, email, password_hash, full_name, barangay_name, user_type, is_active 
                  FROM {$this->table} WHERE username = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        $user = $result->fetch_assoc();

        // Check if account is active
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Your account has been deactivated'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Login successful - set session
        self::ensureSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['barangay_name'] = $user['barangay_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['logged_in'] = true;

        return ['success' => true, 'message' => 'Login successful', 'user_type' => $user['user_type']];
    }

    /**
     * Logout user
     */
    public function logout() {
        self::ensureSession();
        session_destroy();
        return ['success' => true, 'message' => 'Logout successful'];
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        self::ensureSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        self::ensureSession();
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }

    /**
     * Get current user data
     */
    public static function getCurrentUser() {
        self::ensureSession();
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'full_name' => $_SESSION['full_name'],
                'barangay_name' => $_SESSION['barangay_name'],
                'user_type' => $_SESSION['user_type']
            ];
        }
        return null;
    }
}
?>
