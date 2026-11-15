<?php
$admins        = $pageData['admins'];
$current_admin = $pageData['currentAdmin'];
$active_tab    = $pageData['activeTab'];
?>
<div class="tab-panel <?php echo $active_tab === 'users' ? 'active' : ''; ?>" id="tab-users">
    <div class="settings-card">
        <h3>Admin Accounts</h3>
        <p class="card-desc">All reception staff with dashboard access. You cannot remove your own account.</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td>
                            <div class="admin-row-info">
                                <div class="admin-avatar"><?php echo htmlspecialchars(strtoupper(substr((string)$admin['username'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div>
                                    <?php echo htmlspecialchars((string)$admin['username'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($admin['username'] === $current_admin): ?>
                                        <span class="badge-you">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="color: var(--color-text-muted); font-size:13px;">
                            <?php echo htmlspecialchars(date('M j, Y', strtotime((string)$admin['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td style="text-align:right;">
                            <form method="POST" class="js-delete-admin-form" data-username="<?php echo htmlspecialchars((string)$admin['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="admin_id" value="<?php echo (int)$admin['id']; ?>">
                                <button type="submit" class="btn-delete" <?php echo ($admin['username'] === $current_admin) ? 'disabled title="Cannot delete your own account"' : ''; ?>>
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="settings-card">
        <h3>Add New Admin</h3>
        <p class="card-desc">Create a new reception staff account. Share credentials securely.</p>
        <form method="POST" class="settings-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_admin">
            <div class="form-row">
                <div>
                    <label>Username</label>
                    <input type="email" name="admin_username" required pattern=".+@(santafebeachclub|beachclub)\.com$" title="Must end with @santafebeachclub.com" placeholder="name@santafebeachclub.com">
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="admin_password" required minlength="8" placeholder="Min. 8 chars, upper, lower, number, symbol">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>
