<!-- Edit Room Modal -->
<div id="editRoomModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.45); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; box-shadow:0 24px 64px rgba(0,0,0,0.18); width:100%; max-width:560px; margin:24px; overflow:hidden; font-family:'Outfit',sans-serif;">

        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 18px; border-bottom:1px solid #F1F5F9;">
            <h3 style="font-size:17px; font-weight:700; color:#1A1A2E; margin:0;">Edit Room</h3>
            <button id="closeEditRoomModal" type="button" style="background:none; border:none; cursor:pointer; color:#6B7280; padding:4px; line-height:0; border-radius:6px; transition:background 0.15s;" onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='none'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Body -->
        <div style="padding:22px 24px 24px;">

            <!-- Warning notice -->
            <div style="display:flex; gap:12px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:14px 16px; margin-bottom:22px; align-items:flex-start;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    <div style="font-size:13px; font-weight:600; color:#92400E;">Room quantity can only be reduced. Guest numbers are flexible.</div>
                    <div style="font-size:12px; color:#B45309; margin-top:3px;">Once decreased, it cannot be increased.</div>
                </div>
            </div>

            <!-- Room Type Selection -->
            <div style="margin-bottom:18px;">
                <label style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Accommodation Type</label>
                <select id="editRoomType" style="width:100%; padding:12px 16px; border:1.5px solid #E5E7EB; border-radius:10px; font-family:'Outfit',sans-serif; font-size:14px; color:#1A1A2E; background:#fff; outline:none; cursor:pointer;">
                    <option value="beachview_duplex" <?php echo ($room_type === 'beachview_duplex') ? 'selected' : ''; ?>>Beachview Duplex</option>
                    <option value="seaview_duplex" <?php echo ($room_type === 'seaview_duplex') ? 'selected' : ''; ?>>Seaview Duplex</option>
                    <option value="beach_villa" <?php echo ($room_type === 'beach_villa') ? 'selected' : ''; ?>>Beach Villa</option>
                    <option value="standard_king" <?php echo ($room_type === 'standard_king') ? 'selected' : ''; ?>>Standard Family Room</option>
                    <option value="standard_room" <?php echo ($room_type === 'standard_room') ? 'selected' : ''; ?>>Standard Room</option>
                </select>
            </div>

            <!-- Room count -->
            <div style="margin-bottom:18px;">
                <label style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Room Quantity</label>
                <select id="editRoomCount" style="width:100%; padding:12px 16px; border:1.5px solid #E5E7EB; border-radius:10px; font-family:'Outfit',sans-serif; font-size:14px; color:#1A1A2E; background:#F9FAFB; outline:none; cursor:pointer;">
                    <option value="1" selected>1 room</option>
                </select>
            </div>

<?php
$_editIncluded = in_array($room_type, ['beach_villa', 'standard_king'], true) ? 4 : 2;
$_editRate     = (int) $pricing['extra_rate_per_adult'];
?>
            <!-- Guest in Room 1 -->
            <div id="editGuestSection" style="border:1.5px solid #E5E7EB; border-radius:10px; overflow:hidden;">
                <div style="background:#F8FAFC; padding:12px 16px; font-size:13px; font-weight:700; color:#374151; border-bottom:1px solid #E5E7EB;">Guest in Room 1</div>
                <div style="padding:14px 16px; display:flex; gap:12px; align-items:center;">
                    <div style="font-size:13px; font-weight:600; color:#6B7280; flex-shrink:0; width:40px;">Main</div>
                    <select id="editAdults" style="flex:1; padding:10px 14px; border:1.5px solid #E5E7EB; border-radius:8px; font-family:'Outfit',sans-serif; font-size:13px; color:#1A1A2E; background:#fff; outline:none; cursor:pointer; appearance:none; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%234B5563' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:calc(100% - 12px) center;">
                        <option value="1" <?php echo ($guests_total == 1) ? 'selected' : ''; ?>>1 adult<?php echo _editExtraLabel(1, $_editIncluded, $_editRate); ?></option>
                        <option value="2" <?php echo ($guests_total == 2) ? 'selected' : ''; ?>>2 adults<?php echo _editExtraLabel(2, $_editIncluded, $_editRate); ?></option>
                        <option value="3" <?php echo ($guests_total == 3) ? 'selected' : ''; ?>>3 adults<?php echo _editExtraLabel(3, $_editIncluded, $_editRate); ?></option>
                        <option value="4" <?php echo ($guests_total == 4) ? 'selected' : ''; ?>>4 adults<?php echo _editExtraLabel(4, $_editIncluded, $_editRate); ?></option>
                        <option value="5" <?php echo ($guests_total >= 5) ? 'selected' : ''; ?>>5 adults<?php echo _editExtraLabel(5, $_editIncluded, $_editRate); ?></option>
                    </select>
                    <select id="editChildren" style="flex:1; padding:10px 14px; border:1.5px solid #E5E7EB; border-radius:8px; font-family:'Outfit',sans-serif; font-size:13px; color:#1A1A2E; background:#fff; outline:none; cursor:pointer; appearance:none; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%234B5563' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:calc(100% - 12px) center;">
                        <option value="0" selected>0 children</option>
                        <option value="1">1 child</option>
                        <option value="2">2 children</option>
                    </select>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div style="display:flex; justify-content:flex-end; padding:16px 24px; border-top:1px solid #F1F5F9;">
            <button id="applyEditRoom" type="button" style="padding:11px 28px; background:linear-gradient(135deg,#C8996F,#A67850); color:#fff; border:none; border-radius:9px; font-family:'Outfit',sans-serif; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(200,153,111,0.4); transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                Close
            </button>
        </div>

    </div>
