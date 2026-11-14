<?php
if (defined('SFBC_CHATBOT_WIDGET_LOADED')) {
    return;
}
define('SFBC_CHATBOT_WIDGET_LOADED', true);

require_once __DIR__ . '/../../backend/helpers/csrf_helper.php';
$sfbc_chat_csrf = get_csrf_token();

$sfbc_script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$sfbc_asset_prefix = (strpos($sfbc_script_dir, '/frontend') !== false || is_file('assets/logo.jpg')) ? '' : 'frontend/';
$sfbc_logo_url = $sfbc_asset_prefix . 'assets/logo.jpg';
?>
<!-- Santa Fe Beach Club - Intelligent Virtual Guest Assistant Widget -->
<style>
/* Luxury resort concierge styling for Santa Fe AI Concierge (standalone) */
#sfbcChatWidget {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    z-index: 2147483647 !important;
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    direction: ltr !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 0 !important;
    pointer-events: none !important;
}
#sfbcChatWidget *, #sfbcChatWidget *::before, #sfbcChatWidget *::after {
    box-sizing: border-box !important;
}
#sfbcChatWidget button,
#sfbcChatWidget input,
#sfbcChatWidget select,
#sfbcChatWidget textarea {
    min-height: 0 !important;
    font-family: inherit !important;
}
#sfbcChatWidget :is(#sfbcAvailabilityPanel, #sfbcRecommendPanel, #sfbcOtpPanel, #sfbcHandoffPanel) :is(input, select) {
    min-height: 0 !important;
    height: 34px !important;
    font-size: 12px !important;
    line-height: 1.3 !important;
}
#sfbcChatToggle {
    position: relative !important;
    width: 62px !important;
    height: 62px !important;
    border-radius: 50% !important;
    background: linear-gradient(135deg, #84563C 0%, #5C3824 100%) !important;
    color: #ffffff !important;
    border: 2.5px solid rgba(255, 255, 255, 0.82) !important;
    box-shadow: 0 10px 28px rgba(45, 31, 23, 0.34), 0 0 0 1px rgba(132, 86, 60, 0.35) !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    outline: none !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: visible !important;
    pointer-events: auto !important;
    touch-action: manipulation !important;
    user-select: none !important;
    -webkit-user-select: none !important;
    transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease !important;
}
#sfbcChatToggle:hover {
    transform: translateY(-2px) scale(1.05) !important;
    box-shadow: 0 14px 34px rgba(45, 31, 23, 0.44), 0 0 0 3px rgba(214, 180, 140, 0.45) !important;
}
#sfbcChatToggle:active {
    transform: scale(0.95) !important;
}
.sfbc-toggle-logo-wrap {
    width: 100% !important;
    height: 100% !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    position: relative !important;
    pointer-events: none !important;
    display: block !important;
}
.sfbc-toggle-logo-img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
}
.sfbc-toggle-status-dot {
    position: absolute !important;
    top: 1px !important;
    right: 1px !important;
    width: 13px !important;
    height: 13px !important;
    border-radius: 50% !important;
    background: #22C55E !important;
    border: 2px solid #ffffff !important;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3) !important;
    pointer-events: none !important;
}
.sfbc-toggle-unread-badge {
    position: absolute !important;
    top: -4px !important;
    right: -4px !important;
    min-width: 21px !important;
    height: 21px !important;
    padding: 0 5px !important;
    border-radius: 999px !important;
    background: #EF4444 !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
    display: none !important;
    align-items: center !important;
    justify-content: center !important;
    border: 2px solid #ffffff !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.45) !important;
    pointer-events: none !important;
}
.sfbc-toggle-unread-badge.is-visible {
    display: inline-flex !important;
}
#sfbcChatBox {
    display: none;
    position: fixed !important;
    bottom: 98px !important;
    right: 24px !important;
    width: 420px !important;
    max-width: calc(100vw - 28px) !important;
    height: 600px !important;
    max-height: calc(100vh - 118px) !important;
    background: #FAF7F2 !important;
    color: #2D1F17 !important;
    border-radius: 22px !important;
    border: 1px solid rgba(132, 86, 60, 0.2) !important;
    box-shadow: 0 24px 64px rgba(45, 31, 23, 0.28), 0 4px 16px rgba(45, 31, 23, 0.1) !important;
    overflow: hidden !important;
    flex-direction: column !important;
    z-index: 2147483647 !important;
    pointer-events: auto !important;
    transition: width 0.24s cubic-bezier(0.4, 0, 0.2, 1), height 0.24s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
