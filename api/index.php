<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Parse API route - remove base path and api prefix
$base_path = '/auction-platform/api';
$path = parse_url($request_uri, PHP_URL_PATH);

if (strpos($path, $base_path) === 0) {
    $path = substr($path, strlen($base_path));
}

$path = trim($path, '/');

// If path is empty, return API info
if (empty($path)) {
    echo json_encode([
        'success' => true,
        'message' => 'AuctionBay API v1',
        'timestamp' => time(),
        'endpoints' => [
            'GET /api/auctions/list' => 'Get all auctions',
            'GET /api/auctions/detail?id=:id' => 'Get auction details',
            'POST /api/auth/login' => 'User login',
            'POST /api/auth/register' => 'User registration',
            'GET /api/user/profile' => 'Get user profile (requires auth)',
            'GET /api/transactions/list' => 'Get user transactions (requires auth)',
            'POST /api/bids/place' => 'Place a bid (requires auth)'
        ]
    ]);
    exit();
}

$segments = explode('/', $path);

// Route handling
$endpoint = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? null;

switch ($endpoint) {
    case 'auth':
        handleAuthApi($action, $request_method);
        break;
    
    case 'auctions':
        handleAuctionsApi($action, $request_method, $id);
        break;
    
    case 'user':
        handleUserApi($action, $request_method);
        break;
    
    case 'transactions':
        handleTransactionsApi($action, $request_method);
        break;
    
    case 'bids':
        handleBidsApi($action, $request_method);
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found: ' . htmlspecialchars($endpoint),
            'timestamp' => time()
        ]);
        break;
}

function handleAuthApi($action, $method) {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($action === 'login' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            http_response_code(400);
            jsonResponse(false, null, 'Email and password required');
        }
        
        $query = "SELECT id, username, email, password, first_name, last_name, balance, is_admin, status FROM users WHERE email = ? AND email_verified = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            jsonResponse(false, null, 'Invalid credentials');
        }
        
        if ($user['status'] !== 'active') {
            http_response_code(403);
            jsonResponse(false, null, 'Account not active');
        }
        
        $token = generateJWT($user['id'], $user['email']);
        unset($user['password']);
        
        jsonResponse(true, ['user' => $user, 'token' => $token], 'Login successful');
    }
    
    elseif ($action === 'register' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $first_name = trim($input['first_name'] ?? '');
        $last_name = trim($input['last_name'] ?? '');
        
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            http_response_code(400);
            jsonResponse(false, null, 'All fields required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            jsonResponse(false, null, 'Invalid email');
        }
        
        $query = "SELECT id FROM users WHERE email = ? OR username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email, $username]);
        
        if ($stmt->fetch()) {
            http_response_code(409);
            jsonResponse(false, null, 'User already exists');
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, email, password, first_name, last_name, balance, status, email_verified) VALUES (?, ?, ?, ?, ?, 0, 'active', TRUE)";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name]);
        
        $user_id = $db->lastInsertId();
        $token = generateJWT($user_id, $email);
        
        $user = [
            'id' => $user_id,
            'username' => $username,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'balance' => 0
        ];
        
        jsonResponse(true, ['user' => $user, 'token' => $token], 'Registration successful');
    }
    
    else {
        http_response_code(404);
        jsonResponse(false, null, 'Auth endpoint not found');
    }
}

function handleAuctionsApi($action, $method, $id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($action === 'list' && $method === 'GET') {
        $status = $_GET['status'] ?? 'all';
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $where = $status !== 'all' ? "WHERE status = ?" : "";
        $params = $status !== 'all' ? [$status] : [];
        
        $query = "SELECT a.*, COUNT(DISTINCT ut.user_id) as participant_count, u.username as winner_username 
                  FROM auctions a 
                  LEFT JOIN user_tickets ut ON a.id = ut.auction_id 
                  LEFT JOIN users u ON a.winner_id = u.id 
                  $where 
                  GROUP BY a.id 
                  ORDER BY a.start_time DESC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $auctions = $stmt->fetchAll();
        
        jsonResponse(true, ['auctions' => $auctions, 'count' => count($auctions)]);
    }
    
    elseif ($action === 'detail' && $method === 'GET') {
        $auction_id = $_GET['id'] ?? 0;
        
        if (!$auction_id) {
            http_response_code(400);
            jsonResponse(false, null, 'Auction ID required');
        }
        
        $query = "SELECT a.*, u.username as winner_username FROM auctions a 
                  LEFT JOIN users u ON a.winner_id = u.id 
                  WHERE a.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$auction_id]);
        $auction = $stmt->fetch();
        
        if (!$auction) {
            http_response_code(404);
            jsonResponse(false, null, 'Auction not found');
        }
        
        jsonResponse(true, ['auction' => $auction]);
    }
    
    else {
        http_response_code(404);
        jsonResponse(false, null, 'Auction endpoint not found');
    }
}

