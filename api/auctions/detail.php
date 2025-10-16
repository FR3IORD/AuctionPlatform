<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/config.php';

$auction_id = $_GET['id'] ?? 0;

if (!$auction_id) {
    jsonResponse(false, null, 'Auction ID required', 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT a.*, 
              COUNT(DISTINCT ut.user_id) as participant_count,
              COUNT(b.id) as total_bids,
              u.username as winner_username
              FROM auctions a
              LEFT JOIN user_tickets ut ON a.id = ut.auction_id
              LEFT JOIN bids b ON a.id = b.auction_id
              LEFT JOIN users u ON a.winner_id = u.id
              WHERE a.id = ?
              GROUP BY a.id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auction) {
        jsonResponse(false, null, 'Auction not found', 404);
    }
    
    // Get recent bids
    $query = "SELECT b.bid_time, b.id, u.username, u.first_name, u.last_name
              FROM bids b
              JOIN users u ON b.user_id = u.id
              WHERE b.auction_id = ?
              ORDER BY b.bid_time DESC
              LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $recent_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $now = time();
    $start = strtotime($auction['start_time']);
    $end = strtotime($auction['end_time']);
    
    $auction['is_active'] = $now >= $start && $now <= $end && $auction['status'] === 'active';
    $auction['is_upcoming'] = $now < $start || $auction['status'] === 'upcoming';
    $auction['is_ended'] = $now > $end || $auction['status'] === 'completed';
    $auction['time_remaining'] = max(0, $end - $now);
    $auction['tickets_remaining'] = $auction['total_tickets'] - $auction['tickets_sold'];
    $auction['recent_bids'] = $recent_bids;
    
    jsonResponse(true, ['auction' => $auction], null, 200);
    
} catch (Exception $e) {
    error_log('Auction detail API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Server error', 500);
}
?>
