<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/db.php';
discount_maintenance($conn);
echo "Discount holds and ID retention processed.\n";
