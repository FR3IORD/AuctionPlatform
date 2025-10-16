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
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');

if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
    jsonResponse(false, null, 'All fields required', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, null, 'Invalid email', 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE email = ? OR username = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email, $username]);
    
    if ($stmt->fetch()) {
        jsonResponse(false, null, 'User already exists', 409);
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (username, email, password, first_name, last_name, balance, status, email_verified) 
              VALUES (?, ?, ?, ?, ?, 0, 'active', TRUE)";
    $stmt = $db->prepare($query);
    $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name]);
    
    $user_id = $db->lastInsertId();
    
    // Generate JWT token
    $token = generateJWT($user_id, $email);
    
    $user = [
        'id' => (int)$user_id,
        'username' => $username,
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'balance' => 0.0,
        'is_admin' => false,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    jsonResponse(true, ['token' => $token, 'user' => $user], 'Registration successful', 201);
    
} catch (Exception $e) {
    error_log('Register API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Server error', 500);
}
?>
