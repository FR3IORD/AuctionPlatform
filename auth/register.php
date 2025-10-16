<?php 
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$supported_langs = ['en', 'es', 'it', 'el'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $supported_langs)) $lang = 'en';
$_SESSION['lang'] = $lang;
$translations = require __DIR__ . "/../lang/$lang.php";
function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

if (isLoggedIn()) {
    header('Location: /auction-platform/');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']); 
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = t('register_fill_required');
    } elseif (strlen($username) < 3) {
        $error = t('register_username_short');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('register_invalid_email');
    } elseif (strlen($password) < 6) {
        $error = t('register_password_short');
    } elseif ($password !== $confirm_password) {
        $error = t('register_password_mismatch');
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if username or email already exists
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = t('register_exists');
        } else {
            // Create user with welcome bonus
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $welcome_bonus = 10.00; // $10 welcome bonus
            
            $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, balance, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $phone, $welcome_bonus])) {
                $user_id = $db->lastInsertId();
                
                // Record welcome bonus transaction
                $query = "INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'deposit', ?, 'completed', 'Welcome bonus')";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id, $welcome_bonus]);
                
                $success = t('register_success');
            } else {
                $error = t('register_fail');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .flag-icon { width: 24px; height: 18px; display: inline-block; vertical-align: middle; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative">

    <!-- Language Dropdown Switcher (top-right) -->
    <?php
    $currentLang = $_SESSION['lang'] ?? 'en';
    $self = basename($_SERVER['PHP_SELF']);
    $query = $_GET;
    unset($query['lang']);
    $baseUrl = $self . (count($query) ? '?' . http_build_query($query) . '&' : '?');
    $langs = [
        'en' => [
            'label' => 'EN',
            'svg' => '<svg class="flag-icon" viewBox="0 0 60 36"><rect width="60" height="36" fill="#012169"/><path d="M0,0 L60,36 M60,0 L0,36" stroke="#FFF" stroke-width="6"/><path d="M0,0 L60,36 M60,0 L0,36" stroke="#C8102E" stroke-width="4"/><rect x="25" y="0" width="10" height="36" fill="#FFF"/><rect x="0" y="13" width="60" height="10" fill="#FFF"/><rect x="27" y="0" width="6" height="36" fill="#C8102E"/><rect x="0" y="15" width="60" height="6" fill="#C8102E"/></svg>',
        ],
        'es' => [
            'label' => 'ES',
            'svg' => '<svg class="flag-icon" viewBox="0 0 60 36"><rect width="60" height="36" fill="#AA151B"/><rect y="8" width="60" height="20" fill="#F1BF00"/></svg>',
        ],
        'it' => [
            'label' => 'IT',
            'svg' => '<svg class="flag-icon" viewBox="0 0 60 36"><rect width="20" height="36" fill="#009246"/><rect x="20" width="20" height="36" fill="#FFF"/><rect x="40" width="20" height="36" fill="#CE2B37"/></svg>',
        ],
        'el' => [
            'label' => 'GR',
            'svg' => '<svg class="flag-icon" viewBox="0 0 60 36"><rect width="60" height="36" fill="#0D5EAF"/><g fill="#fff"><rect y="13.5" width="60" height="9"/><rect x="18" width="9" height="36"/></g><g fill="#0D5EAF"><rect y="15" width="60" height="6"/><rect x="20" width="6" height="36"/></g><rect width="21" height="21" fill="#0D5EAF"/><g fill="#fff"><rect x="8" width="5" height="21"/><rect y="8" width="21" height="5"/></g></svg>',
        ]
    ];
    ?>
    <div class="absolute top-4 right-4 z-50">
        <div class="relative inline-block text-left">
            <button type="button" onclick="toggleDropdown()" class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-3 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none" id="lang-menu-button" aria-expanded="true" aria-haspopup="true">
                <?= $langs[$currentLang]['svg'] ?>
                <span class="ml-2"><?= $langs[$currentLang]['label'] ?></span>
                <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.584l3.71-3.354a.75.75 0 111.02 1.096l-4.25 3.846a.75.75 0 01-1.02 0l-4.25-3.846a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
            </button>
            <div id="lang-dropdown" class="origin-top-right absolute right-0 mt-2 w-36 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden">
                <div class="py-1">
                    <?php foreach ($langs as $code => $data): ?>
                        <a href="<?= $baseUrl ?>lang=<?= $code ?>"
                           class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $currentLang === $code ? 'font-semibold text-primary' : '' ?>">
                            <?= $data['svg'] ?>
                            <span class="ml-2"><?= $data['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
    function toggleDropdown() {
        var menu = document.getElementById('lang-dropdown');
        menu.classList.toggle('hidden');
        document.addEventListener('click', function handler(e) {
            if (!menu.contains(e.target) && !document.getElementById('lang-menu-button').contains(e.target)) {
                menu.classList.add('hidden');
                document.removeEventListener('click', handler);
            }
        });
    }
    </script>
    <!-- /Language Dropdown Switcher -->

    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <a href="/auction-platform/" class="text-3xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                <?= SITE_NAME ?>
            </a>
            <h2 class="mt-6 text-3xl font-bold text-gray-900"><?= t('register_title') ?></h2>
            <p class="mt-2 text-sm text-gray-600">
                <?= t('register_or') ?> 
                <a href="login.php?lang=<?= $lang ?>" class="font-medium text-blue-600 hover:text-blue-800"><?= t('register_signin') ?></a>
            </p>
        </div>
        
        <form class="mt-8 space-y-6" method="POST">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-2">
                        <a href="login.php?lang=<?= $lang ?>" class="font-medium text-green-800 hover:text-green-900 underline"><?= t('register_login_link') ?></a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700"><?= t('register_first_name') ?> *</label>
                        <input id="first_name" name="first_name" type="text" required 
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                               placeholder="<?= t('register_first_name') ?>" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700"><?= t('register_last_name') ?> *</label>
                        <input id="last_name" name="last_name" type="text" required 
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                               placeholder="<?= t('register_last_name') ?>" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700"><?= t('register_username') ?> *</label>
                    <input id="username" name="username" type="text" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                           placeholder="<?= t('register_username_placeholder') ?>" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <p class="mt-1 text-xs text-gray-500"><?= t('register_username_hint') ?></p>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700"><?= t('register_email') ?> *</label>
                    <input id="email" name="email" type="email" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                           placeholder="<?= t('register_email_placeholder') ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700"><?= t('register_phone') ?></label>
                    <input id="phone" name="phone" type="tel" 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                           placeholder="<?= t('register_phone_placeholder') ?>" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700"><?= t('register_password') ?> *</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required 
                               class="mt-1 appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                               placeholder="<?= t('register_password_placeholder') ?>">
                        <button type="button" onclick="togglePassword('password', 'toggle-icon1')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400" id="toggle-icon1"></i>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500"><?= t('register_password_hint') ?></p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700"><?= t('register_password_confirm') ?> *</label>
                    <div class="relative">
                        <input id="confirm_password" name="confirm_password" type="password" required 
                               class="mt-1 appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                               placeholder="<?= t('register_password_confirm_placeholder') ?>">
                        <button type="button" onclick="togglePassword('confirm_password', 'toggle-icon2')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400" id="toggle-icon2"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <input id="terms" name="terms" type="checkbox" required
                           class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                </div>
                <div class="ml-3 text-sm">
                    <label for="terms" class="font-medium text-gray-700">
                        <?= t('register_terms') ?>
                    </label>
                </div>
            </div>
            
            <div>
                <button type="submit"
                    class="relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all"
                    style="background: linear-gradient(to right, #3B82F6, #8B5CF6);">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-user-plus text-white/70"></i>
                    </span>
                    <?= t('register_btn') ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash text-gray-400';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye text-gray-400';
            }
        }
    </script>
</body>
</html>