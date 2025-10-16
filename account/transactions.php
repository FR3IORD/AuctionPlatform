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

// Get filter parameters
$type = $_GET['type'] ?? 'all';
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);

// Build query
$where_conditions = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($type !== 'all') {
    $where_conditions[] = "type = ?";
    $params[] = $type;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get transactions with pagination
$query = "SELECT t.*, a.title as auction_title FROM transactions t 
          LEFT JOIN auctions a ON t.auction_id = a.id 
          $where_clause 
          ORDER BY t.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM transactions t $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_transactions = $stmt->fetchColumn();

$has_more = ($offset + $limit) < $total_transactions;

if (!function_exists('formatPrice')) {
    function formatPrice($num) { return number_format($num, 2).'₾'; }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('transactions') ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Auction Platform' ?></title>
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
                    <li class="text-gray-900"><?= t('transactions') ?></li>
                </ol>
            </nav>
            <h1 class="text-4xl font-bold text-gray-900 mb-2"><?= t('transaction_history') ?></h1>
            <p class="text-xl text-gray-600"><?= t('view_all_account_transactions') ?></p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2"><?= t('transaction_type') ?></label>
                    <select id="type" name="type" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>><?= t('all_types') ?></option>
                        <option value="deposit" <?= $type === 'deposit' ? 'selected' : '' ?>><?= t('deposits') ?></option>
                        <option value="ticket_purchase" <?= $type === 'ticket_purchase' ? 'selected' : '' ?>><?= t('ticket_purchases') ?></option>
                        <option value="refund" <?= $type === 'refund' ? 'selected' : '' ?>><?= t('refunds') ?></option>
                        <option value="withdrawal" <?= $type === 'withdrawal' ? 'selected' : '' ?>><?= t('withdrawals') ?></option>
                    </select>
                </div>
                
                <button type="submit" class="bg-gradient-to-r from-primary to-secondary text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition-all">
                    <i class="fas fa-filter mr-2"></i><?= t('filter') ?>
                </button>
                
                <a href="/auction-platform/account/transactions.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-600 transition-all">
                    <i class="fas fa-refresh mr-2"></i><?= t('reset') ?>
                </a>
            </form>
        </div>

        <!-- Transactions List -->
        <?php if (empty($transactions)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <i class="fas fa-receipt text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-700 mb-4"><?= t('no_transactions') ?></h3>
                <p class="text-gray-500 mb-8"><?= t('no_transactions_desc') ?></p>
                <a href="/auction-platform/account/add-funds.php" class="bg-gradient-to-r from-primary to-secondary text-white px-8 py-4 rounded-full font-semibold hover:shadow-lg transition-all">
                    <i class="fas fa-plus mr-2"></i><?= t('add_funds') ?>
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900"><?= t('date') ?></th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900"><?= t('type') ?></th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900"><?= t('description') ?></th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900"><?= t('amount') ?></th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900"><?= t('status') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach($transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= date('M j, Y H:i', strtotime($transaction['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                     <?php
                                                     switch($transaction['type']) {
                                                         case 'deposit':
                                                             echo 'bg-success/10 text-success';
                                                             break;
                                                         case 'ticket_purchase':
                                                             echo 'bg-primary/10 text-primary';
                                                             break;
                                                         case 'refund':
                                                             echo 'bg-warning/10 text-warning';
                                                             break;
                                                         case 'withdrawal':
                                                             echo 'bg-danger/10 text-danger';
                                                             break;
                                                         default:
                                                             echo 'bg-gray-100 text-gray-800';
                                                     }
                                                     ?>">
                                            <?php
                                            switch($transaction['type']) {
                                                case 'deposit':
                                                    echo '<i class="fas fa-plus mr-1"></i>' . t('deposit');
                                                    break;
                                                case 'ticket_purchase':
                                                    echo '<i class="fas fa-ticket-alt mr-1"></i>' . t('purchase');
                                                    break;
                                                case 'refund':
                                                    echo '<i class="fas fa-undo mr-1"></i>' . t('refund');
                                                    break;
                                                case 'withdrawal':
                                                    echo '<i class="fas fa-minus mr-1"></i>' . t('withdrawal');
                                                    break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($transaction['description']) ?>
                                        <?php if ($transaction['auction_title']): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?= htmlspecialchars($transaction['auction_title']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold
                                            <?= in_array($transaction['type'], ['deposit', 'refund']) ? 'text-success' : 'text-danger' ?>">
                                        <?= in_array($transaction['type'], ['deposit', 'refund']) ? '+' : '-' ?>
                                        <?= formatPrice($transaction['amount']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                     <?php
                                                     switch($transaction['status']) {
                                                         case 'completed':
                                                             echo 'bg-success/10 text-success';
                                                             break;
                                                         case 'pending':
                                                             echo 'bg-warning/10 text-warning';
                                                             break;
                                                         case 'failed':
                                                             echo 'bg-danger/10 text-danger';
                                                             break;
                                                         default:
                                                             echo 'bg-gray-100 text-gray-800';
                                                     }
                                                     ?>">
                                            <?php
                                            switch($transaction['status']) {
                                                case 'completed':
                                                    echo '<i class="fas fa-check mr-1"></i>' . t('completed');
                                                    break;
                                                case 'pending':
                                                    echo '<i class="fas fa-clock mr-1"></i>' . t('pending');
                                                    break;
                                                case 'failed':
                                                    echo '<i class="fas fa-times mr-1"></i>' . t('failed');
                                                    break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($has_more): ?>
                    <div class="bg-gray-50 px-6 py-4 text-center">
                        <a href="?<?= http_build_query(array_merge($_GET, ['offset' => $offset + $limit])) ?>" 
                           class="bg-primary text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-600 transition-all">
                            <i class="fas fa-chevron-down mr-2"></i><?= t('load_more') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>