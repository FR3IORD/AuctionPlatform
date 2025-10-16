<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$database = new Database();
$db = $database->getConnection();

$auction_id = $_GET['id'] ?? $_GET['auction_id'] ?? 0;
$current_user_id = $_SESSION['user_id'] ?? null;

// Get auction details
$query = "SELECT * FROM auctions WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auction) {
    header('Location: /auction-platform/auctions/');
    exit();
}

// Get user's current balance
$query = "SELECT balance FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_user_id]);
$user_balance = $stmt->fetchColumn() ?: 0;

// Get latest bidder info
$query = "SELECT u.username, u.first_name, u.last_name, b.bid_time, u.id as user_id 
          FROM bids b 
          JOIN users u ON b.user_id = u.id 
          WHERE b.auction_id = ? 
          ORDER BY b.bid_time DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$latest_bid = $stmt->fetch(PDO::FETCH_ASSOC);

// Get top 10 bidders (leaderboard)
$query = "SELECT u.username, u.first_name, u.last_name, COUNT(b.id) as bid_count, 
          MAX(b.bid_time) as last_bid_time, u.id as user_id
          FROM bids b 
          JOIN users u ON b.user_id = u.id 
          WHERE b.auction_id = ? 
          GROUP BY u.id 
          ORDER BY bid_count DESC, last_bid_time DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent bids for this auction (last 15)
$query = "SELECT u.username, u.first_name, u.last_name, b.bid_time, u.id as user_id, b.id as bid_id
          FROM bids b 
          JOIN users u ON b.user_id = u.id 
          WHERE b.auction_id = ? 
          ORDER BY b.bid_time DESC LIMIT 15";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$recent_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total bids count for this auction
$query = "SELECT COUNT(*) FROM bids WHERE auction_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$total_bids = $stmt->fetchColumn();

// Get online users count (estimate)
$query = "SELECT COUNT(DISTINCT user_id) FROM bids WHERE auction_id = ? AND bid_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$stmt = $db->prepare($query);
$stmt->execute([$auction_id]);
$online_users = $stmt->fetchColumn();

// Get user's tickets for this auction
$query = "SELECT tickets_count, used_bids, total_bids FROM user_tickets WHERE user_id = ? AND auction_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_user_id, $auction_id]);
$user_tickets = $stmt->fetch(PDO::FETCH_ASSOC);

