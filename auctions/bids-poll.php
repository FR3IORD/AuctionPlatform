<?php
require_once '../config/config.php';
$database = new Database();
$db = $database->getConnection();

$auction_id = $_GET['auction_id'] ?? 0;
header('Content-Type: application/json');

// Last bid
$query = "SELECT b.id, b.user_id, b.bid_time, u.username FROM bids b 
          JOIN users u ON b.user_id = u.id 
          WHERE b.auction_id = ? 
          ORDER BY b.id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$last_bid = $stmt->fetch(PDO::FETCH_ASSOC);

// Auction status
$query = "SELECT winner_id, status FROM auctions WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

$winner = null;
$winner_id = null;
if ($auction['status'] === 'completed' && $auction['winner_id']) {
    $query = "SELECT username FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction['winner_id']]);
    $winner = $stmt->fetchColumn();
    $winner_id = $auction['winner_id'];
}

// If not completed but last_bid exists, check if 10s passed, then declare winner
if ($auction['status'] !== 'completed' && $last_bid) {
    $now = time();
    $last_bid_time = strtotime($last_bid['bid_time']);
    if(($now - $last_bid_time) >= 10) {
        $query = "UPDATE auctions SET winner_id = ?, status = 'completed' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$last_bid['user_id'], $auction_id]);
        $winner = $last_bid['username'];
        $winner_id = $last_bid['user_id'];
    }
}

echo json_encode([
    'last_bid_time' => $last_bid ? strtotime($last_bid['bid_time']) : null,
    'last_bid_username' => $last_bid['username'] ?? null,
    'last_bid_id' => $last_bid['id'] ?? null,
    'winner' => $winner,
    'winner_id' => $winner_id,
    'server_time' => time()
]);