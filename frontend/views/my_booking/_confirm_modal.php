<!-- Custom Confirmation Modal -->
<div id="sfbc-confirm-overlay" style="
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,0.55);
    backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
    z-index:99999; align-items:center; justify-content:center;">
    <div style="
        background:#fff; border-radius:20px; padding:32px 28px 24px;
        max-width:400px; width:calc(100% - 40px);
        box-shadow:0 24px 60px rgba(0,0,0,0.18);
        text-align:center; animation:sfbcSlideUp 0.2s cubic-bezier(0.16,1,0.3,1);">
        <div id="sfbc-confirm-icon" style="width:60px;height:60px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;color:#DC2626;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
        </div>
        <h3 id="sfbc-confirm-title" style="margin:0 0 8px;font-size:18px;font-weight:800;color:#111827;"></h3>
        <p id="sfbc-confirm-message" style="margin:0 0 24px;font-size:14px;color:#6B7280;line-height:1.6;"></p>
        <div style="display:flex;gap:12px;">
            <button id="sfbc-confirm-cancel" style="flex:1;padding:12px;background:#F3F4F6;color:#374151;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">Keep Booking</button>
            <button id="sfbc-confirm-ok" style="flex:1;padding:12px;background:#DC2626;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">Yes, Cancel</button>
        </div>
    </div>
</div>
