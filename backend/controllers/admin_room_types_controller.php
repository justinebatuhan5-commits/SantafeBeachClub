<?php
require_once __DIR__ . '/../helpers/cloudinary_helper.php';
require_once __DIR__ . '/../helpers/file_scanner.php';

function sfbc_room_type_labels(): array
{
    return [
        'beachview_duplex' => 'Beachview Duplex',
        'seaview_duplex'   => 'Seaview Duplex',
        'beach_villa'      => 'Beach Villa',
        'standard_room'    => 'Standard Room',
        'standard_king'    => 'Standard Family Room',
    ];
}

function sfbc_room_type_upload_image(array $file, string $typeSlug, string $frontendDir): string|false
{
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize     = 8 * 1024 * 1024;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return false;
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return false;
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($realMime, $allowedMime, true) || (int)$file['size'] > $maxSize) {
        return false;
    }

    $scanResult = scan_uploaded_file($file['tmp_name'], $ext);
    if (!$scanResult['safe']) {
        return false;
    }

    $cleanSlug   = preg_replace('/[^a-z0-9_]/', '', $typeSlug);
    $cloudResult = cloudinary_upload($file['tmp_name'], 'sfbc_rooms_' . $cleanSlug);
    if ($cloudResult['success'] && !empty($cloudResult['url'])) {
        return $cloudResult['url'];
    }

    $relativeDir = 'assets/rooms/' . $cleanSlug . '/';
    $absDir      = $frontendDir . '/' . $relativeDir;
    if (!is_dir($absDir)) {
        @mkdir($absDir, 0755, true);
    }

    $filename = uniqid($typeSlug . '_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $absDir . $filename)) {
        return false;
    }

    return $relativeDir . $filename;
}

function sfbc_room_types_handle_post(mysqli $conn, string $admin, array $typeLabels, string $frontendDir): string
{
    require_csrf_token();
    $action   = $_POST['action'] ?? '';
    $typeSlug = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['type_slug'] ?? ''));

    if (!array_key_exists($typeSlug, $typeLabels)) {
        return 'Invalid room type.';
    }

    if ($action === 'set_primary') {
        if (!empty($_FILES['primary_photo']['name'])) {
            $path = sfbc_room_type_upload_image($_FILES['primary_photo'], $typeSlug, $frontendDir);
            if ($path === false) {
                $_SESSION['rt_error'] = 'Upload failed. Use JPG/PNG/WEBP/GIF, max 8 MB.';
            } else {
                $stmt = $conn->prepare('UPDATE room_types SET image_url = ? WHERE name = ?');
                $stmt->bind_param('ss', $path, $typeSlug);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'Room Photo Updated', "Primary photo for $typeSlug set to $path");
                $_SESSION['rt_success'] = 'Primary photo for <strong>' . htmlspecialchars($typeLabels[$typeSlug]) . '</strong> updated.';
            }
        } else {
            $_SESSION['rt_error'] = 'Please choose an image file.';
        }
        header('Location: admin_room_types');
        exit;
    }

    if ($action === 'add_gallery') {
        $uploadedPaths = [];
        if (!empty($_FILES['gallery_photos']['name'][0])) {
            $count = count($_FILES['gallery_photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['gallery_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $singleFile = [
                        'name'     => $_FILES['gallery_photos']['name'][$i],
                        'type'     => $_FILES['gallery_photos']['type'][$i],
                        'tmp_name' => $_FILES['gallery_photos']['tmp_name'][$i],
                        'error'    => $_FILES['gallery_photos']['error'][$i],
                        'size'     => $_FILES['gallery_photos']['size'][$i],
                    ];
                    $path = sfbc_room_type_upload_image($singleFile, $typeSlug, $frontendDir);
                    if ($path !== false) {
                        $uploadedPaths[] = $path;
                    }
                }
            }
        } elseif (!empty($_FILES['gallery_photo']['name'])) {
            $path = sfbc_room_type_upload_image($_FILES['gallery_photo'], $typeSlug, $frontendDir);
            if ($path !== false) {
                $uploadedPaths[] = $path;
            }
        }

        if (!empty($uploadedPaths)) {
            $sel = $conn->prepare('SELECT gallery_images FROM room_types WHERE name = ?');
            $sel->bind_param('s', $typeSlug);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();

            $existing = !empty($row['gallery_images']) ? $row['gallery_images'] : '';
            $appended = implode(',', $uploadedPaths);
            $newList  = $existing ? $existing . ',' . $appended : $appended;

            $stmt = $conn->prepare('UPDATE room_types SET gallery_images = ? WHERE name = ?');
            $stmt->bind_param('ss', $newList, $typeSlug);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Room Gallery Photos Added', count($uploadedPaths) . " photos added to $typeSlug");
            $_SESSION['rt_success'] = count($uploadedPaths) . ' gallery photo(s) added to <strong>' . htmlspecialchars($typeLabels[$typeSlug]) . '</strong>.';
        } else {
            $_SESSION['rt_error'] = 'Upload failed. Please choose valid image file(s) (JPG/PNG/WEBP/GIF, max 8 MB).';
        }
        header('Location: admin_room_types');
        exit;
    }

    if ($action === 'remove_gallery') {
        $removePath = (string)($_POST['remove_path'] ?? '');
        $sel = $conn->prepare('SELECT gallery_images FROM room_types WHERE name = ?');
        $sel->bind_param('s', $typeSlug);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        $list    = array_filter(array_map('trim', explode(',', (string)($row['gallery_images'] ?? ''))));
        $list    = array_values(array_filter($list, static fn(string $p): bool => $p !== $removePath));
        $newList = implode(',', $list);

        if ($removePath !== '' && !preg_match('#^https?://#i', $removePath)) {
            $localFile = realpath($frontendDir . '/' . ltrim($removePath, '/'));
            $roomsRoot = realpath($frontendDir . '/assets/rooms');
            if ($localFile !== false && $roomsRoot !== false && str_starts_with($localFile, $roomsRoot) && is_file($localFile)) {
                @unlink($localFile);
            }
        }

        $stmt = $conn->prepare('UPDATE room_types SET gallery_images = ? WHERE name = ?');
        $stmt->bind_param('ss', $newList, $typeSlug);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $admin, 'Room Gallery Photo Removed', "Removed from $typeSlug: $removePath");
        $_SESSION['rt_success'] = 'Photo removed from <strong>' . htmlspecialchars($typeLabels[$typeSlug]) . '</strong>.';
        header('Location: admin_room_types');
        exit;
    }

    if ($action === 'clear_primary') {
        $stmt = $conn->prepare('UPDATE room_types SET image_url = NULL WHERE name = ?');
        $stmt->bind_param('s', $typeSlug);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $admin, 'Room Photo Cleared', "Primary photo for $typeSlug cleared");
        $_SESSION['rt_success'] = 'Primary photo cleared.';
        header('Location: admin_room_types');
        exit;
    }

    if ($action === 'update_price') {
        $newPrice = (float)($_POST['new_price'] ?? 0);
        if ($newPrice >= 0) {
            $stmt = $conn->prepare('UPDATE room_types SET price = ? WHERE name = ?');
            $stmt->bind_param('ds', $newPrice, $typeSlug);
            $stmt->execute();
            $stmt->close();

            $stmt2 = $conn->prepare('UPDATE rooms SET price_per_night = ? WHERE type = ?');
            $stmt2->bind_param('ds', $newPrice, $typeSlug);
            $stmt2->execute();
            $stmt2->close();

            log_activity($conn, $admin, 'Room Price Updated', "Price for $typeSlug set to $newPrice");
            $_SESSION['rt_success'] = 'Base price for <strong>' . htmlspecialchars($typeLabels[$typeSlug]) . '</strong> updated to ₱' . number_format($newPrice, 2) . '.';
        } else {
            $_SESSION['rt_error'] = 'Invalid price format.';
        }
        header('Location: admin_room_types');
        exit;
    }

    return '';
}

