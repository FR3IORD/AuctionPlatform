<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__.'/../config/config.php';

// --- Language & Translation Loader ---
$supported_langs = ['en', 'es', 'it', 'el'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $supported_langs)) $lang = 'en';
$_SESSION['lang'] = $lang;
if (!function_exists('t')) {
    $translations = require __DIR__ . "/../lang/$lang.php";
    function t($key) {
        global $translations;
        return $translations[$key] ?? $key;
    }
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? 0;

// --- User Statistics ---
$stats = [
    'auctions_joined' => 0,
    'total_tickets' => 0,
    'used_bids' => 0,
    'auctions_won' => 0,
];
$stmt = $db->prepare("SELECT COUNT(DISTINCT auction_id) FROM user_tickets WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['auctions_joined'] = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(tickets_count) FROM user_tickets WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total_tickets'] = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(used_bids) FROM user_tickets WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['used_bids'] = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM auctions WHERE winner_id = ?");
$stmt->execute([$user_id]);
$stats['auctions_won'] = (int)$stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT a.*, ut.tickets_count, ut.total_bids, ut.used_bids 
    FROM auctions a 
    JOIN user_tickets ut ON a.id = ut.auction_id
    WHERE ut.user_id = ? AND a.status = 'active'
    ORDER BY a.end_time ASC
");
$stmt->execute([$user_id]);
$active_auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT * FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('formatPrice')) {
    function formatPrice($num) { return number_format($num, 2).'₾'; }
}
if (!function_exists('timeAgo')) {
    function timeAgo($dt) { return date('M j, H:i', strtotime($dt)); }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('my_account') ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Auction Platform' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#3B82F6',
                    secondary: '#8B5CF6',
                    accent: '#06B6D4',
                    warning: '#F59E0B',
                    success: '#10B981',
                    danger: '#EF4444'
                }
            }
        }
    }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
