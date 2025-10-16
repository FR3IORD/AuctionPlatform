<?php
// Error reporting - განტვირთე development-ისთვის
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Dynamic timezone detection and setting
function detectAndSetTimezone() {
    // Try to detect user's timezone from various sources
    $timezone = 'UTC'; // Default fallback
    
    // Check if timezone is stored in session
    if (isset($_SESSION['user_timezone'])) {
        $timezone = $_SESSION['user_timezone'];
    }
    // Check if timezone is sent via JavaScript (we'll implement this)
    elseif (isset($_POST['user_timezone'])) {
        $timezone = $_POST['user_timezone'];
        $_SESSION['user_timezone'] = $timezone;
    }
    // Check common Georgian timezones for Georgian users
    elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && 
            (strpos($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'ka') !== false || 
             strpos($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'ge') !== false)) {
        $timezone = 'Asia/Tbilisi'; // Georgian timezone UTC+4
    }
    // Default to UTC if nothing detected
    
    try {
        date_default_timezone_set($timezone);
        return $timezone;
    } catch (Exception $e) {
        // If invalid timezone, fallback to UTC
        date_default_timezone_set('UTC');
        return 'UTC';
    }
}

// Set timezone
$current_timezone = detectAndSetTimezone();
if (!defined('CURRENT_TIMEZONE')) {
    define('CURRENT_TIMEZONE', $current_timezone);
}

// Dynamic Base URL Detection
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the current script path
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove common paths to get project root
    $projectPath = '';
    if (strpos($scriptPath, '/auction-platform') !== false) {
        $projectPath = '/auction-platform';
    } elseif (strpos($scriptPath, '/config') !== false) {
        $projectPath = str_replace('/config', '', $scriptPath);
    } elseif (strpos($scriptPath, '/auth') !== false) {
        $projectPath = str_replace('/auth', '', $scriptPath);
    } elseif (strpos($scriptPath, '/admin') !== false) {
        $projectPath = str_replace('/admin', '', $scriptPath);
    } elseif (strpos($scriptPath, '/account') !== false) {
        $projectPath = str_replace('/account', '', $scriptPath);
    } elseif (strpos($scriptPath, '/auctions') !== false) {
        $projectPath = str_replace('/auctions', '', $scriptPath);
    } elseif (strpos($scriptPath, '/includes') !== false) {
        $projectPath = str_replace('/includes', '', $scriptPath);
    }
    
    // If we're in root and have auction-platform folder
    if (empty($projectPath) && is_dir($_SERVER['DOCUMENT_ROOT'] . '/auction-platform')) {
        $projectPath = '/auction-platform';
    }
    
    return $protocol . $host . $projectPath;
}

// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', '192.168.10.60');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'root');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'auction_platform');
}

// Site Configuration - Use defined() to prevent redeclaration
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'AuctionBay');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://192.168.10.60/auction-platform');
}
if (!defined('API_URL')) {
    define('API_URL', 'http://192.168.10.60/auction-platform/api');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/auction-platform');
}

// JWT Configuration
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your-secret-key-change-this-in-production-12345');
}
if (!defined('JWT_EXPIRY')) {
    define('JWT_EXPIRY', 7 * 24 * 60 * 60); // 7 days
}

// CORS Configuration
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', [
        'http://192.168.10.60',
        'http://192.168.10.60:3000',
        'http://192.168.10.60:8080'
    ]);
}

// Enable/Disable Features
if (!defined('ENABLE_API')) {
    define('ENABLE_API', true);
}
if (!defined('ENABLE_WEBSOCKET')) {
    define('ENABLE_WEBSOCKET', false);
}
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true);
}

// Load Database class from separate file
require_once __DIR__ . '/database.php';

// Helper Functions
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireLogin();
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            header('Location: ' . SITE_URL);
            exit();
        }
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        return SITE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset($path = '') {
        return SITE_URL . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('api')) {
    function api($endpoint = '') {
        return API_URL . '/' . ltrim($endpoint, '/');
    }
}

if (!function_exists('getCurrentDateTime')) {
    function getCurrentDateTime($format = 'Y-m-d H:i:s') {
        return date($format);
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($num) {
        return '₾' . number_format($num, 2);
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return round($diff / 60) . 'm ago';
        if ($diff < 86400) return round($diff / 3600) . 'h ago';
        if ($diff < 604800) return round($diff / 86400) . 'd ago';
        if ($diff < 2592000) return round($diff / 604800) . 'w ago';
        
        return date('M j, Y', $time);
    }
}

if (!function_exists('updateUserBalance')) {
    function updateUserBalance($user_id, $amount) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "UPDATE users SET balance = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$amount, $user_id]);
        
        $_SESSION['user_balance'] = $amount;
        return true;
    }
}

if (!function_exists('enableCORS')) {
    function enableCORS() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}

// JWT Helper Functions
if (!function_exists('generateJWT')) {
    function generateJWT($user_id, $email) {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'user_id' => $user_id,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY
        ]);
        
        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        
        $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", JWT_SECRET, true);
        $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        
        return "$header_encoded.$payload_encoded.$signature_encoded";
    }
}

if (!function_exists('verifyJWT')) {
    function verifyJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        $header_encoded = $parts[0];
        $payload_encoded = $parts[1];
        $signature_encoded = $parts[2];
        
        $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", JWT_SECRET, true);
        $signature_verified = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=') === $signature_encoded;
        
        if (!$signature_verified) {
            return false;
        }
        
        $payload = json_decode(base64_decode(strtr($payload_encoded, '-_', '+/')), true);
        
        if ($payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
}

if (!function_exists('getAuthToken')) {
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
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($success, $data = null, $message = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => (bool)$success,
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

// Debug function (remove in production)
if (!function_exists('debugUrl')) {
    function debugUrl() {
        if (isset($_GET['debug_url'])) {
            echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
            echo "<strong>Debug Info:</strong><br>";
            echo "SITE_URL: " . SITE_URL . "<br>";
            echo "BASE_PATH: " . BASE_PATH . "<br>";
            echo "Current Timezone: " . CURRENT_TIMEZONE . "<br>";
            echo "Current DateTime: " . getCurrentDateTime() . "<br>";
            echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
            echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
            echo "</div>";
        }
    }
}

// Start Session (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>