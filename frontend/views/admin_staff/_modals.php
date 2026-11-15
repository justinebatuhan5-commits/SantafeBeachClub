<!-- Add Staff Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button type="button" class="modal-close" data-close-modal="addModal">&times;</button>
        <h3>Add Staff Account</h3>
        <p class="modal-sub">Create a new login for a team member.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php echo $staffData['csrfField']; ?>
            <input type="hidden" name="action" value="add_staff">
            <div class="admin-form-group"><label>System Username (Login ID)</label><input type="email" name="username" required pattern=".+@(santafebeachclub|beachclub)\.com$" title="Must end with @santafebeachclub.com" placeholder="name@santafebeachclub.com"></div>
            <div class="admin-form-group"><label>Personal Email (Receives Login OTPs)</label><input type="email" name="email" required placeholder="personal@gmail.com"></div>
            <div class="admin-form-group">
                <label>Password</label>
                <input type="password" id="staffPassword" name="password" required minlength="8" placeholder="Min 8 chars: upper, lower, number, symbol" title="Must be 8+ characters with uppercase, lowercase, number, and special character">
                <div id="passwordStrengthBar" style="margin-top: 8px; display: none;">
                    <div style="background: #e5e7eb; height: 6px; border-radius: 3px; overflow: hidden;">
                        <div id="passwordStrengthMeter" style="height: 100%; width: 0%; transition: width 0.3s, background-color 0.3s; background-color: #ef4444;"></div>
                    </div>
                    <div style="font-size: 12px; margin-top: 4px; font-weight: 500;">
                        Strength: <span id="passwordStrengthText" style="color: #ef4444;">Weak</span>
                    </div>
                </div>
            </div>
            <div class="admin-form-group">
                <label>Profile Photo (Optional)</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
            <div class="admin-form-group">
                <label>Role</label>
                <select name="role">
                    <option value="receptionist">Receptionist</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Create Account</button>
        </form>
    </div>
</div>

<!-- Edit Staff Photo Modal -->
<div class="modal-overlay" id="editPhotoModal">
    <div class="modal-box">
        <button type="button" class="modal-close" data-close-modal="editPhotoModal">&times;</button>
        <h3>Update Profile Photo</h3>
        <p class="modal-sub" id="editPhotoModalSub">Upload a new photo for this staff member.</p>
        <form method="POST" enctype="multipart/form-data">
            <?php echo $staffData['csrfField']; ?>
            <input type="hidden" name="action" value="update_staff_photo">
            <input type="hidden" name="staff_id" id="editPhotoStaffId">
            <input type="hidden" name="staff_type" id="editPhotoStaffType">
            <div class="admin-form-group">
                <label>Select Photo (JPG, PNG, WEBP, max 5MB)</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-bottom:8px;">Upload &amp; Save</button>
        </form>
        <form method="POST" id="removePhotoForm">
            <?php echo $staffData['csrfField']; ?>
            <input type="hidden" name="action" value="update_staff_photo">
            <input type="hidden" name="staff_id" id="removePhotoStaffId">
            <input type="hidden" name="staff_type" id="removePhotoStaffType">
            <input type="hidden" name="remove_photo" value="1">
            <button type="submit" class="btn-danger" style="width:100%;justify-content:center;background:none;border:1px solid #FCA5A5;color:#DC2626;" id="removePhotoBtn">Remove Existing Photo</button>
        </form>
    </div>
</div>

<!-- Edit OTP Email Modal -->
<div class="modal-overlay" id="editEmailModal">
    <div class="modal-box">
        <button type="button" class="modal-close" data-close-modal="editEmailModal">&times;</button>
        <h3>Edit OTP Delivery Email</h3>
        <p class="modal-sub" id="editEmailModalSub">Update the email address where login OTPs are sent.</p>
        <form method="POST">
            <?php echo $staffData['csrfField']; ?>
            <input type="hidden" name="action" value="edit_email">
            <input type="hidden" name="staff_id" id="editEmailStaffId">
            <input type="hidden" name="staff_type" id="editEmailStaffType">
            <div class="admin-form-group">
                <label>Personal / Delivery Email (Gmail, etc.)</label>
                <input type="email" name="email" id="editEmailInput" required placeholder="name@gmail.com">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Save Email</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal-box">
        <button type="button" class="modal-close" data-close-modal="resetModal">&times;</button>
        <h3>Reset Password</h3>
        <p class="modal-sub" id="resetModalSub">Set a new password for this account.</p>
        <form method="POST">
            <?php echo $staffData['csrfField']; ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="staff_id" id="resetStaffId">
            <input type="hidden" name="staff_type" id="resetStaffType">
            <div class="admin-form-group"><label>New Password</label><input type="password" name="new_password" required minlength="8" placeholder="Min 8 chars: upper, lower, number, symbol" title="Must be 8+ characters with uppercase, lowercase, number, and special character"></div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Save New Password</button>
        </form>
    </div>
</div>
