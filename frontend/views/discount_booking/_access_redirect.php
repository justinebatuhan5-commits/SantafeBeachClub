<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Open your booking</title>
    <?php require __DIR__ . '/../../partials/_responsive.php'; ?>
</head>
<body>
    <p id="accessMessage">Opening your private booking…</p>
    <form id="accessForm" method="post" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="access">
        <input type="hidden" name="access_token" id="accessToken">
    </form>
    <noscript>Enable JavaScript to open your private booking link, or contact reception.</noscript>
    <script src="assets/js/pages/discount-access.js?v=<?= filemtime(__DIR__ . '/../../assets/js/pages/discount-access.js') ?>"></script>
</body>
</html>