#sfbcChatBox.sfbc-open {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}
#sfbcChatBox.sfbc-expanded {
    width: 650px !important;
    max-width: calc(100vw - 32px) !important;
    height: 690px !important;
    max-height: calc(100vh - 110px) !important;
}
#sfbcChatHeader {
    background: linear-gradient(135deg, #4A2C1D 0%, #7C533C 58%, #9A6C50 100%) !important;
    color: #ffffff !important;
    padding: 13px 16px !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 10px !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.14) !important;
    box-shadow: 0 4px 14px rgba(45, 31, 23, 0.16) !important;
    flex-shrink: 0 !important;
}
.sfbc-header-brand {
    display: flex !important;
    align-items: center !important;
    gap: 11px !important;
    min-width: 0 !important;
}
.sfbc-header-logo-ring {
    position: relative !important;
    width: 42px !important;
    height: 42px !important;
    flex-shrink: 0 !important;
}
.sfbc-header-logo-img {
    width: 42px !important;
    height: 42px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 2px solid rgba(255, 248, 240, 0.78) !important;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.24) !important;
    background: #C89B6E !important;
    display: block !important;
}
.sfbc-header-online-dot {
    position: absolute !important;
    bottom: 0 !important;
    right: 0 !important;
    width: 11px !important;
    height: 11px !important;
    border-radius: 50% !important;
    background: #22C55E !important;
    border: 2px solid #5C3824 !important;
    box-shadow: 0 0 6px rgba(34, 197, 94, 0.75) !important;
}
.sfbc-header-controls {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    flex-shrink: 0 !important;
}
.sfbc-header-title {
    font-size: 14.5px !important;
    display: block !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: -0.01em !important;
    color: #ffffff !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.sfbc-header-subtitle {
    font-size: 11px !important;
    color: rgba(255, 248, 240, 0.86) !important;
    display: block !important;
    line-height: 1.25 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.sfbc-lang-pill {
    position: relative !important;
    display: inline-flex !important;
    align-items: center !important;
    height: 30px !important;
    background: rgba(255, 255, 255, 0.16) !important;
    border: 1px solid rgba(255, 255, 255, 0.28) !important;
    border-radius: 999px !important;
    padding: 0 10px !important;
    flex-shrink: 0 !important;
    transition: background 0.15s ease !important;
}
.sfbc-lang-pill:hover,
.sfbc-lang-pill:focus-within {
    background: rgba(255, 255, 255, 0.25) !important;
}
#sfbcChatWidget #sfbcLangSelect {
    background: transparent !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 0 !important;
    font-size: 11.5px !important;
    font-weight: 500 !important;
    min-height: 0 !important;
    height: auto !important;
    line-height: 1.25 !important;
    padding: 0 !important;
    margin: 0 !important;
    outline: none !important;
    cursor: pointer !important;
    width: auto !important;
    max-width: 145px !important;
}
#sfbcChatWidget .sfbc-header-icon-btn {
    min-width: 0 !important;
    min-height: 0 !important;
    width: 30px !important;
    height: 30px !important;
    flex: 0 0 30px !important;
    border-radius: 50% !important;
    background: rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
    cursor: pointer !important;
    padding: 0 !important;
    margin: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.15s ease, transform 0.12s ease !important;
}
#sfbcChatWidget .sfbc-header-icon-btn:hover {
    background: rgba(255, 255, 255, 0.28) !important;
    transform: translateY(-1px) !important;
}
#sfbcQuickChipsBar {
    padding: 10px 14px !important;
    background: #F4EDE3 !important;
    border-bottom: 1px solid rgba(132, 86, 60, 0.14) !important;
    display: flex !important;
    gap: 7px !important;
    overflow-x: auto !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    -webkit-mask-image: linear-gradient(to right, #000000 92%, transparent 100%) !important;
    mask-image: linear-gradient(to right, #000000 92%, transparent 100%) !important;
}
#sfbcQuickChipsBar::-webkit-scrollbar,
#sfbcFaqTabs::-webkit-scrollbar {
    display: none !important;
    height: 0 !important;
}
#sfbcChatBox.sfbc-expanded #sfbcQuickChipsBar {
    flex-wrap: wrap !important;
    white-space: normal !important;
    padding: 10px 16px !important;
    -webkit-mask-image: none !important;
    mask-image: none !important;
}
#sfbcChatMessages {
    flex: 1 1 0% !important;
    padding: 16px !important;
    overflow-y: auto !important;
    background: radial-gradient(circle at top right, rgba(214, 180, 140, 0.14), transparent 52%), #FAF7F2 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
    font-size: 13px !important;
    line-height: 1.5 !important;
}
#sfbcChatMessages::-webkit-scrollbar {
    width: 6px !important;
}
#sfbcChatMessages::-webkit-scrollbar-track {
    background: transparent !important;
}
#sfbcChatMessages::-webkit-scrollbar-thumb {
    background: rgba(132, 86, 60, 0.24) !important;
    border-radius: 999px !important;
}
.sfbc-bot-label {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    margin-bottom: 5px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #6B4F42 !important;
    letter-spacing: 0.01em !important;
}
.sfbc-bot-label img {
    width: 20px !important;
    height: 20px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 1px solid rgba(132, 86, 60, 0.35) !important;
    flex-shrink: 0 !important;
}
.sfbc-msg-bubble-user {
    align-self: flex-end !important;
    max-width: 82% !important;
    background: linear-gradient(135deg, #84563C 0%, #5C3824 100%) !important;
    color: #ffffff !important;
    border-radius: 18px 18px 4px 18px !important;
    padding: 10px 15px !important;
    font-size: 13px !important;
    line-height: 1.45 !important;
    word-break: break-word !important;
    box-shadow: 0 4px 12px rgba(92, 56, 36, 0.22) !important;
}
.sfbc-msg-bubble-model {
    align-self: flex-start !important;
    max-width: 88% !important;
    background: #ffffff !important;
    color: #2D1F17 !important;
    border: 1px solid rgba(132, 86, 60, 0.16) !important;
    border-radius: 4px 18px 18px 18px !important;
    padding: 12px 15px !important;
    font-size: 13px !important;
    line-height: 1.52 !important;
    word-break: break-word !important;
    box-shadow: 0 3px 12px rgba(45, 31, 23, 0.06) !important;
}
.sfbc-staff-label {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    margin-bottom: 5px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #0F766E !important;
    letter-spacing: 0.01em !important;
}
.sfbc-staff-avatar {
    width: 20px !important;
    height: 20px !important;
    border-radius: 50% !important;
    background: #0F766E !important;
    color: #ffffff !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
}
.sfbc-staff-live-pill {
    background: #CCFBF1 !important;
    color: #115E59 !important;
    border: 1px solid #99F6E4 !important;
    border-radius: 999px !important;
    padding: 1px 6px !important;
    font-size: 9px !important;
    font-weight: 800 !important;
    letter-spacing: 0.04em !important;
    text-transform: uppercase !important;
}
.sfbc-msg-bubble-staff {
    align-self: flex-start !important;
    max-width: 88% !important;
    background: #F0FDFA !important;
    color: #134E4A !important;
    border: 1.5px solid #5EEAD4 !important;
    border-radius: 4px 18px 18px 18px !important;
    padding: 12px 15px !important;
    font-size: 13px !important;
    line-height: 1.52 !important;
    word-break: break-word !important;
    box-shadow: 0 3px 12px rgba(15, 118, 110, 0.1) !important;
}
.sfbc-live-delivered-note {
    font-size: 10px !important;
    color: #0F766E !important;
    font-weight: 600 !important;
    margin-top: 3px !important;
    padding-right: 4px !important;
}
.sfbc-quick-chip {
    padding: 6px 12px !important;
    border-radius: 9999px !important;
    background: #ffffff !important;
    border: 1px solid rgba(132, 86, 60, 0.24) !important;
    color: #5C3824 !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    box-shadow: 0 1px 2px rgba(45, 31, 23, 0.04) !important;
    transition: all 0.16s ease !important;
}
.sfbc-quick-chip:hover {
    background: #84563C !important;
    color: #ffffff !important;
    border-color: #84563C !important;
    transform: translateY(-1px) !important;
}
.sfbc-suggested-q-pill {
    background: #ffffff !important;
    border: 1px solid rgba(132, 86, 60, 0.28) !important;
    color: #5C3824 !important;
    padding: 6px 12px !important;
    border-radius: 9999px !important;
    font-size: 11.5px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    text-align: left !important;
    line-height: 1.3 !important;
    white-space: normal !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    box-shadow: 0 1px 3px rgba(45, 31, 23, 0.04) !important;
    transition: all 0.15s ease !important;
}
.sfbc-suggested-q-pill:hover {
    background: #84563C !important;
    color: #ffffff !important;
    border-color: #84563C !important;
    transform: translateY(-1px) !important;
}
.sfbc-faq-tab {
    padding: 5px 11px !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    border: 1px solid rgba(132, 86, 60, 0.22) !important;
    color: #5C3824 !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    transition: all 0.15s ease !important;
}
.sfbc-faq-tab.active, .sfbc-faq-tab:hover {
    background: #84563C !important;
    color: #ffffff !important;
    border-color: #84563C !important;
}
.sfbc-room-card {
    background: #ffffff !important;
    border: 1px solid rgba(132, 86, 60, 0.18) !important;
    border-radius: 12px !important;
    padding: 10px 12px !important;
    margin-bottom: 8px !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 4px !important;
}
.sfbc-faq-item {
    background: #ffffff !important;
    border: 1px solid rgba(132, 86, 60, 0.16) !important;
    border-radius: 10px !important;
    padding: 8px 11px !important;
    margin-bottom: 6px !important;
}
.sfbc-faq-item summary {
    font-weight: 600 !important;
    color: #5C3824 !important;
    cursor: pointer !important;
    font-size: 11.5px !important;
    line-height: 1.3 !important;
}
.sfbc-faq-item-ans {
    margin-top: 6px !important;
    font-size: 11px !important;
    line-height: 1.45 !important;
    color: #4A3529 !important;
    border-top: 1px dashed rgba(132, 86, 60, 0.2) !important;
    padding-top: 6px !important;
}
/* Visual Room Cards Carousel (Reservation Assistance) */
.sfbc-room-carousel {
    display: flex !important;
    gap: 10px !important;
    overflow-x: auto !important;
    width: 100% !important;
    padding: 6px 2px 10px 2px !important;
    margin-top: 8px !important;
    scroll-snap-type: x mandatory !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
}
.sfbc-room-carousel::-webkit-scrollbar {
    height: 5px !important;
}
.sfbc-room-carousel::-webkit-scrollbar-thumb {
    background: rgba(132, 86, 60, 0.25) !important;
    border-radius: 999px !important;
}
.sfbc-visual-room-card {
    background: #ffffff !important;
    border: 1px solid rgba(132, 86, 60, 0.18) !important;
    border-radius: 16px !important;
    padding: 10px !important;
    min-width: 196px !important;
    max-width: 210px !important;
    flex-shrink: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    box-shadow: 0 4px 12px rgba(45, 31, 23, 0.06) !important;
    scroll-snap-align: start !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease !important;
}
.sfbc-visual-room-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 20px rgba(45, 31, 23, 0.12) !important;
}
.sfbc-visual-room-img-wrap {
    position: relative !important;
    width: 100% !important;
    height: 120px !important;
    border-radius: 12px !important;
    overflow: hidden !important;
    background: #EFE7DC !important;
    margin-bottom: 8px !important;
}
.sfbc-visual-room-img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
}
.sfbc-visual-room-badge {
    position: absolute !important;
    top: 7px !important;
    left: 7px !important;
    background: rgba(45, 31, 23, 0.8) !important;
    color: #ffffff !important;
    font-size: 9.5px !important;
    font-weight: 700 !important;
    padding: 2px 8px !important;
    border-radius: 999px !important;
    backdrop-filter: blur(4px) !important;
}
.sfbc-visual-room-title {
    font-size: 13px !important;
    font-weight: 700 !important;
    color: #2D1F17 !important;
    line-height: 1.25 !important;
    margin: 0 0 2px 0 !important;
}
.sfbc-visual-room-price {
    font-size: 15.5px !important;
    font-weight: 800 !important;
    color: #7C533C !important;
    line-height: 1.25 !important;
    margin: 0 0 3px 0 !important;
}
.sfbc-visual-room-meta {
    font-size: 10.5px !important;
    color: #6B4F42 !important;
    line-height: 1.35 !important;
    margin-bottom: 8px !important;
}
.sfbc-visual-book-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    padding: 8px 12px !important;
    background: linear-gradient(135deg, #84563C 0%, #5C3824 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 999px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    letter-spacing: 0.6px !important;
    text-transform: uppercase !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: filter 0.15s ease, transform 0.12s ease !important;
    box-sizing: border-box !important;
}
.sfbc-visual-book-btn:hover {
    filter: brightness(1.08) !important;
    transform: translateY(-1px) !important;
    color: #ffffff !important;
}
.sfbc-composer-dock {
    padding: 11px 14px 10px !important;
    background: #ffffff !important;
    border-top: 1px solid rgba(132, 86, 60, 0.14) !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 7px !important;
    flex-shrink: 0 !important;
}
.sfbc-input-pill {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    background: #FAF7F2 !important;
    border: 1.5px solid rgba(132, 86, 60, 0.22) !important;
    border-radius: 999px !important;
    padding: 4px 5px 4px 15px !important;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease !important;
}
.sfbc-input-pill:focus-within {
    border-color: #84563C !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(132, 86, 60, 0.12) !important;
}
#sfbcChatWidget #sfbcChatInput {
    flex: 1 !important;
    min-width: 0 !important;
    min-height: 0 !important;
    height: 34px !important;
    padding: 4px 0 !important;
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    font-size: 13.5px !important;
    line-height: 1.3 !important;
    outline: none !important;
    color: #2D1F17 !important;
}
#sfbcChatWidget #sfbcChatInput::placeholder {
    color: #9C8375 !important;
}
#sfbcChatWidget .sfbc-send-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    min-height: 0 !important;
    height: 34px !important;
    background: linear-gradient(135deg, #84563C 0%, #5C3824 100%) !important;
    color: #ffffff !important;
    padding: 0 15px !important;
    border: none !important;
    border-radius: 999px !important;
    cursor: pointer !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    flex-shrink: 0 !important;
    box-shadow: 0 2px 8px rgba(92, 56, 36, 0.25) !important;
    transition: filter 0.15s ease, transform 0.12s ease !important;
}
#sfbcChatWidget .sfbc-send-btn:hover {
    filter: brightness(1.08) !important;
    transform: translateY(-1px) !important;
}
@media (max-width: 768px) {
    #sfbcChatWidget {
        bottom: 14px !important;
        right: 14px !important;
    }
    #sfbcChatToggle {
        width: 56px !important;
        height: 56px !important;
    }
    #sfbcChatBox, #sfbcChatBox.sfbc-expanded {
        right: 10px !important;
        left: 10px !important;
        bottom: 78px !important;
        width: auto !important;
        max-width: none !important;
        height: calc(100vh - 96px) !important;
        height: calc(100dvh - 96px) !important;
        max-height: calc(100vh - 96px) !important;
        max-height: calc(100dvh - 96px) !important;
        border-radius: 20px !important;
    }
    #sfbcChatBox.sfbc-expanded #sfbcQuickChipsBar {
        flex-wrap: nowrap !important;
        white-space: nowrap !important;
        -webkit-mask-image: linear-gradient(to right, #000000 90%, transparent 100%) !important;
        mask-image: linear-gradient(to right, #000000 90%, transparent 100%) !important;
    }
    #sfbcChatWidget :is(#sfbcAvailabilityPanel, #sfbcRecommendPanel, #sfbcOtpPanel, #sfbcHandoffPanel) {
        max-height: 240px !important;
        overflow-y: auto !important;
    }
}
@media (max-width: 640px) {
    #sfbcChatHeader {
        padding: 10px 12px !important;
        gap: 6px !important;
    }
    .sfbc-header-brand {
        gap: 8px !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }
    .sfbc-header-logo-ring,
    .sfbc-header-logo-img {
        width: 36px !important;
        height: 36px !important;
    }
    .sfbc-header-title {
        font-size: 12.5px !important;
    }
    .sfbc-header-subtitle {
        font-size: 10px !important;
    }
    .sfbc-header-controls {
        gap: 5px !important;
    }
    .sfbc-lang-pill {
        height: 30px !important;
        padding: 0 9px !important;
    }
    #sfbcChatWidget #sfbcLangSelect {
        max-width: 118px !important;
        font-size: 11px !important;
    }
    #sfbcChatWidget #sfbcExpandBtn {
        display: none !important;
    }
}
</style>

