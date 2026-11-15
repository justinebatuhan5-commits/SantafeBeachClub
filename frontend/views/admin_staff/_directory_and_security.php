<div class="admin-body">
    <?php if ($staffData['success'] !== ''): ?>
        <div class="alert alert-success"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($staffData['success']); ?></div>
    <?php endif; ?>
    <?php if ($staffData['error'] !== ''): ?>
        <div class="alert alert-error"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?php echo htmlspecialchars($staffData['error']); ?></div>
    <?php endif; ?>

    <div class="responsive-stack staff-management-layout">
        <!-- Staff Table -->
        <div class="admin-card staff-list-card" style="overflow:hidden;">
            <div class="admin-card-header" style="padding:20px 24px;border-bottom:1px solid var(--border-light);background:linear-gradient(135deg,rgba(124,83,60,0.04),rgba(180,130,90,0.02));">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#7C533C,#b4824a);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(124,83,60,0.3);flex-shrink:0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-main);">All Staff Accounts</h3>
                        <p style="margin:0;font-size:11px;color:var(--text-muted);">Manage team access &amp; permissions</p>
                    </div>
                </div>
                <button type="button" class="btn-primary" data-action="open-add-staff" style="display:flex;align-items:center;gap:6px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(124,83,60,0.25);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Staff
                </button>
            </div>

            <!-- Column Headers -->
            <div class="staff-grid-head" style="display:grid;grid-template-columns:2fr 1.8fr 1fr 1fr 1.6fr;gap:0;padding:10px 24px;background:rgba(248,250,252,0.9);border-bottom:1px solid var(--border-light);">
                <span style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;">Account</span>
                <span style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;">OTP Email</span>
                <span style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;">Role</span>
                <span style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;">Status</span>
                <span style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;">Actions</span>
            </div>

            <!-- Staff Rows -->
            <div>
            <?php foreach ($staffData['staffRows'] as $s):
                $isLocked  = $s['is_locked'];
                $isMe      = $s['is_me'];
                $roleColor = $s['role'] === 'admin' ? '#7C533C' : '#0369A1';
                $roleBg    = $s['role'] === 'admin' ? 'rgba(124,83,60,0.1)' : 'rgba(3,105,161,0.1)';
                $staffType = $s['user_type'] ?? $s['role'];
            ?>
            <div class="staff-grid-row <?php echo $isLocked ? 'is-locked' : ''; ?>" style="display:grid;grid-template-columns:2fr 1.8fr 1fr 1fr 1.6fr;gap:0;align-items:center;padding:14px 24px;border-bottom:1px solid var(--border-light);">
                <!-- Account -->
                <div style="display:flex;align-items:center;gap:11px;min-width:0;">
                    <div style="position:relative;flex-shrink:0;">
                        <?php if ($s['has_avatar']): ?>
                            <img src="<?php echo htmlspecialchars((string)$s['profile_photo']); ?>" alt="Avatar" style="width:42px;height:42px;border-radius:12px;object-fit:cover;border:2px solid <?php echo $isLocked ? '#FECACA' : 'rgba(124,83,60,0.2)'; ?>;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                        <?php else: ?>
                            <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,<?php echo $roleColor; ?>,<?php echo $roleColor; ?>bb);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.15);"><?php echo htmlspecialchars(strtoupper(substr((string)$s['username'], 0, 1))); ?></div>
                        <?php endif; ?>
                        <button type="button" title="Update Photo"
                            data-action="open-edit-photo"
                            data-id="<?php echo (int)$s['id']; ?>"
                            data-username="<?php echo htmlspecialchars((string)$s['username'], ENT_QUOTES); ?>"
                            data-photo="<?php echo htmlspecialchars((string)($s['profile_photo'] ?? ''), ENT_QUOTES); ?>"
                            data-type="<?php echo htmlspecialchars($staffType, ENT_QUOTES); ?>"
                            style="position:absolute;bottom:-3px;right:-3px;width:19px;height:19px;background:#fff;border:1.5px solid #E2E8F0;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;box-shadow:0 1px 4px rgba(0,0,0,0.12);">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#7C533C" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </button>
                    </div>
                    <div style="min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-main);display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                            <?php echo htmlspecialchars($s['short_name']); ?>
                            <?php if ($isMe): ?>
                                <span style="font-size:9px;font-weight:700;color:#7C533C;background:rgba(124,83,60,0.1);padding:1px 6px;border-radius:4px;border:1px solid rgba(124,83,60,0.2);text-transform:uppercase;letter-spacing:0.5px;">You</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;"><?php echo htmlspecialchars((string)$s['username']); ?></div>
                    </div>
                </div>

                <!-- OTP Email -->
                <div style="display:flex;align-items:center;gap:5px;min-width:0;">
                    <?php if (!empty($s['email'])): ?>
                        <span style="font-size:12px;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars((string)$s['email']); ?></span>
                    <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;color:#DC2626;background:#FEF2F2;padding:3px 8px;border-radius:5px;border:1px solid #FECACA;white-space:nowrap;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            No email
                        </span>
                    <?php endif; ?>
                    <button type="button" title="Edit OTP Email" class="staff-edit-email-btn"
                        data-action="open-edit-email"
                        data-id="<?php echo (int)$s['id']; ?>"
                        data-username="<?php echo htmlspecialchars((string)$s['username'], ENT_QUOTES); ?>"
                        data-email="<?php echo htmlspecialchars((string)($s['email'] ?? ''), ENT_QUOTES); ?>"
                        data-type="<?php echo htmlspecialchars($staffType, ENT_QUOTES); ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    </button>
                </div>

                <!-- Role -->
                <div>
                    <form method="POST" style="display:inline;">
                        <?php echo $staffData['csrfField']; ?>
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="staff_id" value="<?php echo (int)$s['id']; ?>">
                        <input type="hidden" name="staff_type" value="<?php echo htmlspecialchars($staffType); ?>">
                        <select name="new_role" class="js-role-select" style="padding:5px 8px;border-radius:7px;border:1.5px solid <?php echo $roleColor; ?>33;font-family:Outfit,sans-serif;font-size:11px;font-weight:700;color:<?php echo $roleColor; ?>;background:<?php echo $roleBg; ?>;cursor:pointer;">
                            <option value="admin"        <?php echo $s['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="receptionist" <?php echo $s['role'] === 'receptionist' ? 'selected' : ''; ?>>Reception</option>
                        </select>
                    </form>
                </div>

                <!-- Status -->
                <div>
                    <?php if ($isLocked): ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:#991B1B;background:#FEE2E2;padding:4px 9px;border-radius:20px;border:1px solid #FECACA;white-space:nowrap;">
                            <span style="width:6px;height:6px;border-radius:50%;background:#DC2626;display:inline-block;animation:pulse 1.5s infinite;"></span>
                            Locked
                        </span>
                    <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:#166534;background:#DCFCE7;padding:4px 9px;border-radius:20px;border:1px solid #86EFAC;white-space:nowrap;">
                            <span style="width:6px;height:6px;border-radius:50%;background:#16A34A;display:inline-block;"></span>
                            Active
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                    <?php if (!$isMe): ?>
                        <form method="POST" style="display:inline;">
                            <?php echo $staffData['csrfField']; ?>
                            <input type="hidden" name="action" value="toggle_lock">
                            <input type="hidden" name="staff_id" value="<?php echo (int)$s['id']; ?>">
                            <input type="hidden" name="staff_type" value="<?php echo htmlspecialchars($staffType); ?>">
                            <?php if ($isLocked): ?>
                                <input type="hidden" name="lock_op" value="unlock">
                                <button type="submit" class="staff-action-unlock">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                    Unlock
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="lock_op" value="lock">
                                <button type="submit" class="staff-action-lock">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Lock
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <button type="button" class="staff-action-reset"
                        data-action="open-reset-password"
                        data-id="<?php echo (int)$s['id']; ?>"
                        data-username="<?php echo htmlspecialchars((string)$s['username'], ENT_QUOTES); ?>"
                        data-type="<?php echo htmlspecialchars($staffType, ENT_QUOTES); ?>">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 2l-2 2m-1.5 1.5L14 9l-3-3 2.5-2.5a2.121 2.121 0 0 1 3 0l4.5 4.5z"/><circle cx="7.5" cy="16.5" r="4.5"/><path d="M10.5 13.5L3 21"/></svg>
                        Reset
                    </button>

                    <form method="POST" style="display:inline;" class="js-confirm-only"
                        data-confirm-title="Remove Staff Account"
                        data-confirm-msg="Remove <?php echo htmlspecialchars((string)$s['username']); ?>? This cannot be undone."
                        data-confirm-icon="user"
                        data-confirm-icon-bg="#FEE2E2">
                        <?php echo $staffData['csrfField']; ?>
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="staff_id" value="<?php echo (int)$s['id']; ?>">
                        <input type="hidden" name="staff_type" value="<?php echo htmlspecialchars($staffType); ?>">
                        <button type="submit" <?php echo $isMe ? 'disabled' : ''; ?> class="staff-action-remove">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Remove
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>

        <!-- Secondary Grid: Portal Lockdown & Permission Reference -->
        <div class="staff-secondary-grid">
            <div class="admin-card" style="border:1.5px solid <?php echo $staffData['isPortalLocked'] ? '#FCA5A5' : 'var(--border)'; ?>;background:<?php echo $staffData['isPortalLocked'] ? '#FFF5F5' : 'var(--card-bg)'; ?>;">
                <div class="admin-card-header" style="border-bottom:1px solid <?php echo $staffData['isPortalLocked'] ? '#FEE2E2' : 'var(--border-light)'; ?>;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img src="assets/logo.jpg" alt="Logo" style="width:38px;height:38px;border-radius:10px;object-fit:cover;box-shadow:0 2px 6px rgba(0,0,0,0.12);border:1.5px solid var(--border);">
                        <div>
                            <h3 style="margin:0;font-size:15px;color:<?php echo $staffData['isPortalLocked'] ? '#991B1B' : 'var(--text-main)'; ?>;">Staff Portal Lockdown</h3>
                            <p style="margin:2px 0 0;font-size:12px;color:<?php echo $staffData['isPortalLocked'] ? '#B91C1C' : 'var(--text-muted)'; ?>;">
                                Control reception desk access &amp; maintenance mode
                            </p>
                        </div>
                    </div>
                    <div>
                        <?php if ($staffData['isPortalLocked']): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#991B1B;background:#FEE2E2;padding:4px 10px;border-radius:20px;border:1px solid #FCA5A5;letter-spacing:0.5px;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#DC2626;display:inline-block;animation:pulse 1.5s infinite;"></span>
                                LOCKED (OFFLINE)
                            </span>
                        <?php else: ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#166534;background:#DCFCE7;padding:4px 10px;border-radius:20px;border:1px solid #86EFAC;letter-spacing:0.5px;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#16A34A;display:inline-block;"></span>
                                PORTAL OPEN
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="padding:18px;">
                    <p style="font-size:13px;color:<?php echo $staffData['isPortalLocked'] ? '#7F1D1D' : 'var(--text-muted)'; ?>;margin:0 0 16px;line-height:1.5;display:flex;align-items:flex-start;gap:8px;">
                        <?php if ($staffData['isPortalLocked']): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span><strong>Maintenance mode is currently ACTIVE.</strong> The receptionist login page is locked. Anyone visiting <code>staff_login</code> will see a maintenance popup modal and cannot sign in.</span>
                        <?php else: ?>
                            <span>Lock the <code>staff_login</code> page whenever the front desk is under maintenance, undergoing shift auditing, or closed. Receptionists will see your custom popup message.</span>
                        <?php endif; ?>
                    </p>

                    <form method="POST" action="admin_staff" <?php if (!$staffData['isPortalLocked']): ?>data-confirm-title="Lock Reception Portal?" data-confirm-msg="Are you sure you want to LOCK the staff login portal? All receptionist sign-ins will be blocked and an under-maintenance popup will be shown." data-confirm-icon="lock" data-confirm-icon-bg="#FEE2E2" data-confirm-color="#DC2626" data-confirm-text="Lock Portal"<?php endif; ?>>
                        <?php echo $staffData['csrfField']; ?>
                        <input type="hidden" name="action" value="toggle_staff_maintenance">

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:var(--text-main);margin-bottom:6px;">Popup Alert Message for Receptionists:</label>
                            <textarea name="maintenance_message" rows="2" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;" placeholder="E.g., Front Desk Reception Portal is currently locked for system maintenance. Please contact the Resort Administrator."><?php echo htmlspecialchars($staffData['portalLockMsg']); ?></textarea>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:8px;">
                            <?php if ($staffData['isPortalLocked']): ?>
                                <input type="hidden" name="maintenance_mode" value="0">
                                <button type="submit" class="btn-primary" style="background:#16A34A;border-color:#15803D;padding:10px 18px;font-size:13px;display:flex;align-items:center;gap:6px;width:100%;justify-content:center;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                    Unlock Staff Portal (Restore Sign In)
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="maintenance_mode" value="1">
                                <button type="submit" class="btn-danger" style="padding:10px 18px;font-size:13px;display:flex;align-items:center;gap:6px;width:100%;justify-content:center;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Lock Staff Portal (Activate Maintenance Mode)
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Permissions Reference -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Permission Reference</h3></div>
                <table class="admin-table">
                    <thead><tr><th>Feature</th><th style="text-align:center;">Admin</th><th style="text-align:center;">Reception</th></tr></thead>
                    <tbody>
                    <?php
                    $chk = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
                    $ex  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                    foreach ($staffData['perms'] as $feat => $p):
                    ?>
                    <tr>
                        <td style="font-size:13px;"><?php echo htmlspecialchars($feat); ?></td>
                        <td style="text-align:center;"><?php echo $p['admin'] ? $chk : $ex; ?></td>
                        <td style="text-align:center;">
                            <?php
                            if ($p['rec'] === true) {
                                echo $chk;
                            } elseif ($p['rec'] === false) {
                                echo $ex;
                            } else {
                                echo '<span style="font-size:11px;color:var(--text-muted);">' . htmlspecialchars((string)$p['rec']) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
