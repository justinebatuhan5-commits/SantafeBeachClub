<?php
/**
 * _inquiry_reply_assets.php — Shared Staff-to-Guest Two-Way Messaging UI Partial
 *
 * Used by:
 *  - frontend/admin_live_support.php ("Need a human?" Split-Pane Live Chat Desk)
 *  - frontend/admin_inquiries.php (Contact Us Web Inquiries)
 *  - frontend/admin_chatbot.php (AI Concierge Human Support tab)
 */

if (!function_exists('sf_reply_ui_has_real_email')) {
    function sf_reply_ui_has_real_email(?string $email): bool
    {
        if (function_exists('sf_mailer_is_deliverable_email')) {
            return sf_mailer_is_deliverable_email($email);
        }
        $clean = strtolower(trim((string)$email));
        if ($clean === '' || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $placeholders = [
            'pending-contact@santafebeachclub.com',
            '.example',
            '@example.com',
            '@example.org',
            '@example.net',
            'not-provided',
            'guest-widget',
            'no-reply@',
            'noreply@',
        ];
        foreach ($placeholders as $token) {
            if (strpos($clean, $token) !== false) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('sf_reply_ui_format_time')) {
    function sf_reply_ui_format_time(?string $ts, bool $short = false): string
    {
        $raw = trim((string)$ts);
        if ($raw === '') {
            return '';
        }
        $parsed = strtotime($raw);
        if ($parsed === false) {
            return $raw;
        }
        if ($short) {
            return (date('Y-m-d', $parsed) === date('Y-m-d'))
                ? date('g:i A', $parsed)
                : date('M j, g:i A', $parsed);
        }
        return date('M j, Y g:i A', $parsed);
    }
}

if (!function_exists('sf_reply_ui_format_sender')) {
    function sf_reply_ui_format_sender(?string $senderName, string $senderType): array
    {
        $raw = trim((string)$senderName);
        if ($senderType === 'guest') {
            $clean = preg_replace('/\s*\(Live Follow-Up\)\s*$/i', '', $raw);
            $clean = trim((string)$clean);
            return [
                'label' => $clean !== '' ? $clean : 'Guest',
                'tooltip' => $raw !== '' ? $raw : 'Guest',
            ];
        }

        if ($raw === '') {
            return ['label' => 'Reception Staff', 'tooltip' => 'Reception Staff'];
        }

        $role = '';
        $identity = $raw;
        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $raw, $m)) {
            $identity = trim($m[1]);
            $role = trim($m[2]);
        }

        if (strpos($identity, '@') !== false) {
            $local = strstr($identity, '@', true);
            $local = trim(str_replace(['.', '_', '-'], ' ', (string)$local));
            $prettyName = $local !== '' ? ucwords(strtolower($local)) : $identity;
        } else {
            $prettyName = $identity;
        }

        if ($role !== '' && strcasecmp($prettyName, $role) === 0) {
            $label = $prettyName;
        } elseif ($role !== '') {
            $label = $prettyName . ' (' . $role . ')';
        } else {
            $label = $prettyName;
        }

        return [
            'label' => $label,
            'tooltip' => $raw,
        ];
    }
}

if (!function_exists('sf_reply_ui_initial')) {
    function sf_reply_ui_initial(?string $name): string
    {
        $clean = trim((string)$name);
        if ($clean === '') {
            return 'G';
        }
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($clean, 0, 1, 'UTF-8'), 'UTF-8');
        }
        return strtoupper(substr($clean, 0, 1));
    }
}

if (!function_exists('sf_reply_ui_parse_handoff_context')) {
    function sf_reply_ui_parse_handoff_context(string $rawMessage): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawMessage));
        $note = '';
        $transcriptRaw = $normalized;

        if (strpos($normalized, '--- Chat Transcript ---') !== false) {
            $parts = explode('--- Chat Transcript ---', $normalized, 2);
            $head = trim($parts[0]);
            $transcriptRaw = trim($parts[1] ?? '');
            if (stripos($head, 'Guest Note:') === 0) {
                $note = trim(substr($head, strlen('Guest Note:')));
            } elseif ($head !== '') {
                $note = $head;
            }
        }

        $note = trim((string)preg_replace('/^\[Staff Handoff Request\]\s*/i', '', $note));

        $turns = [];
        foreach (explode("\n", $transcriptRaw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $lineClean = trim((string)preg_replace('/\[Staff Handoff Request\]\s*/i', '', $line));
            if ($lineClean === '') {
                continue;
            }
            if (preg_match('/^(Guest|AI|Assistant|Staff|User)\s*:\s*(.+)$/i', $lineClean, $m)) {
                $speakerRaw = ucfirst(strtolower($m[1]));
                $text = trim($m[2]);
                if ($speakerRaw === 'User') {
                    $speakerRaw = 'Guest';
                } elseif ($speakerRaw === 'Assistant') {
                    $speakerRaw = 'AI';
                }
                if ($text !== '') {
                    $turns[] = ['speaker' => $speakerRaw, 'text' => $text];
                }
            } else {
                $turns[] = ['speaker' => 'Context', 'text' => $lineClean];
            }
        }

        $preview = $note;
        if ($preview === '' && !empty($turns)) {
            $preview = (string)$turns[count($turns) - 1]['text'];
        }
        if ($preview === '') {
            $preview = preg_replace('/\s+/', ' ', $normalized);
        }

        return [
            'note' => $note,
            'turns' => $turns,
            'preview' => trim((string)$preview),
        ];
    }
}

if (!function_exists('sf_reply_ui_truncate')) {
    function sf_reply_ui_truncate(string $text, int $limit = 64): string
    {
        $single = trim((string)preg_replace('/\s+/', ' ', $text));
        if ($single === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($single, 'UTF-8') > $limit
                ? mb_substr($single, 0, $limit - 1, 'UTF-8') . '...'
                : $single;
        }
        return strlen($single) > $limit ? substr($single, 0, $limit - 3) . '...' : $single;
    }
}

