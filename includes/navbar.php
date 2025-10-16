<?php
// --- Language & Translation Loader ---
if (session_status() === PHP_SESSION_NONE) session_start();
$supported_langs = ['en', 'es', 'it', 'el'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $supported_langs)) $lang = 'en';
$_SESSION['lang'] = $lang;
if (!function_exists('t')) {
    $translations = require dirname(__DIR__) . "/lang/$lang.php";
    function t($key) {
        global $translations;
        return $translations[$key] ?? $key;
    }
}

// --- Refresh user balance if logged in ---
if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT balance FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['user_balance'] = $stmt->fetchColumn();
}

// --- Language Dropdown Helper ---
$self = $_SERVER['PHP_SELF'];
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
$currentLang = $_SESSION['lang'] ?? 'en';
?>
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo & Brand -->
            <div class="flex items-center">
                <a href="http://192.168.10.60/auction-platform/" class="flex items-center text-2xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent hover:opacity-80 transition-opacity">
                    <i class="fas fa-gavel mr-2"></i>
                    <?= SITE_NAME ?>
                </a>
            </div>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="http://192.168.10.60/auction-platform/" class="text-gray-700 hover:text-primary transition-colors <?= $current_page === 'index' ? 'font-bold text-primary' : '' ?>">
                    <i class="fas fa-home mr-1"></i>Home
                </a>
                <a href="http://192.168.10.60/auction-platform/auctions/" class="text-gray-700 hover:text-primary transition-colors <?= $current_page === 'index' && strpos($_SERVER['REQUEST_URI'], '/auctions') ? 'font-bold text-primary' : '' ?>">
                    <i class="fas fa-gavel mr-1"></i>Auctions
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <div class="flex items-center space-x-6">
                        <!-- Balance Display -->
                        <div class="flex items-center space-x-2 px-4 py-2 bg-gradient-to-r from-success/10 to-green-50 rounded-lg border border-success/20">
                            <i class="fas fa-wallet text-success"></i>
                            <span class="font-semibold text-gray-900"><?= formatPrice($_SESSION['user_balance'] ?? 0) ?></span>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="relative group">
                            <button class="flex items-center space-x-2 text-gray-700 hover:text-primary transition-colors">
                                <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center text-white font-bold">
                                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div class="absolute right-0 mt-0 w-48 bg-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 border border-gray-100">
                                <a href="http://192.168.10.60/auction-platform/account/" class="block px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 first:rounded-t-lg transition-colors">
                                    <i class="fas fa-user-circle mr-2"></i>My Account
                                </a>
                                <a href="http://192.168.10.60/auction-platform/account/tickets.php" class="block px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-ticket-alt mr-2"></i>My Tickets
                                </a>
                                <a href="http://192.168.10.60/auction-platform/account/transactions.php" class="block px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-history mr-2"></i>Transactions
                                </a>
                                <a href="http://192.168.10.60/auction-platform/account/add-funds.php" class="block px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-credit-card mr-2"></i>Add Funds
                                </a>
                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="http://192.168.10.60/auction-platform/admin/" class="block px-4 py-3 text-warning hover:bg-warning/5 hover:text-warning font-medium first:rounded-t-lg transition-colors">
                                        <i class="fas fa-cog mr-2"></i>Admin Panel
                                    </a>
                                <?php endif; ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="http://192.168.10.60/auction-platform/auth/logout.php" class="block px-4 py-3 text-danger hover:text-danger hover:bg-red-50 last:rounded-b-lg transition-colors">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="http://192.168.10.60/auction-platform/auth/login.php" class="text-gray-700 hover:text-primary transition-colors">
                        <i class="fas fa-sign-in-alt mr-1"></i>Login
                    </a>
                    <a href="http://192.168.10.60/auction-platform/auth/register.php" class="bg-gradient-to-r from-primary to-secondary text-white px-4 py-2 rounded-lg hover:shadow-lg transition-all">
                        <i class="fas fa-user-plus mr-1"></i>Register
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center">
                <button onclick="toggleMobileMenu()" class="text-gray-700 hover:text-primary transition-colors">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200">
        <a href="http://192.168.10.60/auction-platform/" class="block px-4 py-3 text-gray-700 hover:bg-gray-50">Home</a>
        <a href="http://192.168.10.60/auction-platform/auctions/" class="block px-4 py-3 text-gray-700 hover:bg-gray-50">Auctions</a>
        
        <?php if (isLoggedIn()): ?>
            <div class="border-t border-gray-200">
                <a href="http://192.168.10.60/auction-platform/account/" class="block px-4 py-3 text-gray-700 hover:bg-gray-50">My Account</a>
                <a href="http://192.168.10.60/auction-platform/account/add-funds.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50">Add Funds</a>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <a href="http://192.168.10.60/auction-platform/admin/" class="block px-4 py-3 text-warning hover:bg-warning/5">Admin Panel</a>
                <?php endif; ?>
                <a href="http://192.168.10.60/auction-platform/auth/logout.php" class="block px-4 py-3 text-danger hover:bg-red-50">Logout</a>
            </div>
        <?php else: ?>
            <div class="border-t border-gray-200">
                <a href="http://192.168.10.60/auction-platform/auth/login.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50">Login</a>
                <a href="http://192.168.10.60/auction-platform/auth/register.php" class="block px-4 py-3 text-primary font-medium hover:bg-gray-50">Register</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    }
</script>

<style>
    .nav-link {
        @apply text-gray-600 hover:text-primary font-medium transition-colors px-3 py-2 rounded-lg;
    }
    .nav-link.active {
        @apply text-primary bg-primary/10;
    }
    .nav-link:hover {
        @apply bg-gray-50;
    }
    .flag-icon { width: 24px; height: 18px; display: inline-block; vertical-align: middle; }
</style>

<script>
    // AJAX Balance Refresh Function
    function refreshBalance() {
        const balanceElement = document.getElementById('user-balance');
        const refreshIcon = document.querySelector('[onclick="refreshBalance()"] i');
        refreshIcon.classList.add('fa-spin');
        fetch('<?= url("refresh-balance.php?ajax=1") ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    balanceElement.textContent = data.formatted;
                    document.querySelectorAll('[data-balance]').forEach(el => {
                        el.textContent = data.formatted;
                    });
                    balanceElement.parentElement.classList.add('bg-blue-50', 'text-blue-800');
                    setTimeout(() => {
                        balanceElement.parentElement.classList.remove('bg-blue-50', 'text-blue-800');
                        balanceElement.parentElement.classList.add('bg-green-50', 'text-green-800');
                    }, 1000);
                }
            })
            .catch(error => {
                console.error('Balance refresh failed:', error);
            })
            .finally(() => {
                refreshIcon.classList.remove('fa-spin');
            });
    }
    setInterval(refreshBalance, 30000);
</script>