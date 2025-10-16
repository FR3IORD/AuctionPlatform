<?php
require_once 'config/config.php';

// ----- LANGUAGE LOADER (add this at the top, before any output) -----
if (session_status() === PHP_SESSION_NONE) session_start();
$supported_langs = ['en', 'es', 'it', 'el'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $supported_langs)) $lang = 'en';
$_SESSION['lang'] = $lang;
$translations = require __DIR__ . "/lang/$lang.php";
function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}
// --------------------------------------------------------------------

$database = new Database();
$db = $database->getConnection();

// Get recent auctions for display (including active, upcoming, and recently completed)
$query = "SELECT * FROM auctions 
          WHERE status IN ('upcoming', 'active', 'completed') 
          ORDER BY 
            CASE status
                WHEN 'active' THEN 1
                WHEN 'upcoming' THEN 2
                WHEN 'completed' THEN 3
            END,
            CASE 
                WHEN status = 'completed' THEN end_time
                ELSE start_time
            END DESC
          LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users count
$stmt = $db->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$users_count = (int)$stmt->fetchColumn();

// Get auctions count (all)
$stmt = $db->prepare("SELECT COUNT(*) FROM auctions");
$stmt->execute();
$auctions_count = (int)$stmt->fetchColumn();

// Get active auctions count
$stmt = $db->prepare("SELECT COUNT(*) FROM auctions WHERE status = 'active'");
$stmt->execute();
$active_auctions_count = (int)$stmt->fetchColumn();

// Get unique winners count
$stmt = $db->prepare("SELECT COUNT(DISTINCT winner_id) FROM auctions WHERE winner_id IS NOT NULL");
$stmt->execute();
$winners_count = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - <?= t('site_subtitle') ?></title>
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
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
    <script src="<?= asset('js/timezone-detector.js') ?>"></script>
    <style>
        /* Simple gradient background */
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        }
        
        /* Simple card styles with minimal hover effects */
        .auction-card { 
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .auction-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }
        
        /* Simple button hover effects */
        .hero-btn {
            background: white;
            color: #374151;
            transition: opacity 0.3s ease;
        }
        
        .hero-btn:hover {
            opacity: 0.9;
        }
        
        .hero-btn-secondary {
            border: 2px solid white;
            color: white;
            background: transparent;
            transition: opacity 0.3s ease;
        }
        
        .hero-btn-secondary:hover {
            opacity: 0.9;
        }
        
        .main-action-btn {
            background: linear-gradient(to right, #3b82f6, #8b5cf6);
            transition: opacity 0.3s ease;
        }
        
        .main-action-btn:hover {
            opacity: 0.9;
        }
        
        /* Simple card hover effects */
        .step-card {
            transition: box-shadow 0.3s ease;
        }
        
        .step-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card {
            transition: box-shadow 0.3s ease;
        }
        
        .feature-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            transition: background-color 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            background-color: #dbeafe;
        }
        
        /* Simple text hover effects */
        .stat-item {
            transition: color 0.3s ease;
        }
        
        /* Clean scroll behavior */
        html {
            scroll-behavior: smooth;
        }
    </style>
    <script>
        window.SERVER_TIMEZONE = '<?= CURRENT_TIMEZONE ?>';
        window.SERVER_TIME = '<?= getCurrentDateTime('c') ?>';
    </script>
</head>

