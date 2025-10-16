<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$supported_langs = ['en', 'es', 'it', 'el'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $supported_langs)) $lang = 'en';
$_SESSION['lang'] = $lang;
if (!function_exists('t')) {
    $translations = require dirname(__DIR__) . "/lang/$lang.php";
    function t($key) {
        global $translations;
        return $translations[$key] ?? $key;
    }
}
$current_year = date('Y');
?>
<footer class="bg-gray-900 text-gray-300 mt-20 border-t border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <!-- Brand -->
            <div>
                <h3 class="text-white font-bold text-xl mb-4 flex items-center">
                    <i class="fas fa-gavel mr-2 text-primary"></i>
                    <?= SITE_NAME ?>
                </h3>
                <p class="text-sm text-gray-400">
                    Experience the thrill of online auctions with <?= SITE_NAME ?>. Bid, win, and discover amazing items.
                </p>
                <div class="flex space-x-4 mt-4">
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-white font-semibold mb-4"><?= t('quick_links') ?></h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= url() ?>" class="text-gray-400 hover:text-primary transition-colors"><?= t('home') ?></a></li>
                    <li><a href="<?= url('auctions') ?>" class="text-gray-400 hover:text-primary transition-colors"><?= t('all_auctions') ?></a></li>
                    <li><a href="<?= url() ?>#how-it-works" class="text-gray-400 hover:text-primary transition-colors"><?= t('how_it_works') ?></a></li>
                    <li><a href="#" class="text-gray-400 hover:text-primary transition-colors"><?= t('winners_gallery') ?></a></li>
                    <li><a href="#" class="text-gray-400 hover:text-primary transition-colors"><?= t('faq') ?></a></li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="text-white font-semibold mb-4"><?= t('support') ?></h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="http://192.168.10.60/auction-platform/faq.php" class="text-gray-400 hover:text-primary transition-colors"><?= t('faq') ?></a></li>
                    <li><a href="http://192.168.10.60/auction-platform/help.php" class="text-gray-400 hover:text-primary transition-colors"><?= t('help_center') ?></a></li>
                    <li><a href="http://192.168.10.60/auction-platform/terms.php" class="text-gray-400 hover:text-primary transition-colors"><?= t('terms_of_service') ?></a></li>
                    <li><a href="http://192.168.10.60/auction-platform/privacy.php" class="text-gray-400 hover:text-primary transition-colors"><?= t('privacy_policy') ?></a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="text-white font-semibold mb-4"><?= t('contact_info') ?></h4>
                <ul class="space-y-2 text-sm">
                    <li class="text-gray-400">
                        <i class="fas fa-phone mr-2 text-primary"></i>
                        +995 123 456 789
                    </li>
                    <li class="text-gray-400">
                        <i class="fas fa-envelope mr-2 text-primary"></i>
                        support@auctionbay.com
                    </li>
                    <li class="text-gray-400">
                        <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
                        Tbilisi, Georgia
                    </li>
                </ul>
            </div>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-800 my-8"></div>

        <!-- Bottom -->
        <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-400">
            <div>
                <p>&copy; <?= $current_year ?> <?= SITE_NAME ?>. <?= t('all_rights_reserved') ?></p>
            </div>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="#" class="hover:text-primary transition-colors"><?= t('sitemap') ?></a>
                <a href="#" class="hover:text-primary transition-colors"><?= t('security') ?></a>
                <a href="#" class="hover:text-primary transition-colors"><?= t('accessibility') ?></a>
            </div>
        </div>
    </div>
</footer>