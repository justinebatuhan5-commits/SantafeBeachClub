<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';

$admin = $_SESSION['admin_username'];

$type_labels = [
    'beachview_duplex' => 'Beachview Duplex',
    'seaview_duplex'   => 'Seaview Duplex',
    'beach_villa'      => 'Beach Villa',
    'standard_room'    => 'Standard Room',
    'standard_king'    => 'Standard Family Room',
];

$success = '';
$error   = '';

/* ─── Helper: upload one image ─────────────────────────────── */
function upload_room_image(array $file, string $type_slug): string|false {
    $allowed_ext  = ['jpg','jpeg','png','webp','gif'];
    $allowed_mime = ['image/jpeg','image/png','image/webp','image/gif'];
    $max_size     = 8 * 1024 * 1024; // 8 MB

    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true))        return false;
    if (!in_array($file['type'], $allowed_mime, true)) return false;
    if ($file['size'] > $max_size)                  return false;

    // Try Cloudinary first
    $cleanSlug = preg_replace('/[^a-z0-9_]/', '', $type_slug);
    $cloudResult = cloudinary_upload($file['tmp_name'], 'sfbc_rooms_' . $cleanSlug);
    if ($cloudResult['success'] && !empty($cloudResult['url'])) {
        return $cloudResult['url'];
    }

    // Local fallback
    $dir = "assets/rooms/" . $cleanSlug . "/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid($type_slug . '_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return false;
    return $dir . $filename;
}

