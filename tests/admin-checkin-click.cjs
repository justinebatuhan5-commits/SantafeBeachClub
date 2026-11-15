const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const scriptPath = path.resolve(__dirname, '../frontend/assets/js/pages/admin-checkin.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function createClassList() {
    const classes = new Set();
    return {
        add: (...names) => names.forEach(n => classes.add(n)),
        remove: (...names) => names.forEach(n => classes.delete(n)),
        contains: (name) => classes.has(name),
    };
}

function createMockElement(tagName, id = '', initial = {}) {
    return {
        tagName,
        id,
        value: '',
        textContent: '',
        style: {},
        dataset: {},
        classList: createClassList(),
        selectedOptions: [],
        addEventListener() {},
        closest() { return null; },
        getAttribute() { return null; },
        ...initial,
    };
}

async function runScenario({ balance, guest, room, total, bookingId, checkin }) {
    const listeners = {};
    const confirmCalls = [];
    let formSubmitted = false;

    const formEl = {
        tagName: 'FORM',
        submit() {
            formSubmitted = true;
        },
    };

    const buttonEl = {
        tagName: 'BUTTON',
        dataset: {
            id: bookingId,
            guest,
            room,
            total: String(total),
            balance: String(balance),
            discount: '',
            checkin,
        },
        closest(selector) {
            if (selector === '.js-checkin-btn') return buttonEl;
            if (selector === 'form') return formEl;
            return null;
        },
    };

    const elements = {
        checkinPageRoot: createMockElement('MAIN', 'checkinPageRoot', { dataset: { today: '2026-11-15' } }),
        modalBookingId: createMockElement('INPUT', 'modalBookingId'),
        modalGuestName: createMockElement('STRONG', 'modalGuestName'),
        modalAccName: createMockElement('STRONG', 'modalAccName'),
        modalDiscountRow: createMockElement('DIV', 'modalDiscountRow'),
        modalDiscountBadge: createMockElement('STRONG', 'modalDiscountBadge'),
        modalScheduledCheckin: createMockElement('STRONG', 'modalScheduledCheckin'),
        modalDateWarning: createMockElement('DIV', 'modalDateWarning'),
        modalTotalCost: createMockElement('STRONG', 'modalTotalCost'),
        modalBalanceDue: createMockElement('STRONG', 'modalBalanceDue'),
        modalBalanceAmountInput: createMockElement('INPUT', 'modalBalanceAmountInput'),
        balanceModalOverlay: createMockElement('DIV', 'balanceModalOverlay'),
    };

    const radioEl = {
        checked: false,
        value: 'Front Desk Cash',
        nextElementSibling: { style: {} },
        addEventListener() {},
    };

    const documentMock = {
        addEventListener(type, handler) {
            listeners[type] = listeners[type] || [];
            listeners[type].push(handler);
        },
        getElementById(id) {
            return elements[id] || null;
        },
        querySelector() {
            return null;
        },
        querySelectorAll(selector) {
            if (selector === '#balanceForm input[name="payment_method"]') return [radioEl];
            return [];
        },
    };

    const windowMock = {
        addEventListener() {},
        SiteDialog: {
            async confirm(msg, opts) {
                confirmCalls.push({ msg, opts });
                return true;
            },
            async alert() {},
        },
        confirm() {
            return true;
        },
        Security: {
            escapeHTML: (s) => String(s),
        },
    };
    windowMock.window = windowMock;

    const unhandledRejections = [];
    const onUnhandled = (err) => unhandledRejections.push(err);
    process.on('unhandledRejection', onUnhandled);

    try {
        vm.runInNewContext(scriptSource, {
            window: windowMock,
            document: documentMock,
            SiteDialog: windowMock.SiteDialog,
            Security: windowMock.Security,
            console,
        }, { filename: 'admin-checkin.js' });

        assert.ok(Array.isArray(listeners.click) && listeners.click.length > 0, 'Expected delegated click listener on document');

        let prevented = false;
        const clickEvent = {
            target: {
                closest(selector) {
                    return selector === '.js-checkin-btn' ? buttonEl : null;
                },
            },
            // Mimic real DOM delegated event where currentTarget === document (not buttonEl)
            currentTarget: documentMock,
            preventDefault() {
                prevented = true;
            },
        };

        for (const handler of listeners.click) {
            handler.call(documentMock, clickEvent);
        }

        // Flush async SiteDialog.confirm promise queue
        await new Promise(resolve => setTimeout(resolve, 20));

        assert.equal(unhandledRejections.length, 0, 'Delegated Check-in click must not throw an unhandled rejection: ' + (unhandledRejections[0]?.message || ''));
        assert.equal(prevented, true, 'Check-in click prevents default immediate form submit pending confirmation');
        assert.equal(confirmCalls.length, 1, 'Check-in click opens SiteDialog.confirm');

        return { elements, formSubmitted, confirmCalls };
    } finally {
        process.off('unhandledRejection', onUnhandled);
    }
}

(async () => {
    // 1. Booking with remaining balance (matches screenshot scenario: #120001 with 3950 due)
    const unpaid = await runScenario({
        bookingId: '120001',
        guest: 'Justine Batuhan',
        room: 'Beach Villa 103',
        total: 7900,
        balance: 3950,
        checkin: '2026-11-15',
    });
    assert.equal(unpaid.formSubmitted, false, 'Booking with balance due opens payment modal before submitting');
    assert.equal(unpaid.elements.balanceModalOverlay.classList.contains('active'), true, 'Balance payment modal opens on Check-in confirmation');
    assert.equal(unpaid.elements.modalBookingId.value, '120001');
    assert.equal(unpaid.elements.modalGuestName.textContent, 'Justine Batuhan');
    assert.equal(unpaid.elements.modalBalanceAmountInput.value, 3950);

    // 2. Booking paid in full submits form directly after confirmation
    const paid = await runScenario({
        bookingId: '120002',
        guest: 'Paid Guest',
        room: 'Beach Villa 104',
        total: 7900,
        balance: 0,
        checkin: '2026-11-15',
    });
    assert.equal(paid.formSubmitted, true, 'Paid-in-full booking submits the check-in form after confirmation');

    console.log('PASS: admin-checkin delegated Check-in button triggers confirmation, balance modal, and form submission.');
})();
