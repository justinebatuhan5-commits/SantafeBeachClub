(function () {
    const root = document.getElementById('checkinPageRoot');
    const today = root?.dataset.today || new Date().toISOString().slice(0, 10);

    // --- Search Filter ---
    function filterCheckinTable() {
        const q = (document.getElementById('reservationSearch')?.value || '').toLowerCase();
        document.querySelectorAll('.reservations-table tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    }
    window.filterCheckinTable = filterCheckinTable;

    const searchInput = document.getElementById('reservationSearch');
    if (searchInput) {
        searchInput.addEventListener('input', filterCheckinTable);
    }

    // --- Walk-in Modal Logic ---
    function calculateWalkinChange() {
        const sel = document.getElementById('walkinRoomSelect');
        let price = 0;
        if (sel && sel.selectedOptions.length) {
            price = parseFloat(sel.selectedOptions[0].getAttribute('data-price')) || 0;
        }
        const cinVal = document.getElementById('walkinCheckIn')?.value;
        const coutVal = document.getElementById('walkinCheckOut')?.value;
        let nights = 1;
        if (cinVal && coutVal) {
            const cin = new Date(cinVal);
            const cout = new Date(coutVal);
            nights = Math.max(1, Math.ceil((cout - cin) / (1000 * 60 * 60 * 24)));
        }
        const total = nights * price;
        const tendered = parseFloat(document.getElementById('walkinAmountTendered')?.value) || 0;
        let change = tendered - total;
        if (change < 0) change = 0;
        const changeEl = document.getElementById('walkinChangeAmount');
        if (changeEl) changeEl.value = change.toFixed(2);
    }

    function updateWalkinTotals() {
        const sel = document.getElementById('walkinRoomSelect');
        let price = 0;
        if (sel && sel.selectedOptions.length) {
            price = parseFloat(sel.selectedOptions[0].getAttribute('data-price')) || 0;
        }

        const cinVal = document.getElementById('walkinCheckIn')?.value;
        const coutVal = document.getElementById('walkinCheckOut')?.value;

        let nights = 1;
        if (cinVal && coutVal) {
            const cin = new Date(cinVal);
            const cout = new Date(coutVal);
            const diffTime = cout - cin;
            nights = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
        }

        const total = nights * price;
        const nightsEl = document.getElementById('walkinNightsDisplay');
        const rateEl = document.getElementById('walkinRateDisplay');
        const totalEl = document.getElementById('walkinTotalCostDisplay');
        if (nightsEl) nightsEl.textContent = nights + (nights === 1 ? ' night' : ' nights');
        if (rateEl) rateEl.textContent = '₱' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '/night';
        if (totalEl) totalEl.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        calculateWalkinChange();
    }

    function onWalkinRoomChange() {
        const sel = document.getElementById('walkinRoomSelect');
        if (!sel || !sel.selectedOptions.length) return;
        const opt = sel.selectedOptions[0];
        const cap = parseInt(opt.getAttribute('data-capacity'), 10) || 2;

        const guestsInput = document.getElementById('walkinGuestsCount');
        if (guestsInput) {
            guestsInput.max = cap;
            if (parseInt(guestsInput.value, 10) > cap) guestsInput.value = cap;
        }
        updateWalkinTotals();
    }

    function updateWalkinPaySelectors() {
        const radios = document.querySelectorAll('#walkinForm input[name="payment_method"]');
        const cashSec = document.getElementById('walkinCashSection');
        const gcashSec = document.getElementById('walkinGcashSection');
        const cardSec = document.getElementById('walkinCardSection');
        if (cashSec) cashSec.style.display = 'none';
        if (gcashSec) gcashSec.style.display = 'none';
        if (cardSec) cardSec.style.display = 'none';

        radios.forEach(radio => {
            const inner = radio.nextElementSibling;
            if (!inner) return;
            if (radio.checked) {
                inner.style.borderColor = '#2E7D32';
                inner.style.background = '#E8F5E9';
                inner.style.color = '#1B5E20';
                if (radio.value === 'Front Desk Cash' && cashSec) cashSec.style.display = 'block';
                if (radio.value === 'GCash QR' && gcashSec) gcashSec.style.display = 'block';
                if (radio.value === 'Front Desk Card' && cardSec) cardSec.style.display = 'block';
            } else {
                inner.style.borderColor = '#E5E7EB';
                inner.style.background = '#fff';
                inner.style.color = '#333';
            }
        });
    }

    function openWalkinModal() {
        document.getElementById('walkinModalOverlay')?.classList.add('active');
        onWalkinRoomChange();
        updateWalkinPaySelectors();
    }

    function closeWalkinModal() {
        document.getElementById('walkinModalOverlay')?.classList.remove('active');
    }

    document.querySelectorAll('[data-action="open-walkin"]').forEach(btn => {
        btn.addEventListener('click', openWalkinModal);
    });
    document.querySelectorAll('[data-action="close-walkin"]').forEach(btn => {
        btn.addEventListener('click', closeWalkinModal);
    });
    document.getElementById('walkinRoomSelect')?.addEventListener('change', onWalkinRoomChange);
    document.getElementById('walkinCheckIn')?.addEventListener('change', updateWalkinTotals);
    document.getElementById('walkinCheckOut')?.addEventListener('change', updateWalkinTotals);
    document.getElementById('walkinAmountTendered')?.addEventListener('input', calculateWalkinChange);
    document.querySelectorAll('#walkinForm input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', updateWalkinPaySelectors);
    });

    const walkinForm = document.getElementById('walkinForm');
    if (walkinForm) {
        walkinForm.addEventListener('submit', function (e) {
            const sel = document.getElementById('walkinRoomSelect');
            if (!sel || !sel.value) {
                e.preventDefault();
                SiteDialog.alert('Please select an available room for the walk-in guest.');
                return;
            }
            const radios = document.querySelectorAll('#walkinForm input[name="payment_method"]');
            let selected = '';
            radios.forEach(r => { if (r.checked) selected = r.value; });
            let refVal = '';
            if (selected === 'GCash QR') {
                refVal = document.querySelector('input[name="walkin_reference_number_gcash"]')?.value || '';
            } else if (selected === 'Front Desk Card') {
                refVal = document.querySelector('input[name="walkin_reference_number_card"]')?.value || '';
            }
            const finalRef = document.getElementById('walkinFinalReferenceNumber');
            if (finalRef) finalRef.value = refVal;
        });
    }

    document.getElementById('walkinModalOverlay')?.addEventListener('click', function (e) {
        if (e.target === this) closeWalkinModal();
    });

    // --- Existing Balance Checkin Modal Logic ---
    function updatePaySelectors() {
        const radios = document.querySelectorAll('#balanceForm input[name="payment_method"]');
        const cashSec = document.getElementById('cashSection');
        const gcashSec = document.getElementById('gcashSection');
        const cardSec = document.getElementById('cardSection');
        if (cashSec) cashSec.style.display = 'none';
        if (gcashSec) gcashSec.style.display = 'none';
        if (cardSec) cardSec.style.display = 'none';

        radios.forEach(radio => {
            const inner = radio.nextElementSibling;
            if (!inner) return;
            if (radio.checked) {
                inner.style.borderColor = '#2E7D32';
                inner.style.background = '#E8F5E9';
                inner.style.color = '#1B5E20';
                if (radio.value === 'Front Desk Cash' && cashSec) cashSec.style.display = 'block';
                if (radio.value === 'GCash QR' && gcashSec) gcashSec.style.display = 'block';
                if (radio.value === 'Front Desk Card' && cardSec) cardSec.style.display = 'block';
            } else {
                inner.style.borderColor = '#E5E7EB';
                inner.style.background = '#fff';
                inner.style.color = '#333';
            }
        });
    }

    function calculateChange() {
        const balanceDue = parseFloat(document.getElementById('modalBalanceAmountInput')?.value) || 0;
        const tendered = parseFloat(document.getElementById('amountTendered')?.value) || 0;
        let change = tendered - balanceDue;
        if (change < 0) change = 0;
        const changeEl = document.getElementById('changeAmount');
        if (changeEl) changeEl.value = change.toFixed(2);
    }

    function closeBalanceModal() {
        document.getElementById('balanceModalOverlay')?.classList.remove('active');
        const scannedOverlay = document.querySelector('.specific-checkin-overlay');
        if (scannedOverlay) scannedOverlay.style.display = 'flex';
    }

    async function handleCheckinClick(event, bookingId, guestName, accName, totalCost, balanceDue, discountCategory = '', scheduledCheckin = '', checkinBtn = null) {
        event.preventDefault();
        const triggerBtn = (checkinBtn && typeof checkinBtn.closest === 'function')
            ? checkinBtn
            : ((event.currentTarget && typeof event.currentTarget.closest === 'function')
                ? event.currentTarget
                : event.target?.closest?.('.js-checkin-btn'));
        const checkinForm = triggerBtn ? triggerBtn.closest('form') : null;
        const scheduledLabel = scheduledCheckin
            ? new Date(scheduledCheckin + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
            : 'Not specified';
        const isEarly = scheduledCheckin && scheduledCheckin > today;
        const isOverdue = scheduledCheckin && scheduledCheckin < today;
        const timingLabel = isEarly ? 'EARLY CHECK-IN' : (isOverdue ? 'OVERDUE ARRIVAL' : 'SCHEDULED TODAY');
        const hasBalance = balanceDue > 0.01;
        const timingWarning = isEarly
            ? 'The guest is checking in before the scheduled date. Confirm that the room is ready.'
            : (isOverdue ? 'The scheduled check-in date has already passed.' : 'The guest is checking in on the scheduled date.');
        const confirmPrompt = `${timingLabel}\n\n${timingWarning}\n\nGuest: ${guestName}\nRoom: ${accName}\nScheduled check-in: ${scheduledLabel}` +
            (hasBalance ? `\nBalance to collect: ₱${balanceDue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '\nPayment status: Paid in full') +
            (hasBalance ? '\n\nContinue to review and collect the payment?' : '\n\nContinuing will mark the guest as Checked In and the room as occupied.');
        const accepted = window.SiteDialog && typeof window.SiteDialog.confirm === 'function'
            ? await window.SiteDialog.confirm(confirmPrompt, {
                title: isEarly ? 'Early check-in warning' : 'Confirm guest check-in',
                confirmText: hasBalance ? 'Continue to payment' : 'Yes, check in guest',
                danger: isEarly,
                icon: isEarly ? '!' : (isOverdue ? '!' : 'i')
            })
            : window.confirm(confirmPrompt);
        if (!accepted) return;

        if (hasBalance) {
            const scannedOverlay = document.querySelector('.specific-checkin-overlay');
            if (scannedOverlay) scannedOverlay.style.display = 'none';

            document.getElementById('modalBookingId').value = bookingId;
            document.getElementById('modalGuestName').textContent = guestName;
            document.getElementById('modalAccName').textContent = accName;
            const discountRow = document.getElementById('modalDiscountRow');
            const discountBadge = document.getElementById('modalDiscountBadge');
            if (discountCategory === 'senior' || discountCategory === 'pwd') {
                discountBadge.textContent = discountCategory === 'senior' ? 'SENIOR CITIZEN · 20%' : 'PWD · 20%';
                discountRow.style.display = 'flex';
            } else {
                discountRow.style.display = 'none';
                discountBadge.textContent = '';
            }
            document.getElementById('modalScheduledCheckin').textContent = scheduledLabel;
            const dateWarning = document.getElementById('modalDateWarning');
            dateWarning.textContent = isEarly
                ? 'Early check-in: confirm that the room is ready before continuing.'
                : (isOverdue ? 'This guest is arriving after the scheduled check-in date.' : 'Guest is checking in on the scheduled date.');
            dateWarning.style.display = 'block';
            dateWarning.style.background = isEarly || isOverdue ? '#FFF7ED' : '#F0FDF4';
            dateWarning.style.color = isEarly || isOverdue ? '#9A3412' : '#166534';
            dateWarning.style.border = '1px solid ' + (isEarly || isOverdue ? '#FDBA74' : '#86EFAC');
            document.getElementById('modalTotalCost').textContent = '₱' + totalCost.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalBalanceDue').textContent = '₱' + balanceDue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalBalanceAmountInput').value = balanceDue;
            const radios = document.querySelectorAll('#balanceForm input[name="payment_method"]');
            if (radios.length > 0) radios[0].checked = true;
            updatePaySelectors();
            document.getElementById('balanceModalOverlay')?.classList.add('active');
            return;
        }

        if (checkinForm) checkinForm.submit();
    }

    document.addEventListener('click', function (e) {
        const checkinBtn = e.target.closest('.js-checkin-btn');
        if (checkinBtn) {
            handleCheckinClick(
                e,
                checkinBtn.dataset.id,
                checkinBtn.dataset.guest || '',
                checkinBtn.dataset.room || '',
                parseFloat(checkinBtn.dataset.total || '0'),
                parseFloat(checkinBtn.dataset.balance || '0'),
                checkinBtn.dataset.discount || '',
                checkinBtn.dataset.checkin || '',
                checkinBtn
            );
            return;
        }

        const noshowBtn = e.target.closest('.js-noshow-btn');
        if (noshowBtn) {
            openNoshowModal(
                noshowBtn.dataset.id,
                noshowBtn.dataset.guest || '',
                noshowBtn.dataset.room || '',
                noshowBtn.dataset.checkin || ''
            );
        }
    });

    document.querySelectorAll('[data-action="close-balance"]').forEach(btn => {
        btn.addEventListener('click', closeBalanceModal);
    });
    document.getElementById('amountTendered')?.addEventListener('input', calculateChange);
    document.querySelectorAll('#balanceForm input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', updatePaySelectors);
    });

    const balanceForm = document.getElementById('balanceForm');
    if (balanceForm) {
        balanceForm.addEventListener('submit', function (e) {
            const radios = document.querySelectorAll('#balanceForm input[name="payment_method"]');
            let selected = '';
            radios.forEach(r => { if (r.checked) selected = r.value; });

            const balanceDue = parseFloat(document.getElementById('modalBalanceAmountInput')?.value) || 0;
            const tendered = parseFloat(document.getElementById('amountTendered')?.value) || 0;

            if (selected === 'Front Desk Cash' && tendered < balanceDue) {
                e.preventDefault();
                SiteDialog.alert('Amount tendered (₱' + tendered.toFixed(2) + ') cannot be less than the balance due (₱' + balanceDue.toFixed(2) + ').');
                document.getElementById('amountTendered')?.focus();
                return;
            }

            let refVal = '';
            if (selected === 'GCash QR') {
                refVal = document.querySelector('input[name="reference_number_gcash"]')?.value || '';
            } else if (selected === 'Front Desk Card') {
                refVal = document.querySelector('input[name="reference_number_card"]')?.value || '';
            }
            const finalRef = document.getElementById('finalReferenceNumber');
            if (finalRef) finalRef.value = refVal;
        });
    }

    document.getElementById('balanceModalOverlay')?.addEventListener('click', function (e) {
        if (e.target === this) closeBalanceModal();
    });

    // --- Mark No-Show Modal Logic ---
    function openNoshowModal(bookingId, guestName, accName, checkinDate) {
        document.getElementById('noshowBookingId').value = bookingId;
        document.getElementById('noshowGuestName').textContent = guestName;
        document.getElementById('noshowAccName').textContent = accName;
        document.getElementById('noshowCheckinDate').textContent = checkinDate;
        document.getElementById('noshowModalOverlay')?.classList.add('active');
    }

    function closeNoshowModal() {
        document.getElementById('noshowModalOverlay')?.classList.remove('active');
    }

    document.querySelectorAll('[data-action="close-noshow"]').forEach(btn => {
        btn.addEventListener('click', closeNoshowModal);
    });
    document.getElementById('noshowModalOverlay')?.addEventListener('click', function (e) {
        if (e.target === this) closeNoshowModal();
    });

    // --- Thermal Receipt Print Window ---
    const receiptEl = document.getElementById('checkinReceiptPayload');
    if (receiptEl) {
        try {
            const receipt = JSON.parse(receiptEl.textContent);
            if (receipt) {
                window.addEventListener('load', function () {
                    const fmt = val => '₱' + parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const sep = '-'.repeat(32);
                    const printWin = window.open('', '', 'width=420,height=700');
                    if (!printWin) return;
                    const discountRow = receipt.discountLabel
                        ? `<div class="row"><span>Discount:</span><strong>${Security.escapeHTML(receipt.discountLabel)}</strong></div>`
                        : '';
                    printWin.document.write(`<html>
                    <head>
                    <link rel="icon" type="image/png" sizes="32x32" href="${Security.escapeHTML(receipt.favicon32)}">
                    <link rel="apple-touch-icon" sizes="180x180" href="${Security.escapeHTML(receipt.favicon180)}">
                    <title>Receipt - ${Security.escapeHTML(receipt.rcpt)}</title>
                        <style>
                            @page { size: 80mm auto; margin: 0; }
                            * { box-sizing: border-box; margin: 0; padding: 0; }
                            body { font-family: 'Courier New', Courier, monospace; background: #fff; color: #000; padding: 16px 8px; font-size: 13px; line-height: 1.6; width: 80mm; margin: 0; }
                            .center { text-align: center; }
                            .sep { color: #000; letter-spacing: 2px; margin: 6px 0; text-align: center; }
                            .row { display: flex; justify-content: space-between; }
                            .row strong { text-align: right; }
                            .brand { font-size: 16px; font-weight: 800; letter-spacing: 1px; }
                            .subtitle { font-size: 12px; color: #666; }
                            .total-label { font-size: 15px; font-weight: 800; letter-spacing: 1px; margin-top: 10px; }
                            .total-amount { font-size: 20px; font-weight: 900; letter-spacing: 1px; margin-bottom: 6px; }
                            .footer { color: #666; font-size: 12px; margin-top: 10px; }
                            .footer-note { color: #888; font-size: 11px; margin-top: 8px; }
                        </style>
                    </head>
                    <body>
                        <div class="center">
                            <div class="brand">SANTA FE BEACH CLUB</div>
                            <div class="subtitle">Bantayan Island, Cebu</div>
                            <div class="subtitle">Official Payment Receipt</div>
                        </div>
                        <div class="sep">${sep}</div>
                        <div class="row"><span>Receipt #:</span><strong>${Security.escapeHTML(receipt.rcpt)}</strong></div>
                        <div class="row"><span>Date &amp; Time:</span><strong>${Security.escapeHTML(receipt.dateStr)}, ${Security.escapeHTML(receipt.timeStr)}</strong></div>
                        <div class="sep">${sep}</div>
                        <div class="row"><span>Guest Name:</span><strong>${Security.escapeHTML(receipt.guestName)}</strong></div>
                        <div class="row"><span>Accommodation:</span><strong>${Security.escapeHTML(receipt.room)}</strong></div>
                        ${discountRow}
                        <div class="sep">${sep}</div>
                        <div class="row"><span>Payment Type:</span><strong>${Security.escapeHTML(receipt.method)}</strong></div>
                        <div class="row"><span>Ref/Txn #:</span><strong>${Security.escapeHTML(receipt.txn)}</strong></div>
                        <div class="sep">${sep}</div>
                        <div class="row"><span>Amount Due:</span><strong>${fmt(receipt.amountDue)}</strong></div>
                        <div class="row"><span>Amount Tendered:</span><strong>${fmt(receipt.tendered)}</strong></div>
                        <div class="row"><span>Change:</span><strong>${fmt(receipt.change)}</strong></div>
                        <div class="sep">${sep}</div>
                        <div class="center">
                            <div class="total-label">TOTAL PAID</div>
                            <div class="total-amount">${fmt(receipt.amountDue)}</div>
                        </div>
                        <div class="sep">${sep}</div>
                        <div class="row"><span>Receptionist:</span><strong>${Security.escapeHTML(receipt.adminName)}</strong></div>
                        <div class="sep">${sep}</div>
                        <div class="center footer">Thank you for staying with us!<br>Have a safe trip!</div>
                        <div class="center footer-note">This is an official receipt.</div>
                        <script>window.onload = function() { window.print(); }<\/script>
                    </body>
                    </html>`);
                    printWin.document.close();
                });
            }
        } catch (err) {
            console.error('Failed to parse receipt payload', err);
        }
    }
})();
