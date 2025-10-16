<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

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
    jsonResponse(false, null, 'Please log in to place a bid', 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get auction_id from JSON body or POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $auction_id = $input['auction_id'] ?? $_POST['auction_id'] ?? 0;
    
    if (!$auction_id) {
        jsonResponse(false, null, 'Invalid auction ID', 400);
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if auction exists and is active
    $query = "SELECT * FROM auctions WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auction) {
        $db->rollBack();
        jsonResponse(false, null, 'Auction not found or not active', 404);
    }
    
    // Check user's tickets for this auction
    $query = "SELECT * FROM user_tickets WHERE user_id = ? AND auction_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $auction_id]);
    $user_tickets = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_tickets) {
        $db->rollBack();
        jsonResponse(false, null, 'You need to purchase tickets first', 400);
    }
    
    // Check if user has remaining bids
    if ($user_tickets['used_bids'] >= $user_tickets['total_bids']) {
        $db->rollBack();
        jsonResponse(false, null, 'You have no bids remaining', 400);
    }
    
    // Check time since last bid (prevent spam)
    $query = "SELECT bid_time FROM bids WHERE auction_id = ? ORDER BY bid_time DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $last_bid_time = $stmt->fetchColumn();
    
    if ($last_bid_time) {
        $time_diff = time() - strtotime($last_bid_time);
        if ($time_diff < 1) { // Minimum 1 second between bids
            $db->rollBack();
            jsonResponse(false, null, 'Please wait before placing another bid', 429);
        }
    }
    
    // Insert the bid
    $query = "INSERT INTO bids (auction_id, user_id, bid_time) VALUES (?, ?, NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id, $user_id]);
    $bid_id = $db->lastInsertId();
    
    // Update user's used bids
    $query = "UPDATE user_tickets SET used_bids = used_bids + 1 WHERE user_id = ? AND auction_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $auction_id]);
    
    // Get remaining bids
    $remaining_bids = $user_tickets['total_bids'] - ($user_tickets['used_bids'] + 1);
    
    // Commit transaction
    $db->commit();
    
    jsonResponse(true, [
        'bid_id' => (int)$bid_id,
        'remaining_bids' => (int)$remaining_bids,
        'auction_id' => (int)$auction_id
    ], 'Bid placed successfully!', 200);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('Place bid error: ' . $e->getMessage());
    jsonResponse(false, null, 'Error placing bid: ' . $e->getMessage(), 400);
} catch (PDOException $e) {
    // Rollback transaction on database error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('Database error in place-bid.php: ' . $e->getMessage());
    jsonResponse(false, null, 'Database error occurred', 500);
}
?>