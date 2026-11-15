<?php
if (defined('SFBC_STAFF_ASSISTANT_LOADED')) {
    return;
}
define('SFBC_STAFF_ASSISTANT_LOADED', true);

require_once __DIR__ . '/../../backend/helpers/csrf_helper.php';
$sfbc_staff_csrf = get_csrf_token();
$sfbc_staff_is_admin = (($_SESSION['admin_role'] ?? '') === 'admin');
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['sfbc_staff_ai_session_key'])) {
    $_SESSION['sfbc_staff_ai_session_key'] = bin2hex(random_bytes(8));
}
$sfbc_staff_scope = substr(
    hash(
        'sha256',
        (string)($_SESSION['admin_role'] ?? 'staff') . '|' .
        (string)($_SESSION['admin_username'] ?? '') . '|' .
        (string)($_SESSION['sfbc_staff_ai_session_key'] ?? '')
    ),
    0,
    16
);
$sfbc_staff_script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$sfbc_staff_prefix = (strpos($sfbc_staff_script_dir, '/frontend') !== false || is_file('assets/logo.jpg')) ? '' : 'frontend/';
$sfbc_staff_css = $sfbc_staff_prefix . 'assets/css/components/staff-assistant-widget.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/components/staff-assistant-widget.css');
$sfbc_staff_js  = $sfbc_staff_prefix . 'assets/js/components/staff-assistant-widget.js?v=' . (int) @filemtime(__DIR__ . '/../assets/js/components/staff-assistant-widget.js');
$sfbc_staff_config = [
    'csrfToken' => $sfbc_staff_csrf,
    'scope' => $sfbc_staff_scope,
];
?>
<!-- Santa Fe Beach Club — Read-Only Staff AI Operations Assistant -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($sfbc_staff_css, ENT_QUOTES, 'UTF-8'); ?>">

<div id="sfbcStaffAiWidget">
    <button id="sfbcStaffAiToggle" type="button" onclick="if(window.sfbcStaffAiToggleBox){window.sfbcStaffAiToggleBox();}else{var b=document.getElementById('sfbcStaffAiBox');if(b){b.style.setProperty('display','flex','important');b.classList.add('sfbc-staff-open');}}" aria-label="Toggle Staff AI Assistant" title="Staff AI Assistant (Read-Only)">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="pointer-events:none;"><path d="M12 2a4 4 0 0 1 4 4v2a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M16 14H8a4 4 0 0 0-4 4v2h16v-2a4 4 0 0 0-4-4z"/></svg>
        <span style="pointer-events:none;">Staff AI Assistant</span>
    </button>

    <div id="sfbcStaffAiBox">
        <div style="background:linear-gradient(135deg,#0F172A,#1E293B); color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <div>
                <strong style="font-size:13px; display:block;">Staff Operations Assistant</strong>
                <span style="font-size:10.5px; opacity:0.8;">Read-Only · <?php echo $sfbc_staff_is_admin ? 'Administrator' : 'Reception Desk'; ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <button id="sfbcStaffClearBtn" type="button" onclick="if(window.sfbcStaffClearChat){window.sfbcStaffClearChat();}" aria-label="Clear staff assistant conversation" title="Clear Chat" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.25); color:#ffffff; font-size:11px; font-weight:600; line-height:1; cursor:pointer; padding:5px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px; transition:background 0.15s ease;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    <span>Clear</span>
                </button>
                <button id="sfbcStaffExpandBtn" type="button" onclick="window.sfbcStaffToggleExpand()" aria-label="Expand staff assistant window" title="Make Bigger / Expand" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.25); color:#ffffff; font-size:12px; line-height:1; cursor:pointer; padding:5px 7px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; transition:background 0.15s ease;">
                    <svg id="sfbcStaffIconExpand" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    <svg id="sfbcStaffIconRestore" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:none; pointer-events:none;"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
                <button type="button" onclick="if(window.sfbcStaffAiToggleBox){window.sfbcStaffAiToggleBox(false);}else{var b=document.getElementById('sfbcStaffAiBox');if(b){b.style.setProperty('display','none','important');b.classList.remove('sfbc-staff-open');}}" style="background:none; border:none; color:#94A3B8; font-size:18px; cursor:pointer; padding:2px 6px;">×</button>
            </div>
        </div>

        <div style="padding:8px 10px; background:rgba(148,163,184,0.1); border-bottom:1px solid var(--border,#E2E8F0); display:flex; gap:6px; overflow-x:auto; white-space:nowrap; flex-shrink:0;">
            <button type="button" onclick="sfbcStaffQuickAsk('Show today arrivals and departures')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Today's Desk</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Who is currently checked in?')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">In-House</button>
            <button type="button" onclick="sfbcStaffQuickAsk('What is our occupancy rate today?')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Occupancy</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show pending payments, refunds, and inquiries')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Pending Queue</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show live chats, inquiries, and reviews')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Chats &amp; Inquiries</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show room inventory, rates, and active promotions')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Rooms &amp; Promos</button>
            <?php if ($sfbc_staff_is_admin): ?>
            <button type="button" onclick="sfbcStaffQuickAsk('Show 30 days revenue summary')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">30d Revenue</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show active sessions and activity logs')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Audit &amp; Sessions</button>
            <?php endif; ?>
            <button type="button" onclick="sfbcStaffQuickAsk('help')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Help</button>
        </div>

        <div id="sfbcStaffAiMessages" style="flex:1; padding:12px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; font-size:12.5px;">
            <div id="sfbcStaffGreetingMsg" style="align-self:flex-start; background:rgba(148,163,184,0.12); padding:10px 12px; border-radius:10px; max-width:90%; line-height:1.45;">
                Hello <strong><?php echo htmlspecialchars((string)($_SESSION['admin_username'] ?? 'Staff')); ?></strong>! Ask me about bookings or guests (<code>REF-...</code>), today's arrivals or in-house stays, room occupancy, promotions, live chats, inquiries, reviews, or pending queues. <em>(Read-only)</em>
            </div>
        </div>

        <div style="padding:10px; border-top:1px solid var(--border,#E2E8F0); display:flex; gap:6px; flex-shrink:0;">
            <input type="text" id="sfbcStaffAiInput" maxlength="400" placeholder="Ask about bookings, guests, rooms, promos, queues..." style="flex:1; padding:8px 10px; border:1px solid var(--border,#CBD5E1); border-radius:8px; font-size:12.5px; background:var(--card-bg,#fff); color:var(--text-main,#0F172A);" onkeypress="if(event.key==='Enter') sfbcStaffSend();">
            <button type="button" onclick="sfbcStaffSend()" style="padding:8px 14px; border-radius:8px; background:#0284C7; color:#fff; border:none; font-weight:700; font-size:12px; cursor:pointer;">Ask</button>
        </div>
    </div>
</div>

<script>window.SFBC_STAFF_AI_CONFIG = <?php echo json_encode($sfbc_staff_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="<?php echo htmlspecialchars($sfbc_staff_js, ENT_QUOTES, 'UTF-8'); ?>"></script>
