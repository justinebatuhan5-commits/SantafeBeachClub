const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const viewPath = path.join(root, 'frontend/views/auth/_verify_otp.php');
const scriptPath = path.join(root, 'frontend/assets/js/pages/verify-otp.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function renderView(vars) {
    const php = `
        function csrf_field() { return '<input type="hidden" name="csrf_token" value="test-csrf">'; }
        extract(json_decode($argv[1], true));
        ob_start();
        require $argv[2];
        echo ob_get_clean();
    `;
    return execFileSync('php', ['-r', php, JSON.stringify(vars), viewPath], { encoding: 'utf8' });
}

// 1. Server-rendered view states
{
    const freshHtml = renderView({
        loginSource: 'admin',
        emailHint: 'Jus***@gmail.com',
        error: '',
        otpRemainingSeconds: 600,
        otpExpiryTotalSeconds: 600,
        resendRemainingSeconds: 60,
        resendCooldownTotalSeconds: 60,
    });
    assert(freshHtml.includes('id="otpExpiryTimer" class="otp-expiry-timer">10:00</span>'));
    assert(freshHtml.includes('Administrator Portal'));
    assert(freshHtml.includes('width: 100%;'));
    assert(freshHtml.includes('Code expires in'));
    assert(!freshHtml.includes('otp-expiry-banner is-expired'));
    assert(freshHtml.includes('id="resendBtn" class="btn-link" disabled'));
    assert(freshHtml.includes('id="countdownText" class="countdown-text" style="display: inline;"'));
    assert(freshHtml.includes('<span id="timer">60</span>'));
    assert(!freshHtml.includes('id="verifyBtn" class="btn-verify" disabled'));

    const warnHtml = renderView({
        loginSource: 'staff',
        emailHint: 'sta***@gmail.com',
        error: '',
        otpRemainingSeconds: 45,
        otpExpiryTotalSeconds: 600,
        resendRemainingSeconds: 0,
        resendCooldownTotalSeconds: 60,
    });
    assert(warnHtml.includes('Front Desk Operations'));
    assert(warnHtml.includes('otp-expiry-banner is-warning'));
    assert(warnHtml.includes('id="otpExpiryTimer" class="otp-expiry-timer">00:45</span>'));
    assert(warnHtml.includes('id="resendBtn" class="btn-link">Resend Code</button>'));

    const expiredHtml = renderView({
        loginSource: 'admin',
        emailHint: 'Jus***@gmail.com',
        error: '',
        otpRemainingSeconds: 0,
        otpExpiryTotalSeconds: 600,
        resendRemainingSeconds: 0,
        resendCooldownTotalSeconds: 60,
    });
    assert(expiredHtml.includes('otp-expiry-banner is-expired'));
    assert(expiredHtml.includes('Code expired'));
    assert(expiredHtml.includes('id="otpExpiryTimer" class="otp-expiry-timer">00:00</span>'));
    assert(expiredHtml.includes('id="verifyBtn" class="btn-verify" disabled'));
    assert.equal((expiredHtml.match(/class="otp-digit"[^>]*disabled/g) || []).length, 6);
    assert(expiredHtml.includes('Verification code has expired.'));
}

// 2. Client-side countdown, expiration, submit guard, and resend reset simulation
function createClassList(initial = []) {
    const set = new Set(initial);
    return {
        add: (...names) => names.forEach(n => set.add(n)),
        remove: (...names) => names.forEach(n => set.delete(n)),
        toggle: (name, force) => {
            if (force === undefined) {
                if (set.has(name)) set.delete(name);
                else set.add(name);
                return set.has(name);
            }
            if (force) set.add(name);
            else set.delete(name);
            return Boolean(force);
        },
        contains: (name) => set.has(name),
    };
}

