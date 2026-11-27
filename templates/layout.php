<?php
/** Main page layout: header, navigation, flash messages, content, footer. */
$authUser = $authUser ?? null;
$unread = $unread ?? 0;
$messages = $messages ?? \DonorNexa\Core\Session::messages();
$scripts = $scripts ?? [];
$pageTitle = ($title ?? '') !== '' ? $title . ' · DonorNexa' : 'DonorNexa · Connecting Donors. Bridging Lives. Saving Hope';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="DonorNexa connects verified hospitals with eligible blood donors in real time.">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%230f172a'/%3E%3Cpath d='M16 8v16M8 16h16' stroke='%23ffffff' stroke-width='2.5' stroke-linecap='round'/%3E%3C/svg%3E">
    <script>
        (function() {
            var theme = localStorage.getItem('dn_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/common.css')) ?>">
    <?php
        $cssMap = [
            'public/'   => 'css/public.css',
            'auth/'     => 'css/auth.css',
            'donor/'    => 'css/donor.css',
            'hospital/' => 'css/hospital.css',
            'admin/'    => 'css/admin.css',
        ];
        $matchedCss = null;
        if (!empty($currentView)) {
            foreach ($cssMap as $prefix => $sheet) {
                if (str_starts_with($currentView, $prefix)) {
                    $matchedCss = $sheet;
                    break;
                }
            }
        }
    ?>
    <?php if ($matchedCss): ?>
    <link rel="stylesheet" href="<?= e(asset($matchedCss)) ?>">
    <?php endif; ?>
    <?php if (!empty($stylesheets)): ?>
        <?php foreach ($stylesheets as $sheet): ?>
        <link rel="stylesheet" href="<?= e(asset($sheet)) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body data-csrf="<?= e(\DonorNexa\Core\Csrf::token()) ?>" data-api="<?= e(api_url('')) ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<main id="main" class="container">
    <?php require __DIR__ . '/partials/flash.php'; ?>
    <?= $content ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= e(asset($src)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
