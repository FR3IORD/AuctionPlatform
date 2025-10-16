<?php 
require_once '../config/config.php';
requireLogin();

// --- Language & Translation Loader ---
$supported_langs = ['en', 'es', 'it', 'el'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $supported_langs)) $lang = 'en';
$_SESSION['lang'] = $lang;
if (!function_exists('t')) {
    $translations = require __DIR__ . "/../lang/$lang.php";
    function t($key) {
        global $translations;
        return $translations[$key] ?? $key;
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $card_number = $_POST['card_number'] ?? '';
    $expiry = $_POST['expiry'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    
    // Basic validation
    if ($amount < 1) {
        $error = t('min_deposit_error');
    } elseif ($amount > 1000) {
        $error = t('max_deposit_error');
    } elseif (empty($card_number) || empty($expiry) || empty($cvv)) {
        $error = t('fill_all_payment_details');
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $db->beginTransaction();
            $transaction_id = 'txn_' . uniqid();
            $new_balance = $_SESSION['balance'] + $amount;
            updateUserBalance($_SESSION['user_id'], $new_balance);
            $query = "INSERT INTO transactions (user_id, type, amount, status, payment_method, transaction_id, description) VALUES (?, 'deposit', ?, 'completed', ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'], 
                $amount, 
                $payment_method, 
                $transaction_id,
                t('deposit_via', ['method' => $payment_method])
            ]);
            $db->commit();
            $success = t('successfully_added', ['amount' => formatPrice($amount)]);
        } catch (Exception $e) {
            $db->rollback();
            $error = t('payment_failed');
        }
    }
}
if (!function_exists('formatPrice')) {
    function formatPrice($num) { return number_format($num, 2).'₾'; }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('add_funds') ?> - <?= SITE_NAME ?></title>
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
                    <li><a href="/auction-platform/account/" class="hover:text-primary"><?= t('account') ?></a></li>
                    <li><i class="fas fa-chevron-right"></i></li>
                    <li class="text-gray-900"><?= t('add_funds') ?></li>
                </ol>
            </nav>
            <h1 class="text-4xl font-bold text-gray-900 mb-2"><?= t('add_funds') ?></h1>
            <p class="text-xl text-gray-600"><?= t('deposit_money_desc') ?></p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Payment Form -->
            <div class="lg:col-span-2">
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
                        </div>
                    <?php endif; ?>
                    <form method="POST" id="payment-form">
                        <!-- Amount Selection -->
                        <div class="mb-8">
                            <label class="block text-lg font-semibold text-gray-900 mb-4"><?= t('select_amount') ?></label>
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <?php foreach([10,25,50,100,250,500] as $preset): ?>
                                <button type="button" onclick="setAmount(<?= $preset ?>)" class="amount-btn p-4 border-2 border-gray-200 rounded-lg text-center hover:border-primary transition-colors">
                                    <div class="text-2xl font-bold">$<?= $preset ?></div>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2"><?= t('custom_amount') ?></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                    <input type="number" id="amount" name="amount" min="1" max="1000" step="0.01"
                                           class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary text-lg"
                                           placeholder="0.00" value="<?= $_POST['amount'] ?? '' ?>">
                                </div>
                                <p class="mt-2 text-sm text-gray-500"><?= t('minmax_deposit') ?></p>
                            </div>
                        </div>
                        <!-- Payment Method -->
                        <div class="mb-8">
                            <label class="block text-lg font-semibold text-gray-900 mb-4"><?= t('payment_method') ?></label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="payment-method-option cursor-pointer">
                                    <input type="radio" name="payment_method" value="visa" class="sr-only" checked>
                                    <div class="p-4 border-2 border-primary bg-primary/5 rounded-lg text-center">
                                        <i class="fab fa-cc-visa text-4xl text-primary mb-2"></i>
                                        <div class="font-semibold">Visa</div>
                                    </div>
                                </label>
                                <label class="payment-method-option cursor-pointer">
                                    <input type="radio" name="payment_method" value="mastercard" class="sr-only">
                                    <div class="p-4 border-2 border-gray-200 rounded-lg text-center hover:border-primary transition-colors">
                                        <i class="fab fa-cc-mastercard text-4xl text-red-500 mb-2"></i>
                                        <div class="font-semibold">Mastercard</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <!-- Card Details -->
                        <div class="mb-8">
                            <label class="block text-lg font-semibold text-gray-900 mb-4"><?= t('card_details') ?></label>
                            <div class="mb-4">
                                <label for="card_number" class="block text-sm font-medium text-gray-700 mb-2"><?= t('card_number') ?></label>
                                <input type="text" id="card_number" name="card_number" maxlength="19" placeholder="1234 5678 9012 3456"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                                       value="<?= $_POST['card_number'] ?? '' ?>">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="expiry" class="block text-sm font-medium text-gray-700 mb-2"><?= t('expiry_date') ?></label>
                                    <input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                                           value="<?= $_POST['expiry'] ?? '' ?>">
                                </div>
                                <div>
                                    <label for="cvv" class="block text-sm font-medium text-gray-700 mb-2"><?= t('cvv') ?></label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-primary focus:border-primary"
                                           value="<?= $_POST['cvv'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-gradient-to-r from-success to-green-600 text-white py-4 rounded-lg font-bold text-lg hover:shadow-lg transition-all">
                            <i class="fas fa-credit-card mr-2"></i>
                            <?= t('add_funds_to_account') ?>
                        </button>
                    </form>
                </div>
            </div>
            <!-- Summary -->
            <div class="space-y-6">
                <!-- Current Balance -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= t('current_balance') ?></h3>
                    <div class="text-3xl font-bold text-success mb-2"><?= formatPrice($_SESSION['balance']) ?></div>
                    <p class="text-sm text-gray-600"><?= t('available_for_auction_tickets') ?></p>
                </div>
                <!-- Security -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-shield-alt mr-2 text-success"></i>
                        <?= t('secure_payment') ?>
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-check text-success mr-2"></i>
                            <?= t('ssl_encryption') ?>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-success mr-2"></i>
                            <?= t('pci_dss_compliant') ?>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-success mr-2"></i>
                            <?= t('instant_processing') ?>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-success mr-2"></i>
                            <?= t('no_hidden_fees') ?>
                        </li>
                    </ul>
                </div>
                <!-- Demo Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-2"><?= t('demo_mode') ?></h3>
                    <p class="text-sm text-blue-700"><?= t('demo_mode_desc') ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
            document.querySelectorAll('.amount-btn').forEach(btn => {
                btn.classList.remove('border-primary', 'bg-primary/10');
                btn.classList.add('border-gray-200');
            });
            event.target.closest('.amount-btn').classList.add('border-primary', 'bg-primary/10');
            event.target.closest('.amount-btn').classList.remove('border-gray-200');
        }
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-method-option div').forEach(div => {
                    div.classList.remove('border-primary', 'bg-primary/5');
                    div.classList.add('border-gray-200');
                });
                this.closest('.payment-method-option').querySelector('div').classList.add('border-primary', 'bg-primary/5');
                this.closest('.payment-method-option').querySelector('div').classList.remove('border-gray-200');
            });
        });
        document.getElementById('card_number').addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            this.value = formattedValue;
        });
        document.getElementById('expiry').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0,2) + '/' + value.substring(2,4);
            }
            this.value = value;
        });
        document.getElementById('cvv').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>