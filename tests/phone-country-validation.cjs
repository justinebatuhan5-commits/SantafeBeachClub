const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const securityJs = fs.readFileSync(path.join(root, 'frontend/assets/js/security.js'), 'utf8');
const bookJs = fs.readFileSync(path.join(root, 'frontend/assets/js/pages/book.js'), 'utf8');
const step2Php = fs.readFileSync(path.join(root, 'frontend/views/book/step2_guest.php'), 'utf8');

// 1. Structural check: Country / Region renders before Contact Number on Step 2
const countryIdx = step2Php.indexOf('name="country"');
const phoneIdx = step2Php.indexOf('name="phone"');
assert(countryIdx !== -1 && phoneIdx !== -1 && countryIdx < phoneIdx, 'Country / Region select must appear before Contact Number input in step2_guest.php');
assert(step2Php.includes('id="bkPhoneLabel"') && step2Php.includes('id="bkPhoneHint"'), 'Step 2 includes #bkPhoneLabel and #bkPhoneHint for dynamic required/optional messaging');
console.log('PASS: Step 2 renders Country / Region before Contact Number with dynamic foreign-guest labels');

// 2. Load Security into a lightweight DOM mock to verify phone + country validation rules
function createSecurityContext(hasDialCodeSelector = true) {
    const errors = new Map();
    const makeClassList = () => {
        const set = new Set();
        return {
            add: c => set.add(c),
            remove: c => set.delete(c),
            contains: c => set.has(c)
        };
    };

    const phoneCodeEl = hasDialCodeSelector ? { value: '+63' } : null;
    const countryOptions = [
        { value: '', getAttribute: () => null },
        { value: 'Philippines', getAttribute: k => (k === 'data-dial-code' ? '+63' : 'PH') },
        { value: 'Bangladesh', getAttribute: k => (k === 'data-dial-code' ? '+880' : 'BD') },
        { value: 'Kyrgyzstan', getAttribute: k => (k === 'data-dial-code' ? '+996' : 'KG') },
        { value: 'United States', getAttribute: k => (k === 'data-dial-code' ? '+1' : 'US') },
        { value: 'Singapore', getAttribute: k => (k === 'data-dial-code' ? '+65' : 'SG') }
    ];
    const countryEl = hasDialCodeSelector ? {
        options: countryOptions,
        selectedIndex: 1,
        get value() {
            return this.options[this.selectedIndex].value;
        },
        setCountry(name) {
            const idx = this.options.findIndex(o => o.value === name);
            if (idx !== -1) this.selectedIndex = idx;
        }
    } : null;

    const container = {
        querySelector: () => null,
        appendChild: el => errors.set('el', el)
    };
    const makeInput = (value, dialCode, countryName = 'Philippines', isRequired = true) => {
        if (phoneCodeEl && dialCode) phoneCodeEl.value = dialCode;
        if (countryEl && countryName !== undefined) countryEl.setCountry(countryName);
        const classList = makeClassList();
        return {
            value,
            disabled: false,
            offsetParent: {},
            classList,
            form: {
                querySelector: sel => {
                    if (sel === 'select[name="phone_code"]') return phoneCodeEl;
                    if (sel === 'select[name="country"]') return countryEl;
                    return null;
                }
            },
            getAttribute: name => {
                if (name === 'type') return 'tel';
                if (name === 'data-validate') return 'phone';
                if (name === 'data-label') return 'Contact Number';
                return null;
            },
            hasAttribute: name => (name === 'required' ? isRequired : false),
            closest: sel => (sel === '.bk-form-group' ? container : null)
        };
    };

    const ctx = {
        window: {},
        document: {
            readyState: 'complete',
            head: { appendChild() {} },
            body: { appendChild() {} },
            getElementById: () => null,
            createElement: () => ({ style: {}, className: '', innerHTML: '', setAttribute() {} }),
            querySelector: sel => {
                if (sel === 'select[name="phone_code"]') return phoneCodeEl;
                if (sel === 'select[name="country"]') return countryEl;
                return null;
            },
            querySelectorAll: () => [],
            addEventListener() {}
        },
        console,
        setTimeout,
        clearTimeout,
        URL
    };
    ctx.window = ctx;
    vm.runInNewContext(securityJs, ctx);
    return { Security: ctx.Security, makeInput, phoneCodeEl, countryEl };
}