if (!function_exists('sf_reply_ui_render_styles')) {
    function sf_reply_ui_render_styles(): void
    {
        ?>
        <style>
            /* Shared Container Clearance (prevents Staff AI Assistant FAB overlap) */
            .sf-comm-page {
                max-width: 1240px;
                margin: 0 auto;
                padding-bottom: 92px;
            }
            .inquiries-container {
                max-width: 980px;
                margin: 0 auto;
                padding-bottom: 92px;
            }

            /* Shared Filter Bar */
            .sf-filter-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 16px;
            }
            .sf-filter-pills {
                display: inline-flex;
                flex-wrap: wrap;
                gap: 6px;
                background: #F3F0EC;
                padding: 4px;
                border-radius: 10px;
                border: 1px solid #E7E0D8;
            }
            .sf-filter-btn {
                padding: 6px 13px;
                border-radius: 7px;
                border: none;
                background: transparent;
                color: #57534E;
                font-size: 12.5px;
                font-weight: 600;
                cursor: pointer;
                transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
            }
            .sf-filter-btn:hover {
                color: #1C1917;
            }
            .sf-filter-btn.is-active {
                background: #FFFFFF;
                color: #6D432C;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            }

            /* Shared Status & Language Badges */
            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 3px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.01em;
                white-space: nowrap;
            }
            .status-unread { background: #DBEAFE; color: #1D4ED8; }
            .status-read { background: #F1F5F9; color: #475569; }
            .status-resolved { background: #ECFDF5; color: #047857; }
            .sf-lang-pill {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
                background: #F5F2EE;
                color: #57534E;
                border: 1px solid #E7E0D8;
            }

            /* ============================================================
               SPLIT-PANE LIVE SUPPORT DESK (admin_live_support.php)
               ============================================================ */
            .sf-desk-shell {
                display: grid;
                grid-template-columns: 312px minmax(0, 1fr);
                background: var(--card-bg, #FFFFFF);
                border: 1px solid #E5E0D8;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);
                min-height: 430px;
                height: clamp(430px, calc(100vh - 305px), 660px);
            }
            @media (max-height: 760px) and (min-width: 961px) {
                .sf-conv-pane .sf-reply-controls {
                    padding-right: 175px;
                }
            }

            .sf-desk-sidebar {
                display: flex;
                flex-direction: column;
                border-right: 1px solid #ECE7E0;
                background: #FAF8F5;
                min-height: 0;
            }
            .sf-desk-sidebar-top {
                padding: 14px 14px 10px;
                border-bottom: 1px solid #ECE7E0;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .sf-desk-search-wrap {
                position: relative;
                display: flex;
                align-items: center;
            }
            .sf-desk-search-icon {
                position: absolute;
                left: 10px;
                color: #94A3B8;
                pointer-events: none;
            }
            .sf-desk-search {
                width: 100%;
                box-sizing: border-box;
                padding: 7px 10px 7px 31px;
                border: 1px solid #E2DDD5;
                border-radius: 9px;
                background: #FFFFFF;
                color: #0F172A;
                font-size: 12.5px;
                font-family: inherit;
                outline: none;
            }
            .sf-desk-search:focus {
                border-color: #7C4D34;
                box-shadow: 0 0 0 3px rgba(124, 77, 52, 0.12);
            }
            .sf-desk-queue-pills {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 4px;
                background: #EFECE6;
                padding: 3px;
                border-radius: 9px;
            }
            .sf-desk-queue-pills .sf-filter-btn {
                padding: 5px 6px;
                font-size: 11.5px;
                text-align: center;
                white-space: nowrap;
            }
            .sf-desk-list {
                flex: 1;
                overflow-y: auto;
                padding: 8px;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .sf-chat-row {
                width: 100%;
                text-align: left;
                border: 1px solid transparent;
                background: transparent;
                border-radius: 11px;
                padding: 10px 11px;
                display: flex;
                align-items: flex-start;
                gap: 10px;
                cursor: pointer;
                font-family: inherit;
                transition: background-color 0.14s ease, border-color 0.14s ease;
            }
            .sf-chat-row:hover {
                background: #F3EFEA;
            }
            .sf-chat-row.is-selected {
                background: #FFFFFF;
                border-color: #E2D9CE;
                box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
            }
            .sf-chat-avatar {
                position: relative;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: #EDE3D8;
                color: #6D432C;
                font-weight: 700;
                font-size: 13.5px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .sf-chat-avatar-dot {
                position: absolute;
                right: 0;
                bottom: 0;
                width: 9px;
                height: 9px;
                border-radius: 50%;
                border: 2px solid #FAF8F5;
                background: #94A3B8;
            }
            .sf-chat-avatar-dot.is-live { background: #10B981; }
            .sf-chat-avatar-dot.is-unread { background: #3B82F6; }
            .sf-chat-row-body {
                flex: 1;
                min-width: 0;
            }
            .sf-chat-row-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                margin-bottom: 2px;
            }
            .sf-chat-row-name {
                font-size: 13px;
                font-weight: 700;
                color: #1E293B;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .sf-chat-row-time {
                font-size: 11px;
                color: #94A3B8;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .sf-chat-row-snippet {
                font-size: 12px;
                color: #64748B;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-bottom: 4px;
            }
            .sf-chat-row.is-unread .sf-chat-row-name,
            .sf-chat-row.is-unread .sf-chat-row-snippet {
                color: #0F172A;
                font-weight: 600;
            }
            .sf-chat-row-meta {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 10.5px;
                color: #64748B;
            }
            .sf-row-state-tag {
                display: inline-block;
                padding: 1px 7px;
                border-radius: 999px;
                font-weight: 600;
                font-size: 10.5px;
            }
            .sf-row-state-tag.unread { background: #DBEAFE; color: #1D4ED8; }
            .sf-row-state-tag.read { background: #FEF3C7; color: #92400E; }
            .sf-row-state-tag.resolved { background: #E2E8F0; color: #475569; }
            .sf-desk-list-empty {
                padding: 28px 14px;
                text-align: center;
                font-size: 12.5px;
                color: #64748B;
            }

            /* Right Pane: Active Conversation */
            .sf-desk-main {
                display: flex;
                flex-direction: column;
                min-width: 0;
                min-height: 0;
                background: var(--card-bg, #FFFFFF);
            }
            .sf-conv-pane {
                display: none;
                flex-direction: column;
                height: 100%;
                min-height: 0;
            }
            .sf-conv-pane.is-selected {
                display: flex;
            }
            .sf-conv-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 13px 18px;
                border-bottom: 1px solid #ECE7E0;
                background: #FFFFFF;
                flex-shrink: 0;
                flex-wrap: wrap;
            }
            .sf-conv-guest {
                display: flex;
                align-items: center;
                gap: 11px;
                min-width: 0;
            }
            .sf-mobile-back {
                display: none;
                align-items: center;
                gap: 4px;
                padding: 6px 10px;
                border-radius: 8px;
                border: 1px solid #E2DDD5;
                background: #FAF8F5;
                color: #334155;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
            }
            .sf-conv-title-wrap {
                min-width: 0;
            }
            .sf-conv-name-row {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .sf-conv-name {
                font-size: 15.5px;
                font-weight: 700;
                color: #0F172A;
                margin: 0;
            }
            .sf-conv-sub {
                font-size: 12px;
                color: #64748B;
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 2px;
            }
            .sf-conv-sub a {
                color: #7C4D34;
                text-decoration: none;
                font-weight: 500;
            }
            .sf-conv-sub a:hover {
                text-decoration: underline;
            }
            .sf-conv-actions {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .sf-conv-actions form {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin: 0;
            }
            .sf-ghost-btn {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 6px 11px;
                border-radius: 8px;
                border: 1px solid #E2E8F0;
                background: #FFFFFF;
                color: #334155;
                font-size: 12px;
                font-weight: 600;
                font-family: inherit;
                cursor: pointer;
                text-decoration: none;
                transition: background-color 0.14s ease, border-color 0.14s ease, color 0.14s ease;
            }
            .sf-ghost-btn:hover {
                background: #F8FAFC;
                border-color: #CBD5E1;
            }
            .sf-ghost-btn.is-resolve {
                color: #047857;
                border-color: #A7F3D0;
                background: #ECFDF5;
            }
            .sf-ghost-btn.is-resolve:hover {
                background: #D1FAE5;
            }
            .sf-ghost-btn.is-read {
                color: #1D4ED8;
                border-color: #BFDBFE;
                background: #EFF6FF;
            }
            .sf-ghost-btn.is-danger {
                color: #DC2626;
                border-color: #FECACA;
                background: #FFF5F5;
            }
            .sf-ghost-btn.is-danger:hover {
                background: #FEE2E2;
            }

            /* Collapsible Initial Handoff Context */
            .sf-context-accordion {
                border-bottom: 1px solid #ECE7E0;
                background: #FAF8F5;
                flex-shrink: 0;
            }
            .sf-context-summary {
                list-style: none;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                padding: 9px 18px;
                cursor: pointer;
                font-size: 12px;
                color: #57534E;
                user-select: none;
            }
            .sf-context-summary::-webkit-details-marker {
                display: none;
            }
            .sf-context-summary-left {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 0;
            }
            .sf-context-kicker {
                font-weight: 700;
                color: #6D432C;
                white-space: nowrap;
            }
            .sf-context-preview {
                color: #64748B;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .sf-context-chevron {
                flex-shrink: 0;
                color: #78716C;
                transition: transform 0.16s ease;
            }
            .sf-context-accordion[open] .sf-context-chevron {
                transform: rotate(180deg);
            }
            .sf-context-body {
                padding: 2px 18px 12px;
                display: flex;
                flex-direction: column;
                gap: 6px;
                max-height: 155px;
                overflow-y: auto;
            }
            .sf-context-note {
                font-size: 12.5px;
                color: #1E293B;
                background: #FFFFFF;
                border: 1px solid #E5E0D8;
                border-left: 3px solid #7C4D34;
                border-radius: 8px;
                padding: 7px 10px;
                line-height: 1.45;
            }
            .sf-context-turn {
                font-size: 12px;
                color: #475569;
                line-height: 1.45;
                padding-left: 4px;
            }
            .sf-context-speaker {
                font-weight: 700;
                color: #334155;
                margin-right: 4px;
            }

            /* ============================================================
               TWO-SIDED MESSAGING BUBBLES (Shared Across Desk, Contacts, Chatbot)
               ============================================================ */
            .sf-reply-box {
                display: flex;
                flex-direction: column;
                min-height: 0;
            }
            .sf-conv-pane .sf-reply-box {
                flex: 1;
            }
            .sf-reply-box.is-compact {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #EEF2F6;
            }
            .sf-reply-status-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 10px;
            }
            .sf-live-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 3px 9px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
            }
            .sf-live-pill.is-active {
                background: #ECFDF5;
                color: #047857;
            }
            .sf-live-pill.is-idle {
                background: #F1F5F9;
                color: #64748B;
            }
            .sf-live-pill.is-email-only {
                background: #EFF6FF;
                color: #1D4ED8;
            }
            .sf-live-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #10B981;
                display: inline-block;
            }

            .sf-thread-wrap {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 16px 18px;
                background: #F8F6F3;
                border-radius: 12px;
                overflow-y: auto;
                max-height: 300px;
            }
            .sf-conv-pane .sf-thread-wrap {
                flex: 1;
                max-height: none;
                border-radius: 0;
                padding: 18px 20px;
            }
            .sf-thread-empty {
                margin: auto;
                text-align: center;
                font-size: 12.5px;
                color: #64748B;
                padding: 18px 16px;
                max-width: 340px;
            }
            .sf-thread-item {
                display: flex;
                flex-direction: column;
                max-width: 78%;
            }
            .sf-thread-item.is-guest {
                align-self: flex-start;
                align-items: flex-start;
            }
            .sf-thread-item.is-staff {
                align-self: flex-end;
                align-items: flex-end;
            }
            .sf-bubble {
                padding: 10px 14px;
                font-size: 13px;
                line-height: 1.5;
                white-space: pre-wrap;
                word-break: break-word;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            }
            .sf-thread-item.is-guest .sf-bubble {
                background: #FFFFFF;
                color: #0F172A;
                border: 1px solid #E5E0D8;
                border-radius: 14px 14px 14px 4px;
            }
            .sf-thread-item.is-staff .sf-bubble {
                background: #7C4D34;
                color: #FFFFFF;
                border: 1px solid #6D432C;
                border-radius: 14px 14px 4px 14px;
            }
            .sf-bubble-meta {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 4px;
                padding: 0 3px;
                font-size: 11px;
                color: #64748B;
            }
            .sf-thread-item.is-staff .sf-bubble-meta {
                justify-content: flex-end;
            }
            .sf-bubble-sender {
                font-weight: 600;
                color: #475569;
            }
            .sf-delivery-note {
                display: inline-flex;
                align-items: center;
                gap: 3px;
                font-size: 10.5px;
                color: #0F766E;
                font-weight: 500;
            }
            .sf-delivery-note.is-warn {
                color: #B45309;
            }

            /* Integrated Slim Reply Composer */
            .sf-reply-composer {
                background: #FFFFFF;
                border: 1px solid #E2DDD5;
                border-radius: 12px;
                padding: 10px 12px;
                margin-top: 10px;
            }
            .sf-conv-pane .sf-reply-composer {
                margin-top: 0;
                border: none;
                border-top: 1px solid #ECE7E0;
                border-radius: 0;
                padding: 12px 18px 14px;
                flex-shrink: 0;
            }
            .sf-reply-textarea {
                width: 100%;
                box-sizing: border-box;
                padding: 9px 12px;
                border: 1px solid #E2E8F0;
                border-radius: 9px;
                font-family: inherit;
                font-size: 13px;
                line-height: 1.45;
                resize: none;
                min-height: 44px;
                max-height: 130px;
                background: #FAF9F7;
                color: #0F172A;
                transition: border-color 0.15s ease, background-color 0.15s ease;
            }
            .sf-reply-textarea:focus {
                outline: none;
                background: #FFFFFF;
                border-color: #7C4D34;
                box-shadow: 0 0 0 3px rgba(124, 77, 52, 0.12);
            }
            .sf-reply-controls {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 8px;
            }
            .sf-reply-checks {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
                font-size: 12px;
                color: #475569;
            }
            .sf-reply-check {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                font-weight: 600;
            }
            .sf-reply-check input[type="checkbox"] {
                accent-color: #7C4D34;
                width: 14px;
                height: 14px;
            }
            .sf-reply-muted-note {
                font-size: 11.5px;
                color: #64748B;
            }
            .sf-reply-buttons {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .sf-btn-send,
            .sf-btn-send-resolve {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 7px 14px;
                border-radius: 8px;
                font-size: 12.5px;
                font-weight: 600;
                font-family: inherit;
                cursor: pointer;
                border: none;
                transition: opacity 0.15s ease, background-color 0.15s ease, transform 0.1s ease;
            }
            .sf-btn-send {
                background: #7C4D34;
                color: #FFFFFF;
            }
            .sf-btn-send:hover {
                background: #683F29;
            }
            .sf-btn-send-resolve {
                background: #ECFDF5;
                color: #047857;
                border: 1px solid #A7F3D0;
            }
            .sf-btn-send-resolve:hover {
                background: #D1FAE5;
            }
            .sf-btn-send:disabled,
            .sf-btn-send-resolve:disabled {
                opacity: 0.55;
                cursor: not-allowed;
            }
            .sf-reply-feedback {
                margin-top: 7px;
                font-size: 12px;
                font-weight: 600;
                padding: 6px 10px;
                border-radius: 7px;
            }
            .sf-reply-feedback.is-ok { background: #ECFDF5; color: #047857; }
            .sf-reply-feedback.is-warn { background: #FFFBEB; color: #92400E; }
            .sf-reply-feedback.is-err { background: #FEF2F2; color: #B91C1C; }

            /* ============================================================
               CLEAN CONTACT INQUIRY CARDS (admin_inquiries.php)
               ============================================================ */
            .inquiry-card {
                background: var(--card-bg, #FFFFFF);
                border: 1px solid #E5E0D8;
                border-radius: 14px;
                padding: 18px 20px;
                margin-bottom: 14px;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
                transition: border-color 0.15s ease, opacity 0.15s ease;
            }
            .inquiry-card.unread {
                border-left: 4px solid #7C4D34;
            }
            .inquiry-card.resolved {
                opacity: 0.88;
            }
            .inquiry-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 10px;
            }
            .inquiry-author {
                display: flex;
                align-items: center;
                gap: 11px;
                min-width: 0;
            }
            .inquiry-meta {
                font-size: 12.5px;
                color: #64748B;
            }
            .inquiry-meta strong {
                font-size: 14.5px;
                color: #0F172A;
                font-weight: 700;
            }
            .inquiry-meta a {
                color: #7C4D34;
                text-decoration: none;
                font-weight: 500;
            }
            .inquiry-meta a:hover {
                text-decoration: underline;
            }
            .inquiry-head-right {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .inquiry-head-right form {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin: 0;
            }
            .inquiry-subject {
                font-size: 14.5px;
                font-weight: 700;
                color: #1E293B;
                margin-bottom: 6px;
            }
            .inquiry-message {
                font-size: 13.5px;
                color: #334155;
                line-height: 1.6;
                padding: 10px 14px;
                background: #FAF8F5;
                border-left: 3px solid #D6CCC2;
                border-radius: 0 9px 9px 0;
                white-space: pre-wrap;
                margin-bottom: 4px;
            }

            /* Dark Mode */
            [data-theme="dark"] .sf-filter-pills { background: #161922; border-color: #2A2F3D; }
            [data-theme="dark"] .sf-filter-btn { color: #94A3B8; }
            [data-theme="dark"] .sf-filter-btn:hover { color: #F1F5F9; }
            [data-theme="dark"] .sf-filter-btn.is-active { background: #262B38; color: #FDE6D8; box-shadow: none; }
            [data-theme="dark"] .status-unread { background: rgba(59, 130, 246, 0.22); color: #93C5FD; }
            [data-theme="dark"] .status-read { background: #2A303E; color: #CBD5E1; }
            [data-theme="dark"] .status-resolved { background: rgba(16, 185, 129, 0.18); color: #6EE7B7; }
            [data-theme="dark"] .sf-lang-pill { background: #262B38; border-color: #334155; color: #CBD5E1; }

            [data-theme="dark"] .sf-desk-shell { background: #1A1D27; border-color: #2D3342; }
            [data-theme="dark"] .sf-desk-sidebar { background: #141720; border-right-color: #2A2F3D; }
            [data-theme="dark"] .sf-desk-sidebar-top { border-bottom-color: #2A2F3D; }
            [data-theme="dark"] .sf-desk-search { background: #1E222E; border-color: #334155; color: #F1F5F9; }
            [data-theme="dark"] .sf-desk-queue-pills { background: #1E222E; }
            [data-theme="dark"] .sf-chat-row:hover { background: #1D212D; }
            [data-theme="dark"] .sf-chat-row.is-selected { background: #232836; border-color: #3B4254; }
            [data-theme="dark"] .sf-chat-avatar { background: #2E231D; color: #F3CBB4; }
            [data-theme="dark"] .sf-chat-avatar-dot { border-color: #141720; }
            [data-theme="dark"] .sf-chat-row-name { color: #F1F5F9; }
            [data-theme="dark"] .sf-chat-row-snippet { color: #94A3B8; }
            [data-theme="dark"] .sf-chat-row.is-unread .sf-chat-row-name,
            [data-theme="dark"] .sf-chat-row.is-unread .sf-chat-row-snippet { color: #FFFFFF; }
            [data-theme="dark"] .sf-row-state-tag.unread { background: rgba(59, 130, 246, 0.25); color: #93C5FD; }
            [data-theme="dark"] .sf-row-state-tag.read { background: rgba(245, 158, 11, 0.2); color: #FCD34D; }
            [data-theme="dark"] .sf-row-state-tag.resolved { background: #2A303E; color: #CBD5E1; }

            [data-theme="dark"] .sf-desk-main,
            [data-theme="dark"] .sf-conv-head { background: #1A1D27; border-bottom-color: #2A2F3D; }
            [data-theme="dark"] .sf-conv-name { color: #F8FAFC; }
            [data-theme="dark"] .sf-conv-sub { color: #94A3B8; }
            [data-theme="dark"] .sf-conv-sub a { color: #E2A988; }
            [data-theme="dark"] .sf-mobile-back,
            [data-theme="dark"] .sf-ghost-btn { background: #232836; border-color: #334155; color: #E2E8F0; }
            [data-theme="dark"] .sf-ghost-btn:hover { background: #2C3242; }
            [data-theme="dark"] .sf-ghost-btn.is-resolve { background: rgba(16, 185, 129, 0.16); border-color: #065F46; color: #6EE7B7; }
            [data-theme="dark"] .sf-ghost-btn.is-read { background: rgba(59, 130, 246, 0.16); border-color: #1E40AF; color: #93C5FD; }
            [data-theme="dark"] .sf-ghost-btn.is-danger { background: rgba(239, 68, 68, 0.14); border-color: #7F1D1D; color: #FCA5A5; }

            [data-theme="dark"] .sf-context-accordion { background: #161922; border-bottom-color: #2A2F3D; }
            [data-theme="dark"] .sf-context-summary { color: #CBD5E1; }
            [data-theme="dark"] .sf-context-kicker { color: #E2A988; }
            [data-theme="dark"] .sf-context-preview { color: #94A3B8; }
            [data-theme="dark"] .sf-context-note { background: #202431; border-color: #334155; border-left-color: #C28A6A; color: #F1F5F9; }
            [data-theme="dark"] .sf-context-turn { color: #CBD5E1; }
            [data-theme="dark"] .sf-context-speaker { color: #E2E8F0; }

            [data-theme="dark"] .sf-thread-wrap { background: #13161F; }
            [data-theme="dark"] .sf-thread-item.is-guest .sf-bubble { background: #222736; border-color: #334155; color: #F1F5F9; }
            [data-theme="dark"] .sf-thread-item.is-staff .sf-bubble { background: #84563C; border-color: #6D432C; color: #FFFFFF; }
            [data-theme="dark"] .sf-bubble-meta { color: #94A3B8; }
            [data-theme="dark"] .sf-bubble-sender { color: #CBD5E1; }
            [data-theme="dark"] .sf-delivery-note { color: #5EEAD4; }
            [data-theme="dark"] .sf-delivery-note.is-warn { color: #FCD34D; }

            [data-theme="dark"] .sf-reply-composer { background: #1A1D27; border-color: #2D3342; }
            [data-theme="dark"] .sf-conv-pane .sf-reply-composer { border-top-color: #2A2F3D; }
            [data-theme="dark"] .sf-reply-textarea { background: #13161F; border-color: #334155; color: #F1F5F9; }
            [data-theme="dark"] .sf-reply-textarea:focus { background: #161922; border-color: #A17052; }
            [data-theme="dark"] .sf-reply-checks { color: #CBD5E1; }
            [data-theme="dark"] .sf-reply-muted-note { color: #94A3B8; }
            [data-theme="dark"] .sf-btn-send-resolve { background: rgba(16, 185, 129, 0.16); border-color: #065F46; color: #6EE7B7; }
            [data-theme="dark"] .sf-reply-feedback.is-ok { background: rgba(16, 185, 129, 0.18); color: #6EE7B7; }
            [data-theme="dark"] .sf-reply-feedback.is-warn { background: rgba(245, 158, 11, 0.2); color: #FDE68A; }
            [data-theme="dark"] .sf-reply-feedback.is-err { background: rgba(239, 68, 68, 0.2); color: #FECACA; }

            [data-theme="dark"] .inquiry-card { background: #1A1D27; border-color: #2D3342; }
            [data-theme="dark"] .inquiry-card.unread { border-left-color: #C28A6A; }
            [data-theme="dark"] .inquiry-meta strong,
            [data-theme="dark"] .inquiry-subject { color: #F1F5F9; }
            [data-theme="dark"] .inquiry-meta a { color: #E2A988; }
            [data-theme="dark"] .inquiry-message { background: #141720; border-left-color: #475569; color: #E2E8F0; }

            /* Mobile Responsive (< 960px) */
            @media (max-width: 960px) {
                .sf-desk-shell {
                    grid-template-columns: 1fr;
                    height: auto;
                    max-height: none;
                    min-height: 480px;
                }
                .sf-desk-shell .sf-desk-main {
                    display: none;
                }
                .sf-desk-shell.is-mobile-detail .sf-desk-sidebar {
                    display: none;
                }
                .sf-desk-shell.is-mobile-detail .sf-desk-main {
                    display: flex;
                    min-height: 520px;
                }
                .sf-mobile-back {
                    display: inline-flex;
                }
                .sf-thread-item {
                    max-width: 88%;
                }
            }
        </style>
        <?php
    }
}

if (!function_exists('sf_reply_ui_render_panel')) {
    function sf_reply_ui_render_panel(int $inquiryId, ?string $guestEmail, string $status, array $thread, array $options = []): void
    {
        $convId = (int)($thread['conversation_id'] ?? 0);
        $hasChat = $convId > 0;
        $liveActive = !empty($thread['live_chat_active']);
        $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
        $hasRealEmail = sf_reply_ui_has_real_email($guestEmail);
        $compactMode = !empty($options['compact']);
        $hideStatusBar = !empty($options['hide_status_bar']);
        ?>
        <div class="sf-reply-box <?php echo $compactMode ? 'is-compact' : ''; ?>"
             data-reply-box
             data-inquiry-id="<?php echo $inquiryId; ?>"
             data-conversation-id="<?php echo $convId; ?>"
             data-live-active="<?php echo $liveActive ? '1' : '0'; ?>"
             data-has-chat="<?php echo $hasChat ? '1' : '0'; ?>"
             data-has-email="<?php echo $hasRealEmail ? '1' : '0'; ?>">

            <?php if (!$hideStatusBar): ?>
                <div class="sf-reply-status-bar">
                    <?php if ($hasChat): ?>
                        <span class="sf-live-pill <?php echo $liveActive ? 'is-active' : 'is-idle'; ?>" data-live-status-pill>
                            <?php if ($liveActive): ?>
                                <span class="sf-live-dot"></span>
                                Active Two-Way Chat &middot; Session #<?php echo $convId; ?>
                            <?php else: ?>
                                Chat Session #<?php echo $convId; ?> &middot; <?php echo ($status === 'Resolved') ? 'Resolved' : 'AI Mode'; ?>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="sf-live-pill is-email-only" data-live-status-pill>
                            Direct Email Reply Channel
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <span class="sf-live-pill" data-live-status-pill hidden></span>
            <?php endif; ?>

            <div class="sf-thread-wrap" data-thread-list>
                <?php if (empty($replies)): ?>
                    <div class="sf-thread-empty" data-thread-empty>
                        <?php echo $hasChat
                            ? 'No replies sent yet. Write a response below to message this guest directly in Live Chat.'
                            : 'No replies sent yet. Use the composer below to send a direct email reply.'; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $rep):
                        $senderType = (($rep['sender_type'] ?? 'staff') === 'staff') ? 'staff' : 'guest';
                        $isStaff = ($senderType === 'staff');
                        $repId = (int)($rep['id'] ?? 0);
                        $fmtSender = sf_reply_ui_format_sender((string)($rep['sender_name'] ?? ''), $senderType);
                        $deliveryParts = [];
                        if ($isStaff && !empty($rep['delivered_live'])) {
                            $deliveryParts[] = 'Live Chat';
                        }
                        if ($isStaff && !empty($rep['delivered_email'])) {
                            $deliveryParts[] = 'Email Sent';
                        }
                        $emailError = trim((string)($rep['email_error'] ?? ''));
                        $timeStr = sf_reply_ui_format_time((string)($rep['created_at'] ?? ''), true);
                    ?>
                        <div class="sf-thread-item <?php echo $isStaff ? 'is-staff' : 'is-guest'; ?>" data-reply-id="<?php echo $repId; ?>">
                            <div class="sf-bubble"><?php echo htmlspecialchars((string)($rep['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="sf-bubble-meta">
                                <span class="sf-bubble-sender" title="<?php echo htmlspecialchars($fmtSender['tooltip'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($fmtSender['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if (!empty($deliveryParts)): ?>
                                    <span>&middot;</span>
                                    <span class="sf-delivery-note">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php echo htmlspecialchars(implode(' & ', $deliveryParts), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($isStaff && $emailError !== ''): ?>
                                    <span class="sf-delivery-note is-warn" title="<?php echo htmlspecialchars($emailError, ENT_QUOTES, 'UTF-8'); ?>">&middot; Email notice</span>
                                <?php endif; ?>
                                <?php if ($timeStr !== ''): ?>
                                    <span>&middot; <?php echo htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sf-reply-composer">
                <textarea class="sf-reply-textarea"
                          data-reply-input
                          rows="2"
                          maxlength="2000"
                          placeholder="<?php echo $hasChat
                              ? 'Reply to guest (Enter to send, Shift+Enter for new line)...'
                              : 'Write your reply to send directly via SMTP email...'; ?>"></textarea>

                <div class="sf-reply-controls">
                    <div class="sf-reply-checks">
                        <?php if ($hasChat): ?>
                            <label class="sf-reply-check">
                                <input type="checkbox" data-send-live checked>
                                <span>Live Chat</span>
                            </label>
                        <?php endif; ?>

                        <?php if ($hasRealEmail): ?>
                            <label class="sf-reply-check">
                                <input type="checkbox" data-send-email checked>
                                <span>Email Copy</span>
                            </label>
                        <?php elseif ($hasChat): ?>
                            <span class="sf-reply-muted-note">No email on file (Live Chat only)</span>
                        <?php else: ?>
                            <span class="sf-reply-muted-note">No deliverable email address on file</span>
                        <?php endif; ?>
                    </div>

                    <div class="sf-reply-buttons">
                        <button type="button" class="sf-btn-send" data-send-reply data-resolve="0">Send Reply</button>
                        <button type="button" class="sf-btn-send-resolve" data-send-reply data-resolve="1">Send &amp; Resolve</button>
                    </div>
                </div>
                <div class="sf-reply-feedback" data-reply-feedback hidden></div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('sf_reply_ui_render_ticket_cards')) {
    function sf_reply_ui_render_ticket_cards(array $tickets, array $threads, array $options = []): void
    {
        $channel = (string)($options['channel'] ?? 'contact');
        $isLiveChatChannel = ($channel === 'live_chat');
        $emptyText = (string)($options['empty_text'] ?? ($isLiveChatChannel
            ? 'No live support chats requested via "Need a human?" yet.'
            : 'No Contact Us page inquiries found.'));

        $openCount = 0;
        $resolvedCount = 0;
        foreach ($tickets as $t) {
            if (($t['status'] ?? '') === 'Resolved') {
                $resolvedCount++;
            } else {
                $openCount++;
            }
        }

        if ($isLiveChatChannel):
            ?>
            <div class="sf-comm-page">
                <?php if (empty($tickets)): ?>
                    <div style="text-align: center; padding: 64px 20px; color: #64748B; background: var(--card-bg, #FFFFFF); border: 1px solid #E5E0D8; border-radius: 16px;">
                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <p style="margin:0; font-size: 14px;"><?php echo htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php else: ?>
                    <?php
                    // Default active chat: first open/unread chat, or first chat overall
                    $defaultSelectedId = (int)$tickets[0]['id'];
                    foreach ($tickets as $cand) {
                        if (($cand['status'] ?? '') !== 'Resolved') {
                            $defaultSelectedId = (int)$cand['id'];
                            break;
                        }
                    }
                    ?>
                    <div class="sf-desk-shell" data-chat-desk>
                        <!-- Left Conversation Queue -->
                        <aside class="sf-desk-sidebar">
                            <div class="sf-desk-sidebar-top">
                                <div class="sf-desk-search-wrap">
                                    <svg class="sf-desk-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    <input type="search" class="sf-desk-search" placeholder="Search guest or message..." data-chat-search>
                                </div>
                                <div class="sf-desk-queue-pills sf-filter-bar" style="margin-bottom: 0;">
                                    <button type="button" class="sf-filter-btn is-active" data-ticket-filter="all">All (<?php echo count($tickets); ?>)</button>
                                    <button type="button" class="sf-filter-btn" data-ticket-filter="open">Active (<?php echo $openCount; ?>)</button>
                                    <button type="button" class="sf-filter-btn" data-ticket-filter="resolved">Done (<?php echo $resolvedCount; ?>)</button>
                                </div>
                            </div>

                            <div class="sf-desk-list" data-chat-queue>
                                <?php foreach ($tickets as $inq):
                                    $inqId = (int)$inq['id'];
                                    $statusStr = (string)($inq['status'] ?? 'Unread');
                                    $lowerStatus = strtolower($statusStr);
                                    $thread = $threads[$inqId] ?? [];
                                    $liveActive = !empty($thread['live_chat_active']);
                                    $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
                                    $ctx = sf_reply_ui_parse_handoff_context((string)($inq['message'] ?? ''));

                                    if (!empty($replies)) {
                                        $lastRep = $replies[count($replies) - 1];
                                        $lastPrefix = (($lastRep['sender_type'] ?? 'staff') === 'staff') ? 'Staff: ' : '';
                                        $snippet = sf_reply_ui_truncate($lastPrefix . (string)($lastRep['message'] ?? ''), 56);
                                        $lastTime = sf_reply_ui_format_time((string)($lastRep['created_at'] ?? ''), true);
                                    } else {
                                        $snippet = sf_reply_ui_truncate($ctx['preview'], 56);
                                        $lastTime = sf_reply_ui_format_time((string)($inq['created_at'] ?? ''), true);
                                    }

                                    $isSelected = ($inqId === $defaultSelectedId);
                                    $rowStateLabel = $statusStr === 'Read' ? 'Open' : $statusStr;
                                ?>
                                    <button type="button"
                                            class="sf-chat-row is-<?php echo htmlspecialchars($lowerStatus, ENT_QUOTES, 'UTF-8'); ?> <?php echo $isSelected ? 'is-selected' : ''; ?>"
                                            data-chat-row
                                            data-select-chat="<?php echo $inqId; ?>"
                                            data-search-haystack="<?php echo htmlspecialchars(strtolower((string)$inq['guest_name'] . ' ' . (string)($inq['guest_email'] ?? '') . ' ' . $snippet), ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="sf-chat-avatar">
                                            <?php echo htmlspecialchars(sf_reply_ui_initial((string)$inq['guest_name']), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($liveActive && $statusStr !== 'Resolved'): ?>
                                                <span class="sf-chat-avatar-dot is-live" data-row-dot></span>
                                            <?php elseif ($statusStr === 'Unread'): ?>
                                                <span class="sf-chat-avatar-dot is-unread" data-row-dot></span>
                                            <?php else: ?>
                                                <span class="sf-chat-avatar-dot" data-row-dot hidden></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="sf-chat-row-body">
                                            <span class="sf-chat-row-top">
                                                <span class="sf-chat-row-name"><?php echo htmlspecialchars((string)$inq['guest_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="sf-chat-row-time" data-row-time><?php echo htmlspecialchars($lastTime, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                            <span class="sf-chat-row-snippet" data-row-snippet><?php echo htmlspecialchars($snippet !== '' ? $snippet : 'Live support request', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="sf-chat-row-meta">
                                                <span class="sf-row-state-tag <?php echo htmlspecialchars($lowerStatus, ENT_QUOTES, 'UTF-8'); ?>" data-row-status><?php echo htmlspecialchars($rowStateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                                <div class="sf-desk-list-empty" data-queue-empty hidden>No conversations match this filter.</div>
                            </div>
                        </aside>

                        <!-- Right Active Conversation Pane -->
                        <section class="sf-desk-main">
                            <?php foreach ($tickets as $inq):
                                $inqId = (int)$inq['id'];
                                $statusStr = (string)($inq['status'] ?? 'Unread');
                                $cardClass = strtolower($statusStr);
                                if (!in_array($cardClass, ['unread', 'read', 'resolved'], true)) {
                                    $cardClass = 'read';
                                }
                                $badgeClass = 'status-' . $cardClass;
                                $hasRealEmail = sf_reply_ui_has_real_email($inq['guest_email'] ?? '');
                                $thread = $threads[$inqId] ?? [];
                                $convId = (int)($thread['conversation_id'] ?? ($inq['conversation_id'] ?? 0));
                                $liveActive = !empty($thread['live_chat_active']);
                                $langLabel = trim((string)($thread['language'] ?? ($inq['language'] ?? '')));
                                $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
                                $ctx = sf_reply_ui_parse_handoff_context((string)($inq['message'] ?? ''));
                                $isSelected = ($inqId === $defaultSelectedId);
                                $expandContext = empty($replies);
                            ?>
                                <article id="inquiry-<?php echo $inqId; ?>"
                                         class="sf-conv-pane <?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?> <?php echo $isSelected ? 'is-selected' : ''; ?>"
                                         data-inquiry-card
                                         data-inquiry-id="<?php echo $inqId; ?>">

                                    <header class="sf-conv-head">
                                        <div class="sf-conv-guest">
                                            <button type="button" class="sf-mobile-back" data-mobile-back>
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                                Chats
                                            </button>
                                            <span class="sf-chat-avatar" style="width:38px;height:38px;">
                                                <?php echo htmlspecialchars(sf_reply_ui_initial((string)$inq['guest_name']), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <div class="sf-conv-title-wrap">
                                                <div class="sf-conv-name-row">
                                                    <h3 class="sf-conv-name"><?php echo htmlspecialchars((string)$inq['guest_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <span class="status-badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>" data-status-badge><?php echo htmlspecialchars($statusStr, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if ($langLabel !== ''): ?>
                                                        <span class="sf-lang-pill"><?php echo htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="sf-conv-sub">
                                                    <?php if ($hasRealEmail): ?>
                                                        <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php else: ?>
                                                        <span>Widget Session</span>
                                                    <?php endif; ?>
                                                    <?php if ($convId > 0): ?>
                                                        <span>&middot;</span>
                                                        <span>Session #<?php echo $convId; ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($liveActive && $statusStr !== 'Resolved'): ?>
                                                        <span>&middot;</span>
                                                        <span style="color:#047857;font-weight:600;">Active Live Chat</span>
                                                    <?php endif; ?>
                                                    <span>&middot; <?php echo htmlspecialchars(sf_reply_ui_format_time((string)$inq['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sf-conv-actions">
                                            <form method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                                <?php if ($statusStr === 'Unread'): ?>
                                                    <button type="submit" name="action" value="mark_read" class="sf-ghost-btn is-read">Mark Read</button>
                                                <?php endif; ?>
                                                <?php if ($statusStr !== 'Resolved'): ?>
                                                    <button type="submit" name="action" value="mark_resolved" class="sf-ghost-btn is-resolve">Resolve</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="reopen" class="sf-ghost-btn">Re-open</button>
                                                <?php endif; ?>
                                                <?php if ($hasRealEmail): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>?subject=Re: <?php echo urlencode((string)$inq['subject']); ?>" class="sf-ghost-btn">Mail</a>
                                                <?php endif; ?>
                                                <button type="button" class="sf-ghost-btn is-danger" onclick="showConfirm({ title: 'Delete Conversation', message: 'Are you sure you want to delete this live support record? This cannot be undone.', icon: 'trash', iconBg: '#FEE2E2', confirmText: 'Delete', onConfirm: () => this.form.requestSubmit(this.form.querySelector('[value=delete]')) })">Delete</button>
                                                <button type="submit" name="action" value="delete" hidden>Confirm Delete</button>
                                            </form>
                                        </div>
                                    </header>

                                    <?php if ($ctx['note'] !== '' || !empty($ctx['turns'])): ?>
                                        <details class="sf-context-accordion" <?php echo $expandContext ? 'open' : ''; ?>>
                                            <summary class="sf-context-summary">
                                                <span class="sf-context-summary-left">
                                                    <span class="sf-context-kicker">Handoff Context</span>
                                                    <span class="sf-context-preview"><?php echo htmlspecialchars(sf_reply_ui_truncate($ctx['preview'], 82), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </span>
                                                <svg class="sf-context-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                            </summary>
                                            <div class="sf-context-body">
                                                <?php if ($ctx['note'] !== ''): ?>
                                                    <div class="sf-context-note">
                                                        <strong>Guest Request:</strong> <?php echo htmlspecialchars($ctx['note'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php foreach ($ctx['turns'] as $turn): ?>
                                                    <div class="sf-context-turn">
                                                        <span class="sf-context-speaker"><?php echo htmlspecialchars($turn['speaker'], ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                        <span><?php echo htmlspecialchars($turn['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>

                                    <?php sf_reply_ui_render_panel($inqId, (string)($inq['guest_email'] ?? ''), $statusStr, $thread, ['hide_status_bar' => true]); ?>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return;
        endif;

        // Clean Contact Us Inquiries View (admin_inquiries.php)
        ?>
        <div class="inquiries-container">
            <?php if (!empty($tickets)): ?>
                <div class="sf-filter-bar">
                    <div class="sf-filter-pills">
                        <button type="button" class="sf-filter-btn is-active" data-ticket-filter="all">All (<?php echo count($tickets); ?>)</button>
                        <button type="button" class="sf-filter-btn" data-ticket-filter="open">Open (<?php echo $openCount; ?>)</button>
                        <button type="button" class="sf-filter-btn" data-ticket-filter="resolved">Resolved (<?php echo $resolvedCount; ?>)</button>
                    </div>
                </div>

                <?php foreach ($tickets as $inq):
                    $inqId = (int)$inq['id'];
                    $statusStr = (string)($inq['status'] ?? 'Unread');
                    $cardClass = strtolower($statusStr);
                    if (!in_array($cardClass, ['unread', 'read', 'resolved'], true)) {
                        $cardClass = 'read';
                    }
                    $badgeClass = 'status-' . $cardClass;
                    $hasRealEmail = sf_reply_ui_has_real_email($inq['guest_email'] ?? '');
                    $thread = $threads[$inqId] ?? [];
                ?>
                    <div id="inquiry-<?php echo $inqId; ?>" class="inquiry-card <?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?>" data-inquiry-card data-inquiry-id="<?php echo $inqId; ?>">
                        <div class="inquiry-header">
                            <div class="inquiry-author">
                                <span class="sf-chat-avatar" style="width:36px;height:36px;">
                                    <?php echo htmlspecialchars(sf_reply_ui_initial((string)$inq['guest_name']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <div class="inquiry-meta">
                                    <strong><?php echo htmlspecialchars((string)$inq['guest_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <?php if ($hasRealEmail): ?>
                                        <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?></a> &middot;
                                    <?php else: ?>
                                        <span>No email provided &middot; </span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars(sf_reply_ui_format_time((string)$inq['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>

                            <div class="inquiry-head-right">
                                <span class="status-badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>" data-status-badge><?php echo htmlspecialchars($statusStr, ENT_QUOTES, 'UTF-8'); ?></span>
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inqId; ?>">
                                    <?php if ($statusStr === 'Unread'): ?>
                                        <button type="submit" name="action" value="mark_read" class="sf-ghost-btn is-read">Mark Read</button>
                                    <?php endif; ?>
                                    <?php if ($statusStr !== 'Resolved'): ?>
                                        <button type="submit" name="action" value="mark_resolved" class="sf-ghost-btn is-resolve">Resolve</button>
                                    <?php endif; ?>
                                    <?php if ($hasRealEmail): ?>
                                        <a href="mailto:<?php echo htmlspecialchars((string)$inq['guest_email'], ENT_QUOTES, 'UTF-8'); ?>?subject=Re: <?php echo urlencode((string)$inq['subject']); ?>" class="sf-ghost-btn">Mail App</a>
                                    <?php endif; ?>
                                    <button type="button" class="sf-ghost-btn is-danger" onclick="showConfirm({ title: 'Delete Message', message: 'Are you sure you want to delete this inquiry? This cannot be undone.', icon: 'trash', iconBg: '#FEE2E2', confirmText: 'Delete', onConfirm: () => this.form.requestSubmit(this.form.querySelector('[value=delete]')) })">Delete</button>
                                    <button type="submit" name="action" value="delete" hidden>Confirm Delete</button>
                                </form>
                            </div>
                        </div>

                        <div class="inquiry-subject"><?php echo htmlspecialchars((string)$inq['subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="inquiry-message"><?php echo htmlspecialchars((string)$inq['message'], ENT_QUOTES, 'UTF-8'); ?></div>

                        <?php sf_reply_ui_render_panel($inqId, (string)($inq['guest_email'] ?? ''), $statusStr, $thread, ['compact' => true, 'hide_status_bar' => true]); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 54px 20px; color: #64748B; background: var(--card-bg, #FFFFFF); border: 1px solid #E5E0D8; border-radius: 14px;">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; opacity: 0.5;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <p style="margin:0; font-size: 14px;"><?php echo htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('sf_reply_ui_render_script')) {
    function sf_reply_ui_render_script(string $csrfToken, array $options = []): void
    {
        $supportTabSelector = (string)($options['support_tab_selector'] ?? '');
        $badgeStyleMode = (string)($options['badge_style'] ?? 'inquiries'); // 'inquiries' or 'chatbot'
        ?>
        <script>
        (function() {
            var CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
            var SUPPORT_TAB_SELECTOR = <?php echo json_encode($supportTabSelector); ?>;
            var BADGE_MODE = <?php echo json_encode($badgeStyleMode); ?>;
            var LAST_UI_ACTIVITY = Date.now();
            var IDLE_LIMIT_MS = 14 * 60 * 1000;
            var currentFilterMode = 'all';
            var currentSearchQuery = '';

            ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function(evt) {
                document.addEventListener(evt, function() {
                    LAST_UI_ACTIVITY = Date.now();
                }, { passive: true });
            });

            function isSessionIdle() {
                if (Date.now() - LAST_UI_ACTIVITY >= IDLE_LIMIT_MS) {
                    return true;
                }
                try {
                    var lsTs = localStorage.getItem('sbc_last_active_timestamp');
                    if (lsTs) {
                        var delta = Date.now() - parseInt(lsTs, 10);
                        if (!isNaN(delta) && delta >= IDLE_LIMIT_MS) {
                            return true;
                        }
                    }
                } catch (e) {}
                return false;
            }

            function markUserActive() {
                LAST_UI_ACTIVITY = Date.now();
                try {
                    localStorage.setItem('sbc_last_active_timestamp', String(Date.now()));
                } catch (e) {}
            }

            function formatThreadTime(rawTs) {
                if (!rawTs) return 'Just now';
                var iso = String(rawTs).replace(' ', 'T');
                var d = new Date(iso);
                if (isNaN(d.getTime())) return String(rawTs);
                var now = new Date();
                var sameDay = d.getFullYear() === now.getFullYear()
                    && d.getMonth() === now.getMonth()
                    && d.getDate() === now.getDate();
                if (sameDay) {
                    return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                }
                return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
            }

            function formatSenderName(rawName, isStaff) {
                var raw = String(rawName || '').trim();
                if (!isStaff) {
                    var cleanGuest = raw.replace(/\s*\(Live Follow-Up\)\s*$/i, '').trim();
                    return { label: cleanGuest || 'Guest', tooltip: raw || 'Guest' };
                }
                if (!raw) {
                    return { label: 'Reception Staff', tooltip: 'Reception Staff' };
                }
                var role = '';
                var identity = raw;
                var m = raw.match(/^(.+?)\s*\(([^)]+)\)\s*$/);
                if (m) {
                    identity = m[1].trim();
                    role = m[2].trim();
                }
                var prettyName = identity;
                if (identity.indexOf('@') !== -1) {
                    var local = identity.split('@')[0].replace(/[._-]+/g, ' ').trim();
                    if (local) {
                        prettyName = local.replace(/\b\w/g, function(ch) { return ch.toUpperCase(); });
                    }
                }
                var label = prettyName;
                if (role && prettyName.toLowerCase() !== role.toLowerCase()) {
                    label = prettyName + ' (' + role + ')';
                }
                return { label: label, tooltip: raw };
            }

            function truncateSnippet(text, limit) {
                var clean = String(text || '').replace(/\s+/g, ' ').trim();
                if (!clean) return '';
                return clean.length > limit ? clean.slice(0, limit - 3) + '...' : clean;
            }

            function updateQueueRowSummary(inquiryId, rep) {
                var row = document.querySelector('[data-select-chat="' + inquiryId + '"]');
                if (!row || !rep) return;
                var snippetEl = row.querySelector('[data-row-snippet]');
                var timeEl = row.querySelector('[data-row-time]');
                var isStaff = (rep.sender_type || 'staff') === 'staff';
                if (snippetEl && rep.message) {
                    snippetEl.textContent = truncateSnippet((isStaff ? 'Staff: ' : '') + rep.message, 56);
                }
                if (timeEl && rep.created_at) {
                    timeEl.textContent = formatThreadTime(rep.created_at);
                }
            }

            function appendReplyItem(box, rep) {
                var list = box.querySelector('[data-thread-list]');
                if (!list || !rep) return;

                var repId = parseInt(rep.id || 0, 10);
                if (repId > 0 && list.querySelector('[data-reply-id="' + repId + '"]')) {
                    return;
                }

                var emptyEl = list.querySelector('[data-thread-empty]');
                if (emptyEl) {
                    emptyEl.remove();
                }

                var isStaff = (rep.sender_type || 'staff') === 'staff';
                var item = document.createElement('div');
                item.className = 'sf-thread-item ' + (isStaff ? 'is-staff' : 'is-guest');
                if (repId > 0) {
                    item.setAttribute('data-reply-id', String(repId));
                }

                var bubble = document.createElement('div');
                bubble.className = 'sf-bubble';
                bubble.textContent = rep.message || '';

                var meta = document.createElement('div');
                meta.className = 'sf-bubble-meta';

                var senderInfo = formatSenderName(rep.sender_name, isStaff);
                var senderSpan = document.createElement('span');
                senderSpan.className = 'sf-bubble-sender';
                senderSpan.title = senderInfo.tooltip;
                senderSpan.textContent = senderInfo.label;
                meta.appendChild(senderSpan);

                var deliveryParts = [];
                if (isStaff && rep.delivered_live) deliveryParts.push('Live Chat');
                if (isStaff && rep.delivered_email) deliveryParts.push('Email Sent');
                if (deliveryParts.length > 0) {
                    var sep1 = document.createElement('span');
                    sep1.textContent = '\u00b7';
                    meta.appendChild(sep1);

                    var delSpan = document.createElement('span');
                    delSpan.className = 'sf-delivery-note';
                    delSpan.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> ';
                    delSpan.appendChild(document.createTextNode(deliveryParts.join(' & ')));
                    meta.appendChild(delSpan);
                }

                if (isStaff && rep.email_error) {
                    var warnSpan = document.createElement('span');
                    warnSpan.className = 'sf-delivery-note is-warn';
                    warnSpan.title = String(rep.email_error);
                    warnSpan.textContent = '\u00b7 Email notice';
                    meta.appendChild(warnSpan);
                }

                var timeSpan = document.createElement('span');
                timeSpan.textContent = '\u00b7 ' + formatThreadTime(rep.created_at);
                meta.appendChild(timeSpan);

                item.appendChild(bubble);
                item.appendChild(meta);
                list.appendChild(item);
                list.scrollTop = list.scrollHeight;

                var inqId = parseInt(box.getAttribute('data-inquiry-id') || '0', 10);
                if (inqId > 0) {
                    updateQueueRowSummary(inqId, rep);
                }
            }

            function updateCardStatus(box, newStatus, liveActive) {
                var card = box.closest('[data-inquiry-card]');
                if (!card) return;
                var inqId = parseInt(card.getAttribute('data-inquiry-id') || '0', 10);

                if (newStatus) {
                    var lower = String(newStatus).toLowerCase();
                    card.classList.remove('unread', 'read', 'resolved');
                    if (lower === 'unread' || lower === 'read' || lower === 'resolved') {
                        card.classList.add(lower);
                    }

                    var badge = card.querySelector('[data-status-badge]');
                    if (badge) {
                        badge.textContent = newStatus;
                        if (BADGE_MODE === 'chatbot') {
                            badge.classList.remove('badge-pending', 'badge-confirmed', 'badge-checkedin');
                            badge.classList.add(
                                newStatus === 'Resolved' ? 'badge-checkedin' : (newStatus === 'Read' ? 'badge-confirmed' : 'badge-pending')
                            );
                        } else {
                            badge.classList.remove('status-unread', 'status-read', 'status-resolved');
                            badge.classList.add('status-' + lower);
                        }
                    }

                    var queueRow = document.querySelector('[data-select-chat="' + inqId + '"]');
                    if (queueRow) {
                        queueRow.classList.remove('is-unread', 'is-read', 'is-resolved');
                        queueRow.classList.add('is-' + lower);
                        var rowTag = queueRow.querySelector('[data-row-status]');
                        if (rowTag) {
                            rowTag.className = 'sf-row-state-tag ' + lower;
                            rowTag.textContent = newStatus === 'Read' ? 'Open' : newStatus;
                        }
                        var dot = queueRow.querySelector('[data-row-dot]');
                        if (dot) {
                            if (liveActive && newStatus !== 'Resolved') {
                                dot.className = 'sf-chat-avatar-dot is-live';
                                dot.hidden = false;
                            } else if (newStatus === 'Unread') {
                                dot.className = 'sf-chat-avatar-dot is-unread';
                                dot.hidden = false;
                            } else {
                                dot.hidden = true;
                            }
                        }
                    }

                    var readBtn = card.querySelector('button[value="mark_read"]');
                    if (readBtn) readBtn.hidden = (lower !== 'unread');
                    var resolveBtn = card.querySelector('button[value="mark_resolved"]');
                    if (resolveBtn) resolveBtn.hidden = (lower === 'resolved');
                    syncFilterButtonCounts();
                }

                var pill = box.querySelector('[data-live-status-pill]');
                var convId = parseInt(box.getAttribute('data-conversation-id') || '0', 10);
                if (pill && convId > 0 && typeof liveActive === 'boolean') {
                    box.setAttribute('data-live-active', liveActive ? '1' : '0');
                    pill.className = 'sf-live-pill ' + (liveActive ? 'is-active' : 'is-idle');
                    if (liveActive) {
                        pill.innerHTML = '<span class="sf-live-dot"></span> Active Two-Way Chat \u00b7 Session #' + convId;
                    } else {
                        pill.textContent = 'Chat Session #' + convId + ' \u00b7 ' + (newStatus === 'Resolved' ? 'Resolved' : 'AI Mode');
                    }
                }
            }

            function syncFilterButtonCounts() {
                var cards = document.querySelectorAll('[data-inquiry-card]');
                if (!cards.length) return;
                var total = cards.length;
                var resolved = 0;
                cards.forEach(function(c) {
                    if (c.classList.contains('resolved')) resolved++;
                });
                var open = total - resolved;
                var isDesk = !!document.querySelector('[data-chat-desk]');
                var allBtn = document.querySelector('[data-ticket-filter="all"]');
                var openBtn = document.querySelector('[data-ticket-filter="open"]');
                var resBtn = document.querySelector('[data-ticket-filter="resolved"]');
                if (allBtn) allBtn.textContent = 'All (' + total + ')';
                if (openBtn) openBtn.textContent = (isDesk ? 'Active (' : 'Open (') + open + ')';
                if (resBtn) resBtn.textContent = (isDesk ? 'Done (' : 'Resolved (') + resolved + ')';
            }

            function selectDeskConversation(inquiryId, openMobileDetail) {
                var desk = document.querySelector('[data-chat-desk]');
                if (!desk) return;
                var targetId = String(inquiryId || '');
                desk.querySelectorAll('[data-select-chat]').forEach(function(btn) {
                    btn.classList.toggle('is-selected', btn.getAttribute('data-select-chat') === targetId);
                });
                var selectedPane = null;
                desk.querySelectorAll('[data-inquiry-card]').forEach(function(pane) {
                    var match = pane.getAttribute('data-inquiry-id') === targetId;
                    pane.classList.toggle('is-selected', match);
                    if (match) selectedPane = pane;
                });
                if (openMobileDetail) {
                    desk.classList.add('is-mobile-detail');
                }
                if (selectedPane) {
                    var threadWrap = selectedPane.querySelector('[data-thread-list]');
                    if (threadWrap) {
                        threadWrap.scrollTop = threadWrap.scrollHeight;
                    }
                }
            }

            function applyDeskOrCardFilters() {
                var desk = document.querySelector('[data-chat-desk]');
                if (desk) {
                    var visibleCount = 0;
                    var firstVisibleId = null;
                    var selectedStillVisible = false;
                    var activeBtn = desk.querySelector('[data-select-chat].is-selected');
                    var activeId = activeBtn ? activeBtn.getAttribute('data-select-chat') : null;

                    desk.querySelectorAll('[data-chat-row]').forEach(function(row) {
                        var isRes = row.classList.contains('is-resolved');
                        var haystack = (row.getAttribute('data-search-haystack') || '').toLowerCase();
                        var matchFilter = (currentFilterMode === 'all'
                            || (currentFilterMode === 'open' && !isRes)
                            || (currentFilterMode === 'resolved' && isRes));
                        var matchSearch = !currentSearchQuery || haystack.indexOf(currentSearchQuery) !== -1;
                        var show = matchFilter && matchSearch;
                        row.style.display = show ? '' : 'none';
                        if (show) {
                            visibleCount++;
                            var rid = row.getAttribute('data-select-chat');
                            if (!firstVisibleId) firstVisibleId = rid;
                            if (rid === activeId) selectedStillVisible = true;
                        }
                    });

                    var emptyQueue = desk.querySelector('[data-queue-empty]');
                    if (emptyQueue) {
                        emptyQueue.hidden = visibleCount > 0;
                    }
                    if (!selectedStillVisible && firstVisibleId) {
                        selectDeskConversation(firstVisibleId, false);
                    }
                    return;
                }

                // Fallback for Contact Us Inquiry Cards
                document.querySelectorAll('[data-inquiry-card]').forEach(function(card) {
                    var isRes = card.classList.contains('resolved');
                    card.style.display = (currentFilterMode === 'all'
                        || (currentFilterMode === 'open' && !isRes)
                        || (currentFilterMode === 'resolved' && isRes)) ? '' : 'none';
                });
            }

            function selectFromHashIfPresent() {
                var hash = window.location.hash || '';
                var m = hash.match(/^#inquiry-(\d+)$/);
                if (m) {
                    selectDeskConversation(parseInt(m[1], 10), true);
                }
            }

            window.addEventListener('hashchange', selectFromHashIfPresent);
            document.addEventListener('DOMContentLoaded', function() {
                selectFromHashIfPresent();
                document.querySelectorAll('[data-thread-list]').forEach(function(el) {
                    el.scrollTop = el.scrollHeight;
                });
            });

            function showFeedback(box, text, tone) {
                var fb = box.querySelector('[data-reply-feedback]');
                if (!fb) return;
                fb.hidden = false;
                fb.className = 'sf-reply-feedback ' + (tone || 'is-ok');
                fb.textContent = text;
            }

            document.addEventListener('input', function(e) {
                var search = e.target.closest('[data-chat-search]');
                if (!search) return;
                currentSearchQuery = (search.value || '').trim().toLowerCase();
                applyDeskOrCardFilters();
            });

            document.addEventListener('keydown', function(e) {
                var ta = e.target.closest('[data-reply-input]');
                if (!ta) return;
                var box = ta.closest('[data-reply-box]');
                if (!box) return;
                var isChat = box.getAttribute('data-has-chat') === '1';
                if (isChat && e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    var sendBtn = box.querySelector('[data-send-reply][data-resolve="0"]');
                    if (sendBtn && !sendBtn.disabled) {
                        sendBtn.click();
                    }
                }
            });

            document.addEventListener('click', function(e) {
                var rowBtn = e.target.closest('[data-select-chat]');
                if (rowBtn) {
                    selectDeskConversation(rowBtn.getAttribute('data-select-chat'), true);
                    return;
                }

                var backBtn = e.target.closest('[data-mobile-back]');
                if (backBtn) {
                    var desk = backBtn.closest('[data-chat-desk]');
                    if (desk) desk.classList.remove('is-mobile-detail');
                    return;
                }

                var filterBtn = e.target.closest('[data-ticket-filter]');
                if (filterBtn) {
                    currentFilterMode = filterBtn.getAttribute('data-ticket-filter') || 'all';
                    var bar = filterBtn.closest('.sf-filter-bar');
                    if (bar) {
                        bar.querySelectorAll('[data-ticket-filter]').forEach(function(b) {
                            b.classList.toggle('is-active', b === filterBtn);
                        });
                    }
                    applyDeskOrCardFilters();
                    return;
                }

                var btn = e.target.closest('[data-send-reply]');
                if (!btn) return;

                var box = btn.closest('[data-reply-box]');
                if (!box) return;

                var inquiryId = parseInt(box.getAttribute('data-inquiry-id') || '0', 10);
                var input = box.querySelector('[data-reply-input]');
                var liveCb = box.querySelector('[data-send-live]');
                var emailCb = box.querySelector('[data-send-email]');
                var message = input ? input.value.trim() : '';

                if (!inquiryId || !message) {
                    showFeedback(box, 'Please enter a reply message before sending.', 'is-err');
                    if (input) input.focus();
                    return;
                }

                var sendLive = liveCb ? !!liveCb.checked : false;
                var sendEmail = emailCb ? !!emailCb.checked : false;
                var markResolved = btn.getAttribute('data-resolve') === '1';

                if (!sendLive && !sendEmail) {
                    showFeedback(box, 'Select at least one delivery channel (Live Chat or Email Copy).', 'is-err');
                    return;
                }

                var allBtns = box.querySelectorAll('[data-send-reply]');
                allBtns.forEach(function(b) { b.disabled = true; });
                markUserActive();

                fetch('../backend/api/inquiry_reply_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        action: 'send_reply',
                        inquiry_id: inquiryId,
                        message: message,
                        send_live: sendLive,
                        send_email: sendEmail,
                        mark_resolved: markResolved,
                        csrf_token: CSRF_TOKEN
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    allBtns.forEach(function(b) { b.disabled = false; });
                    if (!data || !data.success) {
                        showFeedback(box, (data && data.error) ? data.error : 'Could not send reply right now.', 'is-err');
                        return;
                    }

                    if (input) input.value = '';
                    if (data.reply) {
                        appendReplyItem(box, data.reply);
                    }
                    updateCardStatus(box, data.new_status, data.live_chat_active);

                    var notes = [];
                    if (data.delivered_live) notes.push('Sent to Live Chat');
                    if (data.delivered_email) notes.push('Email sent');
                    var tone = 'is-ok';
                    if (data.email_error) {
                        notes.push('Email notice: ' + data.email_error);
                        tone = 'is-warn';
                    }
                    if (markResolved) {
                        notes.push('Marked Resolved');
                    }
                    showFeedback(box, notes.join(' · ') || 'Reply saved.', tone);
                })
                .catch(function() {
                    allBtns.forEach(function(b) { b.disabled = false; });
                    showFeedback(box, 'Network error while sending reply. Please try again.', 'is-err');
                });
            });

            function pollActiveThreads() {
                if (document.hidden || isSessionIdle()) {
                    return;
                }
                if (SUPPORT_TAB_SELECTOR) {
                    var tabEl = document.querySelector(SUPPORT_TAB_SELECTOR);
                    if (!tabEl || !tabEl.classList.contains('active')) {
                        return;
                    }
                }

                var boxes = document.querySelectorAll('[data-reply-box]');
                if (!boxes.length) return;

                var ids = [];
                boxes.forEach(function(box) {
                    var id = parseInt(box.getAttribute('data-inquiry-id') || '0', 10);
                    if (id > 0) ids.push(id);
                });
                if (!ids.length) return;

                fetch('../backend/api/inquiry_reply_api.php?action=poll_threads&inquiry_ids=' + encodeURIComponent(ids.slice(0, 50).join(',')))
                    .then(function(r) {
                        if (!r.ok) return null;
                        return r.json();
                    })
                    .then(function(data) {
                        if (!data || !data.success || !data.threads) return;
                        boxes.forEach(function(box) {
                            var id = box.getAttribute('data-inquiry-id');
                            var thread = data.threads[id];
                            if (!thread) return;
                            if (Array.isArray(thread.replies)) {
                                thread.replies.forEach(function(rep) {
                                    appendReplyItem(box, rep);
                                });
                            }
                            updateCardStatus(box, thread.status || '', !!thread.live_chat_active);
                        });
                    })
                    .catch(function() {});
            }

            setInterval(pollActiveThreads, 6000);
        })();
        </script>
        <?php
    }
}