</div>

<!-- Interactive Availability Calendar Modal -->
<div id="availabilityCalendarModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:16px; box-shadow:0 24px 64px rgba(0,0,0,0.2); width:100%; max-width:540px; overflow:hidden; font-family:'Outfit',sans-serif; animation:modalPop 0.25s ease-out;">

        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:18px 22px; background:#FAF6F0; border-bottom:1px solid #EFE4D6;">
            <div>
                <h3 style="font-size:17px; font-weight:700; color:#5A3E2B; margin:0; display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Live Availability Calendar
                </h3>
                <p style="font-size:12px; color:#84563C; margin:3px 0 0 0;">Select your Check-in and Check-out dates</p>
            </div>
            <button id="closeAvailCalModal" type="button" style="background:none; border:none; cursor:pointer; color:#7C533C; font-size:24px; line-height:1; padding:2px 6px; border-radius:6px;">&times;</button>
        </div>

        <!-- Body -->
        <div style="padding:20px 22px;">

            <!-- Month Navigator -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <button type="button" id="book-cal-prev" style="background:#F3F4F6; border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-weight:700; color:#374151;">&larr; Prev</button>
                <div id="book-cal-month-title" style="font-size:15px; font-weight:700; color:#1F2937;">Loading...</div>
                <button type="button" id="book-cal-next" style="background:#F3F4F6; border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-weight:700; color:#374151;">Next &rarr;</button>
            </div>

            <!-- Legend -->
            <div style="display:flex; justify-content:center; gap:14px; margin-bottom:14px; font-size:11.5px; color:#4B5563;">
                <span style="display:inline-flex; align-items:center; gap:4px;"><span style="width:10px; height:10px; border-radius:3px; background:#ECFDF5; border:1px solid #A7F3D0; display:inline-block;"></span> Available</span>
                <span style="display:inline-flex; align-items:center; gap:4px;"><span style="width:10px; height:10px; border-radius:3px; background:#FFFBEB; border:1px solid #FDE68A; display:inline-block;"></span> Low Stock</span>
                <span style="display:inline-flex; align-items:center; gap:4px;"><span style="width:10px; height:10px; border-radius:3px; background:#FEF2F2; border:1px solid #FECACA; display:inline-block;"></span> Sold Out</span>
            </div>

            <!-- Weekday header -->
            <div style="display:grid; grid-template-columns:repeat(7, 1fr); text-align:center; font-size:11px; font-weight:700; color:#9CA3AF; margin-bottom:8px;">
                <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
            </div>

            <!-- Calendar Days Grid -->
            <div id="book-cal-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:6px;">
                <div style="grid-column:span 7; text-align:center; padding:30px; color:#9CA3AF; font-size:13px;">Loading availability matrix...</div>
            </div>

            <!-- Selected Dates Indicator -->
            <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:11px; color:#64748B; text-transform:uppercase; font-weight:700;">Selected Stay</div>
                    <div id="book-cal-selection-text" style="font-size:13.5px; font-weight:700; color:#1E293B; margin-top:2px;">
                        <?php echo date('M j, Y', strtotime($checkin)); ?> &rarr; <?php echo date('M j, Y', strtotime($checkout)); ?> (<?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>)
                    </div>
                </div>
                <button type="button" id="book-cal-apply-btn" style="background:#84563C; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">
                    Confirm Dates
                </button>
            </div>
        </div>

    </div>
</div>
