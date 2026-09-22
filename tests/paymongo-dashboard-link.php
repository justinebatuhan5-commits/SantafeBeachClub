<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
if ($source === false) throw new RuntimeException('Unable to read refund administration page.');

foreach (['https://dashboard.paymongo.com/login', 'Open PayMongo Dashboard', 'target="_blank"', 'rel="noopener noreferrer"'] as $fragment) {
    if (!str_contains($source, $fragment)) throw new RuntimeException('Missing safe PayMongo dashboard link property: ' . $fragment);
}

echo "Approved refunds provide a safe link to the official PayMongo Dashboard.\n";
