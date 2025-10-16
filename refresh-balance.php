<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get current user's balance from database
$query = "SELECT balance FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$current_balance = $stmt->fetchColumn();

// Update session
$_SESSION['user_balance'] = $current_balance;

// Return JSON response for AJAX calls
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'balance' => $current_balance,
        'formatted' => formatPrice($current_balance)
    ]);
    exit;
}

// Redirect back to previous page
$redirect = $_GET['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? url();
header('Location: ' . $redirect);
exit;
?>