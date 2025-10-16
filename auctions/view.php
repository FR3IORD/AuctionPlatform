<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/config.php';
$database = new Database();
$db = $database->getConnection();

$auction_id = $_GET['id'] ?? 0;

// Get auction details
$query = "SELECT a.*, u.username as winner_username FROM auctions a 
          LEFT JOIN users u ON a.winner_id = u.id 
          WHERE a.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auction) {
    header('Location: /auction-platform/auctions/');
    exit();
}

// Always refresh user's balance from DB for this request
$current_user_balance = 0;
if (isLoggedIn()) {
    $query = "SELECT balance FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $current_user_balance = (float)$stmt->fetchColumn();
    $_SESSION['user_balance'] = $current_user_balance;
}

// Get user's tickets for this auction (if logged in)
$user_tickets = null;
if (isLoggedIn()) {
    $query = "SELECT SUM(tickets_count) as total_tickets, SUM(total_bids) as total_bids, SUM(used_bids) as used_bids 
              FROM user_tickets WHERE user_id = ? AND auction_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $auction_id]);
    $user_tickets = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent bids, plus last bid id for polling
$query = "SELECT b.bid_time, b.id, u.username FROM bids b 
          JOIN users u ON b.user_id = u.id 
          WHERE b.auction_id = ? 
          ORDER BY b.bid_time DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$recent_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get last bid id (for polling)
$last_bid_id = 0;
if (!empty($recent_bids)) {
    $query = "SELECT id FROM bids WHERE auction_id = ? ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$auction_id]);
    $last_bid_id = (int)$stmt->fetchColumn();
}

// Calculate auction status
$now = new DateTime();
$start_time = new DateTime($auction['start_time']);
$end_time = new DateTime($auction['end_time']);
$is_active = $auction['status'] == 'active' && $now >= $start_time && $now <= $end_time;
$is_upcoming = $auction['status'] == 'upcoming' || $now < $start_time;
$is_ended = $auction['status'] == 'completed' || $now > $end_time;

// Handle ticket purchase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_tickets'])) {
    requireLogin();

    $tickets_to_buy = (int)$_POST['tickets_count'];
    $total_cost = $tickets_to_buy * $auction['ticket_price'];

    // Always get fresh balance before purchase
    $query = "SELECT balance FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $current_user_balance = (float)$stmt->fetchColumn();

    if ($tickets_to_buy < 1) {
        $error = 'Please select at least 1 ticket';
    } elseif ($current_user_balance < $total_cost) {
        $error = 'Insufficient balance. Please add funds to your account.';
    } elseif ($auction['tickets_sold'] + $tickets_to_buy > $auction['total_tickets']) {
        $error = 'Not enough tickets available';
    } else {
        try {
            $db->beginTransaction();

            // Deduct from user balance
            $new_balance = $current_user_balance - $total_cost;
            $update_balance_query = "UPDATE users SET balance = ? WHERE id = ?";
            $stmt = $db->prepare($update_balance_query);
            $stmt->execute([$new_balance, $_SESSION['user_id']]);
            $current_user_balance = $new_balance;
            $_SESSION['user_balance'] = $new_balance;

            // Add tickets to user
            $total_bids = $tickets_to_buy * $auction['bids_per_ticket'];
            $query = "INSERT INTO user_tickets (user_id, auction_id, tickets_count, total_bids) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $auction_id, $tickets_to_buy, $total_bids]);

            // Update auction tickets sold
            $query = "UPDATE auctions SET tickets_sold = tickets_sold + ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$tickets_to_buy, $auction_id]);

            // Record transaction
            $query = "INSERT INTO transactions (user_id, auction_id, type, amount, status, description) VALUES (?, ?, 'ticket_purchase', ?, 'completed', ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $auction_id, $total_cost, "Purchased $tickets_to_buy tickets for " . $auction['title']]);

            $db->commit();

            // REDIRECT (PRG pattern)
            header("Location: view.php?id=$auction_id&success=" . urlencode("Successfully purchased $tickets_to_buy tickets! You received $total_bids bids."));
            exit;

        } catch (Exception $e) {
            $db->rollback();
            $error = 'Purchase failed. Please try again.';
        }
    }
}

// Handle bidding - redirect to game-mode.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_bid']) && $is_active) {
    requireLogin();

    // Check if user has available bids
    if (!$user_tickets || $user_tickets['used_bids'] >= $user_tickets['total_bids']) {
        $bid_error = "You don't have any bids available. Please purchase tickets first.";
    } else {
        try {
            $db->beginTransaction();

            // Insert new bid
            $query = "INSERT INTO bids (auction_id, user_id, bid_time) VALUES (?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([$auction_id, $_SESSION['user_id']]);

            // Update used bids count
            $query = "UPDATE user_tickets SET used_bids = used_bids + 1 WHERE user_id = ? AND auction_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $auction_id]);

            $db->commit();

            // Redirect to game mode
            header("Location: game-mode.php?id=$auction_id");
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $bid_error = "Failed to place bid. Please try again.";
        }
    }
}

