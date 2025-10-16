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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = t('error_fill_fields');
    } else {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT id, username, email, password, first_name, last_name, balance, is_admin FROM users WHERE email = ? AND email_verified = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);

        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['balance'] = $user['balance'];
                $_SESSION['is_admin'] = $user['is_admin'];

                header('Location: /auction-platform/');
                exit();
            } else {
                $error = t('error_invalid');
            }
        } else {
            $error = t('error_invalid');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
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
            <h2 class="mt-6 text-3xl font-bold text-gray-900"><?= t('signin_title') ?></h2>
            <p class="mt-2 text-sm text-gray-600">
                <?= t('signin_or') ?> 
                <a href="register.php?lang=<?= $lang ?>" class="font-medium text-blue-600 hover:text-blue-800"><?= t('signin_create_new') ?></a>
            </p>
        </div>

        <form class="mt-8 space-y-6" method="POST">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700"><?= t('email_address') ?></label>
                    <input id="email" name="email" type="email" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                           placeholder="<?= t('email_placeholder') ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700"><?= t('password') ?></label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required 
                               class="mt-1 appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                               placeholder="<?= t('password_placeholder') ?>">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" 
                           class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-900"><?= t('remember_me') ?></label>
                </div>

                <div class="text-sm">
                    <a href="#" class="font-medium text-primary hover:text-secondary"><?= t('forgot_password') ?></a>
                </div>
            </div>

            <div>
                <button type="submit" 
                    class="relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all"
                    style="background: linear-gradient(to right, #3B82F6, #8B5CF6);">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-sign-in-alt text-white/70"></i>
                    </span>
                    <?= t('sign_in') ?>
                </button>
            </div>

            <!-- Demo accounts -->
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h4 class="text-sm font-medium text-blue-800 mb-2"><?= t('demo_accounts') ?></h4>
                <div class="text-xs text-blue-600 space-y-1">
                    <div><strong><?= t('admin') ?>:</strong> admin@auctionbay.com / admin123</div>
                    <div><strong><?= t('user') ?>:</strong> user@auctionbay.com / user123</div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            
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