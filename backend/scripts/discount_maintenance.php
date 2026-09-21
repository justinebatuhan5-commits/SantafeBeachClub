<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/db.php';
// The shared bootstrap expires unpaid room holds and purges retained ID images.
echo "Discount holds and ID retention processed.\n";
