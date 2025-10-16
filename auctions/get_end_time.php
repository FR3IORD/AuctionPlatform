<?php
require_once '../config/config.php';
$auction_id = $_GET['id'] ?? 0;
$database = new Database();
$db = $database->getConnection();
$query = "SELECT end_time FROM auctions WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$end_time = $stmt->fetchColumn();
header('Content-Type: application/json');
echo json_encode(['end_time' => $end_time]);