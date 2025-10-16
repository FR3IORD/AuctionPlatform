<?php
// Get winner information if auction is completed
$winner_name = null;
if ($auction['status'] === 'completed' && $auction['winner_id']) {
    $winner_query = "SELECT username, first_name, last_name FROM users WHERE id = ?";
    $winner_stmt = $db->prepare($winner_query);
    $winner_stmt->execute([$auction['winner_id']]);
    $winner = $winner_stmt->fetch(PDO::FETCH_ASSOC);
    if ($winner) {
        $winner_name = $winner['first_name'] . ' ' . $winner['last_name'];
    }
}

// Get bid count for this auction
$bid_query = "SELECT COUNT(*) FROM bids WHERE auction_id = ?";
$bid_stmt = $db->prepare($bid_query);
$bid_stmt->execute([$auction['id']]);
$bid_count = $bid_stmt->fetchColumn();

// Truncate description to consistent length
$description = htmlspecialchars($auction['description']);
$max_description_length = 80;
if (strlen($description) > $max_description_length) {
    $description = substr($description, 0, $max_description_length) . '...';
}
?>

<div class="auction-card h-full flex flex-col bg-white rounded-lg border border-gray-200 transition-all duration-300 hover:shadow-md hover:border-gray-300">
    <!-- Auction Image -->
    <div class="relative h-48 overflow-hidden flex-shrink-0 rounded-t-lg">
        <img src="<?= $auction['image_url'] ? $auction['image_url'] : asset('images/no-image.jpg') ?>" 
             alt="<?= htmlspecialchars($auction['title']) ?>" 
             class="w-full h-full object-cover">
        
        <!-- Status Badge -->
        <div class="absolute top-3 left-3">
            <?php if ($auction['status'] === 'active'): ?>
                <span class="bg-green-500 text-white px-3 py-1.5 rounded-full text-xs font-semibold flex items-center">
                    <span class="w-1.5 h-1.5 bg-white rounded-full mr-1.5 animate-pulse"></span>
                    LIVE
                </span>
            <?php elseif ($auction['status'] === 'upcoming'): ?>
                <span class="bg-blue-500 text-white px-3 py-1.5 rounded-full text-xs font-semibold">
                    UPCOMING
                </span>
            <?php elseif ($auction['status'] === 'completed'): ?>
                <span class="bg-red-500 text-white px-3 py-1.5 rounded-full text-xs font-semibold">
                    ENDED
                </span>
            <?php endif; ?>
        </div>

        <!-- 100% Guaranteed Badge -->
        <div class="absolute top-3 right-3">
            <span class="bg-green-500 text-white px-3 py-1.5 rounded-full text-xs font-semibold">
                100% Guaranteed
            </span>
        </div>

        <!-- End Time for Active Auctions -->
        <?php if ($auction['status'] === 'active' || $auction['status'] === 'upcoming'): ?>
            <div class="absolute bottom-3 left-3 bg-black/70 backdrop-blur-sm text-white px-3 py-1.5 rounded-lg text-xs">
                <?php if ($auction['status'] === 'active'): ?>
                    Ends: <?= date('M j, H:i', strtotime($auction['end_time'])) ?>
                <?php else: ?>
                    Starts: <?= date('M j, H:i', strtotime($auction['start_time'])) ?>
                <?php endif; ?>
            </div>
        <?php elseif ($auction['status'] === 'completed'): ?>
            <div class="absolute bottom-3 left-3 bg-black/70 backdrop-blur-sm text-white px-3 py-1.5 rounded-lg text-xs">
                Ended: <?= date('M j, H:i', strtotime($auction['end_time'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Auction Details -->
    <div class="p-5 flex flex-col flex-grow">
        <!-- Title - Fixed height -->
        <div class="mb-3" style="height: 48px;">
            <h3 class="text-lg font-bold text-gray-900 line-clamp-2 leading-6"><?= htmlspecialchars($auction['title']) ?></h3>
        </div>

        <!-- Description - Fixed height -->
        <div class="mb-4" style="height: 36px;">
            <p class="text-gray-600 text-sm line-clamp-2 leading-4"><?= $description ?></p>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold text-blue-600"><?= $bid_count ?></div>
                <div class="text-xs text-gray-500">Total Bids</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold text-green-600">$<?= number_format($auction['ticket_price'], 2) ?></div>
                <div class="text-xs text-gray-500">Ticket Price</div>
            </div>
        </div>

        <!-- Tickets Info -->
        <div class="flex items-center justify-between mb-4 text-sm">
            <span class="text-gray-600">Tickets remaining:</span>
            <span class="font-semibold text-orange-600"><?= number_format($auction['total_tickets']) ?></span>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-200 mb-4"></div>

        <!-- Action Buttons - At bottom of card -->
        <div class="space-y-3 mt-auto">
            <?php if ($auction['status'] === 'active'): ?>
                <!-- Active auction buttons -->
                <a href="<?= url("auctions/view.php?id={$auction['id']}") ?>" 
                   class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium text-center block text-sm transition-colors duration-200 hover:bg-blue-700">
                    <i class="fas fa-eye mr-2 text-xs"></i>View & Bid
                </a>
                <a href="<?= url("auctions/game-mode.php?id={$auction['id']}") ?>" 
                   class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium text-center block text-sm transition-colors duration-200 hover:bg-green-700">
                    <i class="fas fa-gamepad mr-2 text-xs"></i>Game Mode
                </a>
            <?php elseif ($auction['status'] === 'upcoming'): ?>
                <!-- Upcoming auction button -->
                <a href="<?= url("auctions/view.php?id={$auction['id']}") ?>" 
                   class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg font-medium text-center block text-sm transition-colors duration-200 hover:bg-gray-700">
                    <i class="fas fa-clock mr-2 text-xs"></i>View Details
                </a>
            <?php elseif ($auction['status'] === 'completed'): ?>
                <!-- Completed auction - Winner info + button together -->
                <div class="bg-yellow-50 p-3 rounded-lg mb-3">
                    <?php if ($winner_name): ?>
                        <div class="flex items-center text-sm mb-2">
                            <i class="fas fa-crown text-yellow-600 mr-2 flex-shrink-0 text-xs"></i>
                            <span class="text-gray-700 truncate">Winner: <strong class="text-yellow-700"><?= htmlspecialchars($winner_name) ?></strong></span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center text-sm mb-2">
                            <i class="fas fa-info-circle text-gray-500 mr-2 flex-shrink-0 text-xs"></i>
                            <span class="text-gray-600">No winner determined</span>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="<?= url("auctions/view.php?id={$auction['id']}") ?>" 
                   class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg font-medium text-center block text-sm transition-colors duration-200 hover:bg-gray-700">
                    <i class="fas fa-history mr-2 text-xs"></i>View Results
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>