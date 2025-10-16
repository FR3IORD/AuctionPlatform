<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/config.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $auction_id = $_GET['auction_id'] ?? 0;
    $user_id = $_GET['user_id'] ?? 0;
    
    if (!$auction_id) {
        throw new Exception('Invalid auction ID');
    }
    
    // Get leaderboard
    $query = "SELECT u.username, u.first_name, u.last_name, COUNT(b.id) as bid_count, 
              MAX(b.bid_time) as last_bid_time, u.id as user_id
              FROM bids b 
              JOIN users u ON b.user_id = u.id 
              WHERE b.auction_id = ? 
              GROUP BY u.id 
              ORDER BY bid_count DESC, last_bid_time DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent bids
    $query = "SELECT u.username, b.bid_time, b.id as bid_id
              FROM bids b 
              JOIN users u ON b.user_id = u.id 
              WHERE b.auction_id = ? 
              ORDER BY b.bid_time DESC LIMIT 15";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $recent_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total bids count
    $query = "SELECT COUNT(*) FROM bids WHERE auction_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $total_bids = (int)$stmt->fetchColumn();
    
    // Get online users count
    $query = "SELECT COUNT(DISTINCT user_id) FROM bids WHERE auction_id = ? AND bid_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $online_users = (int)$stmt->fetchColumn();
    
    // Get user's remaining bids if user_id provided
    $user_remaining_bids = 0;
    $user_balance = 0.0;
    
    if ($user_id) {
        $query = "SELECT tickets_count, used_bids, total_bids FROM user_tickets WHERE user_id = ? AND auction_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $auction_id]);
        $user_tickets = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_tickets) {
            $user_remaining_bids = (int)($user_tickets['total_bids'] - $user_tickets['used_bids']);
        }
        
        $query = "SELECT balance FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $balance = $stmt->fetchColumn();
        $user_balance = (float)($balance ?: 0);
    }
    
    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard,
        'recent_bids' => $recent_bids,
        'total_bids' => $total_bids,
        'online_users' => $online_users,
        'user_remaining_bids' => $user_remaining_bids,
        'user_balance' => $user_balance
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>