/* ─── Handle POST ────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action    = $_POST['action'] ?? '';
    $type_slug = preg_replace('/[^a-z0-9_]/', '', $_POST['type_slug'] ?? '');

    if (!array_key_exists($type_slug, $type_labels)) {
        $error = "Invalid room type.";
    } elseif ($action === 'set_primary') {
        // Upload new primary image
        if (!empty($_FILES['primary_photo']['name'])) {
            $path = upload_room_image($_FILES['primary_photo'], $type_slug);
            if ($path === false) {
                $_SESSION['rt_error'] = "Upload failed. Use JPG/PNG/WEBP/GIF, max 8 MB.";
            } else {
                $stmt = $conn->prepare("UPDATE room_types SET image_url = ? WHERE name = ?");
                $stmt->bind_param("ss", $path, $type_slug);
                $stmt->execute();
                $stmt->close();
                log_activity($conn, $admin, 'Room Photo Updated', "Primary photo for $type_slug set to $path");
                $_SESSION['rt_success'] = "Primary photo for <strong>" . htmlspecialchars($type_labels[$type_slug]) . "</strong> updated.";
            }
        } else {
            $_SESSION['rt_error'] = "Please choose an image file.";
        }
        header('Location: admin_room_types');
        exit;

    } elseif ($action === 'add_gallery') {
        // Append one or multiple gallery photos
        $uploaded_paths = [];
        if (!empty($_FILES['gallery_photos']['name'][0])) {
            $count = count($_FILES['gallery_photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['gallery_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $single_file = [
                        'name'     => $_FILES['gallery_photos']['name'][$i],
                        'type'     => $_FILES['gallery_photos']['type'][$i],
                        'tmp_name' => $_FILES['gallery_photos']['tmp_name'][$i],
                        'error'    => $_FILES['gallery_photos']['error'][$i],
                        'size'     => $_FILES['gallery_photos']['size'][$i]
                    ];
                    $path = upload_room_image($single_file, $type_slug);
                    if ($path !== false) {
                        $uploaded_paths[] = $path;
                    }
                }
            }
        } elseif (!empty($_FILES['gallery_photo']['name'])) {
            $path = upload_room_image($_FILES['gallery_photo'], $type_slug);
            if ($path !== false) {
                $uploaded_paths[] = $path;
            }
        }

        if (!empty($uploaded_paths)) {
            $row = $conn->query("SELECT gallery_images FROM room_types WHERE name='" . $conn->real_escape_string($type_slug) . "'")->fetch_assoc();
            $existing = !empty($row['gallery_images']) ? $row['gallery_images'] : '';
            $appended = implode(',', $uploaded_paths);
            $new_list = $existing ? $existing . ',' . $appended : $appended;

            $stmt = $conn->prepare("UPDATE room_types SET gallery_images = ? WHERE name = ?");
            $stmt->bind_param("ss", $new_list, $type_slug);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $admin, 'Room Gallery Photos Added', count($uploaded_paths) . " photos added to $type_slug");
            $_SESSION['rt_success'] = count($uploaded_paths) . " gallery photo(s) added to <strong>" . htmlspecialchars($type_labels[$type_slug]) . "</strong>.";
        } else {
            $_SESSION['rt_error'] = "Upload failed. Please choose valid image file(s) (JPG/PNG/WEBP/GIF, max 8 MB).";
        }
        header('Location: admin_room_types');
        exit;

    } elseif ($action === 'remove_gallery') {
        $remove_path = $_POST['remove_path'] ?? '';
        $row = $conn->query("SELECT gallery_images FROM room_types WHERE name='" . $conn->real_escape_string($type_slug) . "'")->fetch_assoc();
        $list = array_filter(array_map('trim', explode(',', $row['gallery_images'] ?? '')));
        $list = array_values(array_filter($list, fn($p) => $p !== $remove_path));
        $new_list = implode(',', $list);
        if (file_exists($remove_path)) @unlink($remove_path);
        $stmt = $conn->prepare("UPDATE room_types SET gallery_images = ? WHERE name = ?");
        $stmt->bind_param("ss", $new_list, $type_slug);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $admin, 'Room Gallery Photo Removed', "Removed from $type_slug: $remove_path");
        $_SESSION['rt_success'] = "Photo removed from <strong>" . htmlspecialchars($type_labels[$type_slug]) . "</strong>.";
        header('Location: admin_room_types');
        exit;

    } elseif ($action === 'clear_primary') {
        $stmt = $conn->prepare("UPDATE room_types SET image_url = NULL WHERE name = ?");
        $stmt->bind_param("s", $type_slug);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $admin, 'Room Photo Cleared', "Primary photo for $type_slug cleared");
        $_SESSION['rt_success'] = "Primary photo cleared.";
        header('Location: admin_room_types');
        exit;
        
    } elseif ($action === 'update_price') {
        $new_price = floatval($_POST['new_price'] ?? 0);
        if ($new_price >= 0) {
            $stmt = $conn->prepare("UPDATE room_types SET price = ? WHERE name = ?");
            $stmt->bind_param("ds", $new_price, $type_slug);
            $stmt->execute();
            $stmt->close();
            
            $stmt2 = $conn->prepare("UPDATE rooms SET price_per_night = ? WHERE type = ?");
            $stmt2->bind_param("ds", $new_price, $type_slug);
            $stmt2->execute();
            $stmt2->close();
            
            log_activity($conn, $admin, 'Room Price Updated', "Price for $type_slug set to $new_price");
            $_SESSION['rt_success'] = "Base price for <strong>" . htmlspecialchars($type_labels[$type_slug]) . "</strong> updated to ₱" . number_format($new_price, 2) . ".";
        } else {
            $_SESSION['rt_error'] = "Invalid price format.";
        }
        header('Location: admin_room_types');
        exit;
    }
}

/* ─── Read flash messages from session ───────────────────────── */
$success = $_SESSION['rt_success'] ?? '';
$error   = $_SESSION['rt_error']   ?? '';
unset($_SESSION['rt_success'], $_SESSION['rt_error']);

/* ─── Fetch current room_types data ─────────────────────────── */
$rt_data = [];
$q = $conn->query("SELECT name, image_url, gallery_images, price FROM room_types");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $rt_data[$r['name']] = $r;
    }
}