$progress_percentage = $auction['total_tickets'] > 0 ? ($auction['tickets_sold'] / $auction['total_tickets']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($auction['title']) ?> - <?= SITE_NAME ?></title>
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
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li><a href="/auction-platform/" class="hover:text-primary">Home</a></li>
                <li><i class="fas fa-chevron-right"></i></li>
                <li><a href="/auction-platform/auctions/" class="hover:text-primary">Auctions</a></li>
                <li><i class="fas fa-chevron-right"></i></li>
                <li class="text-gray-900"><?= htmlspecialchars($auction['title']) ?></li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Auction Image and Info -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-8">
                    <!-- Status Badge -->
                    <div class="relative">
                        <img src="<?= $auction['image_url'] ?>" alt="<?= htmlspecialchars($auction['title']) ?>" 
                             class="w-full h-96 object-cover">
                        
                        <?php if ($is_active): ?>
                            <div class="absolute top-4 left-4">
                                <span class="bg-red-500 text-white px-4 py-2 rounded-full text-sm font-semibold animate-pulse">
                                    🔴 LIVE AUCTION
                                </span>
                            </div>
                        <?php elseif ($auction['guaranteed']): ?>
                            <div class="absolute top-4 left-4">
                                <span class="bg-green-500 text-white px-4 py-2 rounded-full text-sm font-semibold">
                                    100% Guaranteed
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-8">
                        <h1 class="text-3xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($auction['title']) ?></h1>
                        
                        <?php if ($auction['description']): ?>
                            <p class="text-gray-600 leading-relaxed mb-6"><?= nl2br(htmlspecialchars($auction['description'])) ?></p>
                        <?php endif; ?>

                        <!-- Auction Details -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-clock text-primary text-2xl mb-2"></i>
                                <div class="text-sm text-gray-600">Start Time</div>
                                <div class="font-semibold"><?= date('M j, H:i', strtotime($auction['start_time'])) ?></div>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-flag-checkered text-secondary text-2xl mb-2"></i>
                                <div class="text-sm text-gray-600">End Time</div>
                                <div class="font-semibold"><?= date('M j, H:i', strtotime($auction['end_time'])) ?></div>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-ticket-alt text-accent text-2xl mb-2"></i>
                                <div class="text-sm text-gray-600">Ticket Price</div>
                                <div class="font-semibold"><?= formatPrice($auction['ticket_price']) ?></div>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-crosshairs text-warning text-2xl mb-2"></i>
                                <div class="text-sm text-gray-600">Bids per Ticket</div>
                                <div class="font-semibold"><?= $auction['bids_per_ticket'] ?></div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-6">
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span>Tickets Sold: <?= number_format($auction['tickets_sold']) ?></span>
                                <span>Available: <?= number_format($auction['total_tickets'] - $auction['tickets_sold']) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-4">
                                <div class="bg-gradient-to-r from-primary via-secondary to-accent h-4 rounded-full transition-all duration-500" 
                                     style="width: <?= min($progress_percentage, 100) ?>%"></div>
                            </div>
                            <div class="text-center mt-2 text-sm text-gray-600">
                                <?= number_format($progress_percentage, 1) ?>% sold
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bids -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-history mr-2 text-primary"></i>
                        Recent Bids
                    </h3>
                    
                    <?php if (empty($recent_bids)): ?>
                        <p class="text-gray-500 text-center py-8">No bids placed yet. Be the first!</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach($recent_bids as $bid): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-circle text-primary text-xl mr-3"></i>
                                        <span class="font-medium"><?= htmlspecialchars($bid['username']) ?></span>
                                    </div>
                                    <span class="text-sm text-gray-500"><?= timeAgo($bid['bid_time']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Countdown Timer -->
                <?php if ($is_active || $is_upcoming): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6" data-end-time="<?= $auction['end_time'] ?>">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 text-center">
                        <?= $is_active ? 'Auction Ends In' : 'Auction Starts In' ?>
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4 text-center countdown-timer">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-primary countdown-days">0</div>
                            <div class="text-sm text-gray-600">Days</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-primary countdown-hours">0</div>
                            <div class="text-sm text-gray-600">Hours</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-primary countdown-minutes">0</div>
                            <div class="text-sm text-gray-600">Minutes</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-primary countdown-seconds">0</div>
                            <div class="text-sm text-gray-600">Seconds</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User's Tickets -->
                <?php if (isLoggedIn() && $user_tickets && $user_tickets['total_tickets'] > 0): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-ticket-alt mr-2 text-success"></i>
                        Your Tickets
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span>Tickets Owned:</span>
                            <span class="font-semibold"><?= $user_tickets['total_tickets'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total Bids:</span>
                            <span class="font-semibold"><?= $user_tickets['total_bids'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Used Bids:</span>
                            <span class="font-semibold text-danger"><?= $user_tickets['used_bids'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Available Bids:</span>
                            <span class="font-semibold text-success"><?= $user_tickets['total_bids'] - $user_tickets['used_bids'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bidding Section -->
                <?php if ($is_active && isLoggedIn()): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-crosshairs mr-2 text-danger"></i>
                        Place Bid
                    </h3>
                    
                    <?php if (isset($bid_error)): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?= htmlspecialchars($bid_error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?= htmlspecialchars($_GET['success']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="view.php?id=<?= $auction_id ?>">
                        <button type="submit" name="place_bid" 
                                class="w-full bg-gradient-to-r from-danger to-red-600 text-white py-4 rounded-lg font-bold text-lg hover:shadow-lg transition-all"
                                <?= !$user_tickets || $user_tickets['used_bids'] >= $user_tickets['total_bids'] ? 'disabled' : '' ?>>
                            <i class="fas fa-crosshairs mr-2"></i>
                            PLACE BID
                        </button>
                    </form>
                    
                    <p class="text-sm text-gray-500 text-center mt-2">
                        Strategic timing is key to winning!
                    </p>
                </div>
                <?php endif; ?>

                <!-- Purchase Tickets -->
                <?php if (!$is_ended && isLoggedIn()): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-shopping-cart mr-2 text-primary"></i>
                        Buy Tickets
                    </h3>
                    
                    <?php if (isset($error)): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label for="tickets_count" class="block text-sm font-medium text-gray-700 mb-2">Number of Tickets</label>
                            <input type="number" id="tickets_count" name="tickets_count" min="1" value="1" 
                                   max="<?= $auction['total_tickets'] - $auction['tickets_sold'] ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                                   onchange="updateTotal()">
                        </div>
                        
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="flex justify-between text-sm">
                                <span>Price per ticket:</span>
                                <span><?= formatPrice($auction['ticket_price']) ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Bids per ticket:</span>
                                <span><?= $auction['bids_per_ticket'] ?></span>
                            </div>
                            <hr class="my-2">
                            <div class="flex justify-between font-semibold">
                                <span>Total cost:</span>
                                <span id="total_cost"><?= formatPrice($auction['ticket_price']) ?></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Total bids you'll get:</span>
                                <span id="total_bids"><?= $auction['bids_per_ticket'] ?></span>
                            </div>
                        </div>
                        
                        <div class="mb-4 text-sm text-gray-600">
                            Your balance: <span class="font-semibold"><?= formatPrice($current_user_balance) ?></span>
                        </div>
                        
                        <button type="submit" name="buy_tickets" 
                                class="w-full bg-gradient-to-r from-primary to-secondary text-white py-3 rounded-lg font-semibold hover:shadow-lg transition-all">
                            <i class="fas fa-ticket-alt mr-2"></i>
                            Purchase Tickets
                        </button>
                    </form>
                    
                    <?php if ($current_user_balance < $auction['ticket_price']): ?>
                        <div class="mt-4 text-center">
                            <a href="/auction-platform/account/add-funds.php" class="text-primary hover:text-secondary font-medium">
                                <i class="fas fa-plus-circle mr-1"></i>
                                Add Funds to Account
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php elseif (!isLoggedIn()): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                    <i class="fas fa-sign-in-alt text-primary text-4xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Login Required</h3>
                    <p class="text-gray-600 mb-4">You need to login to participate in auctions</p>
                    <a href="/auction-platform/auth/login.php" class="bg-gradient-to-r from-primary to-secondary text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transition-all">
                        Login Now
                    </a>
                </div>
                <?php endif; ?>

                <!-- Winner Info -->
                <?php if ($is_ended && $auction['winner_id']): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                    <i class="fas fa-trophy text-warning text-4xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Winner</h3>
                    <p class="text-lg font-semibold text-primary"><?= htmlspecialchars($auction['winner_username']) ?></p>
                    <p class="text-sm text-gray-600 mt-2">Congratulations!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        function updateTotal() {
            const ticketsCount = document.getElementById('tickets_count').value;
            const ticketPrice = <?= $auction['ticket_price'] ?>;
            const bidsPerTicket = <?= $auction['bids_per_ticket'] ?>;
            
            const totalCost = ticketsCount * ticketPrice;
            const totalBids = ticketsCount * bidsPerTicket;
            
            document.getElementById('total_cost').textContent = '$' + totalCost.toFixed(2);
            document.getElementById('total_bids').textContent = totalBids;
        }
    </script>
    <script>
    // Poll for new bids: if detected, redirect to game-mode.php
    var lastBidId = <?= $last_bid_id ?>;
    var auctionId = <?= intval($auction_id) ?>;
    function pollForBidChange() {
        fetch("bids-poll.php?auction_id=" + auctionId + "&t=" + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.last_bid_id && data.last_bid_id != lastBidId) {
                    window.location.href = "game-mode.php?id=" + auctionId;
                }
            });
    }
    setInterval(pollForBidChange, 2000);
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>