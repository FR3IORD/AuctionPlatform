<?php 
require_once '../config/config.php';
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'end_time';
$order = $_GET['order'] ?? 'ASC';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
$order_clause = "ORDER BY $sort $order";

$query = "SELECT * FROM auctions $where_clause $order_clause";
$stmt = $db->prepare($query);
$stmt->execute($params);
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Auctions - <?= SITE_NAME ?></title>
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
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">All Auctions</h1>
            <p class="text-xl text-gray-600">Find your next winning opportunity</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-64">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search auctions..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Live</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select id="sort" name="sort" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="end_time" <?= $sort === 'end_time' ? 'selected' : '' ?>>End Time</option>
                        <option value="ticket_price" <?= $sort === 'ticket_price' ? 'selected' : '' ?>>Ticket Price</option>
                        <option value="tickets_sold" <?= $sort === 'tickets_sold' ? 'selected' : '' ?>>Popularity</option>
                        <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Newest</option>
                    </select>
                </div>
                
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <select id="order" name="order" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
                
                <button type="submit" class="bg-gradient-to-r from-primary to-secondary text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition-all">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                
                <a href="/auction-platform/auctions/" class="bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-600 transition-all">
                    <i class="fas fa-refresh mr-2"></i>Reset
                </a>
            </form>
        </div>

        <!-- Auctions Grid -->
        <?php if (empty($auctions)): ?>
            <div class="text-center py-12">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No auctions found</h3>
                <p class="text-gray-500">Try adjusting your search criteria</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($auctions as $auction): ?>
                    <?php include '../includes/auction-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Load More / Pagination can be added here -->
        <?php if (count($auctions) >= 12): ?>
            <div class="text-center mt-12">
                <button class="bg-gradient-to-r from-primary to-secondary text-white px-8 py-3 rounded-full font-semibold hover:shadow-lg transition-all">
                    <i class="fas fa-plus mr-2"></i>Load More
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>