<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/config.php';

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

$user_id = $_SESSION['user_id'] ?? null;
$auction_id = $_GET['auction_id'] ?? 0;

if(!$user_id) {
    header('Location: /auction-platform/auth/login.php');
    exit();
}

$query = "SELECT a.*, u.username as winner_username, u.id as winner_id 
          FROM auctions a 
          LEFT JOIN users u ON a.winner_id = u.id
          WHERE a.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auction) {
    $error = t('auction_not_found');
} elseif ($auction['winner_id'] != $user_id) {
    $error = t('not_the_winner');
}

$prize_title = $auction['title'] ?? '';
$prize_image = $auction['image_url'] ?? '';
$prize_description = $auction['description'] ?? '';
$prize_status = $auction['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('your_prize') ?> - <?= htmlspecialchars($prize_title) ?> | <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include '../includes/navbar.php'; ?>
    <div class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="bg-white rounded-2xl shadow-xl p-8 max-w-lg w-full text-center">
            <?php if (isset($error)): ?>
                <div class="text-xl text-danger font-bold mb-6"><?= htmlspecialchars($error) ?></div>
                <a href="/auction-platform/auctions/" class="mt-8 inline-block bg-primary text-white rounded-lg px-6 py-3 font-bold text-lg hover:shadow-lg transition-all">
                    <?= t('back_to_auctions') ?>
                </a>
            <?php else: ?>
                <h1 class="text-3xl font-bold text-success mb-4">🎉 <?= t('congratulations') ?></h1>
                <p class="text-lg text-gray-700 mb-6">
                    <?= t('winner_of_auction') ?><br>
                    <span class="font-bold text-primary"><?= htmlspecialchars($prize_title) ?></span>
                </p>
                <?php if ($prize_image): ?>
                    <img src="<?= htmlspecialchars($prize_image) ?>" alt="Prize" class="w-72 h-72 object-cover mx-auto rounded-2xl mb-6 shadow-lg border-4 border-success/40">
                <?php endif; ?>
                <?php if ($prize_description): ?>
                    <div class="text-gray-600 mb-6"><?= nl2br(htmlspecialchars($prize_description)) ?></div>
                <?php endif; ?>
                <div class="mb-4">
                    <span class="inline-block bg-success/20 text-success px-4 py-2 rounded-full font-semibold text-base"><?= t('status') ?>: <?= htmlspecialchars(ucfirst($prize_status)) ?></span>
                </div>
                <div class="mt-6">
                    <a href="/auction-platform/auctions/" class="bg-primary text-white font-bold px-6 py-3 rounded-lg text-lg hover:shadow-lg transition-all mr-2">
                        <?= t('back_to_auctions') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>