function createMockElement(tagName, id = '', initial = {}) {
    const listeners = {};
    return {
        tagName,
        id,
        value: '',
        textContent: '',
        innerHTML: '',
        disabled: false,
        focused: false,
        style: {},
        dataset: {},
        classList: createClassList(initial.classes || []),
        addEventListener(type, fn) {
            listeners[type] = listeners[type] || [];
            listeners[type].push(fn);
        },
        async dispatchEvent(type, event = {}) {
            for (const fn of listeners[type] || []) {
                await fn(event);
            }
        },
        focus() { this.focused = true; },
        getAttribute(name) {
            if (name === 'content') return this.content || '';
            return null;
        },
        querySelectorAll() { return []; },
        ...initial,
    };
}

async function runJsSimulation() {
    let nowMs = 1_700_000_000_000;
    const intervals = new Map();
    let nextIntervalId = 1;
    const fetchCalls = [];
    let fetchHandler = async () => ({ json: async () => ({ success: false, message: 'Unexpected' }) });

    const digits = Array.from({ length: 6 }, () => createMockElement('INPUT'));
    const inputsWrap = createMockElement('DIV', '', {
        querySelectorAll: () => [],
    });
    const fullOtp = createMockElement('INPUT', 'fullOtp');
    const msgContainer = createMockElement('DIV', 'msgContainer');
    const authLoader = createMockElement('DIV', 'authLoader');
    const verifyBtn = createMockElement('BUTTON', 'verifyBtn');
    const resendBtn = createMockElement('BUTTON', 'resendBtn');
    const countdownText = createMockElement('SPAN', 'countdownText', { style: { display: 'none' } });
    const timerSpan = createMockElement('SPAN', 'timer');
    const expiryBanner = createMockElement('DIV', 'otpExpiryBanner', {
        dataset: {
            remaining: '65',
            total: '600',
            resendRemaining: '5',
            resendTotal: '60',
        },
    });
    const expiryLabel = createMockElement('SPAN', 'otpExpiryLabel', { textContent: 'Code expires in' });
    const expiryTimer = createMockElement('SPAN', 'otpExpiryTimer', { textContent: '01:05' });
    const expiryProgress = createMockElement('SPAN', 'otpExpiryProgress', { style: { width: '11%' } });
    const csrfMeta = createMockElement('META', '', { content: 'csrf-123' });

    const otpForm = createMockElement('FORM', 'otpForm', {
        querySelector(selector) {
            if (selector === '.otp-inputs') return inputsWrap;
            return null;
        },
        requestSubmit() {
            return this.dispatchEvent('submit', { preventDefault() {} });
        },
    });

    const elements = {
        fullOtp,
        otpForm,
        msgContainer,
        authLoader,
        verifyBtn,
        resendBtn,
        countdownText,
        timer: timerSpan,
        otpExpiryBanner: expiryBanner,
        otpExpiryLabel: expiryLabel,
        otpExpiryTimer: expiryTimer,
        otpExpiryProgress: expiryProgress,
    };

    const docListeners = {};
    const documentMock = {
        hidden: false,
        getElementById(id) { return elements[id] || null; },
        querySelectorAll(sel) { return sel === '.otp-digit' ? digits : []; },
        querySelector(sel) { return sel === 'meta[name="csrf-token"]' ? csrfMeta : null; },
        addEventListener(type, fn) {
            docListeners[type] = docListeners[type] || [];
            docListeners[type].push(fn);
        },
    };

    class MockDate extends Date {
        static now() { return nowMs; }
    }

    function FormDataMock() {
        this.entries = [];
        this.append = (k, v) => this.entries.push([k, v]);
    }

    const context = {
        Date: MockDate,
        document: documentMock,
        window: { location: { href: '' } },
        FormData: FormDataMock,
        Security: { escapeHTML: (s) => String(s) },
        fetch: async (url, opts) => {
            fetchCalls.push({ url, opts });
            return fetchHandler(url, opts);
        },
        setInterval(fn, ms) {
            const id = nextIntervalId++;
            intervals.set(id, fn);
            return id;
        },
        clearInterval(id) {
            intervals.delete(id);
        },
        setTimeout,
        clearTimeout,
        parseInt,
        Number,
        String,
        Math,
        Boolean,
    };

    vm.runInNewContext(scriptSource, context);

    function tickTimers() {
        for (const fn of [...intervals.values()]) {
            fn();
        }
    }

    // Initial state: 01:05 remaining, 5s resend cooldown active
    assert.equal(expiryTimer.textContent, '01:05');
    assert.equal(expiryBanner.classList.contains('is-warning'), false);
    assert.equal(expiryBanner.classList.contains('is-expired'), false);
    assert.equal(verifyBtn.disabled, false);
    assert.equal(resendBtn.disabled, true);
    assert.equal(countdownText.style.display, 'inline');
    assert.equal(timerSpan.textContent, 5);

    // Advance 6 seconds -> warning state (<60s) and resend cooldown finished
    nowMs += 6000;
    tickTimers();
    assert.equal(expiryTimer.textContent, '00:59');
    assert.equal(expiryBanner.classList.contains('is-warning'), true);
    assert.equal(resendBtn.disabled, false);
    assert.equal(countdownText.style.display, 'none');

    // Advance past 65s -> expired state
    nowMs += 60000;
    tickTimers();
    assert.equal(expiryTimer.textContent, '00:00');
    assert.equal(expiryProgress.style.width, '0%');
    assert.equal(expiryLabel.textContent, 'Code expired');
    assert.equal(expiryBanner.classList.contains('is-expired'), true);
    assert.equal(expiryBanner.classList.contains('is-warning'), false);
    assert.equal(verifyBtn.disabled, true);
    assert(digits.every(d => d.disabled === true));
    assert(msgContainer.innerHTML.includes('Verification code has expired.'));

    // Attempting submit while expired should not call fetch
    await otpForm.requestSubmit();
    assert.equal(fetchCalls.length, 0);

    // Click Resend Code -> resets expiry to 10:00, enables digits & verifyBtn, starts 60s cooldown
    fetchHandler = async () => ({
        json: async () => ({
            success: true,
            message: 'A new 6-digit code has been sent to your email.',
            expires_in: 600,
            resend_cooldown: 60,
        }),
    });
    await resendBtn.dispatchEvent('click', {});
    assert.equal(fetchCalls.length, 1);
    assert.equal(expiryTimer.textContent, '10:00');
    assert.equal(expiryProgress.style.width, '100%');
    assert.equal(expiryLabel.textContent, 'Code expires in');
    assert.equal(expiryBanner.classList.contains('is-expired'), false);
    assert.equal(verifyBtn.disabled, false);
    assert(digits.every(d => d.disabled === false));
    assert.equal(digits[0].focused, true);
    assert.equal(resendBtn.disabled, true);
    assert.equal(timerSpan.textContent, 60);
    assert(msgContainer.innerHTML.includes('success-box'));

    // Entering 6 digits auto-submits; if backend reports expired: true, UI locks immediately
    fetchHandler = async () => ({
        json: async () => ({
            success: false,
            message: 'Verification code expired or not found. Please click "Resend Code".',
            expired: true,
        }),
    });
    for (let i = 0; i < 6; i++) {
        digits[i].value = String(i + 1);
        await digits[i].dispatchEvent('input', { target: digits[i] });
    }
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(fetchCalls.length, 2);
    assert.equal(verifyBtn.disabled, true);
    assert(digits.every(d => d.disabled === true));
    assert.equal(expiryBanner.classList.contains('is-expired'), true);
    assert.equal(expiryTimer.textContent, '00:00');
}

runJsSimulation().then(() => {
    console.log('PASS: verify-otp server rendering, expiry countdown, warning/expired states, and resend reset all work.');
}).catch((err) => {
    console.error(err);
    process.exit(1);
});
