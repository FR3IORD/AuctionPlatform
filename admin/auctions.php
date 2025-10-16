<?php 
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle auction status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $auction_id = isset($_POST['auction_id']) ? (int)$_POST['auction_id'] : 0;
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'activate':
                $query = "UPDATE auctions SET status = 'active' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$auction_id]);
                $success = "Auction activated successfully!";
                break;
                
            case 'complete':
                $query = "UPDATE auctions SET status = 'completed' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$auction_id]);
                $success = "Auction completed successfully!";
                break;
                
            case 'cancel':
                $query = "UPDATE auctions SET status = 'cancelled' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$auction_id]);
                $success = "Auction cancelled successfully!";
                break;
                
            case 'delete':
                // First delete related records
                $queries = [
                    "DELETE FROM bids WHERE auction_id = ?",
                    "DELETE FROM user_tickets WHERE auction_id = ?",
                    "DELETE FROM transactions WHERE auction_id = ?",
                    "DELETE FROM auctions WHERE id = ?"
                ];
                foreach($queries as $query) {
                    $stmt = $db->prepare($query);
                    $stmt->execute([$auction_id]);
                }
                $success = "Auction deleted successfully!";
                break;
                
            case 'update_time':
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $new_status = $_POST['new_status'] ?? 'upcoming';
                
                // Validate dates
                if (strtotime($start_time) >= strtotime($end_time)) {
                    throw new Exception("End time must be after start time!");
                }
                
                $query = "UPDATE auctions SET start_time = ?, end_time = ?, status = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$start_time, $end_time, $new_status, $auction_id]);
                $success = "Auction time updated successfully!";
                break;
                
            case 'quick_time_fix':
                // Quick fix for specific auction
                $hours_from_now = (int)$_POST['hours_from_now'];
                $duration_hours = (int)$_POST['duration_hours'] ?: 2;
                
                $start_time = date('Y-m-d H:i:s', strtotime("+{$hours_from_now} hours"));
                $end_time = date('Y-m-d H:i:s', strtotime("+{$hours_from_now} hours +{$duration_hours} hours"));
                $new_status = $hours_from_now <= 0 ? 'active' : 'upcoming';
                
                $query = "UPDATE auctions SET start_time = ?, end_time = ?, status = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$start_time, $end_time, $new_status, $auction_id]);
                $success = "Auction time fixed successfully!";
                break;
                
            case 'fix_all_times':
                // Fix all auction times to be realistic
                $time_offsets = [0, 2, 6, 12, 24, 48]; // hours from now
                $query = "SELECT id FROM auctions ORDER BY id LIMIT 6";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $auction_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach($auction_ids as $index => $id) {
                    $offset = $time_offsets[$index] ?? ($index * 8);
                    $start_time = date('Y-m-d H:i:s', strtotime("+{$offset} hours"));
                    $end_time = date('Y-m-d H:i:s', strtotime("+{$offset} hours +2 hours"));
                    $status = $offset <= 0 ? 'active' : 'upcoming';
                    
                    $update_query = "UPDATE auctions SET start_time = ?, end_time = ?, status = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$start_time, $end_time, $status, $id]);
                }
                $success = "All auction times fixed successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all auctions
