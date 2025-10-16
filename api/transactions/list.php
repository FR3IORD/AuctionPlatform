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
    
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure numeric fields are properly typed
    foreach ($transactions as &$transaction) {
        $transaction['id'] = (int)$transaction['id'];
        $transaction['user_id'] = (int)$transaction['user_id'];
        $transaction['amount'] = (float)$transaction['amount'];
    }
    
    jsonResponse(true, ['transactions' => $transactions], null, 200);
    
} catch (Exception $e) {
    error_log('Transactions list API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Server error', 500);
}
?>
