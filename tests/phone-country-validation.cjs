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
assert(step2Php.includes('local PH (+63) SIM'), 'Step 2 renders helper note for foreign guests using local PH SIM');
console.log('PASS: Step 2 renders Country / Region before Contact Number with local PH SIM guidance');

// 2. Load Security into a lightweight DOM mock to verify phone validation rules
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
    const container = {
        querySelector: () => null,
        appendChild: el => errors.set('el', el)
    };
    const makeInput = (value, dialCode) => {
        if (phoneCodeEl && dialCode) phoneCodeEl.value = dialCode;
        const classList = makeClassList();
        return {
            value,
            disabled: false,
            offsetParent: {},
            classList,
            form: {
                querySelector: sel => (sel === 'select[name="phone_code"]' ? phoneCodeEl : null)
            },
            getAttribute: name => {
                if (name === 'type') return 'tel';
                if (name === 'data-validate') return 'phone';
                if (name === 'data-label') return 'Contact Number';
                return null;
            },
            hasAttribute: name => name === 'required',
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
            querySelector: sel => (sel === 'select[name="phone_code"]' ? phoneCodeEl : null),
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
    return { Security: ctx.Security, makeInput, phoneCodeEl };
}

{
    const { Security, makeInput } = createSecurityContext(true);

    // Philippine numbers (+63)
    assert.equal(Security.validateField(makeInput('09505223146', '+63')), true, 'PH 11-digit 09... passes');
    assert.equal(Security.validateField(makeInput('9505223146', '+63')), true, 'PH 10-digit 9... passes');
    assert.equal(Security.validateField(makeInput('+639505223146', '+63')), true, 'PH +639... passes');
    assert.equal(Security.validateField(makeInput('950522314', '+63')), false, 'PH 9-digit short number fails');
    assert.equal(Security.validateField(makeInput('95052231467', '+63')), false, 'PH 11-digit without leading 0 fails');

    // Kyrgyzstan (+996) — strictly 9 national digits
    assert.equal(Security.validateField(makeInput('9505223146', '+996')), false, 'KG +996 with 10-digit PH number 9505223146 fails');
    assert.equal(Security.validateField(makeInput('555123456', '+996')), true, 'KG +996 with 9-digit number passes');
    assert.equal(Security.validateField(makeInput('0555123456', '+996')), true, 'KG +996 with trunk 0 + 9-digit number passes');

    // Mismatched explicit + prefix in combo box
    assert.equal(Security.validateField(makeInput('+639505223146', '+996')), false, 'Mismatched +63 prefix under +996 fails');

    // Other international dial codes
    assert.equal(Security.validateField(makeInput('4155550199', '+1')), true, 'US +1 10-digit number passes');
    assert.equal(Security.validateField(makeInput('12345', '+1')), false, 'US +1 5-digit number fails');
    assert.equal(Security.validateField(makeInput('91234567', '+65')), true, 'SG +65 8-digit number passes');
    assert.equal(Security.validateField(makeInput('912345678', '+65')), false, 'SG +65 9-digit number fails');
    console.log('PASS: Security.validateField enforces PH & country-specific digit lengths');
}

{
    // Standalone tel input (no phone_code dropdown, e.g., Contact Us / Walk-in Modal)
    const { Security, makeInput } = createSecurityContext(false);
    assert.equal(Security.validateField(makeInput('09171234567')), true, 'Standalone tel accepts PH 0917...');
    assert.equal(Security.validateField(makeInput('+1 415 555 0199')), true, 'Standalone tel accepts international +1 number');
    assert.equal(Security.validateField(makeInput('+996 555 123 456')), true, 'Standalone tel accepts international +996 number');
    assert.equal(Security.validateField(makeInput('+123')), false, 'Standalone tel rejects too-short international number');
    console.log('PASS: Standalone tel inputs (Contact Us / Walk-in) accept both PH and valid international +... numbers');
}

// 3. Test book.js country-to-dial-code sync & foreign guest local PH SIM preservation
{
    const listeners = {};
    const makeSelect = (options, initialIndex = 0) => {
        const el = {
            options: options.map(o => ({ ...o, getAttribute: k => o[k] || null })),
            selectedIndex: initialIndex,
            get value() {
                return this.options[this.selectedIndex] ? this.options[this.selectedIndex].value : '';
            },
            set value(v) {
                const idx = this.options.findIndex(o => o.value === v);
                if (idx !== -1) this.selectedIndex = idx;
            },
            appendChild(opt) { this.options.push(opt); },
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
        { value: 'Kyrgyzstan', 'data-dial-code': '+996', 'data-flag': 'KG' }
    ], 0);
    const phoneCodeSelect = makeSelect([
        { value: '+63', textContent: 'PH +63' },
        { value: '+996', textContent: 'KG +996' }
    ], 0);
    const phoneInput = { value: '', placeholder: '09171234567' };
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
            getElementById: () => null,
            createElement: () => ({ value: '', textContent: '' }),
            addEventListener(ev, fn) {
                listeners[ev] = listeners[ev] || [];
                listeners[ev].push(fn);
            }
        },
        window: {},
        Security: {
            validateField: () => {
                validatedCount++;
                return true;
            }
        },
        console,
        Event: function (type) { this.type = type; },
        URLSearchParams
    };
    ctx.window = ctx;
    vm.runInNewContext(bookJs, ctx);

    // Case A: Empty phone -> choosing Kyrgyzstan switches dial code to +996
    countrySelect.selectedIndex = 2;
    countrySelect.trigger('change');
    assert.equal(phoneCodeSelect.value, '+996', 'Empty phone switches dial code to +996 when Kyrgyzstan is chosen');

    // Case B: User types 9505223146 under +996, or changes dial code manually to +63 -> triggers re-validation
    phoneInput.value = '9505223146';
    phoneCodeSelect.selectedIndex = 0; // +63
    phoneCodeSelect.trigger('change', { isTrusted: true });
    assert.equal(validatedCount, 1, 'Changing phone_code with non-empty phone re-validates immediately');

    // Case C: Now changing Country / Region to Kyrgyzstan preserves PH +63 because guest has a valid PH SIM & manually chose +63
    countrySelect.selectedIndex = 2;
    countrySelect.trigger('change');
    assert.equal(phoneCodeSelect.value, '+63', 'Valid PH SIM + manual +63 selection is preserved when Country / Region changes to Kyrgyzstan');
    console.log('PASS: book.js preserves foreign guest local PH SIM while syncing empty/default fields and re-validating');
}
