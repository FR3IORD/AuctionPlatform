<?php 
require_once '../config/config.php';
requireLogin();

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

// Get user's tickets with auction details
$query = "
    SELECT 
        ut.*,
        a.title,
        a.image_url,
        a.status,
        a.start_time,
        a.end_time,
        a.ticket_price,
        a.winner_id,
        winner.username as winner_username
    FROM user_tickets ut
    JOIN auctions a ON ut.auction_id = a.id
    LEFT JOIN users winner ON a.winner_id = winner.id
    WHERE ut.user_id = ?
    ORDER BY ut.purchase_date DESC
";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('formatPrice')) {
    function formatPrice($num) { return number_format($num, 2).'₾'; }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('my_tickets') ?> - <?= SITE_NAME ?></title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <nav class="mb-4">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="/auction-platform/account/" class="hover:text-primary"><?= t('account') ?></a></li>
                    <li><i class="fas fa-chevron-right"></i></li>
                    <li class="text-gray-900"><?= t('my_tickets') ?></li>
                </ol>
            </nav>
            <h1 class="text-4xl font-bold text-gray-900 mb-2"><?= t('my_tickets') ?></h1>
            <p class="text-xl text-gray-600"><?= t('view_all_auction_tickets') ?></p>
        </div>

        <?php if (empty($tickets)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-700 mb-4"><?= t('no_tickets_yet') ?></h3>
                <p class="text-gray-500 mb-8"><?= t('no_tickets_yet_desc') ?></p>
                <a href="/auction-platform/auctions/" class="bg-gradient-to-r from-primary to-secondary text-white px-8 py-4 rounded-full font-semibold hover:shadow-lg transition-all">
                    <i class="fas fa-gavel mr-2"></i><?= t('browse_auctions') ?>
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach($tickets as $ticket): ?>
                    <?php
                    $now = new DateTime();
                    $end_time = new DateTime($ticket['end_time']);
                    $is_winner = $ticket['winner_id'] == $_SESSION['user_id'];
                    $is_ended = $ticket['status'] == 'completed' || $now > $end_time;
                    $available_bids = $ticket['total_bids'] - $ticket['used_bids'];
                    ?>
                    
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <img src="<?= $ticket['image_url'] ?>" alt="<?= htmlspecialchars($ticket['title']) ?>" 
                                         class="w-24 h-24 object-cover rounded-lg">
                                    
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($ticket['title']) ?></h3>
                                        
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                            <div>
                                                <div class="text-sm text-gray-600"><?= t('tickets_owned') ?></div>
                                                <div class="font-semibold text-lg"><?= $ticket['tickets_count'] ?></div>
                                            </div>
                                            <div>
                                                <div class="text-sm text-gray-600"><?= t('total_bids') ?></div>
                                                <div class="font-semibold text-lg"><?= $ticket['total_bids'] ?></div>
                                            </div>
                                            <div>
                                                <div class="text-sm text-gray-600"><?= t('used_bids') ?></div>
                                                <div class="font-semibold text-lg text-danger"><?= $ticket['used_bids'] ?></div>
                                            </div>
                                            <div>
                                                <div class="text-sm text-gray-600"><?= t('available_bids') ?></div>
                                                <div class="font-semibold text-lg text-success"><?= $available_bids ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-4 text-sm text-gray-600">
                                            <span><i class="fas fa-calendar mr-1"></i><?= t('purchased') ?>: <?= date('M j, Y', strtotime($ticket['purchase_date'])) ?></span>
                                            <span><i class="fas fa-clock mr-1"></i><?= t('ends') ?>: <?= date('M j, Y H:i', strtotime($ticket['end_time'])) ?></span>
                                            <span><i class="fas fa-dollar-sign mr-1"></i><?= t('paid') ?>: <?= formatPrice($ticket['tickets_count'] * $ticket['ticket_price']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <!-- Status Badge -->
                                    <?php if ($is_winner): ?>
                                        <span class="inline-block px-4 py-2 bg-warning text-white rounded-full text-sm font-semibold mb-4">
                                            <i class="fas fa-trophy mr-1"></i><?= t('winner') ?>!
                                        </span>
                                    <?php elseif ($ticket['status'] == 'active'): ?>
                                        <span class="inline-block px-4 py-2 bg-red-500 text-white rounded-full text-sm font-semibold mb-4 animate-pulse">
                                            <i class="fas fa-play mr-1"></i><?= t('live') ?>
                                        </span>
                                    <?php elseif ($ticket['status'] == 'upcoming'): ?>
                                        <span class="inline-block px-4 py-2 bg-blue-500 text-white rounded-full text-sm font-semibold mb-4">
                                            <i class="fas fa-clock mr-1"></i><?= t('upcoming') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-4 py-2 bg-gray-500 text-white rounded-full text-sm font-semibold mb-4">
                                            <i class="fas fa-flag-checkered mr-1"></i><?= t('ended') ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <a href="/auction-platform/auctions/view.php?id=<?= $ticket['auction_id'] ?>" 
                                           class="bg-primary text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-600 transition-all">
                                            <?= t('view_auction') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Winner Info -->
                            <?php if ($is_ended && $ticket['winner_id'] && !$is_winner): ?>
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-trophy text-warning mr-2"></i>
                                        <span><?= t('winner') ?>: <strong><?= htmlspecialchars($ticket['winner_username']) ?></strong></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Bid Progress Bar -->
                            <?php if ($ticket['total_bids'] > 0): ?>
                                <div class="mt-4">
                                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                                        <span><?= t('bid_usage') ?></span>
                                        <span><?= $ticket['used_bids'] ?>/<?= $ticket['total_bids'] ?> <?= t('used') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-gradient-to-r from-primary to-secondary h-2 rounded-full transition-all" 
                                             style="width: <?= ($ticket['used_bids'] / $ticket['total_bids']) * 100 ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>