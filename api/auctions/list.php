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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $status = $_GET['status'] ?? 'all';
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $where = [];
    $params = [];
    
    if ($status !== 'all') {
        $where[] = "a.status = ?";
        $params[] = $status;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT a.*, 
              COUNT(DISTINCT ut.user_id) as participant_count,
              COUNT(b.id) as total_bids,
              u.username as winner_username
              FROM auctions a
              LEFT JOIN user_tickets ut ON a.id = ut.auction_id
              LEFT JOIN bids b ON a.id = b.auction_id
              LEFT JOIN users u ON a.winner_id = u.id
              $where_clause
              GROUP BY a.id
              ORDER BY a.start_time DESC
              LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add computed fields
    foreach ($auctions as &$auction) {
        $now = time();
        $start = strtotime($auction['start_time']);
        $end = strtotime($auction['end_time']);
        
        $auction['is_active'] = $now >= $start && $now <= $end && $auction['status'] === 'active';
        $auction['is_upcoming'] = $now < $start || $auction['status'] === 'upcoming';
        $auction['is_ended'] = $now > $end || $auction['status'] === 'completed';
        $auction['time_remaining'] = max(0, $end - $now);
        $auction['tickets_remaining'] = $auction['total_tickets'] - $auction['tickets_sold'];
    }
    
    jsonResponse(true, [
        'auctions' => $auctions,
        'count' => count($auctions)
    ], null, 200);
    
} catch (Exception $e) {
    error_log('Auctions list API error: ' . $e->getMessage());
    jsonResponse(false, null, 'Server error', 500);
}
?>