$can_bid = $user_tickets && ($user_tickets['used_bids'] < $user_tickets['total_bids']);
$remaining_bids = $user_tickets ? ($user_tickets['total_bids'] - $user_tickets['used_bids']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>🎮 LIVE GAME MODE - <?php echo htmlspecialchars($auction['title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            font-size: 14px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .primary-button {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .primary-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }

        .success-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .success-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -3px rgba(16, 185, 129, 0.4);
        }

        .countdown-circle {
            background: conic-gradient(#3b82f6 var(--progress, 0), #e5e7eb 0);
            border-radius: 50%;
            position: relative;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.2);
        }

        .countdown-circle::before {
            content: '';
            position: absolute;
            inset: 8px;
            background: white;
            border-radius: 50%;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .slide-up {
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .leaderboard-item {
            transition: all 0.2s ease;
            border-radius: 8px;
        }

        .leaderboard-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .bid-item {
            transition: all 0.2s ease;
        }

        .bid-item:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .new-bid-animation {
            animation: newBidPulse 0.8s ease-out;
        }

        @keyframes newBidPulse {
            0% { 
                transform: scale(0.95) translateX(-20px); 
                background-color: #dbeafe; 
                opacity: 0; 
            }
            50% { 
                transform: scale(1.02) translateX(0); 
                background-color: #bfdbfe; 
                opacity: 1; 
            }
            100% { 
                transform: scale(1) translateX(0); 
                background-color: #f9fafb; 
                opacity: 1; 
            }
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            border-radius: 8px;
        }

        .stat-card:hover {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            transform: translateY(-1px);
        }

        .winner-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: 2px solid #3b82f6;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }

        .loser-card {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }

        .text-muted { color: #64748b; }
        .text-primary { color: #1e293b; }
        .text-blue { color: #3b82f6; }
        .text-green { color: #10b981; }
        .text-red { color: #ef4444; }
        .text-orange { color: #f59e0b; }
        .text-purple { color: #8b5cf6; }

        .scrollbar-thin {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 2px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

        .notification {
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .trophy-animation {
            animation: bounce 1s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #3b82f6;
            animation: confetti-fall 3s linear infinite;
        }

        @keyframes confetti-fall {
            to { transform: translateY(100vh) rotate(360deg); }
        }

        .glow-effect {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
            to { box-shadow: 0 0 30px rgba(59, 130, 246, 0.8); }
        }

        @media (max-width: 768px) {
            .grid-container { grid-template-columns: 1fr; }
            body { font-size: 13px; }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50 backdrop-blur-sm bg-white/95">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Back Button -->
                <a href="/auction-platform/auctions/" class="text-muted hover:text-blue transition-colors text-sm flex items-center group">
                    <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                    <span class="hidden sm:inline">Back to Auctions</span>
                </a>

                <!-- Title -->
                <div class="text-center">
                    <h1 class="text-xl sm:text-2xl font-bold text-primary flex items-center justify-center">
                        🎮 <span class="text-blue ml-2">LIVE GAME MODE</span>
                    </h1>
                    <div class="flex items-center justify-center text-xs text-muted mt-1">
                        <span class="live-indicator"></span>
                        <span id="online-count"><?php echo $online_users; ?> players online</span>
                    </div>
                </div>

                <!-- User Balance -->
                <div class="text-right text-sm">
                    <div class="text-muted">Balance</div>
                    <div class="text-green font-semibold" id="user-balance">$<?php echo number_format($user_balance, 2); ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 grid-container">
            
            <!-- Left Sidebar - Auction Info & Leaderboard -->
            <div class="lg:col-span-3 space-y-4">
                <!-- Auction Info Card -->
                <div class="card p-4 slide-up">
                    <div class="flex items-center space-x-3 mb-4">
                        <img src="<?php echo $auction['image_url'] ? $auction['image_url'] : asset('images/no-image.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                             class="w-12 h-12 rounded-lg object-cover shadow-sm">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-primary font-semibold text-sm truncate"><?php echo htmlspecialchars($auction['title']); ?></h3>
                            <div class="text-blue text-xs">ID: #<?php echo $auction['id']; ?></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="stat-card p-3 text-center">
                            <div class="text-blue font-bold text-lg" id="total-bids"><?php echo number_format($total_bids); ?></div>
                            <div class="text-muted text-xs">Total Bids</div>
                        </div>
                        <div class="stat-card p-3 text-center">
                            <div class="text-green font-bold text-lg" id="remaining-bids"><?php echo $remaining_bids; ?></div>
                            <div class="text-muted text-xs">Your Bids</div>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard -->
                <div class="card p-4 slide-up">
                    <h3 class="text-primary font-semibold text-sm mb-4 flex items-center">
                        <i class="fas fa-trophy text-blue mr-2"></i>
                        TOP BIDDERS
                    </h3>
                    
                    <div id="leaderboard" class="space-y-2 max-h-80 overflow-y-auto scrollbar-thin">
                        <!-- Leaderboard will be updated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Center - Main Game Area -->
            <div class="lg:col-span-6">
                <!-- Countdown Timer -->
                <div class="card p-8 text-center mb-6 slide-up">
                    <div class="relative">
                        <div id="countdown-circle" class="countdown-circle w-32 h-32 mx-auto mb-6" style="--progress: 0deg;">
                            <div class="absolute inset-0 flex items-center justify-center z-10">
                                <span id="countdown-value" class="text-4xl font-bold text-blue">10</span>
                            </div>
                        </div>
                        <div id="timer-status" class="text-primary text-lg font-semibold mb-3">Waiting for bids...</div>
                        <div id="last-bid-info" class="text-muted text-sm">
                            <?php if ($latest_bid): ?>
                                Last bid by <span class="text-blue font-medium">@<?php echo htmlspecialchars($latest_bid['username']); ?></span>
                            <?php else: ?>
                                <span>No bids yet - be the first!</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bid Button -->
                <div class="mb-6 slide-up">
                    <?php if ($can_bid): ?>
                        <form method="POST" action="../api/place-bid.php" id="bidForm" class="w-full">
                            <input type="hidden" name="auction_id" value="<?php echo $auction_id; ?>">
                            <button type="submit" 
                                    class="w-full success-button py-6 px-8 text-xl font-bold shadow-lg glow-effect"
                                    id="placeBidBtn">
                                <i class="fas fa-crosshairs mr-3"></i>
                                PLACE BID
                                <div class="text-sm opacity-90 mt-1" id="bid-count-text"><?php echo $remaining_bids; ?> bids remaining</div>
                            </button>
                        </form>
                    <?php elseif ($user_tickets): ?>
                        <div class="card p-6 text-center">
                            <i class="fas fa-exclamation-triangle text-red text-3xl mb-3"></i>
                            <h3 class="text-primary text-lg font-semibold mb-2">No Bids Remaining</h3>
                            <p class="text-muted text-sm">You've used all your bids for this auction</p>
                        </div>
                    <?php else: ?>
                        <div class="card p-6 text-center">
                            <i class="fas fa-ticket-alt text-blue text-3xl mb-3"></i>
                            <h3 class="text-primary text-lg font-semibold mb-2">Purchase Tickets</h3>
                            <p class="text-muted text-sm mb-4">You need tickets to participate in this auction</p>
                            <a href="/auction-platform/auctions/view.php?id=<?php echo $auction_id; ?>" 
                               class="primary-button inline-block py-3 px-6 text-sm font-semibold">
                                <i class="fas fa-shopping-cart mr-2"></i>
                                Buy Tickets Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Winner/Result Area -->
                <div id="winner-announce" class="hidden slide-up"></div>
            </div>

            <!-- Right Sidebar - Live Feed & Stats -->
            <div class="lg:col-span-3 space-y-4">
                <!-- Live Activity Feed -->
                <div class="card p-4 slide-up">
                    <h3 class="text-red font-semibold text-sm mb-4 flex items-center">
                        <span class="live-indicator"></span>
                        LIVE FEED
                    </h3>
                    
                    <div id="bid-feed" class="space-y-2 max-h-96 overflow-y-auto scrollbar-thin">
                        <?php if (!empty($recent_bids)): ?>
                            <?php foreach($recent_bids as $bid): ?>
                                <div class="bid-item flex items-center space-x-3 p-3 bg-gray-50 rounded-lg border border-gray-100" data-bid-id="<?php echo $bid['bid_id']; ?>">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center shadow-sm">
                                        <i class="fas fa-gavel text-white text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-primary text-sm font-medium truncate">
                                            @<?php echo htmlspecialchars($bid['username']); ?>
                                        </div>
                                        <div class="text-muted text-xs">
                                            <?php echo date('H:i:s', strtotime($bid['bid_time'])); ?>
                                        </div>
                                    </div>
                                    <div class="text-green text-xs font-semibold bg-green-50 px-2 py-1 rounded">
                                        BID
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-8">
                                <i class="fas fa-comments text-3xl mb-3 opacity-30"></i>
                                <p class="text-sm">No activity yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card p-4 slide-up">
                    <h3 class="text-blue font-semibold text-sm mb-4">
                        <i class="fas fa-chart-line mr-2"></i>
                        QUICK STATS
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-muted text-sm">Ticket Price</span>
                            <span class="text-green font-semibold">$<?php echo number_format($auction['ticket_price'], 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-muted text-sm">Total Tickets</span>
                            <span class="text-blue font-semibold"><?php echo number_format($auction['total_tickets']); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-muted text-sm">Online Players</span>
                            <span class="text-purple font-semibold" id="online-players"><?php echo $online_users; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-muted text-sm">Your Position</span>
                            <span class="text-red font-semibold" id="user-position">N/A</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card p-4 slide-up">
                    <h3 class="text-primary font-semibold text-sm mb-4">
                        <i class="fas fa-bolt mr-2"></i>
                        QUICK ACTIONS
                    </h3>
                    
                    <div class="space-y-2">
                        <a href="/auction-platform/auctions/view.php?id=<?php echo $auction_id; ?>" 
                           class="primary-button block w-full py-3 px-4 text-center text-sm font-medium">
                            <i class="fas fa-eye mr-2"></i>
                            View Details
                        </a>
                        
                        <a href="/auction-platform/auctions/" 
                           class="block w-full py-3 px-4 text-center text-sm font-medium bg-gray-100 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-200 transition-colors">
                            <i class="fas fa-list mr-2"></i>
                            Browse Auctions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification System -->
    <div id="notification-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        // Configuration
        const CONFIG = {
            AUCTION_ID: <?php echo $auction_id; ?>,
            USER_ID: <?php echo $current_user_id; ?>,
            POLL_INTERVAL: 1000, // 1 second for countdown
            DATA_UPDATE_INTERVAL: 3000, // 3 seconds for feed/stats
            COUNTDOWN_DURATION: 10
        };

        // State management
        let gameState = {
            winnerDeclared: false,
            lastBidId: null,
            lastUpdateTime: 0,
            totalBids: <?php echo $total_bids; ?>,
            userRemainingBids: <?php echo $remaining_bids; ?>,
            currentBidIds: new Set(<?php echo json_encode(array_column($recent_bids, 'bid_id')); ?>),
            lastFeedUpdate: 0
        };

        // DOM elements
        const elements = {
            countdownValue: document.getElementById('countdown-value'),
            countdownCircle: document.getElementById('countdown-circle'),
            timerStatus: document.getElementById('timer-status'),
            lastBidInfo: document.getElementById('last-bid-info'),
            placeBidBtn: document.getElementById('placeBidBtn'),
            winnerAnnounce: document.getElementById('winner-announce'),
            bidForm: document.getElementById('bidForm'),
            bidFeed: document.getElementById('bid-feed'),
            leaderboard: document.getElementById('leaderboard'),
            notificationContainer: document.getElementById('notification-container'),
            totalBids: document.getElementById('total-bids'),
            remainingBids: document.getElementById('remaining-bids'),
            bidCountText: document.getElementById('bid-count-text'),
            onlineCount: document.getElementById('online-count'),
            onlinePlayers: document.getElementById('online-players'),
            userPosition: document.getElementById('user-position'),
            userBalance: document.getElementById('user-balance')
        };

        // Utility functions
        function parseNumber(value) {
            if (typeof value === 'number') return value;
            if (typeof value === 'string') return parseFloat(value) || 0;
            return 0;
        }

        function formatCurrency(value) {
            const num = parseNumber(value);
            return '$' + num.toFixed(2);
        }

        // Create confetti effect
        function createConfetti() {
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.backgroundColor = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'][Math.floor(Math.random() * 4)];
                document.body.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 3000);
            }
        }

        // Notification system
        function showNotification(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            const bgColor = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-orange-500',
                'info': 'bg-blue-500'
            }[type] || 'bg-blue-500';

            notification.className = `notification ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg max-w-xs`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-white/80 hover:text-white">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            `;

            elements.notificationContainer.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }

        // Countdown management
        function updateCountdown(seconds) {
            const progress = (seconds / CONFIG.COUNTDOWN_DURATION) * 360;
            elements.countdownValue.textContent = Math.max(0, seconds);
            elements.countdownCircle.style.setProperty('--progress', `${progress}deg`);

            if (seconds > 0) {
                elements.timerStatus.innerHTML = `<i class="fas fa-crosshairs mr-2"></i>PLACE YOUR BID NOW!`;
                if (elements.placeBidBtn && gameState.userRemainingBids > 0) {
                    elements.placeBidBtn.style.display = '';
                    elements.placeBidBtn.disabled = false;
                }
            } else {
                elements.timerStatus.innerHTML = `<i class="fas fa-hourglass-end mr-2"></i>CHECKING WINNER...`;
                if (elements.placeBidBtn) {
                    elements.placeBidBtn.style.display = 'none';
                }
            }
        }

        // Update last bidder info
        function updateLastBidInfo(username) {
            if (username) {
                elements.lastBidInfo.innerHTML = `Last bid by <span class="text-blue font-medium">@${username}</span>`;
            } else {
                elements.lastBidInfo.innerHTML = `<span class="text-muted">No bids yet - be the first!</span>`;
            }
        }

        // Update leaderboard (only when data changes)
        function updateLeaderboard(leaderboard) {
            if (!leaderboard || leaderboard.length === 0) {
                elements.leaderboard.innerHTML = `
                    <div class="text-center text-muted py-8">
                        <i class="fas fa-trophy text-3xl mb-3 opacity-30"></i>
                        <p class="text-sm">No bids yet</p>
                    </div>
                `;
                return;
            }

            const leaderboardHtml = leaderboard.map((bidder, index) => {
                const isCurrentUser = parseInt(bidder.user_id) === CONFIG.USER_ID;
                const badgeClass = index === 0 ? 'bg-blue-500' : 
                                 index === 1 ? 'bg-blue-400' : 
                                 index === 2 ? 'bg-blue-300' : 'bg-gray-400';

                // Update user position
                if (isCurrentUser) {
                    elements.userPosition.textContent = `#${index + 1}`;
                }

                return `
                    <div class="leaderboard-item flex items-center space-x-3 p-3 border ${isCurrentUser ? 'bg-blue-50 border-blue-200' : 'border-gray-100'}">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white ${badgeClass}">
                            ${index + 1}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-primary text-sm font-medium truncate">
                                ${bidder.first_name} ${bidder.last_name}
                            </div>
                            <div class="text-muted text-xs">@${bidder.username}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-blue font-bold text-sm">${bidder.bid_count}</div>
                            <div class="text-muted text-xs">bids</div>
                        </div>
                    </div>
                `;
            }).join('');

            elements.leaderboard.innerHTML = leaderboardHtml;
        }

        // Smart bid feed update (only add new bids)
        function updateBidFeed(newBids) {
            if (!newBids || newBids.length === 0) {
                return;
            }

            // Check for truly new bids
            const reallyNewBids = newBids.filter(bid => !gameState.currentBidIds.has(parseInt(bid.bid_id)));
            
            if (reallyNewBids.length === 0) {
                return; // No new bids, don't update
            }

            // Add new bid IDs to our tracking set
            reallyNewBids.forEach(bid => {
                gameState.currentBidIds.add(parseInt(bid.bid_id));
            });

            // Only update if we have actual new bids
            reallyNewBids.forEach(bid => {
                const newBidElement = document.createElement('div');
                newBidElement.className = 'bid-item flex items-center space-x-3 p-3 bg-gray-50 rounded-lg border border-gray-100 new-bid-animation';
                newBidElement.setAttribute('data-bid-id', bid.bid_id);
                newBidElement.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center shadow-sm">
                        <i class="fas fa-gavel text-white text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-primary text-sm font-medium truncate">
                            @${bid.username}
                        </div>
                        <div class="text-muted text-xs">
                            ${new Date(bid.bid_time).toLocaleTimeString()}
                        </div>
                    </div>
                    <div class="text-green text-xs font-semibold bg-green-50 px-2 py-1 rounded">
                        BID
                    </div>
                `;

                // Insert at the top
                elements.bidFeed.insertBefore(newBidElement, elements.bidFeed.firstChild);
            });

            // Remove old bids to keep list manageable (keep only last 15)
            const bidItems = elements.bidFeed.querySelectorAll('.bid-item');
            if (bidItems.length > 15) {
                for (let i = 15; i < bidItems.length; i++) {
                    const bidId = parseInt(bidItems[i].getAttribute('data-bid-id'));
                    gameState.currentBidIds.delete(bidId);
                    bidItems[i].remove();
                }
            }
        }

        // Update stats
        function updateStats(data) {
            try {
                if (data.total_bids !== undefined && data.total_bids !== gameState.totalBids) {
                    elements.totalBids.textContent = data.total_bids.toLocaleString();
                    gameState.totalBids = data.total_bids;
                }

                if (data.online_users !== undefined) {
                    elements.onlineCount.textContent = `${data.online_users} players online`;
                    elements.onlinePlayers.textContent = data.online_users;
                }

                if (data.user_remaining_bids !== undefined && data.user_remaining_bids !== gameState.userRemainingBids) {
                    elements.remainingBids.textContent = data.user_remaining_bids;
                    if (elements.bidCountText) {
                        elements.bidCountText.textContent = `${data.user_remaining_bids} bids remaining`;
                    }
                    gameState.userRemainingBids = data.user_remaining_bids;

                    // Hide bid button if no bids remaining
                    if (elements.placeBidBtn && data.user_remaining_bids <= 0) {
                        elements.placeBidBtn.style.display = 'none';
                    }
                }

                if (data.user_balance !== undefined) {
                    elements.userBalance.textContent = formatCurrency(data.user_balance);
                }
            } catch (error) {
                console.error('Error updating stats:', error);
            }
        }

        // Winner declaration
        function declareWinner(data) {
            gameState.winnerDeclared = true;
            updateCountdown(0);
            
            const isWinner = data.winner_id && CONFIG.USER_ID && parseInt(data.winner_id) === parseInt(CONFIG.USER_ID);
            
            elements.winnerAnnounce.classList.remove('hidden');
            
            if (isWinner) {
                createConfetti();
                elements.winnerAnnounce.innerHTML = `
                    <div class="winner-card rounded-lg p-8 text-center shadow-xl relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 to-blue-600/20"></div>
                        <div class="relative z-10">
                            <div class="text-4xl mb-4 trophy-animation">🏆</div>
                            <h2 class="text-3xl font-bold mb-3">VICTORY!</h2>
                            <p class="text-lg font-medium mb-4 opacity-90">Congratulations! You won this auction!</p>
                            <div class="text-sm mb-6 opacity-80">
                                Auction: <?php echo htmlspecialchars($auction['title']); ?>
                            </div>
                            <a href="/auction-platform/account/my-prize.php?auction_id=<?php echo $auction_id; ?>"
                               class="inline-block bg-white text-blue-600 font-bold px-8 py-4 rounded-lg text-lg border-2 border-white hover:bg-blue-50 transition-all shadow-lg">
                                <i class="fas fa-gift mr-2"></i>
                                CLAIM YOUR PRIZE
                            </a>
                        </div>
                    </div>
                `;
            } else {
                elements.winnerAnnounce.innerHTML = `
                    <div class="loser-card rounded-lg p-8 text-center shadow-xl">
                        <div class="text-4xl mb-4">😔</div>
                        <h2 class="text-2xl font-bold mb-3">Game Over</h2>
                        <p class="text-lg mb-4 opacity-90">Better luck next time!</p>
                        <div class="text-sm mb-6 opacity-80">
                            Winner: <span class="font-medium">${data.winner || "Unknown"}</span>
                        </div>
                        <a href="/auction-platform/auctions/"
                           class="inline-block bg-white/20 text-white font-bold px-8 py-4 rounded-lg text-lg border-2 border-white/30 hover:bg-white/30 transition-all">
                            <i class="fas fa-redo mr-2"></i>
                            PLAY AGAIN
                        </a>
                    </div>
                `;
            }
        }

        // Bid form handling
        function initializeBidForm() {
            if (!elements.bidForm) return;

            elements.bidForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const submitButton = elements.placeBidBtn;
                const originalHtml = submitButton.innerHTML;
                
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>PLACING BID...';
                submitButton.classList.remove('success-button', 'glow-effect');
                submitButton.classList.add('bg-gray-400');
                
                try {
                    const formData = new FormData(elements.bidForm);
                    const response = await fetch(elements.bidForm.action, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification('✅ Bid placed successfully!', 'success');
                        
                        // Update local state immediately
                        gameState.userRemainingBids = result.remaining_bids || (gameState.userRemainingBids - 1);
                        gameState.totalBids++;
                        
                        // Update UI immediately
                        elements.remainingBids.textContent = gameState.userRemainingBids;
                        elements.totalBids.textContent = gameState.totalBids.toLocaleString();
                        if (elements.bidCountText) {
                            elements.bidCountText.textContent = `${gameState.userRemainingBids} bids remaining`;
                        }
                        
                        // Force immediate poll for countdown reset
                        pollGameState();
                        // Force immediate data update for feed
                        setTimeout(updateDynamicContent, 500);
                    } else {
                        throw new Error(result.message || 'Failed to place bid');
                    }
                } catch (error) {
                    showNotification('❌ ' + error.message, 'error');
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalHtml;
                    submitButton.classList.add('success-button', 'glow-effect');
                    submitButton.classList.remove('bg-gray-400');
                }
            });
        }

        // Main polling function (only for countdown)
        async function pollGameState() {
            try {
                const response = await fetch(`bids-poll.php?auction_id=${CONFIG.AUCTION_ID}&t=${Date.now()}`);
                const data = await response.json();
                
                if (!data) return;
                
                const now = data.server_time;
                let remaining = CONFIG.COUNTDOWN_DURATION;
                
                if (data.last_bid_time !== null) {
                    remaining = CONFIG.COUNTDOWN_DURATION - Math.floor(now - data.last_bid_time);
                    remaining = Math.max(0, remaining);
                }
                
                updateCountdown(remaining);
                updateLastBidInfo(data.last_bid_username);
                
                if (data.winner && !gameState.winnerDeclared) {
                    declareWinner(data);
                }
                
            } catch (error) {
                console.error('Polling error:', error);
            }
        }

        // Update dynamic content (separate from countdown)
        async function updateDynamicContent() {
            const now = Date.now();
            
            // Prevent too frequent updates
            if (now - gameState.lastFeedUpdate < 2000) {
                return;
            }
            
            gameState.lastFeedUpdate = now;
            
            try {
                const response = await fetch(`get-auction-data.php?auction_id=${CONFIG.AUCTION_ID}&user_id=${CONFIG.USER_ID}&t=${now}`);
                const data = await response.json();
                
                if (data.success) {
                    if (data.leaderboard) updateLeaderboard(data.leaderboard);
                    if (data.recent_bids) updateBidFeed(data.recent_bids);
                    updateStats(data);
                }
            } catch (error) {
                console.error('Dynamic content update error:', error);
            }
        }

        // Initialize game
        function initializeGame() {
            initializeBidForm();
            
            // Initialize with current data
            updateLeaderboard(<?php echo json_encode($leaderboard); ?>);
            
            // Start polling
            pollGameState();
            updateDynamicContent();
            
            setInterval(pollGameState, CONFIG.POLL_INTERVAL);
            setInterval(updateDynamicContent, CONFIG.DATA_UPDATE_INTERVAL);
            
            showNotification('🎮 Game Mode activated! Good luck!', 'info');
        }

        document.addEventListener('DOMContentLoaded', initializeGame);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollGameState();
                updateDynamicContent();
            }
        });
    </script>
</body>
</html>