/**
 * Handles room type photo & base price updates and prepares cards for admin_room_types.php.
 */
function sfbc_handle_admin_room_types_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $admin       = (string)$_SESSION['admin_username'];
    $frontendDir = __DIR__ . '/../../frontend';
    $typeLabels  = sfbc_room_type_labels();
    $error       = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = sfbc_room_types_handle_post($conn, $admin, $typeLabels, $frontendDir);
    }

    $success = (string)($_SESSION['rt_success'] ?? '');
    if ($error === '') {
        $error = (string)($_SESSION['rt_error'] ?? '');
    }
    unset($_SESSION['rt_success'], $_SESSION['rt_error']);

    $rtData = [];
    $q = $conn->query('SELECT name, image_url, gallery_images, price FROM room_types');
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $rtData[$row['name']] = $row;
        }
    }

    $defaultPhotos = [
        'beachview_duplex' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=60',
        'seaview_duplex'   => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=800&q=60',
        'beach_villa'      => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=60',
        'standard_room'    => 'assets/rooms/standard/standard-room-1.png',
        'standard_king'    => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=800&q=60',
    ];
    $defaultPrices = [
        'beachview_duplex' => 6900.00,
        'seaview_duplex'   => 7900.00,
        'beach_villa'      => 7900.00,
        'standard_room'    => 2900.00,
        'standard_king'    => 4300.00,
    ];

    $cards = [];
    foreach ($typeLabels as $slug => $label) {
        $data       = $rtData[$slug] ?? [];
        $primary    = $data['image_url'] ?? null;
        $display    = $primary ?: ($defaultPhotos[$slug] ?? '');
        $galleryRaw = (string)($data['gallery_images'] ?? '');
        $gallery    = array_values(array_filter(array_map('trim', explode(',', $galleryRaw))));
        $price      = isset($data['price']) && (float)$data['price'] > 0
            ? (float)$data['price']
            : ($defaultPrices[$slug] ?? 0.0);

        $cards[] = [
            'slug'    => $slug,
            'label'   => $label,
            'primary' => $primary,
            'display' => $display,
            'gallery' => $gallery,
            'price'   => $price,
        ];
    }

    return [
        'success'   => $success,
        'error'     => $error,
        'cards'     => $cards,
        'csrfField' => csrf_field(),
        'versions'  => [
            'adminCss' => filemtime($frontendDir . '/assets/css/admin.css'),
            'pageCss'  => file_exists($frontendDir . '/assets/css/pages/admin-room-types.css')
                ? filemtime($frontendDir . '/assets/css/pages/admin-room-types.css')
                : 1,
            'dialogJs' => filemtime($frontendDir . '/assets/js/site-dialog.js'),
            'pageJs'   => file_exists($frontendDir . '/assets/js/pages/admin-room-types.js')
                ? filemtime($frontendDir . '/assets/js/pages/admin-room-types.js')
                : 1,
        ],
    ];
}
