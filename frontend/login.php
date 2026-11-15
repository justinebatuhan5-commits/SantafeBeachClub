<?php
// This page no longer exists. Access is via dedicated portals only.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Error</title>
    <link rel="stylesheet" href="assets/css/pages/not-found.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/not-found.css'); ?>">
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
<?php require __DIR__ . '/views/auth/_login_404.php'; ?>
</body>
</html>