{
    const { Security, makeInput } = createSecurityContext(true);

    // Philippine numbers (+63) — required
    assert.equal(Security.validateField(makeInput('', '+63', 'Philippines', true)), false, 'PH empty phone is rejected (required)');
    assert.equal(Security.validateField(makeInput('09505223146', '+63', 'Philippines', true)), true, 'PH 11-digit 09... passes');
    assert.equal(Security.validateField(makeInput('9505223146', '+63', 'Philippines', true)), true, 'PH 10-digit 9... passes');
    assert.equal(Security.validateField(makeInput('+639505223146', '+63', 'Philippines', true)), true, 'PH +639... passes');
    assert.equal(Security.validateField(makeInput('950522314', '+63', 'Philippines', true)), false, 'PH 9-digit short number fails');
    assert.equal(Security.validateField(makeInput('95052231467', '+63', 'Philippines', true)), false, 'PH 11-digit without leading 0 fails');

    // Foreign guest — Bangladesh (+880) & local PH SIM (+63) vs mismatched IN +91
    assert.equal(Security.validateField(makeInput('', '+880', 'Bangladesh', false)), true, 'Foreign empty phone is allowed (optional)');
    assert.equal(Security.validateField(makeInput('9505223146', '+91', 'Bangladesh', false)), false, 'Mismatched third-country dial code Bangladesh + IN +91 is rejected');
    assert.equal(Security.validateField(makeInput('9505223146', '+880', 'Bangladesh', false)), false, 'Bangladesh +880 rejects 9505223146 (must start with 1)');
    assert.equal(Security.validateField(makeInput('1712345678', '+880', 'Bangladesh', false)), true, 'Bangladesh +880 accepts 1712345678');
    assert.equal(Security.validateField(makeInput('01712345678', '+880', 'Bangladesh', false)), true, 'Bangladesh +880 accepts 01712345678');
    assert.equal(Security.validateField(makeInput('09505223146', '+63', 'Bangladesh', false)), true, 'Bangladesh guest with local PH +63 SIM 09505223146 passes');

    // Kyrgyzstan (+996) — strictly 9 national digits
    assert.equal(Security.validateField(makeInput('9505223146', '+996', 'Kyrgyzstan', false)), false, 'KG +996 with 10-digit PH number 9505223146 fails');
    assert.equal(Security.validateField(makeInput('555123456', '+996', 'Kyrgyzstan', false)), true, 'KG +996 with 9-digit number passes');
    assert.equal(Security.validateField(makeInput('0555123456', '+996', 'Kyrgyzstan', false)), true, 'KG +996 with trunk 0 + 9-digit number passes');

    // Other international dial codes
    assert.equal(Security.validateField(makeInput('4155550199', '+1', 'United States', false)), true, 'US +1 10-digit number passes');
    assert.equal(Security.validateField(makeInput('12345', '+1', 'United States', false)), false, 'US +1 5-digit number fails');
    assert.equal(Security.validateField(makeInput('91234567', '+65', 'Singapore', false)), true, 'SG +65 8-digit number passes');
    assert.equal(Security.validateField(makeInput('912345678', '+65', 'Singapore', false)), false, 'SG +65 9-digit number fails');
    console.log('PASS: Security.validateField enforces dial-code-to-country locking, prefix rules, and foreign optionality');
}

{
    // Standalone tel input (no phone_code dropdown, e.g., Contact Us / Walk-in Modal)
    const { Security, makeInput } = createSecurityContext(false);
    assert.equal(Security.validateField(makeInput('09171234567', undefined, undefined, false)), true, 'Standalone tel accepts PH 0917...');
    assert.equal(Security.validateField(makeInput('+1 415 555 0199', undefined, undefined, false)), true, 'Standalone tel accepts international +1 number');
    assert.equal(Security.validateField(makeInput('+996 555 123 456', undefined, undefined, false)), true, 'Standalone tel accepts international +996 number');
    assert.equal(Security.validateField(makeInput('+123', undefined, undefined, false)), false, 'Standalone tel rejects too-short international number');
    console.log('PASS: Standalone tel inputs (Contact Us / Walk-in) accept both PH and valid international +... numbers');
}