$query = "SELECT a.*, COUNT(ut.id) as participant_count, u.username as winner_username 
          FROM auctions a 
          LEFT JOIN user_tickets ut ON a.id = ut.auction_id 
          LEFT JOIN users u ON a.winner_id = u.id 
          GROUP BY a.id 
          ORDER BY a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Auctions - Admin - <?= SITE_NAME ?></title>
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
        /* Modal Styles */
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
            max-width: 600px; 
            width: 90%; 
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-radius: 8px;
            z-index: 100;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            margin-top: 4px;
        }

        .dropdown-content.show {
            display: block;
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            text-decoration: none;
            color: #374151;
            font-size: 14px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
            color: #111827;
        }

        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        /* Action button styles */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px -2px rgba(0, 0, 0, 0.1);
        }

        /* Table improvements */
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }

        /* Status indicators */
        .status-live {
            animation: livePulse 2s infinite;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            .modal-content {
                margin: 20px;
                padding: 20px;
            }
            
            .action-btn {
                width: 32px;
                height: 32px;
            }
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
                    <li class="text-gray-900">Manage Auctions</li>
                </ol>
            </nav>
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Manage Auctions</h1>
                    <p class="text-xl text-gray-600">View and manage all auctions</p>
                    <div class="text-sm text-gray-500 mt-2 flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        Current Time: <?= date('M j, Y H:i:s') ?> (<?= CURRENT_TIMEZONE ?>)
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <form method="POST" class="inline">
                        <button type="submit" name="action" value="fix_all_times" 
                                class="bg-gradient-to-r from-warning to-yellow-600 text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transition-all flex items-center"
                                onclick="return confirm('🚀 This will reset ALL auction times to realistic schedules.\n\n• First auction: LIVE now\n• Others: Spread over next 48 hours\n\nContinue?')">
                            <i class="fas fa-magic mr-2"></i>Fix All Times
                        </button>
                    </form>
                    <a href="add-auction.php" class="bg-gradient-to-r from-primary to-secondary text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transition-all flex items-center">
                        <i class="fas fa-plus mr-2"></i>Add New Auction
                    </a>
                </div>
            </div>
        </div>

        <!-- Auctions Table -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="table-container">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Auction</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Timing</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Tickets</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Participants</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900 min-w-[200px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($auctions as $auction): ?>
                            <?php 
                            $now = new DateTime();
                            $start = new DateTime($auction['start_time']);
                            $end = new DateTime($auction['end_time']);
                            $is_ended = $now > $end;
                            $is_active = $now >= $start && $now <= $end;
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <img src="<?= $auction['image_url'] ?>" alt="<?= htmlspecialchars($auction['title']) ?>" 
                                             class="w-16 h-16 object-cover rounded-lg mr-4 shadow-sm">
                                        <div>
                                            <div class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($auction['title']) ?></div>
                                            <div class="text-sm text-gray-600">Price: <?= formatPrice($auction['ticket_price']) ?></div>
                                            <?php if ($auction['guaranteed']): ?>
                                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mt-1 font-medium">
                                                    <i class="fas fa-shield-alt mr-1"></i>Guaranteed
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                     <?php
                                                     if ($is_ended) echo 'bg-gray-100 text-gray-800';
                                                     elseif ($is_active) echo 'bg-red-100 text-red-800 status-live';
                                                     elseif ($auction['status'] == 'upcoming') echo 'bg-blue-100 text-blue-800';
                                                     elseif ($auction['status'] == 'completed') echo 'bg-green-100 text-green-800';
                                                     elseif ($auction['status'] == 'cancelled') echo 'bg-gray-100 text-gray-800';
                                                     ?>">
                                            <i class="<?php
                                                if ($is_ended && $auction['status'] != 'completed') echo 'fas fa-exclamation-triangle mr-1';
                                                elseif ($is_active) echo 'fas fa-broadcast-tower mr-1';
                                                elseif ($auction['status'] == 'upcoming') echo 'fas fa-clock mr-1';
                                                elseif ($auction['status'] == 'completed') echo 'fas fa-check-circle mr-1';
                                                elseif ($auction['status'] == 'cancelled') echo 'fas fa-ban mr-1';
                                            ?>"></i>
                                            <?php
                                            if ($is_ended && $auction['status'] != 'completed') echo 'ENDED';
                                            elseif ($is_active) echo 'LIVE';
                                            else echo ucfirst($auction['status']);
                                            ?>
                                        </span>
                                        <?php if ($is_ended && $auction['status'] != 'completed'): ?>
                                            <span class="text-xs text-red-600 font-medium">⚠️ Needs completion</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="<?= $is_ended ? 'text-red-600' : ($is_active ? 'text-green-600' : '') ?>">
                                        <div class="flex items-center mb-1">
                                            <i class="fas fa-play text-xs mr-2 text-gray-400"></i>
                                            <?= date('M j, Y H:i', strtotime($auction['start_time'])) ?>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-stop text-xs mr-2 text-gray-400"></i>
                                            <?= date('M j, Y H:i', strtotime($auction['end_time'])) ?>
                                        </div>
                                        <?php if ($is_ended): ?>
                                            <div class="text-xs text-red-500 mt-1 font-medium">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Past due
                                            </div>
                                        <?php elseif ($is_active): ?>
                                            <div class="text-xs text-green-600 mt-1 font-medium status-live">
                                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>LIVE NOW
                                            </div>
                                        <?php else: ?>
                                            <div class="text-xs text-blue-600 mt-1">
                                                <?php
                                                $diff = $start->diff($now);
                                                if ($start > $now) {
                                                    echo "<i class='fas fa-hourglass-half mr-1'></i>Starts in: ";
                                                    if ($diff->d > 0) echo $diff->d . "d ";
                                                    if ($diff->h > 0) echo $diff->h . "h ";
                                                    echo $diff->i . "m";
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="space-y-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-ticket-alt text-green-500 text-xs mr-2"></i>
                                            <span>Sold: <?= number_format($auction['tickets_sold']) ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-layer-group text-gray-400 text-xs mr-2"></i>
                                            <span>Total: <?= number_format($auction['total_tickets']) ?></span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <i class="fas fa-percentage text-xs mr-1"></i>
                                            <?= number_format(($auction['tickets_sold'] / $auction['total_tickets']) * 100, 1) ?>% sold
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center">
                                        <i class="fas fa-users text-primary text-xs mr-2"></i>
                                        <span class="font-medium"><?= $auction['participant_count'] ?> users</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <!-- View Action -->
                                        <a href="<?= url('auctions/view.php?id=' . $auction['id']) ?>" 
                                           class="action-btn text-primary hover:bg-primary hover:text-white border-primary" 
                                           title="View Auction">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- Time Management -->
                                        <button onclick="openTimeModal(<?= $auction['id'] ?>, '<?= $auction['start_time'] ?>', '<?= $auction['end_time'] ?>', '<?= htmlspecialchars($auction['title'], ENT_QUOTES) ?>')" 
                                                class="action-btn text-warning hover:bg-warning hover:text-white border-warning" 
                                                title="Change Time">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        
                                        <!-- Quick Time Fixes Dropdown -->
                                        <div class="dropdown">
                                            <button onclick="toggleDropdown(<?= $auction['id'] ?>)" 
                                                    class="action-btn text-accent hover:bg-accent hover:text-white border-accent" 
                                                    title="Quick Time Fix">
                                                <i class="fas fa-magic"></i>
                                            </button>
                                            <div id="dropdown-<?= $auction['id'] ?>" class="dropdown-content">
                                                <form method="POST">
                                                    <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                    <input type="hidden" name="action" value="quick_time_fix">
                                                    <input type="hidden" name="duration_hours" value="2">
                                                    
                                                    <button type="submit" name="hours_from_now" value="0" class="dropdown-item">
                                                        <i class="fas fa-play text-red-500"></i>
                                                        Start Now (Live)
                                                    </button>
                                                    <button type="submit" name="hours_from_now" value="1" class="dropdown-item">
                                                        <i class="fas fa-clock text-blue-500"></i>
                                                        Start in 1 hour
                                                    </button>
                                                    <button type="submit" name="hours_from_now" value="4" class="dropdown-item">
                                                        <i class="fas fa-hourglass-half text-blue-500"></i>
                                                        Start in 4 hours
                                                    </button>
                                                    <button type="submit" name="hours_from_now" value="24" class="dropdown-item">
                                                        <i class="fas fa-calendar text-purple-500"></i>
                                                        Start tomorrow
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Status Actions -->
                                        <?php if ($auction['status'] == 'upcoming' && !$is_ended): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                <button type="submit" name="action" value="activate" 
                                                        class="action-btn text-success hover:bg-success hover:text-white border-success" 
                                                        title="Activate Auction">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (($auction['status'] == 'active' || $is_ended) && $auction['status'] != 'completed'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                <button type="submit" name="action" value="complete" 
                                                        class="action-btn text-success hover:bg-success hover:text-white border-success" 
                                                        title="Complete Auction">
                                                    <i class="fas fa-flag-checkered"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($auction['status'], ['upcoming', 'active'])): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('❌ Cancel this auction?\n\nThis will stop the auction and refund participants.')">
                                                <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                <button type="submit" name="action" value="cancel" 
                                                        class="action-btn text-danger hover:bg-danger hover:text-white border-danger" 
                                                        title="Cancel Auction">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Delete -->
                                        <form method="POST" class="inline" onsubmit="return confirm('🗑️ Delete this auction permanently?\n\n⚠️ This will also delete:\n• All bids\n• All tickets\n• All transactions\n\nThis action cannot be undone!')">
                                            <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                            <button type="submit" name="action" value="delete" 
                                                    class="action-btn text-danger hover:bg-danger hover:text-white border-danger" 
                                                    title="Delete Auction">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <?php if (empty($auctions)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <i class="fas fa-gavel text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-600 mb-4">No Auctions Found</h3>
                <p class="text-gray-500 mb-6">Get started by creating your first auction</p>
                <a href="add-auction.php" class="bg-gradient-to-r from-primary to-secondary text-white px-8 py-3 rounded-lg font-semibold hover:shadow-lg transition-all inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>Add New Auction
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Time Edit Modal -->
    <div id="timeModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-clock text-warning mr-3"></i>
                    Change Auction Time
                </h3>
                <button onclick="closeTimeModal()" class="text-gray-500 hover:text-gray-700 action-btn border-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="timeForm">
                <input type="hidden" name="action" value="update_time">
                <input type="hidden" name="auction_id" id="modal_auction_id">
                
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Auction Title</label>
                    <div id="modal_auction_title" class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-gavel text-primary mr-2"></i>
                        <span></span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-play text-green-500 mr-1"></i>Start Time
                        </label>
                        <input type="datetime-local" name="start_time" id="start_time" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all">
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-stop text-red-500 mr-1"></i>End Time
                        </label>
                        <input type="datetime-local" name="end_time" id="end_time" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all">
                    </div>
                </div>
                
                <div class="mb-8">
                    <label for="new_status" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-flag text-blue-500 mr-1"></i>Status
                    </label>
                    <select name="new_status" id="new_status" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all">
                        <option value="upcoming">🔵 Upcoming</option>
                        <option value="active">🔴 Active (Live)</option>
                        <option value="completed">🟢 Completed</option>
                        <option value="cancelled">⚫ Cancelled</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeTimeModal()" 
                            class="px-6 py-3 text-gray-600 hover:text-gray-800 font-medium transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-gradient-to-r from-primary to-secondary text-white px-8 py-3 rounded-lg font-semibold hover:shadow-lg transition-all flex items-center">
                        <i class="fas fa-save mr-2"></i>Update Time
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Modal functions
        function openTimeModal(auctionId, startTime, endTime, title) {
            document.getElementById('modal_auction_id').value = auctionId;
            document.getElementById('modal_auction_title').querySelector('span').textContent = title;
            
            // Convert MySQL datetime to HTML datetime-local format
            const start = new Date(startTime).toISOString().slice(0, 16);
            const end = new Date(endTime).toISOString().slice(0, 16);
            
            document.getElementById('start_time').value = start;
            document.getElementById('end_time').value = end;
            
            document.getElementById('timeModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function closeTimeModal() {
            document.getElementById('timeModal').classList.remove('active');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
        
        // Dropdown functions
        function toggleDropdown(auctionId) {
            const dropdown = document.getElementById(`dropdown-${auctionId}`);
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-content').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('timeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTimeModal();
            }
        });
        
        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTimeModal();
                // Close all dropdowns
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Auto-refresh for live status updates (every 30 seconds)
        setInterval(function() {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.active')) {
                location.reload();
            }
        }, 30000);
        
        console.log('🚀 Auction Management Panel loaded successfully!');
    </script>
</body>
</html>