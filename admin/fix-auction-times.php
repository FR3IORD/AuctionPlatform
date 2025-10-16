<?php 
require_once '../config/config.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Update all auctions to have realistic dates relative to current time
        $queries = [
            // Make first auction LIVE now (ends in 2 hours)
            "UPDATE auctions SET 
                start_time = NOW(),
                end_time = DATE_ADD(NOW(), INTERVAL 2 HOUR),
                status = 'active'
            WHERE id = 1",
            
            // Make second auction upcoming (starts in 1 hour, lasts 2 hours)
            "UPDATE auctions SET 
                start_time = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                end_time = DATE_ADD(NOW(), INTERVAL 3 HOUR),
                status = 'upcoming'
            WHERE id = 2",
            
            // Third auction (starts in 4 hours)
            "UPDATE auctions SET 
                start_time = DATE_ADD(NOW(), INTERVAL 4 HOUR),
                end_time = DATE_ADD(NOW(), INTERVAL 6 HOUR),
                status = 'upcoming'
            WHERE id = 3",
            
            // Fourth auction (starts in 8 hours)
            "UPDATE auctions SET 
                start_time = DATE_ADD(NOW(), INTERVAL 8 HOUR),
                end_time = DATE_ADD(NOW(), INTERVAL 10 HOUR),
                status = 'upcoming'
            WHERE id = 4",
            
            // Fifth auction (starts in 12 hours)
            "UPDATE auctions SET 
                start_time = DATE_ADD(NOW(), INTERVAL 12 HOUR),
                end_time = DATE_ADD(NOW(), INTERVAL 14 HOUR),
                status = 'upcoming'
            WHERE id = 5",
            
            // Sixth auction (starts in 16 hours)
            "UPDATE auctions SET 
                start_time = DATE_ADD(NOW(), INTERVAL 16 HOUR),
                end_time = DATE_ADD(NOW(), INTERVAL 18 HOUR),
                status = 'upcoming'
            WHERE id = 6"
        ];
        
        foreach($queries as $query) {
            $db->exec($query);
        }
        
        $success = 'All auction times updated successfully! Now you should see LIVE and UPCOMING auctions.';
        
    } catch (Exception $e) {
        $error = 'Failed to update auction times: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Auction Times - Admin - <?= SITE_NAME ?></title>
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
</head>

<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">
                <i class="fas fa-clock text-warning mr-3"></i>
                Fix Auction Times
            </h1>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-4 flex space-x-4">
                        <a href="<?= url() ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-all">
                            <i class="fas fa-home mr-2"></i>View Homepage
                        </a>
                        <a href="<?= url('auctions') ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all">
                            <i class="fas fa-gavel mr-2"></i>View All Auctions
                        </a>
                        <a href="<?= url('admin') ?>" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-all">
                            <i class="fas fa-tachometer-alt mr-2"></i>Admin Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-8">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-yellow-800 mb-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Problem: All Auctions Show "AUCTION ENDED"
                    </h3>
                    <p class="text-yellow-700">All auctions are showing "AUCTION ENDED" because they have old dates from 2025-01-24 to 2025-01-27. This tool will fix the timing to make them current and realistic.</p>
                </div>

                <h3 class="text-lg font-semibold mb-4">This will update auction times to:</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-semibold text-red-800 mb-2">
                            <i class="fas fa-play-circle mr-2"></i>
                            Auction 1 - iPhone 16 Pro Max
                        </h4>
                        <p class="text-red-700 text-sm">Will be <strong>LIVE</strong> right now (ends in 2 hours)</p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-clock mr-2"></i>
                            Auction 2 - PlayStation 5 Slim
                        </h4>
                        <p class="text-blue-700 text-sm"><strong>Upcoming:</strong> Starts in 1 hour</p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-clock mr-2"></i>
                            Auction 3 - MacBook Pro M4
                        </h4>
                        <p class="text-blue-700 text-sm"><strong>Upcoming:</strong> Starts in 4 hours</p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-calendar mr-2"></i>
                            Remaining 3 Auctions
                        </h4>
                        <p class="text-blue-700 text-sm"><strong>Upcoming:</strong> Spread over 8-18 hours</p>
                    </div>
                </div>
            </div>

            <?php if (!$success): ?>
            <div class="text-center">
                <form method="POST" class="inline">
                    <button type="submit" class="bg-gradient-to-r from-red-500 to-blue-500 text-white px-8 py-4 rounded-lg font-bold text-lg hover:shadow-xl transition-all">
                        <i class="fas fa-magic mr-3"></i>
                        🚀 Fix All Auction Times Now!
                    </button>
                </form>
                <p class="text-gray-500 text-sm mt-3">This will make auctions live and upcoming with proper countdown timers</p>
                
                <div class="mt-6">
                    <a href="<?= url('admin') ?>" class="text-gray-600 hover:text-gray-800 underline">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Admin Dashboard
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>