<div id="sfbcChatWidget">
    <!-- Floating Toggle Button -->
    <button id="sfbcChatToggle" type="button" onclick="if(window.sfbcToggleChat){window.sfbcToggleChat();}else{var b=document.getElementById('sfbcChatBox');if(b){b.style.setProperty('display','flex','important');b.classList.add('sfbc-open');}}" aria-label="Toggle AI Guest Assistant" aria-expanded="false" aria-controls="sfbcChatBox" title="Ask Santa Fe AI Concierge">
        <span id="sfbcIconOpen" style="display:block; width:100%; height:100%; position:relative; pointer-events:none;">
            <span class="sfbc-toggle-logo-wrap">
                <img src="<?php echo htmlspecialchars($sfbc_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club Concierge" class="sfbc-toggle-logo-img" onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='frontend/assets/logo.jpg';}">
            </span>
            <span class="sfbc-toggle-status-dot" aria-hidden="true"></span>
        </span>
        <span id="sfbcToggleUnreadBadge" class="sfbc-toggle-unread-badge" aria-hidden="true"></span>
        <svg id="sfbcIconClose" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none; pointer-events:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <!-- Chat Window Container -->
    <div id="sfbcChatBox">
        <!-- Header -->
        <div id="sfbcChatHeader">
            <div class="sfbc-header-brand">
                <div class="sfbc-header-logo-ring">
                    <img src="<?php echo htmlspecialchars($sfbc_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club" class="sfbc-header-logo-img" onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='frontend/assets/logo.jpg';}">
                    <span class="sfbc-header-online-dot" aria-hidden="true"></span>
                </div>
                <div style="min-width:0;">
                    <strong class="sfbc-header-title">Santa Fe AI Concierge</strong>
                    <span id="sfbcHeaderSubtitle" class="sfbc-header-subtitle">24/7 Resort Concierge</span>
                </div>
            </div>
            <div class="sfbc-header-controls">
                <!-- Language Selector (All Languages Supported) -->
                <div class="sfbc-lang-pill">
                    <select id="sfbcLangSelect" aria-label="Preferred language" onchange="window.sfbcOnLangChange && window.sfbcOnLangChange(this.value)">
                        <option value="" style="color:#0f172a;">All Languages</option>
                        <option value="English" style="color:#0f172a;">English</option>
                        <option value="Tagalog" style="color:#0f172a;">Tagalog</option>
                        <option value="Cebuano / Bisaya" style="color:#0f172a;">Bisaya / Cebuano</option>
                        <option value="Korean" style="color:#0f172a;">한국어 (Korean)</option>
                        <option value="Japanese" style="color:#0f172a;">日本語 (Japanese)</option>
                        <option value="Chinese" style="color:#0f172a;">中文 (Chinese)</option>
                        <option value="Spanish" style="color:#0f172a;">Español (Spanish)</option>
                        <option value="French" style="color:#0f172a;">Français (French)</option>
                        <option value="German" style="color:#0f172a;">Deutsch (German)</option>
                        <option value="Russian" style="color:#0f172a;">Русский (Russian)</option>
                        <option value="Arabic" style="color:#0f172a;">العربية (Arabic)</option>
                    </select>
                </div>
                <!-- Expand / Make Big Button -->
                <button id="sfbcExpandBtn" class="sfbc-header-icon-btn" type="button" onclick="window.sfbcToggleExpand()" aria-label="Expand chat window" title="Make Bigger / Expand">
                    <svg id="sfbcIconExpand" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    <svg id="sfbcIconRestore" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:none; pointer-events:none;"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
                <!-- Close Window Button -->
                <button class="sfbc-header-icon-btn" type="button" onclick="if(window.sfbcToggleChat){window.sfbcToggleChat(false);}else{var b=document.getElementById('sfbcChatBox');if(b){b.style.setProperty('display','none','important');b.classList.remove('sfbc-open');}}" aria-label="Close chat window" title="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="pointer-events:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Quick-Reply Chips Bar -->
        <div id="sfbcQuickChipsBar">
            <button type="button" id="sfbcChipFaq" onclick="sfbcTogglePanel('faq')" class="sfbc-quick-chip" style="background:#7C533C !important; color:#ffffff !important; border-color:#7C533C !important;">Browse FAQs &amp; Rooms</button>
            <button type="button" id="sfbcChipRecommend" onclick="sfbcTogglePanel('recommend')" class="sfbc-quick-chip" style="background:#EFE2D0 !important; color:#5C3824 !important; border-color:#D6B48C !important;">Recommend a Room</button>
            <button type="button" id="sfbcChipRates" onclick="sfbcQuickAsk('What are your room rates and capacities?')" class="sfbc-quick-chip">Room Rates</button>
            <button type="button" id="sfbcChipAvail" onclick="sfbcOpenDatePrompt()" class="sfbc-quick-chip">Check Availability</button>
            <button type="button" id="sfbcChipPay" onclick="sfbcQuickAsk('How does the 50% deposit and balance payment work?')" class="sfbc-quick-chip">How to Pay</button>
            <button type="button" id="sfbcChipCancel" onclick="sfbcQuickAsk('What is your cancellation and refund policy?')" class="sfbc-quick-chip">Cancellation</button>
            <button type="button" id="sfbcChipDir" onclick="sfbcQuickAsk('How do I get to Santa Fe Beach Club?')" class="sfbc-quick-chip">Directions</button>
            <button type="button" id="sfbcChipOtp" onclick="sfbcTogglePanel('otp')" class="sfbc-quick-chip">My Booking (OTP)</button>
            <button type="button" id="sfbcChipStaff" onclick="sfbcTogglePanel('handoff')" class="sfbc-quick-chip">Talk to Staff</button>
        </div>

        <!-- Active Conversation Context Memory Bar (Phase 4 Intelligent Assistance) -->
        <div id="sfbcContextBar" style="display:none; padding:6px 12px; background:#EFF6FF; border-bottom:1px solid #BFDBFE; font-size:11px; color:#1E40AF; align-items:center; justify-content:space-between; gap:8px; flex-shrink:0; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <strong style="font-size:10.5px; color:#1E3A8A;">Remembered Context:</strong>
                <span id="sfbcContextBadges" style="display:inline-flex; gap:5px; flex-wrap:wrap;"></span>
            </div>
            <button type="button" onclick="window.sfbcResetContext()" style="background:none; border:1px solid #93C5FD; color:#1D4ED8; border-radius:4px; padding:1px 6px; font-size:10px; font-weight:600; cursor:pointer;" title="Clear remembered dates, guests, and preferences">× Reset</button>
        </div>

        <!-- Active Live Reception Desk Banner -->
        <div id="sfbcLiveStaffBanner" style="display:none; padding:7px 14px; background:linear-gradient(90deg, #ECFEFF 0%, #F0FDF4 100%); border-bottom:1px solid #99F6E4; font-size:11px; color:#134E4A; align-items:center; justify-content:space-between; gap:8px; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:7px; min-width:0;">
                <span style="width:8px; height:8px; border-radius:50%; background:#10B981; flex-shrink:0; box-shadow:0 0 0 3px rgba(16,185,129,0.2);"></span>
                <span style="font-weight:700; white-space:nowrap;">Live Staff Chat</span>
                <span style="color:#0F766E; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Replies appear here &amp; by email</span>
            </div>
            <button type="button" onclick="window.sfbcResumeAiMode()" style="background:#ffffff; border:1px solid #5EEAD4; color:#0F766E; border-radius:6px; padding:3px 8px; font-size:10.5px; font-weight:700; cursor:pointer; flex-shrink:0;">Switch to AI</button>
        </div>

        <!-- Inline FAQ & Accommodation Knowledge Drawer -->
        <div id="sfbcFaqPanel" style="display:none; max-height:360px; overflow-y:auto; padding:12px 14px; background:#FAF7F2; border-bottom:1px solid #e2e8f0; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="color:#5C3824; font-size:12.5px;">Resort FAQs &amp; Accommodation Guide</strong>
                    <span style="font-size:10.5px; color:#64748B; display:block;">Tap a question for instant answers or to ask AI Concierge</span>
                </div>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#64748B; font-size:14px; font-weight:700; cursor:pointer;" title="Close drawer">&times;</button>
            </div>
            
            <!-- Category Tabs -->
            <div style="display:flex; gap:5px; margin-bottom:10px; overflow-x:auto; padding-bottom:4px;" id="sfbcFaqTabs">
                <button type="button" class="sfbc-faq-tab active" onclick="sfbcSwitchFaqTab('rooms', this)">Rooms &amp; Rates</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('payment', this)">Booking &amp; 50% Deposit</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('cancellation', this)">Cancellation &amp; Refund</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('amenities', this)">Amenities &amp; Wi-Fi</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('policies', this)">Early Arrival &amp; Pets</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('directions', this)">Directions &amp; Port</button>
            </div>

            <!-- Tab 1: Rooms & Rates Guide -->
            <div id="sfbcFaqSection_rooms" class="sfbc-faq-section" style="display:block;">
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Beachview Duplex</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱6,900<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 2 guests · Max extra: 1 adult (+₱1,000/stay) · Direct beachfront view</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Beachview Duplex and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=beachview_duplex" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Seaview Duplex</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱7,900<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 2 guests · Max extra: 1 adult (+₱1,000/stay) · Panoramic ocean views</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Seaview Duplex and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=seaview_duplex" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Beach Villa</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱7,900<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 4 guests · Max extra: 3 adults (+₱1,000 each/stay) · Standalone luxury villa</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Beach Villa and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=beach_villa" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Standard Family Room</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱4,300<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 4 guests · Max extra: 1 adult (+₱700/stay) · Spacious family value</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Standard Family Room and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=standard_king" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Standard Room</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱3,450<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 2 guests · Max extra: 3 adults (+₱700 each/stay) · Clean &amp; comfortable</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Standard Room and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=standard_room" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Payment & 50% Deposit -->
            <div id="sfbcFaqSection_payment" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>How does the 50% deposit and remaining balance work?</summary>
                    <div class="sfbc-faq-item-ans">A 50% deposit confirms your reservation. The remaining 50% balance is due 24 hours before your scheduled arrival time (with a 6-hour grace period). Pay online via PayMongo, GCash (0950 522 3146), or Bank Transfer.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>What payment methods do you accept?</summary>
                    <div class="sfbc-faq-item-ans">We accept GCash, Maya, credit/debit cards via PayMongo, BDO/BPI Bank Deposit, and Cash upon check-in.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>Do you offer Senior Citizen or PWD discounts?</summary>
                    <div class="sfbc-faq-item-ans">Yes! 20% statutory discount + VAT exemption is granted for eligible Senior Citizen and PWD guests via our <a href="discount_booking.php" style="color:#84563C; font-weight:600;">Discount Booking</a> page.</div>
                </details>
            </div>

            <!-- Tab 3: Cancellation & Refund -->
            <div id="sfbcFaqSection_cancellation" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>What is your official cancellation and refund policy?</summary>
                    <div class="sfbc-faq-item-ans">
                        - <strong>1 to 3 nights</strong>: Cancel at least 48 hours (2 days) before 2:00 PM check-in for a 100% refund.<br>
                        - <strong>4 to 6 nights</strong>: Cancel at least 5 days (120 hours) before check-in for a 100% refund.<br>
                        - <strong>7+ nights</strong>: Cancel at least 7 days (168 hours) before check-in for a 100% refund.<br>
                        Cancellations after the deadline are non-refundable.
                    </div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>What happens if my ferry is cancelled due to a typhoon or sea gale?</summary>
                    <div class="sfbc-faq-item-ans">If the Philippine Coast Guard cancels sea travel due to a typhoon warning or sea weather advisory, we gladly reschedule your reservation with no penalty. Contact our front desk at 0950 522 3146.</div>
                </details>
            </div>

            <!-- Tab 4: Amenities & Wi-Fi -->
            <div id="sfbcFaqSection_amenities" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>Is high-speed Wi-Fi included in rooms and beachfront?</summary>
                    <div class="sfbc-faq-item-ans">Yes! Free high-speed Wi-Fi covers all guest rooms, beachfront loungers, lobby, and dining areas.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>Do you offer island hopping, motorcycle rental, and dining?</summary>
                    <div class="sfbc-faq-item-ans">Yes! Our reception assists with motorcycle/scooter rentals, Virgin Island boat tours, and our on-site beachfront restaurant serves daily breakfast and fresh seafood.</div>
                </details>
            </div>

            <!-- Tab 5: Policies & Pets -->
            <div id="sfbcFaqSection_policies" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>Can we store luggage if we arrive before 2:00 PM?</summary>
                    <div class="sfbc-faq-item-ans">Yes! Guests arriving early before the 2:00 PM check-in may store their luggage for free at reception and enjoy the beachfront while waiting.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>Are pets allowed at the resort?</summary>
                    <div class="sfbc-faq-item-ans">Pets are welcome in our ground-level Beach Villas upon prior notice. Please bring pet bedding and keep pets leashed in common areas.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>What are the check-in and check-out times?</summary>
                    <div class="sfbc-faq-item-ans">Standard check-in is at 2:00 PM and check-out is at 12:00 PM noon. Quiet hours are observed from 10:00 PM to 7:00 AM.</div>
                </details>
            </div>

            <!-- Tab 6: Directions & Port -->
            <div id="sfbcFaqSection_directions" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>How far is the resort from Santa Fe Port?</summary>
                    <div class="sfbc-faq-item-ans">We are located along the Pantalan shoreline in Santa Fe, Bantayan Island — only 3 to 5 minutes by tricycle (₱25–₱50/pax) from Santa Fe Port.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>How do I get to Bantayan Island from Cebu City?</summary>
                    <div class="sfbc-faq-item-ans">Take a bus or van from Cebu North Bus Terminal to Hagnaya Port (San Remigio), then take the 1-hour ferry to Santa Fe Port.</div>
                </details>
            </div>
        </div>

        <!-- Inline Live Room Availability & Guided Reservation Assistance Drawer -->
        <div id="sfbcAvailabilityPanel" style="display:none; padding:12px 14px; background:#F0FDF4; border-bottom:1px solid #BBF7D0; font-size:12px; color:#166534; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="color:#14532D; font-size:12.5px;">Reservation Assistance &amp; Live Availability</strong>
                    <span style="font-size:10.5px; color:#15803D; display:block;">Step 1: Collect Details &rarr; Step 2: Check Requirements &rarr; Step 3: Confirm Booking</span>
                </div>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#15803D; font-size:14px; font-weight:700; cursor:pointer;" title="Close drawer">&times;</button>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px; margin-bottom:6px;">
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Check-In Date</label>
                    <input type="date" id="sfbcAvailCin" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                </div>
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Check-Out Date</label>
                    <input type="date" id="sfbcAvailCout" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px; margin-bottom:8px;">
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Total Guests</label>
                    <select id="sfbcAvailGuests" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="1">1 Guest</option>
                        <option value="2" selected>2 Guests</option>
                        <option value="3">3 Guests</option>
                        <option value="4">4 Guests</option>
                        <option value="5">5 Guests</option>
                        <option value="6">6 Guests</option>
                        <option value="7">7 Guests</option>
                        <option value="8">8+ Guests</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Room Preference</label>
                    <select id="sfbcAvailRoomType" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="">All / Any Accommodation</option>
                        <option value="beachview_duplex">Beachview Duplex (₱6,900)</option>
                        <option value="seaview_duplex">Seaview Duplex (₱7,900)</option>
                        <option value="beach_villa">Beach Villa (₱7,900)</option>
                        <option value="standard_king">Standard Family (₱4,300)</option>
                        <option value="standard_room">Standard Room (₱3,450)</option>
                    </select>
                </div>
            </div>
            <div style="background:#DCFCE7; border:1px solid #86EFAC; border-radius:6px; padding:6px 8px; margin-bottom:8px; font-size:10.5px; color:#14532D; line-height:1.35;">
                <strong>Reservation Requirements:</strong> <strong>50% deposit</strong> required upon booking (PayMongo / GCash / Bank); <strong>50% balance</strong> due 24h before arrival; bring a <strong>valid ID &amp; booking QR code</strong> at 2:00 PM check-in.
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <button type="button" onclick="sfbcSubmitAvailabilityQuery()" style="width:100%; padding:8px 6px; background:#16A34A; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:11.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <span>Show Room Cards</span>
                </button>
                <button type="button" onclick="sfbcDirectToConfirmBooking()" style="width:100%; padding:8px 6px; background:#0E9AAD; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:11.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <span>Confirm Booking &rarr;</span>
                </button>
            </div>
        </div>

        <!-- Inline Smart Accommodation Recommender & Reservation Assistance Drawer -->
        <div id="sfbcRecommendPanel" style="display:none; padding:12px 14px; background:#FDF4FF; border-bottom:1px solid #F0ABFC; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#701A75; font-size:12px;">Smart Room Recommender &amp; Reservation Helper</strong>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#701A75; font-size:12px; font-weight:700; cursor:pointer;">Close ×</button>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#701A75; display:block; margin-bottom:2px;">Number of Guests</label>
                    <select id="sfbcRecGuests" style="width:100%; padding:5px 8px; border:1px solid #E879F9; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="2" selected>2 Guests (Couple / Pair)</option>
                        <option value="1">1 Guest (Solo Traveler)</option>
                        <option value="3">3 Guests</option>
                        <option value="4">4 Guests (Small Family)</option>
                        <option value="5">5 Guests</option>
                        <option value="6">6 Guests (Family / Group)</option>
                        <option value="8">8+ Guests (Barkada / Multi-Room)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#701A75; display:block; margin-bottom:2px;">Max Nightly Budget</label>
                    <select id="sfbcRecBudget" style="width:100%; padding:5px 8px; border:1px solid #E879F9; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="">Any Budget</option>
                        <option value="4000">Up to ₱4,000 / night</option>
                        <option value="5000">Up to ₱5,000 / night</option>
                        <option value="7000">Up to ₱7,000 / night</option>
                        <option value="8000">Up to ₱8,000 / night</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:8px;">
                <label style="font-size:10.5px; font-weight:600; color:#701A75; display:block; margin-bottom:2px;">Trip Style / Priority</label>
                <select id="sfbcRecStyle" style="width:100%; padding:5px 8px; border:1px solid #E879F9; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                    <option value="beachfront">Direct Beachfront &amp; Ocean View</option>
                    <option value="honeymoon">Romantic / Honeymoon / Anniversary</option>
                    <option value="family">Family / Barkada Getaway</option>
                    <option value="budget">Best Value / Budget-Friendly</option>
                    <option value="senior">Senior / Ground-Floor Easy Access</option>
                    <option value="workation">Quiet Workation / Remote Work</option>
                </select>
            </div>
            <button type="button" onclick="sfbcSubmitRecommendQuery()" style="width:100%; padding:8px; background:#86198F; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:background 0.15s ease;">
                <span>Find Best Room &amp; Pre-Filled Booking Link</span> &rarr;
            </button>
        </div>

        <!-- Inline OTP Booking Verification Drawer -->
        <div id="sfbcOtpPanel" style="display:none; padding:12px 14px; background:#FFFBEB; border-bottom:1px solid #FDE68A; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#5C3824; font-size:12px;">Verify My Booking (Email OTP)</strong>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#64748B; font-size:12px; cursor:pointer;">Close ×</button>
            </div>
            <div id="sfbcOtpStep1" style="display:flex; flex-direction:column; gap:6px;">
                <input type="text" id="sfbcOtpRef" placeholder="Booking Reference (e.g. REF-001)" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <input type="email" id="sfbcOtpEmail" placeholder="Booking Email Address" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <button type="button" onclick="sfbcRequestBookingOtp()" style="width:100%; padding:7px; background:#84563C; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">Send 6-Digit Verification Code</button>
            </div>
            <div id="sfbcOtpStep2" style="display:none; flex-direction:column; gap:6px;">
                <p id="sfbcOtpHintText" style="font-size:11px; color:#475569; margin:0;"></p>
                <input type="text" id="sfbcOtpCode" maxlength="6" placeholder="Enter 6-digit OTP code" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#fff; letter-spacing:4px; text-align:center; font-weight:700;">
                <button type="button" onclick="sfbcVerifyBookingOtp()" style="width:100%; padding:7px; background:#16A34A; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">Verify &amp; Show Booking</button>
            </div>
        </div>

        <!-- Inline Human Staff Handoff Drawer -->
        <div id="sfbcHandoffPanel" style="display:none; padding:12px 14px; background:#F1F5F9; border-bottom:1px solid #e2e8f0; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#5C3824; font-size:12px;">Forward Chat to Reception Staff</strong>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#64748B; font-size:12px; cursor:pointer;">Close ×</button>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px;">
                <input type="text" id="sfbcHandoffName" placeholder="Your Full Name" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <input type="email" id="sfbcHandoffEmail" placeholder="Your Email Address" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <input type="text" id="sfbcHandoffNote" placeholder="Short note for reception (optional)" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <button type="button" onclick="sfbcSubmitHandoff()" style="width:100%; padding:7px; background:#84563C; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">Send Transcript to Staff</button>
            </div>
        </div>

        <!-- Messages Area -->
        <div id="sfbcChatMessages">
            <div style="display:flex; flex-direction:column; align-items:flex-start; width:100%;">
                <div class="sfbc-bot-label">
                    <img src="<?php echo htmlspecialchars($sfbc_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club" onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='frontend/assets/logo.jpg';}">
                    <span>Santa Fe Concierge</span>
                </div>
                <div class="sfbc-msg-bubble-model">
                    Hello! Welcome to <strong>Santa Fe Beach Club</strong>. I can check live room availability &amp; prices, recommend the best accommodation for your group, prepare pre-filled booking links, verify your reservation via OTP, or answer resort questions in <strong>any language</strong> (English, Tagalog, Bisaya, 한국어, 日本語, 中文, Español, Français, Deutsch, and more). How may I assist you today?
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; max-width:100%;">
                    <span style="font-size:10.5px; color:#7C533C; font-weight:700; width:100%; margin-bottom:1px;">Popular questions to get started:</span>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('Hi! Can I book a stay on February 1-11?')">Book a Stay (Feb 1–11)</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('What are your room rates and capacities?')">Room Rates &amp; Capacities</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcOpenDatePrompt()">Check Dates Availability</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('How do I book and what are the reservation requirements?')">Reservation Requirements</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('What is your cancellation and refund policy?')">Cancellation Policy</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('How do I get to Santa Fe Beach Club?')">Directions from Port</button>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="sfbc-composer-dock">
            <div class="sfbc-input-pill">
                <input type="text" id="sfbcChatInput" maxlength="500" placeholder="Ask about rooms, dates, rates, policies..." onkeypress="if(event.key==='Enter') sfbcSendMessage();">
                <button type="button" class="sfbc-send-btn" onclick="sfbcSendMessage()">
                    <span>Send</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px; color:#8B7366; padding:0 4px;">
                <span>Chats stored to improve service (<a href="../terms.php#privacy" style="text-decoration:underline; color:#6B4F42;">Privacy Policy</a>)</span>
                <button type="button" onclick="sfbcTogglePanel('handoff')" style="background:none; border:none; color:#84563C; font-weight:700; text-decoration:underline; cursor:pointer; font-size:10.5px; padding:0;">Need a human?</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const CSRF_TOKEN = <?php echo json_encode($sfbc_chat_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const SFBC_LOGO_URL = <?php echo json_encode($sfbc_logo_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function resolveChatbotApiUrl() {
        const path = window.location.pathname || '';
        if (path.indexOf('/frontend/') !== -1) {
            return '../backend/api/chatbot_api.php';
        }
        return 'backend/api/chatbot_api.php';
    }

    /**
     * Strictly escape HTML entities and render only a safe Markdown subset
     * (bold, inline code, bullet lines, and whitelisted relative resort links).
     */
    function sfbcEscapeHtml(raw) {
        return String(raw ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/`/g, '&#96;');
    }

    function sfbcSanitizeRelativeUrl(url) {
        const clean = String(url ?? '').trim();
        const allowed = /^(book|rooms|my_booking|contact|location|gallery|discount_booking\.php|cancel_booking|checkin)(\?[a-zA-Z0-9_=&%\-\.]*)?(#[\w\-]+)?$/;
        return allowed.test(clean) ? clean : null;
    }

    function sfbcFormatSafeReply(rawText) {
        let escaped = sfbcEscapeHtml(rawText);

        // Safe relative links: [Label](book?room_type=...)
        escaped = escaped.replace(/\[([^\]]{1,80})\]\(([^)]{1,200})\)/g, function(_, label, rawHref) {
            const decodedHref = rawHref.replace(/&amp;/g, '&');
            const safeHref = sfbcSanitizeRelativeUrl(decodedHref);
            if (!safeHref) {
                return label;
            }
            const safeAttr = sfbcEscapeHtml(safeHref);
            const isBookingLink = safeHref.indexOf('book') === 0;
            const clickAttr = isBookingLink ? ' onclick="window.sfbcTrackBookingClick && window.sfbcTrackBookingClick()"' : '';
            return '<a href="' + safeAttr + '"' + clickAttr + ' style="display:inline-flex; align-items:center; gap:4px; font-weight:700; color:#84563C; text-decoration:underline;">' + label + ' &rarr;</a>';
        });

        // Bold: **text**
        escaped = escaped.replace(/\*\*([^*]{1,160})\*\*/g, '<strong>$1</strong>');

        // Inline code: &#96;code&#96;
        escaped = escaped.replace(/&#96;([^&]{1,80})&#96;/g, '<code style="padding:2px 4px; background:#f1f5f9; border-radius:4px; font-size:11px; font-family:monospace;">$1</code>');

        // Line breaks
        return escaped.replace(/\r?\n/g, '<br>');
    }

    // Expose formatter for automated XSS regression verification
    window.sfbcFormatSafeReply = sfbcFormatSafeReply;

    function getChatEls() {
        return {
            toggleBtn: document.getElementById('sfbcChatToggle'),
            chatBox: document.getElementById('sfbcChatBox'),
            iconOpen: document.getElementById('sfbcIconOpen'),
            iconClose: document.getElementById('sfbcIconClose'),
            inputEl: document.getElementById('sfbcChatInput'),
            messagesEl: document.getElementById('sfbcChatMessages'),
            langSelect: document.getElementById('sfbcLangSelect'),
            otpPanel: document.getElementById('sfbcOtpPanel'),
            handoffPanel: document.getElementById('sfbcHandoffPanel'),
            faqPanel: document.getElementById('sfbcFaqPanel'),
            availPanel: document.getElementById('sfbcAvailabilityPanel'),
            recPanel: document.getElementById('sfbcRecommendPanel')
        };
    }

    let sfbcLastSyncedId = 0;
    const sfbcRenderedServerIds = new Set();
    let sfbcLiveStaffActive = false;
    let sfbcPollTimer = null;
    let sfbcIsSending = false;
    let sfbcUnreadStaffCount = 0;

    function updateUnreadBadgeUI() {
        const badge = document.getElementById('sfbcToggleUnreadBadge');
        if (!badge) return;
        if (sfbcUnreadStaffCount > 0) {
            badge.textContent = sfbcUnreadStaffCount > 9 ? '9+' : String(sfbcUnreadStaffCount);
            badge.classList.add('is-visible');
        } else {
            badge.textContent = '';
            badge.classList.remove('is-visible');
        }
    }

    function isChatWindowOpen() {
        const els = getChatEls();
        return Boolean(els.chatBox && (els.chatBox.style.display === 'flex' || els.chatBox.classList.contains('sfbc-open')));
    }

    window.sfbcSetLiveStaffMode = function(active) {
        const nextActive = Boolean(active);
        const wasActive = sfbcLiveStaffActive;
        sfbcLiveStaffActive = nextActive;

        const banner = document.getElementById('sfbcLiveStaffBanner');
        const subtitle = document.getElementById('sfbcHeaderSubtitle');
        const inputEl = document.getElementById('sfbcChatInput');

        if (banner) {
            banner.style.setProperty('display', nextActive ? 'flex' : 'none', 'important');
        }
        if (subtitle) {
            subtitle.textContent = nextActive ? 'Reception Desk · Live Support' : '24/7 Resort Concierge';
        }
        if (inputEl) {
            if (nextActive) {
                inputEl.placeholder = 'Message our reception staff live...';
            } else {
                const langVal = (document.getElementById('sfbcLangSelect') || {}).value || 'English';
                const dict = (typeof SFBC_UI_I18N !== 'undefined' && SFBC_UI_I18N[langVal]) ? SFBC_UI_I18N[langVal] : null;
                inputEl.placeholder = dict ? dict.placeholder : 'Ask about rooms, dates, rates, policies...';
            }
        }

        if (nextActive && !wasActive) {
            startLiveSyncPolling();
        } else if (!nextActive && sfbcPollTimer) {
            clearInterval(sfbcPollTimer);
            sfbcPollTimer = null;
        }
    };

    window.sfbcResumeAiMode = async function() {
        try {
            await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'resume_ai', csrf_token: CSRF_TOKEN })
            });
        } catch (e) {}
        window.sfbcSetLiveStaffMode(false);
        appendBubble('Switched back to **Santa Fe AI Concierge**. How else may I assist you today?', 'model');
    };

    async function syncGuestMessages(isBackgroundPoll) {
        if (isBackgroundPoll && (sfbcIsSending || document.hidden)) {
            return;
        }
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'sync_messages', after_id: sfbcLastSyncedId, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (!data || !data.success) return;

            const wasLive = sfbcLiveStaffActive;
            if (Array.isArray(data.messages) && data.messages.length > 0) {
                data.messages.forEach(function(m) {
                    const mid = Number(m.id || 0);
                    if (mid > sfbcLastSyncedId) {
                        sfbcLastSyncedId = mid;
                    }
                    if (mid > 0 && sfbcRenderedServerIds.has(mid)) {
                        return;
                    }
                    if (mid > 0) {
                        sfbcRenderedServerIds.add(mid);
                    }
                    const role = m.role === 'staff' ? 'staff' : (m.role === 'user' ? 'user' : 'model');
                    appendBubble(
                        String(m.content || ''),
                        role,
                        role === 'model' ? mid : 0,
                        [],
                        [],
                        false,
                        [],
                        { sender_name: m.sender_name || 'Reception Staff', created_at: m.created_at || '' }
                    );
                    if (isBackgroundPoll && role === 'staff' && !isChatWindowOpen()) {
                        sfbcUnreadStaffCount += 1;
                        updateUnreadBadgeUI();
                    }
                });
            }

            if (Number(data.last_id || 0) > sfbcLastSyncedId) {
                sfbcLastSyncedId = Number(data.last_id || 0);
            }

            window.sfbcSetLiveStaffMode(Boolean(data.live_chat_active));

            if (wasLive && data.ticket_resolved) {
                appendBubble('Our reception team has marked this support ticket as resolved. Let me know if there is anything else I can assist you with!', 'model');
            }
        } catch (e) {}
    }

    function startLiveSyncPolling() {
        if (sfbcPollTimer) {
            clearInterval(sfbcPollTimer);
        }
        sfbcPollTimer = setInterval(function() {
            if (sfbcLiveStaffActive && !document.hidden) {
                syncGuestMessages(true);
            }
        }, 4000);
    }

    // Rock-solid toggle function with debounce and !important property enforcement
    let sfbcLastToggleTime = 0;
    window.sfbcToggleChat = function(forceState) {
        const now = Date.now();
        if (typeof forceState !== 'boolean' && (now - sfbcLastToggleTime < 300)) {
            return;
        }
        sfbcLastToggleTime = now;

        const els = getChatEls();
        if (!els.chatBox) return;
        const isCurrentlyOpen = (els.chatBox.style.display === 'flex' || els.chatBox.classList.contains('sfbc-open'));
        const shouldOpen = (typeof forceState === 'boolean') ? forceState : !isCurrentlyOpen;

        if (shouldOpen) {
            els.chatBox.style.setProperty('display', 'flex', 'important');
            els.chatBox.classList.add('sfbc-open');
            if (els.iconOpen) els.iconOpen.style.setProperty('display', 'none', 'important');
            if (els.iconClose) els.iconClose.style.setProperty('display', 'block', 'important');
            if (els.toggleBtn) els.toggleBtn.setAttribute('aria-expanded', 'true');
            sfbcUnreadStaffCount = 0;
            updateUnreadBadgeUI();
            if (els.messagesEl) {
                els.messagesEl.scrollTop = els.messagesEl.scrollHeight;
            }
            if (sfbcLiveStaffActive) {
                syncGuestMessages(true);
            }
            setTimeout(function() {
                if (els.inputEl) els.inputEl.focus();
            }, 60);
        } else {
            els.chatBox.style.setProperty('display', 'none', 'important');
            els.chatBox.classList.remove('sfbc-open');
            if (els.iconOpen) els.iconOpen.style.setProperty('display', 'block', 'important');
            if (els.iconClose) els.iconClose.style.setProperty('display', 'none', 'important');
            if (els.toggleBtn) els.toggleBtn.setAttribute('aria-expanded', 'false');
        }
    };

    // Toggle between normal and big/expanded window size
    window.sfbcToggleExpand = function(forceState) {
        const box = document.getElementById('sfbcChatBox');
        const iconExp = document.getElementById('sfbcIconExpand');
        const iconRes = document.getElementById('sfbcIconRestore');
        const btn = document.getElementById('sfbcExpandBtn');
        if (!box) return;
        const isExpanded = box.classList.contains('sfbc-expanded');
        const shouldExpand = (typeof forceState === 'boolean') ? forceState : !isExpanded;

        if (shouldExpand) {
            box.classList.add('sfbc-expanded');
            if (iconExp) iconExp.style.display = 'none';
            if (iconRes) iconRes.style.display = 'block';
            if (btn) {
                btn.title = 'Shrink / Normal Size';
                btn.setAttribute('aria-label', 'Shrink chat window');
            }
            try { localStorage.setItem('sfbc_chat_expanded', '1'); } catch (e) {}
        } else {
            box.classList.remove('sfbc-expanded');
            if (iconExp) iconExp.style.display = 'block';
            if (iconRes) iconRes.style.display = 'none';
            if (btn) {
                btn.title = 'Make Bigger / Expand';
                btn.setAttribute('aria-label', 'Expand chat window');
            }
            try { localStorage.removeItem('sfbc_chat_expanded'); } catch (e) {}
        }
    };

    // Auto-restore expanded preference
    try {
        if (localStorage.getItem('sfbc_chat_expanded') === '1') {
            window.sfbcToggleExpand(true);
        }
    } catch (e) {}

    window.sfbcTogglePanel = function(which) {
        const els = getChatEls();
        if (els.otpPanel) els.otpPanel.style.setProperty('display', 'none', 'important');
        if (els.handoffPanel) els.handoffPanel.style.setProperty('display', 'none', 'important');
        if (els.faqPanel) els.faqPanel.style.setProperty('display', 'none', 'important');
        if (els.availPanel) els.availPanel.style.setProperty('display', 'none', 'important');
        if (els.recPanel) els.recPanel.style.setProperty('display', 'none', 'important');

        if (which === 'otp' && els.otpPanel) {
            els.otpPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'handoff' && els.handoffPanel) {
            els.handoffPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'faq' && els.faqPanel) {
            els.faqPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'avail' && els.availPanel) {
            els.availPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'recommend' && els.recPanel) {
            els.recPanel.style.setProperty('display', 'block', 'important');
        }
    };

    window.sfbcSwitchFaqTab = function(tabName, btnEl) {
        const tabs = document.querySelectorAll('#sfbcFaqTabs .sfbc-faq-tab');
        tabs.forEach(function(t) { t.classList.remove('active'); });
        if (btnEl) btnEl.classList.add('active');

        const sections = document.querySelectorAll('.sfbc-faq-section');
        sections.forEach(function(s) { s.style.display = 'none'; });
        const target = document.getElementById('sfbcFaqSection_' + tabName);
        if (target) target.style.display = 'block';
    };

    window.sfbcQuickAsk = function(questionText) {
        const els = getChatEls();
        if (!els.inputEl) return;
        els.inputEl.value = questionText;
        window.sfbcSendMessage();
    };

    window.sfbcOpenDatePrompt = function() {
        const els = getChatEls();
        const isAvailOpen = (els.availPanel && els.availPanel.style.display === 'block');
        if (isAvailOpen) {
            window.sfbcTogglePanel(null);
            return;
        }
        window.sfbcTogglePanel('avail');

        const cinEl = document.getElementById('sfbcAvailCin');
        const coutEl = document.getElementById('sfbcAvailCout');
        const today = new Date().toISOString().slice(0, 10);
        const tom = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        const nextDay = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);

        if (cinEl) {
            cinEl.min = today;
            if (!cinEl.value) cinEl.value = tom;
            cinEl.addEventListener('change', function() {
                if (coutEl && coutEl.value <= cinEl.value) {
                    const next = new Date(new Date(cinEl.value).getTime() + 86400000);
                    coutEl.value = next.toISOString().slice(0, 10);
                    coutEl.min = next.toISOString().slice(0, 10);
                }
            });
        }
        if (coutEl) {
            coutEl.min = tom;
            if (!coutEl.value) coutEl.value = nextDay;
        }
    };

    window.sfbcSubmitAvailabilityQuery = function() {
        const cinEl = document.getElementById('sfbcAvailCin');
        const coutEl = document.getElementById('sfbcAvailCout');
        const guestsEl = document.getElementById('sfbcAvailGuests');
        const roomEl = document.getElementById('sfbcAvailRoomType');

        const cin = (cinEl && cinEl.value) ? cinEl.value : '';
        const cout = (coutEl && coutEl.value) ? coutEl.value : '';
        const guests = (guestsEl && guestsEl.value) ? guestsEl.value : '2';
        const roomSlug = (roomEl && roomEl.value) ? roomEl.value : '';

        if (!cin || !cout) {
            alert('Please select check-in and check-out dates.');
            return;
        }
        if (cout <= cin) {
            alert('Check-out date must be after check-in date.');
            return;
        }

        window.sfbcTogglePanel(null);

        let query = 'Check availability from ' + cin + ' to ' + cout + ' for ' + guests + ' guest(s)';
        if (roomSlug) {
            query += ' in ' + roomSlug;
        }
        window.sfbcQuickAsk(query);
    };

    window.sfbcDirectToConfirmBooking = function() {
        const cinEl = document.getElementById('sfbcAvailCin');
        const coutEl = document.getElementById('sfbcAvailCout');
        const guestsEl = document.getElementById('sfbcAvailGuests');
        const roomEl = document.getElementById('sfbcAvailRoomType');

        const cin = (cinEl && cinEl.value) ? cinEl.value : '';
        const cout = (coutEl && coutEl.value) ? coutEl.value : '';
        const guests = (guestsEl && guestsEl.value) ? guestsEl.value : '2';
        const roomSlug = (roomEl && roomEl.value) ? roomEl.value : 'beachview_duplex';

        if (!cin || !cout || cout <= cin) {
            alert('Please select valid check-in and check-out dates first.');
            return;
        }
        window.sfbcTrackBookingClick();
        const target = 'book?room_type=' + encodeURIComponent(roomSlug)
            + '&checkin=' + encodeURIComponent(cin)
            + '&checkout=' + encodeURIComponent(cout)
            + '&guests=' + encodeURIComponent(guests);
        window.location.href = target;
    };

    window.sfbcSubmitRecommendQuery = function() {
        const guestsEl = document.getElementById('sfbcRecGuests');
        const budgetEl = document.getElementById('sfbcRecBudget');
        const styleEl = document.getElementById('sfbcRecStyle');

        const guests = (guestsEl && guestsEl.value) ? guestsEl.value : '2';
        const budget = (budgetEl && budgetEl.value) ? budgetEl.value : '';
        const style = (styleEl && styleEl.value) ? styleEl.value : 'beachfront';

        const styleMap = {
            'beachfront': 'beachfront ocean view',
            'honeymoon': 'romantic honeymoon couple',
            'family': 'family barkada group',
            'budget': 'budget affordable',
            'senior': 'senior accessible ground floor',
            'workation': 'quiet workation remote work'
        };
        const styleDesc = styleMap[style] || style;

        window.sfbcTogglePanel(null);

        let query = 'Please recommend the best room for ' + guests + ' guests looking for a ' + styleDesc + ' stay';
        if (budget) {
            query += ' under ' + budget + ' per night';
        }
        window.sfbcQuickAsk(query);
    };

    window.sfbcTrackBookingClick = function() {
        fetch(resolveChatbotApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'track_booking_click', csrf_token: CSRF_TOKEN })
        }).catch(function() {});
    };

    window.sfbcSendFeedback = function(messageId, rating, btnEl) {
        if (!messageId) return;
        fetch(resolveChatbotApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'feedback', message_id: messageId, feedback: rating, csrf_token: CSRF_TOKEN })
        }).then(function() {
            if (btnEl && btnEl.parentElement) {
                if (rating === -1) {
                    btnEl.parentElement.innerHTML = '<span style="font-size:10.5px; color:#b45309;">Sorry about that! <button type="button" onclick="sfbcTogglePanel(\'handoff\')" style="background:none; border:none; color:#84563C; font-weight:700; text-decoration:underline; cursor:pointer; font-size:10.5px; padding:0;">Forward chat to live staff &rarr;</button></span>';
                    window.sfbcTogglePanel('handoff');
                } else {
                    btnEl.parentElement.innerHTML = '<span style="font-size:10px; color:#16a34a;">Thank you for your feedback!</span>';
                }
            }
        }).catch(function() {});
    };

    window.sfbcRequestBookingOtp = async function() {
        const ref = (document.getElementById('sfbcOtpRef').value || '').trim();
        const email = (document.getElementById('sfbcOtpEmail').value || '').trim();
        if (!ref || !email) {
            appendBubble('Please enter both your Booking Reference (e.g. **REF-001**) and email address.', 'model');
            return;
        }
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'request_booking_otp', booking_ref: ref, email: email, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('sfbcOtpStep1').style.setProperty('display', 'none', 'important');
                document.getElementById('sfbcOtpStep2').style.setProperty('display', 'flex', 'important');
                document.getElementById('sfbcOtpHintText').textContent = data.message || 'Enter the 6-digit code sent to your email.';
            } else {
                appendBubble(data.error || 'Could not find that booking.', 'model');
            }
        } catch (e) {
            appendBubble('Could not send OTP right now. Please try again.', 'model');
        }
    };

    window.sfbcVerifyBookingOtp = async function() {
        const otp = (document.getElementById('sfbcOtpCode').value || '').trim();
        if (!otp) return;
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'verify_booking_otp', otp: otp, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success && data.reply) {
                window.sfbcTogglePanel(null);
                appendBubble(data.reply, 'model', 0, data.suggested_actions || []);
            } else {
                appendBubble(data.error || 'Invalid OTP code.', 'model');
            }
        } catch (e) {
            appendBubble('Verification failed. Please try again.', 'model');
        }
    };

    window.sfbcSubmitHandoff = async function() {
        const name = (document.getElementById('sfbcHandoffName').value || '').trim();
        const email = (document.getElementById('sfbcHandoffEmail').value || '').trim();
        const noteEl = document.getElementById('sfbcHandoffNote');
        const note = (noteEl ? noteEl.value : '').trim();
        if (!name || !email) {
            appendBubble('Please enter your name and email address so our reception desk can contact you.', 'model');
            return;
        }
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'handoff', guest_name: name, guest_email: email, note: note, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success) {
                window.sfbcTogglePanel(null);
                if (noteEl) noteEl.value = '';
                const hmId = Number(data.handoff_message_id || 0);
                if (hmId > 0) {
                    sfbcRenderedServerIds.add(hmId);
                    if (hmId > sfbcLastSyncedId) sfbcLastSyncedId = hmId;
                }
                appendBubble(data.message || 'Your message has been sent to our front desk staff.', 'model');
                if (data.live_chat_active) {
                    window.sfbcSetLiveStaffMode(true);
                }
            } else {
                appendBubble(data.error || 'Could not forward conversation.', 'model');
            }
        } catch (e) {
            appendBubble('Network error while forwarding your request.', 'model');
        }
    };

    const SFBC_UI_I18N = {
        'English': {
            placeholder: 'Ask about rooms, dates, rates, recommendations...',
            faq: 'Browse FAQs & Rooms',
            recommend: 'Recommend a Room',
            recommendQ: 'Can you recommend the best room for us?',
            rates: 'Room Rates',
            ratesQ: 'What are your room rates and capacities?',
            avail: 'Check Availability',
            pay: 'How to Pay',
            payQ: 'How does the 50% deposit and balance payment work?',
            cancel: 'Cancellation',
            cancelQ: 'What is your cancellation and refund policy?',
            dir: 'Directions',
            dirQ: 'How do I get to Santa Fe Beach Club?',
            otp: 'My Booking (OTP)',
            staff: 'Talk to Staff'
        },
        'Tagalog': {
            placeholder: 'Magtanong tungkol sa kwarto, petsa, presyo, o rekomendasyon...',
            faq: 'Mga FAQ at Kwarto',
            recommend: 'Mag-recommend ng Kwarto',
            recommendQ: 'Anong magandang kwarto ang ma-rerecommend ninyo para sa amin?',
            rates: 'Presyo ng Kwarto',
            ratesQ: 'Magkano ang mga kwarto at ilang tao ang kasya?',
            avail: 'Tingnan ang Petsa',
            pay: '50% Deposit',
            payQ: 'Paano magbayad ng 50% deposit at kailan ang balanse?',
            cancel: 'Cancellation Policy',
            cancelQ: 'Ano ang cancellation at refund policy?',
            dir: 'Direksyon',
            dirQ: 'Paano pumunta sa Santa Fe Beach Club mula sa pantalan?',
            otp: 'Booking Ko (OTP)',
            staff: 'Kausapin ang Staff'
        },
        'Cebuano / Bisaya': {
            placeholder: 'Pangutana bahin sa kwarto, petsa, presyo, o rekomendasyon...',
            faq: 'Mga FAQ ug Kwarto',
            recommend: 'I-recommend og Kwarto',
            recommendQ: 'Unsang maayong kwarto ang imong ma-recommend para namo?',
            rates: 'Presyo sa Kwarto',
            ratesQ: 'Pila ang presyo sa inyong mga kwarto ug kapasidad?',
            avail: 'Tan-awa ang Petsa',
            pay: 'Unsaon Pagbayad',
            payQ: 'Unsaon pagbayad sa 50% deposit ug kanus-a ang balanse?',
            cancel: 'Polisiya sa Refund',
            cancelQ: 'Unsa ang inyong polisiya sa pag-cancel ug refund?',
            dir: 'Direksyon',
            dirQ: 'Unsaon pag-abot sa Santa Fe Beach Club gikan sa pantalan?',
            otp: 'Akong Booking (OTP)',
            staff: 'Istorya sa Staff'
        },
        'Korean': {
            placeholder: '객실, 날짜, 요금, 추천에 대해 질문해 보세요...',
            faq: 'FAQ 및 객실 안내',
            recommend: '맞춤 객실 추천',
            recommendQ: '저희에게 가장 좋은 객실을 추천해 주시겠어요?',
            rates: '객실 요금',
            ratesQ: '객실 요금과 정원은 어떻게 되나요?',
            avail: '빈방 조회',
            pay: '결제 방법',
            payQ: '50% 예약금 및 잔금 결제는 어떻게 하나요?',
            cancel: '취소 규정',
            cancelQ: '예약 취소 및 환불 규정이 어떻게 되나요?',
            dir: '오시는 길',
            dirQ: '산타페 비치 클럽까지 어떻게 가나요?',
            otp: '예약 확인 (OTP)',
            staff: '상담원 연결'
        },
        'Japanese': {
            placeholder: '客室、日程、料金、おすすめについて質問してください...',
            faq: 'FAQ・客室案内',
            recommend: 'おすすめ客室診断',
            recommendQ: '私たちに最適なお部屋をおすすめしてくれますか？',
            rates: '客室料金',
            ratesQ: '客室料金と定員を教えてください。',
            avail: '空室確認',
            pay: 'お支払い方法',
            payQ: '50%のデポジットと残金の支払い方法は？',
            cancel: 'キャンセル規定',
            cancelQ: 'キャンセルと返金ポリシーを教えてください。',
            dir: 'アクセス',
            dirQ: 'サンタフェ・ビーチクラブへの行き方を教えてください。',
            otp: '予約確認 (OTP)',
            staff: 'スタッフに相談'
        },
        'Chinese': {
            placeholder: '询问房型、日期、价格或住宿推荐...',
            faq: '常见问题与房型',
            recommend: '智能房型推荐',
            recommendQ: '您能为我们推荐最合适的房间吗？',
            rates: '房价与人数',
            ratesQ: '你们的房价和可住人数是多少？',
            avail: '查询空房',
            pay: '付款方式',
            payQ: '50%定金和余款如何支付？',
            cancel: '取消政策',
            cancelQ: '你们的取消和退款政策是什么？',
            dir: '交通指南',
            dirQ: '从码头怎么去圣达菲海滩俱乐部？',
            otp: '我的预订 (OTP)',
            staff: '联系人工客服'
        },
        'Spanish': {
            placeholder: 'Pregunte sobre habitaciones, fechas, tarifas o recomendaciones...',
            faq: 'Preguntas y Habitaciones',
            recommend: 'Recomendar Habitación',
            recommendQ: '¿Puede recomendarnos la mejor habitación?',
            rates: 'Tarifas',
            ratesQ: '¿Cuáles son las tarifas y capacidades de las habitaciones?',
            avail: 'Ver Disponibilidad',
            pay: 'Cómo Pagar',
            payQ: '¿Cómo funciona el depósito del 50% y el saldo?',
            cancel: 'Cancelación',
            cancelQ: '¿Cuál es su política de cancelación y reembolso?',
            dir: 'Cómo Llegar',
            dirQ: '¿Cómo llego a Santa Fe Beach Club desde el puerto?',
            otp: 'Mi Reserva (OTP)',
            staff: 'Hablar con Recepción'
        },
        'French': {
            placeholder: 'Posez vos questions sur les chambres, dates, tarifs...',
            faq: 'FAQ et Chambres',
            recommend: 'Recommander une Chambre',
            recommendQ: 'Pouvez-vous nous recommander la meilleure chambre ?',
            rates: 'Tarifs',
            ratesQ: 'Quels sont les tarifs et capacités des chambres ?',
            avail: 'Disponibilités',
            pay: 'Paiement',
            payQ: 'Comment fonctionne l’acompte de 50 % ?',
            cancel: 'Annulation',
            cancelQ: 'Quelle est votre politique d’annulation et de remboursement ?',
            dir: 'Itinéraire',
            dirQ: 'Comment se rendre au Santa Fe Beach Club ?',
            otp: 'Ma Réservation (OTP)',
            staff: 'Parler au Personnel'
        },
        'German': {
            placeholder: 'Fragen Sie nach Zimmern, Daten, Preisen oder Empfehlungen...',
            faq: 'FAQs & Zimmer',
            recommend: 'Zimmer Empfehlen',
            recommendQ: 'Können Sie uns das beste Zimmer empfehlen?',
            rates: 'Zimmerpreise',
            ratesQ: 'Wie hoch sind die Zimmerpreise und Kapazitäten?',
            avail: 'Verfügbarkeit',
            pay: 'Zahlung',
            payQ: 'Wie funktioniert die 50% Anzahlung?',
            cancel: 'Stornierung',
            cancelQ: 'Wie lautet Ihre Stornierungs- und Rückerstattungsrichtlinie?',
            dir: 'Anfahrt',
            dirQ: 'Wie komme ich zum Santa Fe Beach Club?',
            otp: 'Meine Buchung (OTP)',
            staff: 'Personal Kontaktieren'
        }
    };

    window.sfbcOnLangChange = function(lang) {
        const key = SFBC_UI_I18N[lang] ? lang : 'English';
        const dict = SFBC_UI_I18N[key];
        const inputEl = document.getElementById('sfbcChatInput');
        if (inputEl) inputEl.placeholder = dict.placeholder;

        const setChip = function(id, label, q) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = label;
            if (q) {
                el.onclick = function() { window.sfbcQuickAsk(q); };
            }
        };
        setChip('sfbcChipFaq', dict.faq, null);
        setChip('sfbcChipRecommend', dict.recommend, null);
        setChip('sfbcChipRates', dict.rates, dict.ratesQ);
        setChip('sfbcChipAvail', dict.avail, null);
        setChip('sfbcChipPay', dict.pay, dict.payQ);
        setChip('sfbcChipCancel', dict.cancel, dict.cancelQ);
        setChip('sfbcChipDir', dict.dir, dict.dirQ);
        setChip('sfbcChipOtp', dict.otp, null);
        setChip('sfbcChipStaff', dict.staff, null);
    };

    window.sfbcUpdateContextBar = function(ctx) {
        const bar = document.getElementById('sfbcContextBar');
        const badgesEl = document.getElementById('sfbcContextBadges');
        if (!bar || !badgesEl || !ctx) return;

        const items = [];
        if (Array.isArray(ctx.dates) && ctx.dates.length === 2) {
            items.push('Dates: ' + ctx.dates[0] + ' → ' + ctx.dates[1]);
        }
        if (ctx.guests) {
            items.push(ctx.guests + ' Guest(s)');
        }
        if (ctx.room_name) {
            items.push('Room: ' + ctx.room_name);
        }
        if (ctx.max_budget) {
            items.push('Budget ≤ ₱' + Number(ctx.max_budget).toLocaleString() + '/night');
        }
        if (ctx.preference) {
            items.push('Style: ' + ctx.preference);
        }

        if (items.length === 0) {
            bar.style.setProperty('display', 'none', 'important');
            badgesEl.innerHTML = '';
            return;
        }

        badgesEl.innerHTML = '';
        items.forEach(function(txt) {
            const span = document.createElement('span');
            span.style.background = '#DBEAFE';
            span.style.color = '#1E3A8A';
            span.style.padding = '1px 7px';
            span.style.borderRadius = '999px';
            span.style.fontWeight = '600';
            span.style.fontSize = '10.5px';
            span.textContent = txt;
            badgesEl.appendChild(span);
        });
        bar.style.setProperty('display', 'flex', 'important');
    };

    window.sfbcResetContext = async function() {
        try {
            await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'reset_context', csrf_token: CSRF_TOKEN })
            });
        } catch (e) {}
        sfbcLastSyncedId = 0;
        sfbcRenderedServerIds.clear();
        window.sfbcSetLiveStaffMode(false);
        const bar = document.getElementById('sfbcContextBar');
        const badgesEl = document.getElementById('sfbcContextBadges');
        if (bar) bar.style.setProperty('display', 'none', 'important');
        if (badgesEl) badgesEl.innerHTML = '';
        appendBubble('Conversation context memory cleared. Feel free to share new travel dates, guest counts, or room preferences!', 'model');
    };

    window.sfbcSendMessage = async function() {
        const els = getChatEls();
        if (!els.inputEl) return;
        const text = els.inputEl.value.trim();
        if (!text) return;
        els.inputEl.value = '';

        const userWrapper = appendBubble(text, 'user');
        const typingId = sfbcLiveStaffActive ? null : appendTypingIndicator();
        sfbcIsSending = true;

        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'message',
                    message: text,
                    language: (els.langSelect && els.langSelect.value) ? els.langSelect.value : null,
                    csrf_token: CSRF_TOKEN
                })
            });
            const data = await res.json();
            if (typingId) removeElement(typingId);

            const uMid = Number(data.user_message_id || 0);
            const mMid = Number(data.message_id || 0);
            if (uMid > 0) {
                sfbcRenderedServerIds.add(uMid);
                if (uMid > sfbcLastSyncedId) sfbcLastSyncedId = uMid;
            }
            if (mMid > 0) {
                sfbcRenderedServerIds.add(mMid);
                if (mMid > sfbcLastSyncedId) sfbcLastSyncedId = mMid;
            }

            if (data.success && data.live_staff_mode) {
                window.sfbcSetLiveStaffMode(true);
                if (userWrapper) {
                    const noteEl = document.createElement('div');
                    noteEl.className = 'sfbc-live-delivered-note';
                    noteEl.textContent = 'Sent to Reception Desk';
                    userWrapper.appendChild(noteEl);
                }
            } else if (data.success && data.reply) {
                if (data.language && (!els.langSelect || !els.langSelect.value)) {
                    window.sfbcOnLangChange(data.language);
                }
                if (data.context_memory) {
                    window.sfbcUpdateContextBar(data.context_memory);
                }
                appendBubble(
                    data.reply,
                    'model',
                    mMid,
                    data.suggested_actions || [],
                    data.suggested_questions || [],
                    Boolean(data.unanswered_detected),
                    data.room_cards || []
                );
                if (data.suggest_handoff) {
                    window.sfbcTogglePanel('handoff');
                }
            } else {
                appendBubble(data.error || 'Sorry, I encountered an issue. Please ask our front desk staff for assistance.', 'model');
            }
        } catch (err) {
            if (typingId) removeElement(typingId);
            appendBubble('Could not connect to the resort concierge service. Please check your network connection.', 'model');
        } finally {
            sfbcIsSending = false;
        }
    };

    function resolveRoomCardImageUrl(rawUrl) {
        const clean = String(rawUrl || 'assets/hero-slide-2.jpg').trim();
        if (/^(https?:)?\/\//i.test(clean)) return clean;
        const path = window.location.pathname || '';
        if (path.indexOf('/frontend/') !== -1) {
            return clean.indexOf('frontend/') === 0 ? clean.slice(9) : clean;
        }
        return clean.indexOf('frontend/') === 0 ? clean : clean;
    }

    function appendBubble(text, sender, messageId, suggestedActions, suggestedQuestions, unansweredDetected, roomCards, meta) {
        const els = getChatEls();
        if (!els.messagesEl) return null;
        const wrapper = document.createElement('div');
        wrapper.style.display = 'flex';
        wrapper.style.flexDirection = 'column';
        wrapper.style.alignItems = sender === 'user' ? 'flex-end' : 'flex-start';
        wrapper.style.width = '100%';

        if (sender === 'staff') {
            const staffLabel = document.createElement('div');
            staffLabel.className = 'sfbc-staff-label';
            staffLabel.innerHTML = '<span class="sfbc-staff-avatar" aria-hidden="true"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg></span>';
            const sName = document.createElement('span');
            sName.textContent = (meta && meta.sender_name) ? String(meta.sender_name) : 'Reception Staff';
            const livePill = document.createElement('span');
            livePill.className = 'sfbc-staff-live-pill';
            livePill.textContent = 'LIVE STAFF';
            staffLabel.appendChild(sName);
            staffLabel.appendChild(livePill);
            wrapper.appendChild(staffLabel);
        } else if (sender !== 'user') {
            const botLabel = document.createElement('div');
            botLabel.className = 'sfbc-bot-label';
            const botImg = document.createElement('img');
            botImg.src = SFBC_LOGO_URL || 'assets/logo.jpg';
            botImg.alt = 'Santa Fe Concierge';
            botImg.onerror = function() {
                if (!this.dataset.fb) {
                    this.dataset.fb = '1';
                    this.src = 'frontend/assets/logo.jpg';
                }
            };
            const botName = document.createElement('span');
            botName.textContent = 'Santa Fe Concierge';
            botLabel.appendChild(botImg);
            botLabel.appendChild(botName);
            wrapper.appendChild(botLabel);
        }

        const div = document.createElement('div');
        div.className = sender === 'user'
            ? 'sfbc-msg-bubble-user'
            : (sender === 'staff' ? 'sfbc-msg-bubble-staff' : 'sfbc-msg-bubble-model');

        if (sender === 'user') {
            div.textContent = text;
        } else {
            div.innerHTML = sfbcFormatSafeReply(text);
        }
        wrapper.appendChild(div);

        // Visual Room Cards Carousel (Reservation Assistance — matching Akia / Hotel Segundo style)
        if (sender === 'model' && Array.isArray(roomCards) && roomCards.length > 0) {
            const carousel = document.createElement('div');
            carousel.className = 'sfbc-room-carousel';

            roomCards.forEach(function(rc) {
                const card = document.createElement('div');
                card.className = 'sfbc-visual-room-card';

                const topWrap = document.createElement('div');

                const imgWrap = document.createElement('div');
                imgWrap.className = 'sfbc-visual-room-img-wrap';

                const img = document.createElement('img');
                img.className = 'sfbc-visual-room-img';
                const rawImg = String(rc.image_url || 'assets/hero-slide-2.jpg');
                img.src = resolveRoomCardImageUrl(rawImg);
                img.alt = String(rc.display_name || 'Resort Room');
                img.loading = 'lazy';
                img.onerror = function() {
                    if (!this.dataset.retried) {
                        this.dataset.retried = '1';
                        this.src = (rawImg.indexOf('frontend/') === 0) ? rawImg.slice(9) : ('frontend/' + rawImg);
                    } else {
                        this.onerror = null;
                        this.src = 'assets/hero-slide-2.jpg';
                    }
                };
                imgWrap.appendChild(img);

                if (rc.badge) {
                    const badge = document.createElement('span');
                    badge.className = 'sfbc-visual-room-badge';
                    badge.textContent = String(rc.badge);
                    imgWrap.appendChild(badge);
                }
                topWrap.appendChild(imgWrap);

                const titleEl = document.createElement('div');
                titleEl.className = 'sfbc-visual-room-title';
                titleEl.textContent = String(rc.display_name || 'Resort Room');
                topWrap.appendChild(titleEl);

                const priceEl = document.createElement('div');
                priceEl.className = 'sfbc-visual-room-price';
                priceEl.textContent = String(rc.price_label || ('₱' + Number(rc.price_per_night || 0).toLocaleString() + '/night'));
                topWrap.appendChild(priceEl);

                const metaEl = document.createElement('div');
                metaEl.className = 'sfbc-visual-room-meta';
                let metaText = String(rc.capacity_label || '');
                if (rc.stay_summary) {
                    metaText += (metaText ? ' · ' : '') + String(rc.stay_summary);
                }
                metaEl.textContent = metaText;
                topWrap.appendChild(metaEl);

                card.appendChild(topWrap);

                const safeHref = sfbcSanitizeRelativeUrl(rc.booking_url || 'book');
                const bookBtn = document.createElement('a');
                bookBtn.className = 'sfbc-visual-book-btn';
                bookBtn.href = safeHref || 'book';
                bookBtn.textContent = 'BOOK NOW';
                bookBtn.addEventListener('click', function() {
                    window.sfbcTrackBookingClick();
                });
                card.appendChild(bookBtn);

                carousel.appendChild(card);
            });

            wrapper.appendChild(carousel);
        }

        if (sender === 'model' && unansweredDetected) {
            const uaBadge = document.createElement('div');
            uaBadge.style.marginTop = '5px';
            uaBadge.style.padding = '4px 9px';
            uaBadge.style.background = '#FEF3C7';
            uaBadge.style.border = '1px solid #FCD34D';
            uaBadge.style.borderRadius = '6px';
            uaBadge.style.fontSize = '10.5px';
            uaBadge.style.color = '#92400E';
            uaBadge.innerHTML = '<strong>Unanswered Question Detected:</strong> Logged for admin FAQ review. <button type="button" onclick="sfbcTogglePanel(\'handoff\')" style="background:none;border:none;color:#84563C;font-weight:700;text-decoration:underline;cursor:pointer;font-size:10.5px;padding:0;">Forward to Reception Staff &rarr;</button>';
            wrapper.appendChild(uaBadge);
        }

        // Suggested action buttons (e.g. Pre-filled Book Now link)
        if (sender === 'model' && Array.isArray(suggestedActions) && suggestedActions.length > 0) {
            const actionsRow = document.createElement('div');
            actionsRow.style.display = 'flex';
            actionsRow.style.flexWrap = 'wrap';
            actionsRow.style.gap = '6px';
            actionsRow.style.marginTop = '6px';
            suggestedActions.forEach(function(act) {
                const safeHref = sfbcSanitizeRelativeUrl(act.url || '');
                if (!safeHref) return;
                const link = document.createElement('a');
                link.href = safeHref;
                link.style.display = 'inline-flex';
                link.style.alignItems = 'center';
                link.style.gap = '4px';
                link.style.padding = '4px 10px';
                link.style.borderRadius = '6px';
                link.style.background = '#84563C';
                link.style.color = '#ffffff';
                link.style.fontSize = '11px';
                link.style.fontWeight = '600';
                link.style.textDecoration = 'none';
                link.textContent = (act.label || 'Open') + ' →';
                if (safeHref.indexOf('book') === 0) {
                    link.addEventListener('click', function() {
                        window.sfbcTrackBookingClick();
                    });
                }
                actionsRow.appendChild(link);
            });
            wrapper.appendChild(actionsRow);
        }

        // Contextual Suggested Questions pills
        if (sender === 'model' && Array.isArray(suggestedQuestions) && suggestedQuestions.length > 0) {
            const qRow = document.createElement('div');
            qRow.style.display = 'flex';
            qRow.style.flexWrap = 'wrap';
            qRow.style.gap = '5px';
            qRow.style.marginTop = '8px';
            qRow.style.maxWidth = '100%';

            const hint = document.createElement('span');
            hint.style.fontSize = '10px';
            hint.style.color = '#7C533C';
            hint.style.fontWeight = '600';
            hint.style.width = '100%';
            hint.textContent = 'Suggested follow-ups:';
            qRow.appendChild(hint);

            suggestedQuestions.forEach(function(q) {
                const qBtn = document.createElement('button');
                qBtn.type = 'button';
                qBtn.className = 'sfbc-suggested-q-pill';
                qBtn.textContent = q;
                qBtn.addEventListener('click', function() {
                    window.sfbcQuickAsk(q);
                });
                qRow.appendChild(qBtn);
            });
            wrapper.appendChild(qRow);
        }

        // Feedback buttons (Helpful / Not helpful)
        if (sender === 'model' && messageId > 0) {
            const fbRow = document.createElement('div');
            fbRow.style.display = 'flex';
            fbRow.style.alignItems = 'center';
            fbRow.style.gap = '8px';
            fbRow.style.marginTop = '4px';
            fbRow.style.paddingLeft = '4px';
            fbRow.style.color = '#94a3b8';
            fbRow.style.fontSize = '10.5px';
            fbRow.innerHTML = '<span>Helpful?</span>'
                + '<button type="button" onclick="sfbcSendFeedback(' + Number(messageId) + ', 1, this)" style="background:none; border:none; cursor:pointer; color:#64748b; padding:2px; display:inline-flex; align-items:center;" title="Helpful" aria-label="Helpful"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg></button>'
                + '<button type="button" onclick="sfbcSendFeedback(' + Number(messageId) + ', -1, this)" style="background:none; border:none; cursor:pointer; color:#64748b; padding:2px; display:inline-flex; align-items:center;" title="Not helpful" aria-label="Not helpful"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg></button>';
            wrapper.appendChild(fbRow);
        }

        els.messagesEl.appendChild(wrapper);
        els.messagesEl.scrollTop = els.messagesEl.scrollHeight;
        return wrapper;
    }

    function appendTypingIndicator() {
        const els = getChatEls();
        if (!els.messagesEl) return null;
        const id = 'typing_' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.style.alignSelf = 'flex-start';
        div.style.background = '#EFE7DC';
        div.style.color = '#6B4F42';
        div.style.border = '1px solid rgba(132, 86, 60, 0.16)';
        div.style.borderRadius = '999px';
        div.style.padding = '7px 14px';
        div.style.fontSize = '11.5px';
        div.style.fontWeight = '600';
        div.textContent = 'Santa Fe Concierge is typing...';
        els.messagesEl.appendChild(div);
        els.messagesEl.scrollTop = els.messagesEl.scrollHeight;
        return id;
    }

    function removeElement(id) {
        if (!id) return;
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // Attach immediate click handler
    const sfbcBtn = document.getElementById('sfbcChatToggle');
    if (sfbcBtn) {
        sfbcBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            window.sfbcToggleChat();
        });
    }

    // Hydrate prior session messages & active live staff support across page navigation
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && sfbcLiveStaffActive) {
            syncGuestMessages(true);
        }
    });
    setTimeout(function() {
        syncGuestMessages(false);
    }, 350);
})();
</script>