<body class="bg-gray-50">
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="gradient-bg text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6 leading-tight">
                        <?= t('hero_title1') ?>
                        <span class="text-yellow-300"><?= t('hero_title2') ?></span>
                    </h1>
                    <p class="text-xl mb-8 opacity-90">
                        <?= t('hero_subtitle') ?>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="#auctions" class="hero-btn px-8 py-4 rounded-full font-semibold inline-flex items-center justify-center">
                            <i class="fas fa-gavel mr-2"></i>
                            <?= t('view_auctions') ?>
                        </a>
                        <?php if (!isLoggedIn()): ?>
                        <a href="<?= url('auth/register.php') ?>" class="hero-btn-secondary px-8 py-4 rounded-full font-semibold inline-flex items-center justify-center">
                            <i class="fas fa-user-plus mr-2"></i>
                            <?= t('join_now') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="relative">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 shadow-2xl">
                        <div class="bg-white rounded-xl p-6">
                            <h3 class="text-gray-800 text-xl font-bold mb-4"><?= t('how_it_works') ?></h3>
                            <div class="space-y-3 text-gray-600">
                                <div class="flex items-center">
                                    <span class="bg-blue-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm mr-3">1</span>
                                    <?= t('step1') ?>
                                </div>
                                <div class="flex items-center">
                                    <span class="bg-purple-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm mr-3">2</span>
                                    <?= t('step2') ?>
                                </div>
                                <div class="flex items-center">
                                    <span class="bg-cyan-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm mr-3">3</span>
                                    <?= t('step3') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">
                    <?= t('how_auctions_work') ?>
                </h2>
                <p class="text-xl text-gray-600"><?= t('how_auctions_desc') ?></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center step-card p-8 rounded-2xl auction-card">
                    <div class="step-number w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-400 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">1</div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4"><?= t('buy_tickets') ?></h3>
                    <p class="text-gray-600 leading-relaxed"><?= t('buy_tickets_desc') ?></p>
                </div>
                <div class="text-center step-card p-8 rounded-2xl auction-card">
                    <div class="step-number w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-400 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">2</div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4"><?= t('place_bids') ?></h3>
                    <p class="text-gray-600 leading-relaxed"><?= t('place_bids_desc') ?></p>
                </div>
                <div class="text-center step-card p-8 rounded-2xl auction-card">
                    <div class="step-number w-20 h-20 bg-gradient-to-br from-cyan-500 to-cyan-400 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">3</div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4"><?= t('win_prizes') ?></h3>
                    <p class="text-gray-600 leading-relaxed"><?= t('win_prizes_desc') ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Auctions Section -->
    <section id="auctions" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">
                    <?= t('live_upcoming') ?> <span class="text-blue-500"><?= t('auctions') ?></span>
                </h2>
                <p class="text-xl text-gray-600"><?= t('dont_miss') ?></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($auctions)): ?>
                    <?php foreach($auctions as $auction): ?>
                        <?php include 'includes/auction-card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-gavel text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-600 mb-4"><?= t('no_active_auctions') ?></h3>
                        <p class="text-gray-500"><?= t('check_back_soon') ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-12">
                <a href="<?= url('auctions') ?>" class="main-action-btn inline-flex items-center text-white px-8 py-4 rounded-full font-semibold">
                    <span><?= t('view_all_auctions') ?></span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">
                    <?= t('why_choose') ?> <span class="text-blue-500"><?= SITE_NAME ?></span>
                </h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center feature-card p-8">
                    <div class="feature-icon w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-shield-alt text-blue-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4"><?= t('secure') ?></h3>
                    <p class="text-gray-600"><?= t('secure_desc') ?></p>
                </div>
                <div class="text-center feature-card p-8">
                    <div class="feature-icon w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-users text-purple-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4"><?= t('fair_play') ?></h3>
                    <p class="text-gray-600"><?= t('fair_play_desc') ?></p>
                </div>
                <div class="text-center feature-card p-8">
                    <div class="feature-icon w-16 h-16 bg-cyan-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-gift text-cyan-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4"><?= t('premium_prizes') ?></h3>
                    <p class="text-gray-600"><?= t('premium_prizes_desc') ?></p>
                </div>
            </div>
        </div>
    </section>

     <!-- Statistics Section (REAL DATA) -->
    <section class="py-20 bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center">
                <div class="stat-item">
                    <div class="text-4xl font-bold text-blue-400 mb-2"><?= number_format($users_count) ?></div>
                    <div class="text-gray-300"><?= t('registered_users') ?></div>
                </div>
                <div class="stat-item">
                    <div class="text-4xl font-bold text-purple-400 mb-2"><?= number_format($auctions_count) ?></div>
                    <div class="text-gray-300"><?= t('total_auctions') ?></div>
                </div>
                <div class="stat-item">
                    <div class="text-4xl font-bold text-cyan-400 mb-2"><?= number_format($active_auctions_count) ?></div>
                    <div class="text-gray-300"><?= t('active_auctions') ?></div>
                </div>
                <div class="stat-item">
                    <div class="text-4xl font-bold text-yellow-400 mb-2"><?= number_format($winners_count) ?></div>
                    <div class="text-gray-300"><?= t('unique_winners') ?></div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>