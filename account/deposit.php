<?php
session_start();

// Database connection
try {
    $host = '192.168.10.60';
    $dbname = 'auction_platform';
    $username = 'root';
    $password = 'root';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../auth/login.php');
    exit();
}

// Process deposit
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $payment_method = $_POST['payment_method'] ?? '';
    
    if ($amount && $amount >= 5 && $amount <= 10000) {
        // In real implementation, you would integrate with Stripe API here
        // For demo purposes, we'll simulate a successful payment
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Add funds to user account
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            
            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, status, created_at) VALUES (?, 'deposit', ?, ?, 'completed', NOW())");
            $stmt->execute([$user_id, $amount, "Deposit via {$payment_method}"]);
            
            $pdo->commit();
            
            $success_message = "თანხა წარმატებით შემოიტანა! თქვენს ანგარიშზე დაემატა ₾" . number_format($amount, 2);
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user['balance'] = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "შეცდომა მოხდა. გთხოვთ ცადოთ ხელახლა.";
        }
    } else {
        $error_message = "თანხა უნდა იყოს ₾5-დან ₾10,000-მდე.";
    }
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>თანხის შემოტანა - Auction Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-input {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            transition: border-color 0.3s;
        }
        .card-input:focus {
            border-color: #3b82f6;
            outline: none;
        }
        .amount-btn {
            transition: all 0.3s;
        }
        .amount-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../index.php" class="text-xl font-bold text-blue-600">
                        <i class="fas fa-gavel mr-2"></i>Auction Platform
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">ბალანსი: <strong>₾<?= number_format($user['balance'], 2) ?></strong></span>
                    <a href="index.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-user mr-1"></i>ანგარიში
                    </a>
                    <a href="../auth/logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>გასვლა
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-credit-card text-blue-600 mr-3"></i>
                თანხის შემოტანა
            </h1>
            <p class="text-gray-600">შემოიტანეთ თანხა ანგარიშზე უსაფრთხო გადახდის მეთოდით</p>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Amount Selection -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
                    აირჩიეთ თანხა
                </h2>
                
                <!-- Quick Amount Buttons -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <button onclick="setAmount(10)" class="amount-btn bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg border border-blue-200">
                        ₾10
                    </button>
                    <button onclick="setAmount(25)" class="amount-btn bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg border border-blue-200">
                        ₾25
                    </button>
                    <button onclick="setAmount(50)" class="amount-btn bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg border border-blue-200">
                        ₾50
                    </button>
                    <button onclick="setAmount(100)" class="amount-btn bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg border border-blue-200">
                        ₾100
                    </button>
                    <button onclick="setAmount(250)" class="amount-btn bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg border border-blue-200">
                        ₾250
                    </button>
                    <button onclick="setAmount(500)" class="amount-btn bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg border border-blue-200">
                        ₾500
                    </button>
                </div>

                <!-- Custom Amount -->
                <form method="POST" id="depositForm">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            კასტომური თანხა (₾5 - ₾10,000)
                        </label>
                        <input type="number" name="amount" id="amount" min="5" max="10000" step="0.01" 
                               class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-lg"
                               placeholder="შეიყვანეთ თანხა..." required>
                    </div>

                    <!-- Payment Method Selection -->
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            გადახდის მეთოდი
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="payment_method" value="Visa" class="mr-3" required>
                                <i class="fab fa-cc-visa text-2xl text-blue-600 mr-3"></i>
                                <span class="font-medium">Visa</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="payment_method" value="Mastercard" class="mr-3" required>
                                <i class="fab fa-cc-mastercard text-2xl text-red-600 mr-3"></i>
                                <span class="font-medium">Mastercard</span>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="payment_method" value="American Express" class="mr-3" required>
                                <i class="fab fa-cc-amex text-2xl text-green-600 mr-3"></i>
                                <span class="font-medium">American Express</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Payment Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-lock text-green-600 mr-2"></i>
                    უსაფრთხო გადახდა
                </h2>

                <!-- Card Details Form -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            ბარათის ნომერი
                        </label>
                        <input type="text" id="card_number" class="card-input w-full" 
                               placeholder="1234 5678 9012 3456" maxlength="19">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                MM/YY
                            </label>
                            <input type="text" id="expiry" class="card-input w-full" 
                                   placeholder="12/25" maxlength="5">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                CVC
                            </label>
                            <input type="text" id="cvc" class="card-input w-full" 
                                   placeholder="123" maxlength="4">
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            ბარათის მფლობელი
                        </label>
                        <input type="text" id="card_holder" class="card-input w-full" 
                               placeholder="სახელი გვარი" value="<?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?>">
                    </div>
                </div>

                <!-- Security Info -->
                <div class="bg-gray-50 rounded-lg p-4 mt-6">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                        <span>თქვენი ინფორმაცია დაცულია SSL დაშიფვრით</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" form="depositForm" name="deposit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition duration-300 mt-6">
                    <i class="fas fa-credit-card mr-2"></i>
                    თანხის შემოტანა
                </button>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="mt-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-history text-gray-600 mr-2"></i>
                    ბოლო ტრანზაქციები
                </h2>

                <?php
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'deposit' ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$user_id]);
                $transactions = $stmt->fetchAll();
                ?>

                <?php if (empty($transactions)): ?>
                    <p class="text-gray-500 text-center py-4">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-2 block"></i>
                        ტრანზაქციები არ მოიძებნა
                    </p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">თარიღი</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">თანხა</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">მეთოდი</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">სტატუსი</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                            +₾<?= number_format($transaction['amount'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($transaction['description']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>
                                                დასრულებული
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Set amount function
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
        }

        // Format card number input
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ');
            e.target.value = formattedValue || value;
        });

        // Format expiry input
        document.getElementById('expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Allow only numbers for CVC
        document.getElementById('cvc').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });

        // Form validation
        document.getElementById('depositForm').addEventListener('submit', function(e) {
            const amount = document.getElementById('amount').value;
            const cardNumber = document.getElementById('card_number').value;
            const expiry = document.getElementById('expiry').value;
            const cvc = document.getElementById('cvc').value;
            const cardHolder = document.getElementById('card_holder').value;

            if (!amount || amount < 5 || amount > 10000) {
                alert('თანხა უნდა იყოს ₾5-დან ₾10,000-მდე');
                e.preventDefault();
                return;
            }

            if (!cardNumber || cardNumber.replace(/\s/g, '').length < 13) {
                alert('შეიყვანეთ სწორი ბარათის ნომერი');
                e.preventDefault();
                return;
            }

            if (!expiry || expiry.length !== 5) {
                alert('შეიყვანეთ ბარათის ვადის გასვლის თარიღი');
                e.preventDefault();
                return;
            }

            if (!cvc || cvc.length < 3) {
                alert('შეიყვანეთ CVC კოდი');
                e.preventDefault();
                return;
            }

            if (!cardHolder.trim()) {
                alert('შეიყვანეთ ბარათის მფლობელის სახელი');
                e.preventDefault();
                return;
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>მუშავდება...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