</head>
<body class="bg-graylight min-h-screen">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="max-w-7xl mx-auto py-12 px-2 sm:px-6 grid grid-cols-1 lg:grid-cols-4 gap-10">
        <!-- Sidebar -->
        <div>
            <nav class="bg-white shadow-lg rounded-2xl mb-8 border border-primary/10">
                <ul class="divide-y divide-gray-100">
                    <li>
                        <a href="tickets.php" class="flex items-center px-4 py-3 text-primary hover:bg-primary/10 rounded-lg font-medium transition-all">
                            <i class="fas fa-ticket-alt mr-3"></i><?= t('my_tickets') ?>
                        </a>
                    </li>
                    <li>
                        <a href="transactions.php" class="flex items-center px-4 py-3 text-primary hover:bg-primary/10 rounded-lg font-medium transition-all">
                            <i class="fas fa-exchange-alt mr-3"></i><?= t('transaction_history') ?>
                        </a>
                    </li>
                    <li>
                        <a href="add-funds.php" class="flex items-center px-4 py-3 text-primary hover:bg-primary/10 rounded-lg font-medium transition-all">
                            <i class="fas fa-credit-card mr-3"></i><?= t('add_funds') ?>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="flex items-center px-4 py-3 text-primary hover:bg-primary/10 rounded-lg font-medium transition-all">
                            <i class="fas fa-user-edit mr-3"></i><?= t('edit_profile') ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <!-- Main Content -->
        <div class="lg:col-span-3 space-y-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <?php
                $cards = [
                    [
                        'icon' => 'fa-gavel',
                        'label' => t('auctions_joined'),
                        'value' => $stats['auctions_joined']
                    ],
                    [
                        'icon' => 'fa-ticket-alt',
                        'label' => t('tickets_purchased'),
                        'value' => $stats['total_tickets']
                    ],
                    [
                        'icon' => 'fa-crosshairs',
                        'label' => t('bids_placed'),
                        'value' => $stats['used_bids']
                    ],
                    [
                        'icon' => 'fa-trophy',
                        'label' => t('auctions_won'),
                        'value' => $stats['auctions_won']
                    ],
                ];
                foreach($cards as $card): ?>
                <div class="bg-white rounded-2xl shadow p-6 flex items-center gap-4 border border-primary/10">
                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <i class="fas <?= $card['icon'] ?> text-primary text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-primary"><?= $card['value'] ?></div>
                        <div class="text-sm text-dark/70"><?= $card['label'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Active Auctions -->
            <div class="bg-white rounded-2xl shadow p-6 border border-primary/10">
                <h3 class="text-xl font-bold text-dark mb-6 flex items-center gap-2">
                    <i class="fas fa-play-circle text-primary"></i>
                    <?= t('active_auctions') ?>
                </h3>
                <?php if (empty($active_auctions)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p class="text-dark/70 mb-4"><?= t('no_active_auctions_user') ?></p>
                        <a href="/auction-platform/auctions/" class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg hover:bg-primary/80 transition-all">
                            <?= t('browse_auctions') ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach($active_auctions as $auction): ?>
                            <div class="border border-primary/10 rounded-lg p-4 bg-white hover:shadow-md transition-shadow flex items-center justify-between">
                                <div class="flex items-center">
                                    <img src="<?= $auction['image_url'] ?>" alt="<?= htmlspecialchars($auction['title']) ?>" 
                                         class="w-16 h-16 object-cover rounded-lg mr-4 shadow border border-primary/10 bg-primary/10">
                                    <div>
                                        <h4 class="font-semibold text-dark"><?= htmlspecialchars($auction['title']) ?></h4>
                                        <div class="flex items-center text-xs text-dark/60 gap-3 mt-1">
                                            <span><i class="fas fa-calendar-alt mr-1"></i><?= t('ends') ?>: <?= date('M j, Y H:i', strtotime($auction['end_time'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right min-w-[260px]">
                                    <div class="flex gap-4 mb-1">
                                        <div>
                                            <span class="text-dark/60 text-xs"><?= t('tickets_owned') ?></span>
                                            <div class="font-semibold text-dark"><?= $auction['tickets_count'] ?></div>
                                        </div>
                                        <div>
                                            <span class="text-dark/60 text-xs"><?= t('total_bids') ?></span>
                                            <div class="font-semibold text-dark"><?= $auction['total_bids'] ?></div>
                                        </div>
                                        <div>
                                            <span class="text-dark/60 text-xs"><?= t('used_bids') ?></span>
                                            <div class="font-semibold text-negative"><?= $auction['used_bids'] ?></div>
                                        </div>
                                        <div>
                                            <span class="text-dark/60 text-xs"><?= t('available_bids') ?></span>
                                            <div class="font-semibold text-positive"><?= max(0, $auction['total_bids'] - $auction['used_bids']) ?></div>
                                        </div>
                                    </div>
                                    <a href="/auction-platform/auctions/view.php?id=<?= $auction['id'] ?>" 
                                       class="inline-block mt-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary/80 transition-all">
                                        <?= t('view_auction') ?>
                                    </a>
                                    <div class="mt-2">
                                        <div class="text-xs text-dark/60 mb-1"><?= t('bid_usage') ?></div>
                                        <div class="h-2 rounded bg-gray-100 w-full overflow-hidden">
                                            <div class="h-2 rounded bg-primary" style="width:<?= $auction['total_bids'] ? round(($auction['used_bids']/$auction['total_bids'])*100) : 0 ?>%"></div>
                                        </div>
                                        <div class="text-xs text-dark/60 text-right mt-1"><?= $auction['used_bids'] ?>/<?= $auction['total_bids'] ?> <?= t('used') ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Recent Transactions -->
            <div class="bg-white rounded-2xl shadow p-6 border border-primary/10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-dark flex items-center gap-2">
                        <i class="fas fa-history text-primary"></i>
                        <?= t('recent_transactions') ?>
                    </h3>
                    <a href="transactions.php" class="text-primary hover:underline font-medium flex items-center gap-1">
                        <?= t('view_all') ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php if (empty($recent_transactions)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                        <p class="text-dark/70"><?= t('no_transactions') ?></p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach($recent_transactions as $transaction): ?>
                            <div class="flex items-center justify-between p-4 border border-primary/10 rounded-lg bg-white">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4
                                                <?= $transaction['type'] == 'deposit' ? 'bg-positive/10' : 'bg-negative/10' ?>">
                                        <i class="fas <?= $transaction['type'] == 'deposit' ? 'fa-plus text-positive' : 'fa-minus text-negative' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-dark">
                                            <?= ucfirst(str_replace('_', ' ', $transaction['type'])) ?>
                                        </div>
                                        <div class="text-sm text-dark/60"><?= $transaction['description'] ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold <?= $transaction['type'] == 'deposit' ? 'text-positive' : 'text-negative' ?>">
                                        <?= $transaction['type'] == 'deposit' ? '+' : '-' ?><?= formatPrice($transaction['amount']) ?>
                                    </div>
                                    <div class="text-sm text-dark/60"><?= timeAgo($transaction['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>