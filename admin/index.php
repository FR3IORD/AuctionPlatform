<?php 
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get dashboard statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) FROM users WHERE is_admin = FALSE";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetchColumn();

// Total auctions
$query = "SELECT COUNT(*) FROM auctions";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_auctions'] = $stmt->fetchColumn();

// Active auctions
$query = "SELECT COUNT(*) FROM auctions WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_auctions'] = $stmt->fetchColumn();

// Total revenue
$query = "SELECT SUM(amount) FROM transactions WHERE type = 'ticket_purchase' AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetchColumn() ?: 0;

// Recent transactions
$query = "SELECT t.*, u.username, a.title as auction_title FROM transactions t 
          JOIN users u ON t.user_id = u.id 
          LEFT JOIN auctions a ON t.auction_id = a.id 
          ORDER BY t.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent users
$query = "SELECT * FROM users WHERE is_admin = FALSE ORDER BY created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure arrays are not null
$recent_transactions = $recent_transactions ?: [];
$recent_users = $recent_users ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
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
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
            <p class="text-xl text-gray-600">Manage your auction platform</p>
            <div class="mt-2 text-sm text-gray-500">
                <i class="fas fa-user mr-1"></i>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                <span class="ml-4"><i class="fas fa-clock mr-1"></i><?= getCurrentDateTime('M j, Y H:i') ?></span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <nav class="bg-white rounded-2xl shadow-lg p-4 mb-6">
                    <ul class="space-y-2">
                        <li>
                            <a href="<?= url('admin') ?>" class="flex items-center px-4 py-3 text-primary bg-primary/10 rounded-lg font-medium">
                                <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('admin/auctions.php') ?>" class="flex items-center px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-medium transition-all">
                                <i class="fas fa-gavel mr-3"></i>Manage Auctions
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('admin/add-auction.php') ?>" class="flex items-center px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-medium transition-all">
                                <i class="fas fa-plus-circle mr-3"></i>Add Auction
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('admin/users.php') ?>" class="flex items-center px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-medium transition-all">
                                <i class="fas fa-users mr-3"></i>Manage Users
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('admin/transactions.php') ?>" class="flex items-center px-4 py-3 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-medium transition-all">
                                <i class="fas fa-exchange-alt mr-3"></i>Transactions
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('admin/fix-auction-times.php') ?>" class="flex items-center px-4 py-3 text-warning hover:text-yellow-600 hover:bg-yellow-50 rounded-lg font-medium transition-all">
                                <i class="fas fa-clock mr-3"></i>Fix Auction Times
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Quick Stats Mini Cards -->
                <div class="space-y-4">
                    <div class="bg-gradient-to-r from-primary to-blue-600 text-white p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold"><?= number_format($stats['total_users']) ?></div>
                                <div class="text-blue-100 text-sm">Total Users</div>
                            </div>
                            <i class="fas fa-users text-2xl opacity-80"></i>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-success to-green-600 text-white p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold"><?= formatPrice($stats['total_revenue']) ?></div>
                                <div class="text-green-100 text-sm">Total Revenue</div>
                            </div>
                            <i class="fas fa-dollar-sign text-2xl opacity-80"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-users text-primary text-xl"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_users']) ?></div>
                                <div class="text-sm text-gray-600">Total Users</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-secondary/10 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-gavel text-secondary text-xl"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_auctions']) ?></div>
                                <div class="text-sm text-gray-600">Total Auctions</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-danger/10 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-play text-danger text-xl"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['active_auctions']) ?></div>
                                <div class="text-sm text-gray-600">Live Auctions</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-success/10 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-dollar-sign text-success text-xl"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= formatPrice($stats['total_revenue']) ?></div>
                                <div class="text-sm text-gray-600">Total Revenue</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="<?= url('admin/add-auction.php') ?>" class="bg-gradient-to-r from-primary to-blue-600 text-white p-6 rounded-xl text-center hover:shadow-lg transition-all group">
                            <i class="fas fa-plus text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <div class="font-semibold">Add New Auction</div>
                            <div class="text-sm opacity-90 mt-1">Create a new auction</div>
                        </a>
                        <a href="<?= url('admin/auctions.php') ?>" class="bg-gradient-to-r from-secondary to-purple-600 text-white p-6 rounded-xl text-center hover:shadow-lg transition-all group">
                            <i class="fas fa-gavel text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <div class="font-semibold">Manage Auctions</div>
                            <div class="text-sm opacity-90 mt-1">Edit existing auctions</div>
                        </a>
                        <a href="<?= url('admin/fix-auction-times.php') ?>" class="bg-gradient-to-r from-warning to-yellow-600 text-white p-6 rounded-xl text-center hover:shadow-lg transition-all group">
                            <i class="fas fa-clock text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <div class="font-semibold">Fix Auction Times</div>
                            <div class="text-sm opacity-90 mt-1">Update auction dates</div>
                        </a>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900">Recent Transactions</h3>
                        <a href="<?= url('admin/transactions.php') ?>" class="text-primary hover:text-secondary font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($recent_transactions)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No transactions yet</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 text-sm font-semibold text-gray-900">User</th>
                                        <th class="text-left py-3 text-sm font-semibold text-gray-900">Type</th>
                                        <th class="text-left py-3 text-sm font-semibold text-gray-900">Amount</th>
                                        <th class="text-left py-3 text-sm font-semibold text-gray-900">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_transactions as $transaction): ?>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-3">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-sm"><?= htmlspecialchars($transaction['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                             <?php
                                                             switch($transaction['type']) {
                                                                 case 'deposit':
                                                                     echo 'bg-success/10 text-success';
                                                                     break;
                                                                 case 'ticket_purchase':
                                                                     echo 'bg-primary/10 text-primary';
                                                                     break;
                                                                 default:
                                                                     echo 'bg-gray-100 text-gray-800';
                                                             }
                                                             ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $transaction['type'])) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 font-semibold
                                                    <?= $transaction['type'] == 'deposit' ? 'text-success' : 'text-danger' ?>">
                                                <?= $transaction['type'] == 'deposit' ? '+' : '-' ?>
                                                <?= formatPrice($transaction['amount']) ?>
                                            </td>
                                            <td class="py-3 text-sm text-gray-600">
                                                <?= timeAgo($transaction['created_at']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900">Recent Users</h3>
                        <a href="<?= url('admin/users.php') ?>" class="text-primary hover:text-secondary font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($recent_users)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No users registered yet</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($recent_users as $user): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                            <div class="text-sm text-gray-600">@<?= htmlspecialchars($user['username']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-semibold text-success"><?= formatPrice($user['balance']) ?></div>
                                        <div class="text-xs text-gray-500"><?= timeAgo($user['created_at']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>