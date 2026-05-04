<?php
/**
 * Authentication Class
 * 
 * Handles user authentication, JWT tokens, and session management
 * 
 * @author Hariom Vimal
 * @version 1.2.0
 * @since 2024-04-20
 */

class Auth {
    private $db;
    private $jwtSecret;
    
    public function __construct($database) {
        $this->db = $database;
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default_secret_change_me';
    }
    
    public function login($email, $password) {
        // Basic validation
        if (empty($email) || empty($password)) {
            throw new Exception("Email and password are required");
        }
        
        // Check user exists
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        if (!$user) {
            throw new Exception("Invalid email or password");
        }
        
        // Verify password (assuming password_hash was used)
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Invalid email or password");
        }
        
        // Generate JWT token
        $token = $this->generateToken($user);
        
        // Update last login
        $this->db->update(
            'users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );
        
        return [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    }
    
    public function register($userData) {
        // Validate required fields
        $required = ['name', 'email', 'password', 'phone'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new Exception("{$field} is required");
            }
        }
        
        // Check if email already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$userData['email']]
        );
        
        if ($existing) {
            throw new Exception("Email already registered");
        }
        
        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        $userData['status'] = 'active';
        $userData['role'] = 'customer';
        $userData['created_at'] = date('Y-m-d H:i:s');
        
        try {
            $userId = $this->db->insert('users', $userData);
            
            // Get user data for response
            $user = $this->db->fetchOne(
                "SELECT id, name, email, role FROM users WHERE id = ?",
                [$userId]
            );
            
            return [
                'user' => $user,
                'message' => 'Registration successful'
            ];
        } catch (Exception $e) {
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }
    
    public function generateToken($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtSecret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    public function verifyToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        $signature = $parts[2];
        
        // Verify signature
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtSecret, true);
        $base64UrlExpectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
        
        if (!hash_equals($signature, $base64UrlExpectedSignature)) {
            return false;
        }
        
        // Check expiration
        $payloadData = json_decode($payload, true);
        if ($payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    public function getCurrentUser($token) {
        $tokenData = $this->verifyToken($token);
        
        if (!$tokenData) {
            return false;
        }
        
        $user = $this->db->fetchOne(
            "SELECT id, name, email, role, phone FROM users WHERE id = ? AND status = 'active'",
            [$tokenData['user_id']]
        );
        
        return $user;
    }
    
    public function requireAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            throw new Exception("Authorization token required");
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        $user = $this->getCurrentUser($token);
        
        if (!$user) {
            throw new Exception("Invalid or expired token");
        }
        
        return $user;
    }
    
    public function requireAdmin() {
        $user = $this->requireAuth();
        
        if ($user['role'] !== 'admin') {
            throw new Exception("Admin access required");
        }
        
        return $user;
    }
    
    public function logout($token) {
        // In a real implementation, you might want to blacklist the token
        // For now, we just return success since JWT is stateless
        return ['message' => 'Logged out successfully'];
    }
}
?>
