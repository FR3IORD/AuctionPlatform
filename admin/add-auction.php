<?php 
require_once '../config/config.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $ticket_price = (float)$_POST['ticket_price'];
    $total_tickets = (int)$_POST['total_tickets'];
    $bids_per_ticket = (int)$_POST['bids_per_ticket'];
    $guaranteed = isset($_POST['guaranteed']) ? 1 : 0;
    
    // Validation
    if (empty($title) || empty($description) || empty($image_url) || empty($start_time) || empty($end_time)) {
        $error = 'Please fill in all required fields';
    } elseif ($ticket_price < 0.01) {
        $error = 'Ticket price must be at least $0.01';
    } elseif ($total_tickets < 1) {
        $error = 'Total tickets must be at least 1';
    } elseif ($bids_per_ticket < 1) {
        $error = 'Bids per ticket must be at least 1';
    } elseif (strtotime($start_time) <= time()) {
        $error = 'Start time must be in the future';
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = 'End time must be after start time';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO auctions (title, description, image_url, start_time, end_time, ticket_price, total_tickets, bids_per_ticket, guaranteed, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$title, $description, $image_url, $start_time, $end_time, $ticket_price, $total_tickets, $bids_per_ticket, $guaranteed])) {
            $success = 'Auction created successfully!';
            // Clear form data
            $_POST = [];
        } else {
            $error = 'Failed to create auction. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Auction - Admin - <?= SITE_NAME ?></title>
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

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <nav class="mb-4">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="/auction-platform/admin/" class="hover:text-primary">Admin</a></li>
                    <li><i class="fas fa-chevron-right"></i></li>
                    <li class="text-gray-900">Add New Auction</li>
                </ol>
            </nav>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Add New Auction</h1>
            <p class="text-xl text-gray-600">Create a new auction for users to participate in</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-8">
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
                    <div class="mt-2">
                        <a href="/auction-platform/admin/auctions.php" class="font-medium text-green-800 hover:text-green-900 underline">View all auctions</a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Auction Title *</label>
                        <input type="text" id="title" name="title" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               placeholder="e.g., iPhone 15 Pro Max 256GB" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                        <textarea id="description" name="description" rows="4" required 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                                  placeholder="Detailed description of the auction item..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="image_url" class="block text-sm font-medium text-gray-700 mb-2">Image URL *</label>
                        <input type="url" id="image_url" name="image_url" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>"
                               onchange="previewImage()">
                        <div id="image-preview" class="mt-4 hidden">
                            <img id="preview-img" src="" alt="Preview" class="w-32 h-32 object-cover rounded-lg border">
                        </div>
                    </div>
                </div>

                <!-- Timing -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Date & Time *</label>
                        <input type="datetime-local" id="start_time" name="start_time" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               value="<?= $_POST['start_time'] ?? '' ?>" min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">End Date & Time *</label>
                        <input type="datetime-local" id="end_time" name="end_time" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               value="<?= $_POST['end_time'] ?? '' ?>" min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>

                <!-- Auction Settings -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="ticket_price" class="block text-sm font-medium text-gray-700 mb-2">Ticket Price ($) *</label>
                        <input type="number" id="ticket_price" name="ticket_price" min="0.01" step="0.01" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               placeholder="1.00" value="<?= $_POST['ticket_price'] ?? '' ?>">
                    </div>
                    
                    <div>
                        <label for="total_tickets" class="block text-sm font-medium text-gray-700 mb-2">Total Tickets *</label>
                        <input type="number" id="total_tickets" name="total_tickets" min="1" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               placeholder="1000" value="<?= $_POST['total_tickets'] ?? '' ?>">
                    </div>
                    
                    <div>
                        <label for="bids_per_ticket" class="block text-sm font-medium text-gray-700 mb-2">Bids per Ticket *</label>
                        <input type="number" id="bids_per_ticket" name="bids_per_ticket" min="1" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                               placeholder="5" value="<?= $_POST['bids_per_ticket'] ?? '5' ?>">
                    </div>
                </div>

                <!-- Options -->
                <div class="flex items-center">
                    <input type="checkbox" id="guaranteed" name="guaranteed" 
                           class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                           <?= isset($_POST['guaranteed']) ? 'checked' : '' ?>>
                    <label for="guaranteed" class="ml-2 block text-sm text-gray-900">
                        Guaranteed Auction (100% guaranteed to happen)
                    </label>
                </div>

                <!-- Submit Buttons -->
                <div class="flex justify-between pt-6">
                    <a href="/auction-platform/admin/auctions.php" 
                       class="bg-gray-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-all">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Auctions
                    </a>
                    
                    <button type="submit" 
                            class="bg-gradient-to-r from-primary to-secondary text-white px-8 py-3 rounded-lg font-semibold hover:shadow-lg transition-all">
                        <i class="fas fa-plus mr-2"></i>Create Auction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function previewImage() {
            const imageUrl = document.getElementById('image_url').value;
            const preview = document.getElementById('image-preview');
            const img = document.getElementById('preview-img');
            
            if (imageUrl) {
                img.src = imageUrl;
                preview.classList.remove('hidden');
                
                img.onerror = function() {
                    preview.classList.add('hidden');
                };
            } else {
                preview.classList.add('hidden');
            }
        }

        // Auto-calculate end time (2 hours after start time)
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = new Date(this.value);
            if (startTime) {
                const endTime = new Date(startTime.getTime() + (2 * 60 * 60 * 1000)); // Add 2 hours
                document.getElementById('end_time').value = endTime.toISOString().slice(0, 16);
            }
        });
    </script>
</body>
</html>