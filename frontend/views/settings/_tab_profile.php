<?php
$current_admin    = $pageData['currentAdmin'];
$my_profile_photo = $pageData['myProfilePhoto'];
$hasProfilePhoto  = $pageData['hasProfilePhoto'];
$isAdmin          = $pageData['isAdmin'];
$active_tab       = $pageData['activeTab'];
?>
<div class="tab-panel <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="tab-profile">
    <div class="settings-card">
        <h3>Profile Photo</h3>
        <p class="card-desc">Upload a personal photo for your admin/receptionist profile displayed across the dashboard, sidebar, and headers.</p>
        <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap; margin-top:16px;">
            <div style="position:relative;">
                <?php if ($hasProfilePhoto): ?>
                    <img src="<?php echo htmlspecialchars((string)$my_profile_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Photo" style="width:84px; height:84px; border-radius:50%; object-fit:cover; border:3px solid var(--color-primary, #7C533C); box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div style="width:84px; height:84px; border-radius:50%; background:linear-gradient(135deg, #7C533C, #5C3D2B); color:#FFF; display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:700; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                        <?php echo htmlspecialchars(strtoupper(substr($current_admin !== '' ? $current_admin : 'U', 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="flex:1; min-width:260px;">
                <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:10px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile_photo">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Select New Image (JPG, PNG, WEBP, max 5MB):</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" required style="padding:8px 12px; font-size:13px; border:1px solid #D1D5DB; border-radius:8px; background:#F9FAFB; flex:1;">
                        <button type="submit" class="btn-save" style="padding:9px 18px; font-size:13px; white-space:nowrap;">Upload Photo</button>
                    </div>
                </form>

                <?php if ($hasProfilePhoto): ?>
                    <form method="POST" style="margin-top:8px;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="remove_profile_photo">
                        <button type="submit" style="background:none; border:none; color:#DC2626; font-size:12px; font-weight:600; cursor:pointer; padding:0; display:flex; align-items:center; gap:4px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            Remove Current Photo
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <div class="settings-card">
            <h3>Username</h3>
            <p class="card-desc">Update the username you use to sign in.</p>
            <form method="POST" class="settings-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_username">
                <div class="form-row">
                    <div>
                        <label>Current Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($current_admin, ENT_QUOTES, 'UTF-8'); ?>" disabled style="background:#F9FAFB; color:#888;">
                    </div>
                    <div>
                        <label>New Username</label>
                        <input type="email" name="new_username" required pattern=".+@(santafebeachclub|beachclub)\.com$" title="Must end with @santafebeachclub.com" placeholder="name@santafebeachclub.com">
                    </div>
                </div>
                <div class="form-row single">
                    <div>
                        <label>Confirm with Password</label>
                        <input type="password" name="password_for_username" required placeholder="Your current password">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">Update Username</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
