<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/config.php';

// Get user ID from either session or Bearer token
$user_id = null;

// Try Bearer token first (for mobile app)
$token = getAuthToken();
if ($token) {
    $user_data = verifyJWT($token);
    if ($user_data && isset($user_data['user_id'])) {
        $user_id = $user_data['user_id'];
    }
}

// Fallback to session (for web app)
if (!$user_id && isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
}

// Check if user is authenticated
if (!$user_id) {
    jsonResponse(false, null, 'Unauthorized', 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, email, first_name, last_name, balance, is_admin, status, created_at FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        jsonResponse(false, null, 'User not found', 404);
    }
    
    // Ensure proper data types
    $user['id'] = (int)$user['id'];
    $user['balance'] = (float)$user['balance'];
    $user['is_admin'] = (bool)$user['is_admin'];
    
    jsonResponse(true, ['user' => $user], null, 200);
    
} catch (Exception $e) {
    error_log('Profile API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Server error', 500);
}
?>
