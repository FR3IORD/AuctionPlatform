<?php 
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'make_admin':
                $query = "UPDATE users SET is_admin = 1 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $success = "User promoted to admin successfully!";
                break;
                
            case 'remove_admin':
                // Prevent removing self
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception("You cannot remove admin privileges from yourself!");
                }
                $query = "UPDATE users SET is_admin = 0 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $success = "Admin privileges removed successfully!";
                break;
                
            case 'activate':
                $query = "UPDATE users SET status = 'active' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $success = "User activated successfully!";
                break;
                
            case 'suspend':
                // Prevent suspending self
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception("You cannot suspend yourself!");
                }
                $query = "UPDATE users SET status = 'suspended' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $success = "User suspended successfully!";
                break;
                
            case 'update_balance':
    $new_balance = (float)$_POST['new_balance'];
    $balance_action = $_POST['balance_action']; // 'set', 'add', 'subtract'
    
    if ($balance_action == 'set') {
        $query = "UPDATE users SET balance = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_balance, $user_id]);
    } elseif ($balance_action == 'add') {
        $query = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_balance, $user_id]);
    } elseif ($balance_action == 'subtract') {
        $query = "UPDATE users SET balance = GREATEST(0, balance - ?) WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_balance, $user_id]);
    }
    
    // Log the transaction
    $query = "INSERT INTO transactions (user_id, type, amount, status, description, created_at) VALUES (?, 'admin_adjustment', ?, 'completed', ?, NOW())";
    $description = "Admin balance adjustment: " . ucfirst($balance_action) . " " . formatPrice($new_balance);
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $new_balance, $description]);
    
    // If updating current user's balance, refresh their session
    if ($user_id == $_SESSION['user_id']) {
        $query = "SELECT balance FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $_SESSION['user_balance'] = $stmt->fetchColumn();
    }
    
    $success = "User balance updated successfully!";
    break;
                
            case 'delete':
                // Prevent deleting self
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception("You cannot delete yourself!");
                }
                
                // Delete related records first
                $queries = [
                    "DELETE FROM bids WHERE user_id = ?",
                    "DELETE FROM user_tickets WHERE user_id = ?",
                    "DELETE FROM transactions WHERE user_id = ?",
                    "DELETE FROM users WHERE id = ?"
                ];
                foreach($queries as $query) {
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user_id]);
                }
                $success = "User deleted successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$admin_filter = $_GET['admin'] ?? 'all';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_term = '%' . $search . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($admin_filter !== 'all') {
    $where_conditions[] = "is_admin = ?";
    $params[] = ($admin_filter === 'admin') ? 1 : 0;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();

// Pagination
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_users / $per_page);

// Get users with stats
$query = "SELECT u.*, 
          COUNT(DISTINCT ut.id) as total_tickets,
          COUNT(DISTINCT b.id) as total_bids,
          COALESCE(SUM(CASE WHEN t.type = 'ticket_purchase' THEN t.amount ELSE 0 END), 0) as total_spent,
          MAX(t.created_at) as last_transaction
          FROM users u
          LEFT JOIN user_tickets ut ON u.id = ut.user_id
          LEFT JOIN bids b ON u.id = b.user_id
          LEFT JOIN transactions t ON u.id = t.user_id
          $where_clause
          GROUP BY u.id
          ORDER BY $sort $order
          LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats - NOW INCLUDING STATUS COUNTS
