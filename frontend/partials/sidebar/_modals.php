<!-- Global Custom Confirmation Dialog & QR Scanner Modal -->
<div id="sfbc-confirm-overlay" style="
    display: none;
    position: fixed; inset: 0; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; height: 100dvh; margin: 0; box-sizing: border-box;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: sfbcFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
">
    <div style="
        background: #FFFFFF;
        border-radius: 24px;
        padding: 36px 32px 28px;
        max-width: 440px;
        width: 100%;
        margin: auto;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(226, 232, 240, 0.8);
        text-align: center;
        animation: sfbcSlideUp 0.28s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
    ">
        <!-- Resort Branding Header -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 20px;">
            <img src="assets/logo.jpg" alt="Santa Fe Beach Club" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
            <span style="font-family: 'Outfit', sans-serif; font-size: 13px; font-weight: 700; color: #84563C; letter-spacing: 0.5px; text-transform: uppercase;">Santa Fe Beach Club</span>
        </div>

        <!-- Dynamic Icon / Badge -->
        <div id="sfbc-confirm-icon" style="
            width: 68px; height: 68px;
            border-radius: 20px;
            background: #FEF2F2;
            border: 2px solid #FECACA;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 8px 16px -4px rgba(239, 68, 68, 0.15);
        "><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>

        <h3 id="sfbc-confirm-title" style="margin: 0 0 10px; font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700; color: #0F172A; letter-spacing: -0.3px;"></h3>
        <p id="sfbc-confirm-message" style="margin: 0 0 26px; font-size: 14px; color: #475569; line-height: 1.6; background: #F8FAFC; padding: 12px 16px; border-radius: 12px; border: 1px solid #E2E8F0;"></p>

        <div style="display: flex; gap: 12px;">
            <button id="sfbc-confirm-cancel" style="
                flex: 1; padding: 13px 18px;
                background: #F1F5F9; color: #475569;
                border: 1px solid #CBD5E1; border-radius: 12px;
                font-family: 'Outfit', sans-serif;
                font-size: 14px; font-weight: 600;
                cursor: pointer; transition: all 0.2s ease;
            " onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#475569';">
                Cancel
            </button>
            <button id="sfbc-confirm-ok" style="
                flex: 1.2; padding: 13px 18px;
                background: #DC2626; color: #FFFFFF;
                border: none; border-radius: 12px;
                font-family: 'Outfit', sans-serif;
                font-size: 14px; font-weight: 700;
                cursor: pointer; transition: all 0.2s ease;
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            " onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 16px rgba(220, 38, 38, 0.4)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 12px rgba(220, 38, 38, 0.3)';">
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- In-Browser QR Scanner Modal (Native getUserMedia + jsQR) -->
<div id="qr-scanner-overlay">
    <div id="qr-scanner-box">
        <button id="qr-close-btn" type="button" onclick="closeQrScanner()">&#215;</button>
        <h3><img src="assets/logo.jpg" alt="Santa Fe Beach Club logo" class="qr-logo"> QR Code Scanner</h3>
        <p class="qr-sub">Align the guest's booking QR code within the frame</p>

        <div id="qr-video-wrap">
            <video id="qr-video" autoplay playsinline muted></video>
            <canvas id="qr-canvas"></canvas>
            <div class="qr-corner tl"></div>
            <div class="qr-corner tr"></div>
            <div class="qr-corner bl"></div>
            <div class="qr-corner br"></div>
            <div class="qr-scanline"></div>
            <div id="qr-success-flash">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                     stroke="#4ADE80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
                <span>QR Verified! Loading Pass…</span>
            </div>
        </div>

        <div id="qr-status-text">Starting camera…</div>
        <select id="qr-cam-select" style="display:none; margin-top:12px; width:100%; padding:10px 14px; border-radius:12px; background:rgba(30,41,59,0.9); color:#F1F5F9; border:1px solid rgba(255,255,255,0.15); font-family:'Outfit',sans-serif; font-size:13px; outline:none; cursor:pointer;"></select>
    </div>
</div>