$default_photos = [
    'beachview_duplex' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=60',
    'seaview_duplex'   => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=800&q=60',
    'beach_villa'      => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=60',
    'standard_room'    => 'assets/rooms/standard/standard-room-1.png',
    'standard_king'    => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=800&q=60',
];
$default_prices = [
    'beachview_duplex' => 6900.00,
    'seaview_duplex'   => 7900.00,
    'beach_villa'      => 7900.00,
    'standard_room'    => 2900.00,
    'standard_king'    => 4300.00,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Types & Photos — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <style>
        .rp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
        }
        .rp-card {
            background: var(--bg-card, #fff);
            border: 1px solid var(--border-color, #E5E7EB);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            transition: box-shadow .2s;
        }
        .rp-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.12); }
        .rp-card-header {
            padding: 16px 18px 12px;
            border-bottom: 1px solid var(--border-color, #E5E7EB);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rp-card-header h3 { margin: 0; font-size: 15px; font-weight: 700; }
        .rp-type-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            background: #EFF6FF;
            color: #1D4ED8;
            letter-spacing: .4px;
        }
        /* Primary photo section */
        .rp-primary-wrap {
            position: relative;
            height: 200px;
            background: #F3F4F6;
            overflow: hidden;
        }
        .rp-primary-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .rp-primary-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(0,0,0,.5);
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .rp-primary-actions {
            position: absolute;
            bottom: 10px;
            right: 10px;
            display: flex;
            gap: 6px;
        }
        .rp-btn-sm {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            line-height: 1;
        }
        .rp-btn-primary  { background: #1D4ED8; color: #fff; }
        .rp-btn-primary:hover { background: #1E40AF; }
        .rp-btn-danger   { background: #DC2626; color: #fff; }
        .rp-btn-danger:hover { background: #B91C1C; }
        .rp-btn-secondary { background: #F3F4F6; color: #374151; }
        .rp-btn-secondary:hover { background: #E5E7EB; }
        /* Gallery row */
        .rp-gallery-section {
            padding: 14px 18px;
            border-top: 1px solid var(--border-color, #E5E7EB);
        }
        .rp-gallery-section h4 {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted, #6B7280);
            margin: 0 0 10px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .rp-gallery-thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }
        .rp-thumb-wrap {
            position: relative;
            width: 72px;
            height: 72px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color, #E5E7EB);
        }
        .rp-thumb-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .rp-thumb-del {
            position: absolute;
            top: 3px;
            right: 3px;
            background: rgba(220,38,38,.85);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 13px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rp-upload-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rp-dropzone {
            width: 100%;
            border: 2px dashed #CBD5E1;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-bottom: 8px;
        }
        .rp-dropzone:hover, .rp-dropzone.drag-over {
            border-color: #84563C;
            background: #FDF8F5;
        }
        .rp-dropzone svg {
            color: #84563C;
        }
        .rp-dropzone-text {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .rp-dropzone-sub {
            font-size: 10.5px;
            color: #94A3B8;
        }
        .rp-file-input {
            flex: 1;
            min-width: 0;
            padding: 7px 10px;
            border: 1px solid var(--border-color, #E5E7EB);
            border-radius: 8px;
            font-size: 13px;
            background: var(--bg-input, #F9FAFB);
        }
        .rp-upload-btn {
            white-space: nowrap;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-weight: 600;
            font-size: 14px;
        }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .rp-empty-thumb {
            color: var(--text-muted, #9CA3AF);
            font-size: 12px;
            font-style: italic;
        }
    </style>
</head>
<body>
<?php $active_page = 'room_photos'; include __DIR__ . '/partials/_sidebar.php'; ?>

<main class="main-content">
    <?php
    $page_title    = 'Room Types & Photos';
    $page_subtitle = 'Manage the base price and photos shown on the rooms page for each accommodation type.';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <?php if ($success): ?>
        <div class="alert alert-success">✔ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">✖ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="rp-grid">
    <?php foreach ($type_labels as $slug => $label):
        $data    = $rt_data[$slug] ?? [];
        $primary = $data['image_url'] ?? null;
        $display = $primary ?: ($default_photos[$slug] ?? '');
        $gallery_raw = $data['gallery_images'] ?? '';
        $gallery = array_filter(array_map('trim', explode(',', $gallery_raw)));
    ?>
    <div class="rp-card">

        <!-- Card header -->
        <div class="rp-card-header">
            <h3><?php echo htmlspecialchars($label); ?></h3>
            <span class="rp-type-badge"><?php echo htmlspecialchars($slug); ?></span>
        </div>

        <!-- Primary Photo -->
        <div class="rp-primary-wrap">
            <?php if ($display): ?>
                <img src="<?php echo htmlspecialchars($display); ?>" alt="<?php echo htmlspecialchars($label); ?>" class="rp-primary-img">
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9CA3AF;">
                    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
            <?php endif; ?>
            <span class="rp-primary-badge">Primary Photo</span>
            <?php if ($primary): ?>
            <div class="rp-primary-actions">
                <form method="POST" onsubmit="return false;" data-confirm-title="Reset Photo" data-confirm-msg="Reset to the default photo? Your current primary photo will be removed." data-confirm-icon="image" data-confirm-icon-bg="#FEF3C7">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="clear_primary">
                    <input type="hidden" name="type_slug" value="<?php echo $slug; ?>">
                    <button type="submit" class="rp-btn-sm rp-btn-danger">Reset</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Price Update -->
        <div class="rp-gallery-section" style="background:#FAFAFA; border-bottom:1px solid var(--border-color,#E5E7EB);">
            <h4>Base Price (Per Night)</h4>
            <form method="POST" class="rp-upload-row">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_price">
                <input type="hidden" name="type_slug" value="<?php echo $slug; ?>">
                <div style="display:flex; align-items:center; gap:8px; flex:1;">
                    <span style="font-weight:bold; color:#374151;">₱</span>
                    <input type="number" step="0.01" min="0" name="new_price" value="<?php echo isset($data['price']) && $data['price'] > 0 ? $data['price'] : ($default_prices[$slug] ?? 0); ?>" required class="rp-file-input" style="max-width:150px;">
                </div>
                <button type="submit" class="rp-btn-sm rp-btn-primary rp-upload-btn">Save Price</button>
            </form>
        </div>

        <!-- Upload new primary -->
        <div class="rp-gallery-section">
            <h4>Change Primary Photo</h4>
            <p style="font-size:11.5px; color:#6B7280; margin:0 0 8px;">Recommended size: <strong>800 × 500 px</strong> (or 1200 × 750 px), Aspect ratio <strong>16:10 / 16:9</strong> landscape, JPG or WebP under 300KB.</p>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="set_primary">
                <input type="hidden" name="type_slug" value="<?php echo $slug; ?>">
                
                <div class="rp-dropzone" onclick="this.parentElement.querySelector('.rp-file-input').click()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div class="rp-dropzone-text">Drag &amp; drop photo here</div>
                    <div class="rp-dropzone-sub">or click to browse from computer</div>
                </div>

                <div class="rp-upload-row">
                    <input type="file" name="primary_photo" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" class="rp-file-input">
                    <button type="submit" class="rp-btn-sm rp-btn-primary rp-upload-btn" onclick="return validateFileInput(this,'primary_photo')">Upload Primary</button>
                </div>
            </form>
        </div>

        <!-- Gallery -->
        <div class="rp-gallery-section">
            <h4>Gallery Photos (modal slideshow)</h4>
            <div class="rp-gallery-thumbs">
                <?php if (empty($gallery)): ?>
                    <span class="rp-empty-thumb">No gallery photos yet.</span>
                <?php else: ?>
                    <?php foreach ($gallery as $gpath): ?>
                    <div class="rp-thumb-wrap">
                        <img src="<?php echo htmlspecialchars($gpath); ?>" alt="Gallery"
                             onclick="openAdminLightbox(this)" style="cursor:zoom-in;">
                        <form method="POST" onsubmit="return false;" data-confirm-title="Remove Photo" data-confirm-msg="Remove this gallery photo? This cannot be undone." data-confirm-icon="trash" data-confirm-icon-bg="#FEE2E2" style="margin:0">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="remove_gallery">
                            <input type="hidden" name="type_slug" value="<?php echo $slug; ?>">
                            <input type="hidden" name="remove_path" value="<?php echo htmlspecialchars($gpath); ?>">
                            <button type="submit" class="rp-thumb-del" title="Remove">×</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_gallery">
                <input type="hidden" name="type_slug" value="<?php echo $slug; ?>">
                
                <div class="rp-dropzone" onclick="this.parentElement.querySelector('.rp-file-input').click()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div class="rp-dropzone-text">Drag &amp; drop multiple gallery photos here</div>
                    <div class="rp-dropzone-sub">or click to browse from computer (select multiple files)</div>
                </div>

                <div class="rp-upload-row">
                    <input type="file" name="gallery_photos[]" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" multiple class="rp-file-input">
                    <button type="submit" class="rp-btn-sm rp-btn-secondary rp-upload-btn" onclick="return validateFileInput(this,'gallery_photos')">Upload Photos</button>
                </div>
            </form>
        </div>

    </div><!-- /.rp-card -->
    <?php endforeach; ?>
    </div><!-- /.rp-grid -->

    <!-- ── Admin Photo Lightbox ── -->
    <div id="admin-lightbox" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.92); align-items:center; justify-content:center;">
        <!-- Close -->
        <button onclick="closeAdminLightbox()" style="position:absolute; top:18px; right:18px; background:rgba(255,255,255,0.15); border:none; color:#fff; width:40px; height:40px; border-radius:50%; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.28)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>
        <!-- Prev -->
        <button id="lb-prev" onclick="lightboxNav(-1)" style="position:absolute; left:18px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.13); border:none; color:#fff; width:48px; height:48px; border-radius:50%; font-size:26px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.26)'" onmouseout="this.style.background='rgba(255,255,255,0.13)'">&#8249;</button>
        <!-- Photo -->
        <img id="lb-img" src="" alt="" style="max-width:min(800px, calc(100vw - 130px)); max-height:calc(100vh - 120px); border-radius:10px; object-fit:contain; display:block; box-shadow:0 20px 60px rgba(0,0,0,0.6);">
        <!-- Next -->
        <button id="lb-next" onclick="lightboxNav(1)" style="position:absolute; right:18px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.13); border:none; color:#fff; width:48px; height:48px; border-radius:50%; font-size:26px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.26)'" onmouseout="this.style.background='rgba(255,255,255,0.13)'">&#8250;</button>
        <!-- Caption -->
        <div id="lb-caption" style="position:absolute; bottom:0; left:0; right:0; text-align:center; padding:18px; color:rgba(255,255,255,0.85); font-size:14px; letter-spacing:0.3px;"></div>
    </div>

</main>
<script src="assets/js/sidebar-toggle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropzones = document.querySelectorAll('.rp-dropzone');
    
    dropzones.forEach(zone => {
        const form = zone.closest('form');
        const input = form.querySelector('.rp-file-input');
        const textEl = zone.querySelector('.rp-dropzone-text');

        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('drag-over');
            }, false);
        });

        zone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                input.files = files;
                if (textEl) {
                    if (files.length === 1) {
                        textEl.textContent = 'Selected: ' + files[0].name;
                    } else {
                        textEl.textContent = 'Selected ' + files.length + ' photos ready to upload';
                    }
                }
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        input.addEventListener('change', function() {
            if (this.files && this.files.length > 0 && textEl) {
                if (this.files.length === 1) {
                    textEl.textContent = 'Selected: ' + this.files[0].name;
                } else {
                    textEl.textContent = 'Selected ' + this.files.length + ' photos ready to upload';
                }
            }
        });
    });
});

// ── File validation (no required attr, friendlier UX) ──
function validateFileInput(btn, fieldName) {
    var form = btn.closest('form');
    var input = form.querySelector('.rp-file-input');
    if (!input || !input.files || input.files.length === 0) {
        // Highlight the dropzone
        var zone = form.querySelector('.rp-dropzone');
        if (zone) {
            zone.style.borderColor = '#EF4444';
            zone.style.background = '#FEF2F2';
            setTimeout(function() {
                zone.style.borderColor = '';
                zone.style.background = '';
            }, 2500);
        }
        alert('Please select or drop a photo first.');
        return false;
    }
    return true;
}

// ── Admin Lightbox ─────────────────────────────────────
var lbImages = [];
var lbIndex  = 0;

function openAdminLightbox(imgEl) {
    // Collect all gallery images in the same card
    var card = imgEl.closest('.rp-card');
    var allImgs = Array.from(card.querySelectorAll('.rp-gallery-thumbs img, .rp-primary-img'));
    lbImages = allImgs.map(function(i) {
        return { src: i.src, caption: i.alt || '' };
    });
    // Fallback: if not inside a card, just show the clicked image
    if (!lbImages.length) {
        lbImages = [{ src: imgEl.src, caption: imgEl.alt || '' }];
    }
    lbIndex = lbImages.findIndex(function(i) { return i.src === imgEl.src; });
    if (lbIndex < 0) lbIndex = 0;
    renderLightbox();
    var lb = document.getElementById('admin-lightbox');
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', lbKeyHandler);
}

function closeAdminLightbox() {
    document.getElementById('admin-lightbox').style.display = 'none';
    document.body.style.overflow = '';
    document.removeEventListener('keydown', lbKeyHandler);
}

function lightboxNav(dir) {
    lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
    renderLightbox();
}

function renderLightbox() {
    var img = lbImages[lbIndex];
    document.getElementById('lb-img').src = img.src;
    var total = lbImages.length;
    document.getElementById('lb-caption').textContent = (lbIndex + 1) + ' / ' + total + (img.caption ? '  •  ' + img.caption : '');
    document.getElementById('lb-prev').style.display = total > 1 ? 'flex' : 'none';
    document.getElementById('lb-next').style.display = total > 1 ? 'flex' : 'none';
}

function lbKeyHandler(e) {
    if (e.key === 'ArrowRight') lightboxNav(1);
    if (e.key === 'ArrowLeft')  lightboxNav(-1);
    if (e.key === 'Escape')     closeAdminLightbox();
}

// Click backdrop to close
document.getElementById('admin-lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeAdminLightbox();
});
</script>
</body>
</html>