$stats_query = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
    COUNT(CASE WHEN status = 'suspended' THEN 1 END) as suspended_users,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
    COUNT(CASE WHEN is_admin = 1 THEN 1 END) as admin_users,
    COALESCE(AVG(balance), 0) as avg_balance,
    COALESCE(SUM(balance), 0) as total_balance
    FROM users";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin - <?= SITE_NAME ?></title>
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
    
    <style>
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal.active { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .modal-content { 
            background: white; 
            padding: 30px; 
            border-radius: 16px; 
            max-width: 500px; 
            width: 90%; 
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px -2px rgba(0, 0, 0, 0.1);
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Messages -->
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?= htmlspecialchars($success) ?></span>
                <button onclick="this.parentElement.style.display='none'" class="ml-auto text-green-500 hover:text-green-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?= htmlspecialchars($error) ?></span>
                <button onclick="this.parentElement.style.display='none'" class="ml-auto text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="mb-8">
            <nav class="mb-4">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="<?= url('admin') ?>" class="hover:text-primary transition-colors">Admin</a></li>
                    <li><i class="fas fa-chevron-right"></i></li>
                    <li class="text-gray-900">Manage Users</li>
                </ol>
            </nav>
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2 flex items-center">
                        <i class="fas fa-users text-primary mr-4"></i>
                        Manage Users
                    </h1>
                    <p class="text-xl text-gray-600">View and manage all registered users</p>
                    <div class="text-sm text-gray-500 mt-3 flex items-center flex-wrap gap-4">
                        <span class="flex items-center">
                            <i class="fas fa-users mr-2"></i>
                            Total: <?= number_format($total_users) ?> users
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-clock mr-1"></i>
                            <?= date('M j, Y H:i:s') ?> (<?= CURRENT_TIMEZONE ?>)
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i>
                            Real-time statistics
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
            <div class="stats-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-primary">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-3xl font-bold text-gray-900"><?= number_format($stats['total_users']) ?></div>
                        <div class="text-sm text-gray-600 font-medium">Total Users</div>
                        <div class="text-xs text-primary mt-1">All registered</div>
                    </div>
                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-primary text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-success">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-3xl font-bold text-gray-900"><?= number_format($stats['active_users']) ?></div>
                        <div class="text-sm text-gray-600 font-medium">Active</div>
                        <div class="text-xs text-success mt-1">
                            <?= $stats['total_users'] > 0 ? round(($stats['active_users'] / $stats['total_users']) * 100) : 0 ?>% of total
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-success/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-check text-success text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-danger">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-3xl font-bold text-gray-900"><?= number_format($stats['suspended_users']) ?></div>
                        <div class="text-sm text-gray-600 font-medium">Suspended</div>
                        <div class="text-xs text-danger mt-1">
                            <?= $stats['suspended_users'] > 0 ? 'Need attention' : 'All good' ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-danger/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-slash text-danger text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-secondary">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-3xl font-bold text-gray-900"><?= number_format($stats['admin_users']) ?></div>
                        <div class="text-sm text-gray-600 font-medium">Admins</div>
                        <div class="text-xs text-secondary mt-1">System managers</div>
                    </div>
                    <div class="w-12 h-12 bg-secondary/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-shield text-secondary text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-warning">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900"><?= formatPrice($stats['avg_balance']) ?></div>
                        <div class="text-sm text-gray-600 font-medium">Avg Balance</div>
                        <div class="text-xs text-warning mt-1">Per user average</div>
                    </div>
                    <div class="w-12 h-12 bg-warning/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-warning text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-accent">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900"><?= formatPrice($stats['total_balance']) ?></div>
                        <div class="text-sm text-gray-600 font-medium">Total Balance</div>
                        <div class="text-xs text-accent mt-1">Platform total</div>
                    </div>
                    <div class="w-12 h-12 bg-accent/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-wallet text-accent text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters and Search -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-filter text-primary mr-2"></i>
                    Filter & Search Users
                </h3>
                <div class="text-sm text-gray-500">
                    <?= number_format($total_users) ?> users found
                </div>
            </div>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search mr-1"></i>Search Users
                    </label>
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Username, email, or name..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-flag mr-1"></i>Status
                    </label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active (<?= $stats['active_users'] ?>)</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended (<?= $stats['suspended_users'] ?>)</option>
                        <?php if ($stats['pending_users'] > 0): ?>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending (<?= $stats['pending_users'] ?>)</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-tag mr-1"></i>Role
                    </label>
                    <select name="admin" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="all" <?= $admin_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="admin" <?= $admin_filter === 'admin' ? 'selected' : '' ?>>Admins (<?= $stats['admin_users'] ?>)</option>
                        <option value="user" <?= $admin_filter === 'user' ? 'selected' : '' ?>>Users (<?= $stats['total_users'] - $stats['admin_users'] ?>)</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-gradient-to-r from-primary to-blue-600 text-white px-4 py-2 rounded-lg hover:shadow-lg transition-all flex items-center justify-center font-medium">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
            </form>
            
            <?php if (!empty($search) || $status_filter !== 'all' || $admin_filter !== 'all'): ?>
            <div class="mt-4 flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                <div class="text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Filters applied - showing <?= number_format($total_users) ?> of <?= number_format($stats['total_users']) ?> users
                </div>
                <a href="?" class="text-sm text-blue-600 hover:text-blue-800 underline">Clear all filters</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                <i class="fas fa-user mr-2"></i>User
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                <i class="fas fa-flag mr-2"></i>Status
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                <i class="fas fa-wallet mr-2"></i>Balance
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                <i class="fas fa-chart-line mr-2"></i>Activity
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                <i class="fas fa-calendar mr-2"></i>Joined
                            </th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900 min-w-[160px]">
                                <i class="fas fa-cogs mr-2"></i>Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($users as $user): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4 text-white font-bold">
                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 flex items-center">
                                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                                <?php if ($user['is_admin']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <i class="fas fa-crown mr-1"></i>Admin
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <i class="fas fa-user mr-1"></i>You
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-gray-600">@<?= htmlspecialchars($user['username']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                 <?php
                                                 switch($user['status']) {
                                                     case 'active':
                                                         echo 'bg-green-100 text-green-800';
                                                         break;
                                                     case 'suspended':
                                                         echo 'bg-red-100 text-red-800';
                                                         break;
                                                     case 'pending':
                                                         echo 'bg-yellow-100 text-yellow-800';
                                                         break;
                                                     default:
                                                         echo 'bg-gray-100 text-gray-800';
                                                 }
                                                 ?>">
                                        <i class="<?php
                                            switch($user['status']) {
                                                case 'active': echo 'fas fa-check-circle'; break;
                                                case 'suspended': echo 'fas fa-ban'; break;
                                                case 'pending': echo 'fas fa-clock'; break;
                                                default: echo 'fas fa-question-circle';
                                            }
                                        ?> mr-1"></i>
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-lg font-semibold text-gray-900"><?= formatPrice($user['balance']) ?></div>
                                    <div class="text-sm text-gray-600">Spent: <?= formatPrice($user['total_spent']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="space-y-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-ticket-alt text-blue-500 mr-2"></i>
                                            <span><?= number_format($user['total_tickets']) ?> tickets</span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-gavel text-purple-500 mr-2"></i>
                                            <span><?= number_format($user['total_bids']) ?> bids</span>
                                        </div>
                                        <?php if ($user['last_transaction']): ?>
                                            <div class="text-xs text-gray-500">
                                                Last: <?= timeAgo($user['last_transaction']) ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-xs text-gray-400">No activity yet</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <div><?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= timeAgo($user['created_at']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <!-- Balance Management -->
                                        <button onclick="openBalanceModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', <?= $user['balance'] ?>)" 
                                                class="action-btn text-success hover:bg-success hover:text-white border border-success" 
                                                title="Manage Balance">
                                            <i class="fas fa-wallet"></i>
                                        </button>
                                        
                                        <!-- Admin Actions -->
                                        <?php if (!$user['is_admin'] && $user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="make_admin" 
                                                        class="action-btn text-secondary hover:bg-secondary hover:text-white border border-secondary" 
                                                        title="Make Admin"
                                                        onclick="return confirm('Make <?= htmlspecialchars($user['username']) ?> an admin?')">
                                                    <i class="fas fa-crown"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($user['is_admin'] && $user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="remove_admin" 
                                                        class="action-btn text-warning hover:bg-warning hover:text-white border border-warning" 
                                                        title="Remove Admin"
                                                        onclick="return confirm('Remove admin privileges from <?= htmlspecialchars($user['username']) ?>?')">
                                                    <i class="fas fa-user-minus"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Status Actions -->
                                        <?php if ($user['status'] == 'active' && $user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="suspend" 
                                                        class="action-btn text-danger hover:bg-danger hover:text-white border border-danger" 
                                                        title="Suspend User"
                                                        onclick="return confirm('Suspend <?= htmlspecialchars($user['username']) ?>?')">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($user['status'] == 'suspended'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="activate" 
                                                        class="action-btn text-success hover:bg-success hover:text-white border border-success" 
                                                        title="Activate User">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Action -->
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('🗑️ Delete <?= htmlspecialchars($user['username']) ?> permanently?\n\n⚠️ This will also delete:\n• All bids\n• All tickets\n• All transactions\n\nThis action cannot be undone!')">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="delete" 
                                                        class="action-btn text-danger hover:bg-danger hover:text-white border border-danger" 
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between mt-6">
                <div class="text-sm text-gray-700">
                    Showing <?= number_format(($page - 1) * $per_page + 1) ?> to <?= number_format(min($page * $per_page, $total_users)) ?> of <?= number_format($total_users) ?> users
                </div>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                            <i class="fas fa-chevron-left mr-1"></i>Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="px-3 py-2 border rounded-lg text-sm <?= $i == $page ? 'bg-primary text-white border-primary' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                            Next<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($users)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-600 mb-4">No Users Found</h3>
                <p class="text-gray-500 mb-6">No users match your current filters</p>
                <a href="?" class="text-primary hover:text-blue-600 underline">Clear all filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Balance Management Modal -->
    <div id="balanceModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-wallet text-success mr-3"></i>
                    Manage Balance
                </h3>
                <button onclick="closeBalanceModal()" class="text-gray-500 hover:text-gray-700 action-btn border-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="balanceForm">
                <input type="hidden" name="action" value="update_balance">
                <input type="hidden" name="user_id" id="balance_user_id">
                
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-lg font-semibold text-gray-900" id="balance_username"></div>
                            <div class="text-sm text-gray-600">Current Balance</div>
                        </div>
                        <div class="text-2xl font-bold text-green-600" id="balance_current"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="balance_action" class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <select name="balance_action" id="balance_action" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="set">Set Balance To</option>
                            <option value="add">Add Amount</option>
                            <option value="subtract">Subtract Amount</option>
                        </select>
                    </div>
                    <div>
                        <label for="new_balance" class="block text-sm font-medium text-gray-700 mb-2">Amount ($)</label>
                        <input type="number" name="new_balance" id="new_balance" required step="0.01" min="0"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeBalanceModal()" 
                            class="px-6 py-3 text-gray-600 hover:text-gray-800 font-medium">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-gradient-to-r from-success to-green-600 text-white px-8 py-3 rounded-lg font-semibold hover:shadow-lg transition-all flex items-center">
                        <i class="fas fa-save mr-2"></i>Update Balance
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Balance modal functions
        function openBalanceModal(userId, username, currentBalance) {
            document.getElementById('balance_user_id').value = userId;
            document.getElementById('balance_username').textContent = '@' + username;
            document.getElementById('balance_current').textContent = '$' + parseFloat(currentBalance).toFixed(2);
            document.getElementById('new_balance').value = '';
            document.getElementById('balanceModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeBalanceModal() {
            document.getElementById('balanceModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('balanceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBalanceModal();
            }
        });
        
        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBalanceModal();
            }
        });
        
        console.log('👥 User Management Panel loaded successfully!');
        console.log('📊 Statistics calculated correctly:');
        console.log('- Total Users: <?= $stats['total_users'] ?>');
        console.log('- Active: <?= $stats['active_users'] ?>');
        console.log('- Suspended: <?= $stats['suspended_users'] ?>');
        console.log('- Admins: <?= $stats['admin_users'] ?>');
        console.log('- Average Balance: $<?= number_format($stats['avg_balance'], 2) ?>');
        console.log('- Total Balance: $<?= number_format($stats['total_balance'], 2) ?>');
    </script>
</body>
</html>