// 3. Test book.js dial-code locking & dynamic required/optional behavior
{
    const makeSelect = (options, initialIndex = 0) => {
        const el = {
            options: options.map(o => ({ ...o, getAttribute: k => o[k] || null, setAttribute(k, v) { this[k] = v; } })),
            selectedIndex: initialIndex,
            _innerHTML: '',
            get innerHTML() { return this._innerHTML; },
            set innerHTML(val) {
                this._innerHTML = val;
                if (val === '') {
                    this.options = [];
                    this.selectedIndex = -1;
                }
            },
            get value() {
                return (this.selectedIndex >= 0 && this.options[this.selectedIndex]) ? this.options[this.selectedIndex].value : '';
            },
            set value(v) {
                const idx = this.options.findIndex(o => o.value === v);
                if (idx !== -1) this.selectedIndex = idx;
            },
            appendChild(opt) {
                this.options.push(opt);
                if (this.selectedIndex === -1) this.selectedIndex = 0;
            },
            addEventListener(ev, fn) {
                if (!el._listeners) el._listeners = {};
                el._listeners[ev] = el._listeners[ev] || [];
                el._listeners[ev].push(fn);
            },
            trigger(ev, eventObj = {}) {
                (el._listeners && el._listeners[ev] || []).forEach(fn => fn.call(el, eventObj));
            }
        };
        return el;
    };

    const countrySelect = makeSelect([
        { value: '', 'data-dial-code': '', 'data-flag': '' },
        { value: 'Philippines', 'data-dial-code': '+63', 'data-flag': 'PH' },
        { value: 'Bangladesh', 'data-dial-code': '+880', 'data-flag': 'BD' },
        { value: 'Kyrgyzstan', 'data-dial-code': '+996', 'data-flag': 'KG' }
    ], 0);
    const phoneCodeSelect = makeSelect([
        { value: '+63', textContent: 'PH +63' },
        { value: '+91', textContent: 'IN +91' },
        { value: '+880', textContent: 'BD +880' },
        { value: '+996', textContent: 'KG +996' }
    ], 0);
    const phoneAttrs = new Set(['required']);
    const phoneInput = {
        value: '',
        placeholder: '09171234567',
        classList: { contains: () => false },
        setAttribute: k => phoneAttrs.add(k),
        removeAttribute: k => phoneAttrs.delete(k),
        hasAttribute: k => phoneAttrs.has(k)
    };
    const phoneLabel = { innerHTML: '' };
    const phoneHint = { textContent: '' };
    let validatedCount = 0;

    const ctx = {
        document: {
            readyState: 'complete',
            querySelector: sel => {
                if (sel === 'select[name="country"]') return countrySelect;
                if (sel === 'select[name="phone_code"]') return phoneCodeSelect;
                if (sel === 'input[name="phone"]') return phoneInput;
                return null;
            },
            querySelectorAll: () => [],
            getElementById: id => {
                if (id === 'bkPhoneLabel') return phoneLabel;
                if (id === 'bkPhoneHint') return phoneHint;
                return null;
            },
            createElement: () => ({
                value: '',
                textContent: '',
                setAttribute(k, v) { this[k] = v; },
                getAttribute(k) { return this[k] || null; }
            }),
            addEventListener() {}
        },
        window: {},
        Security: {
            validateField: () => {
                validatedCount++;
                return true;
            }
        },
        console,
        URLSearchParams
    };
    ctx.window = ctx;
    vm.runInNewContext(bookJs, ctx);

    // Case A: Initial empty country locks dial code to +63 and keeps phone required
    assert.deepEqual(phoneCodeSelect.options.map(o => o.value), ['+63'], 'Default/Philippines restricts dial code options to +63 only');
    assert.equal(phoneInput.hasAttribute('required'), true, 'Contact Number is required by default');

    // Case B: Selecting Bangladesh locks options to [+880, +63], selects +880, and makes phone optional
    countrySelect.selectedIndex = 2; // Bangladesh
    countrySelect.trigger('change');
    assert.deepEqual(phoneCodeSelect.options.map(o => o.value), ['+880', '+63'], 'Selecting Bangladesh restricts dial codes to BD +880 and PH +63 (no IN +91)');
    assert.equal(phoneCodeSelect.value, '+880', 'Selecting Bangladesh defaults dial code to +880');
    assert.equal(phoneInput.hasAttribute('required'), false, 'Contact Number becomes optional for foreign guests');
    assert(phoneLabel.innerHTML.includes('Optional'), 'Phone label indicates (Optional) for foreign guests');

    // Case C: Guest selects PH +63 and types local PH SIM 09505223146, then switches country to Kyrgyzstan -> PH +63 is preserved
    phoneCodeSelect.value = '+63';
    phoneInput.value = '09505223146';
    phoneCodeSelect.trigger('change');
    assert(validatedCount >= 1, 'Changing phone_code with non-empty phone re-validates immediately');

    countrySelect.selectedIndex = 3; // Kyrgyzstan
    countrySelect.trigger('change');
    assert.deepEqual(phoneCodeSelect.options.map(o => o.value), ['+996', '+63'], 'Kyrgyzstan restricts dial codes to +996 and +63');
    assert.equal(phoneCodeSelect.value, '+63', 'Valid PH SIM is preserved when changing foreign country');

    // Case D: Selecting Philippines locks dial code to +63 only and restores required
    countrySelect.selectedIndex = 1; // Philippines
    countrySelect.trigger('change');
    assert.deepEqual(phoneCodeSelect.options.map(o => o.value), ['+63'], 'Selecting Philippines restricts dial code to +63 only');
    assert.equal(phoneInput.hasAttribute('required'), true, 'Contact Number becomes required again for Philippines');

    console.log('PASS: book.js enforces country-to-dial-code locking and optional foreign guest phone UX');
}
