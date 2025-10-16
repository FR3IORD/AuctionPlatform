<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonResponse(false, null, 'Email and password required', 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, email, password, first_name, last_name, balance, is_admin, status, created_at FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(false, null, 'Invalid credentials', 401);
    }
    
    if ($user['status'] !== 'active') {
        jsonResponse(false, null, 'Account not active', 403);
    }
    
    // Generate JWT token
    $token = generateJWT($user['id'], $user['email']);
    
    unset($user['password']);
    
    // Ensure balance is a float
    $user['balance'] = (float)$user['balance'];
    $user['is_admin'] = (bool)$user['is_admin'];
    
    jsonResponse(true, ['token' => $token, 'user' => $user], 'Login successful', 200);
    
} catch (Exception $e) {
    error_log('Login API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Server error', 500);
}
?>