function handleUserApi($action, $method) {
    $token = getAuthToken();
    
    if (!$token) {
        http_response_code(401);
        jsonResponse(false, null, 'Unauthorized');
    }
    
    $user_data = verifyJWT($token);
    
    if (!$user_data) {
        http_response_code(401);
        jsonResponse(false, null, 'Invalid token');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $user_data['user_id'];
    
    if ($action === 'profile' && $method === 'GET') {
        $query = "SELECT id, username, email, first_name, last_name, balance, status FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            jsonResponse(false, null, 'User not found');
        }
        
        jsonResponse(true, ['user' => $user]);
    }
    
    elseif ($action === 'balance' && $method === 'GET') {
        $query = "SELECT balance FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $balance = $stmt->fetchColumn();
        
        jsonResponse(true, ['balance' => $balance]);
    }
    
    else {
        http_response_code(404);
        jsonResponse(false, null, 'User endpoint not found');
    }
}

function handleTransactionsApi($action, $method) {
    $token = getAuthToken();
    
    if (!$token) {
        http_response_code(401);
        jsonResponse(false, null, 'Unauthorized');
    }
    
    $user_data = verifyJWT($token);
    
    if (!$user_data) {
        http_response_code(401);
        jsonResponse(false, null, 'Invalid token');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $user_data['user_id'];
    
    if ($action === 'list' && $method === 'GET') {
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $query = "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $transactions = $stmt->fetchAll();
        
        jsonResponse(true, ['transactions' => $transactions]);
    }
    
    else {
        http_response_code(404);
        jsonResponse(false, null, 'Transaction endpoint not found');
    }
}

function handleBidsApi($action, $method) {
    $token = getAuthToken();
    
    if (!$token) {
        http_response_code(401);
        jsonResponse(false, null, 'Unauthorized');
    }
    
    $user_data = verifyJWT($token);
    
    if (!$user_data) {
        http_response_code(401);
        jsonResponse(false, null, 'Invalid token');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $user_id = $user_data['user_id'];
    
    if ($action === 'place' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $auction_id = $input['auction_id'] ?? 0;
        
        if (!$auction_id) {
            http_response_code(400);
            jsonResponse(false, null, 'Auction ID required');
        }
        
        try {
            $db->beginTransaction();
            
            $query = "SELECT * FROM auctions WHERE id = ? AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute([$auction_id]);
            $auction = $stmt->fetch();
            
            if (!$auction) {
                throw new Exception('Auction not found or not active');
            }
            
            $query = "SELECT * FROM user_tickets WHERE user_id = ? AND auction_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $auction_id]);
            $tickets = $stmt->fetch();
            
            if (!$tickets || $tickets['used_bids'] >= $tickets['total_bids']) {
                throw new Exception('No bids available');
            }
            
            $query = "INSERT INTO bids (auction_id, user_id, bid_time) VALUES (?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([$auction_id, $user_id]);
            
            $query = "UPDATE user_tickets SET used_bids = used_bids + 1 WHERE user_id = ? AND auction_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $auction_id]);
            
            $db->commit();
            
            jsonResponse(true, ['bid_placed' => true, 'message' => 'Bid placed successfully']);
            
        } catch (Exception $e) {
            $db->rollback();
            http_response_code(400);
            jsonResponse(false, null, $e->getMessage());
        }
    }
    
    else {
        http_response_code(404);
        jsonResponse(false, null, 'Bid endpoint not found');
    }
}

// Helper function to get auth token from headers
function getAuthToken() {
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }
    }
    
    return null;
}

// Note: jsonResponse(), generateJWT(), and verifyJWT() functions 
// are already defined in config.php, so we don't define them here