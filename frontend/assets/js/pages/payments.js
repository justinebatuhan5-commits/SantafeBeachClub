(function () {
    'use strict';

    var pageData = { breakdownMap: {}, receptionistName: 'Administrator' };
    var dataEl = document.getElementById('paymentsPageData');
    if (dataEl && dataEl.textContent) {
        try {
            pageData = JSON.parse(dataEl.textContent);
        } catch (err) {
            pageData = { breakdownMap: {}, receptionistName: 'Administrator' };
        }
    }

    var PAYMENTS_BREAKDOWN = pageData.breakdownMap || {};
    var RECEPTIONIST_NAME = pageData.receptionistName || 'Administrator';
    var receiptData = {};

    function openProofImageModal(url, refNumber) {
        var img = document.getElementById('proofModalImg');
        var refBadge = document.getElementById('proofRefBadge');
        var refNumEl = document.getElementById('proofRefNumber');

        if (refBadge && refNumEl) {
            if (refNumber && refNumber !== '' && !refNumber.startsWith('TXN-')) {
                refNumEl.textContent = refNumber;
                refBadge.style.display = 'block';
            } else {
                refBadge.style.display = 'none';
            }
        }

        if (img) {
            if (/^(?:storage|uploads)\/receipts\//.test(url)) {
                var relativePath = url.replace(/^(?:storage|uploads)\//, '');
                var endpoint = new URL(
                    window.location.pathname.includes('/frontend/')
                        ? '../backend/api/serve_file.php'
                        : 'backend/api/serve_file.php',
                    window.location.href
                );
                endpoint.searchParams.set('file', relativePath);
                img.src = endpoint.href;
                img.onerror = null;
            } else {
                var finalUrl = url;
                if (!url.startsWith('http') && !url.startsWith('/')) {
                    finalUrl = window.location.pathname.includes('/frontend/') ? url : 'frontend/' + url;
                }
                img.src = finalUrl;
                img.onerror = function () {
                    if (!this.src.includes('frontend/')) {
                        this.src = 'frontend/' + url;
                    }
                };
            }
        }

        var overlay = document.getElementById('proofOverlay');
        if (overlay) {
            overlay.classList.add('active');
            overlay.style.display = 'flex';
        }
    }

    function closeProofModal() {
        var overlay = document.getElementById('proofOverlay');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.display = 'none';
        }
        var img = document.getElementById('proofModalImg');
        if (img) {
            img.src = '';
        }
    }

    function openReceiptModal(btn) {
        var bid = btn.dataset.bookingId;
        var rcpt = btn.dataset.rcpt || '';
        var inv = btn.dataset.inv || '';
        var guest = btn.dataset.guest || '';
        var room = btn.dataset.room || '';
        var totalCost = parseFloat(btn.dataset.total) || 0;
        var guestCount = parseInt(btn.dataset.guests, 10) || 0;
        var extraAdults = parseInt(btn.dataset.extraAdults, 10) || 0;
        var extraChildren = parseInt(btn.dataset.extraChildren, 10) || 0;
        var discountCategory = btn.dataset.discountCategory || '';
        var discountAmount = parseFloat(btn.dataset.discountAmount) || 0;

        var payments = PAYMENTS_BREAKDOWN[bid] || [];
        var totalPaid = payments.reduce(function (sum, p) { return sum + p.amount; }, 0);

        receiptData = {
            bid: bid,
            rcpt: rcpt,
            inv: inv,
            guest: guest,
            room: room,
            totalCost: totalCost,
            guestCount: guestCount,
            extraAdults: extraAdults,
            extraChildren: extraChildren,
            discountCategory: discountCategory,
            discountAmount: discountAmount,
            payments: payments,
            totalPaid: totalPaid
        };

        renderReceipt();
        var overlay = document.getElementById('receiptOverlay');
        if (overlay) {
            overlay.classList.add('active');
            overlay.style.display = 'flex';
        }
    }

    function closeReceiptModal() {
        var overlay = document.getElementById('receiptOverlay');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.display = 'none';
        }
    }

    function formatCurrency(v) {
        return '₱ ' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getDiscountLabel(category) {
        if (category === 'senior') {
            return 'Senior Citizen Discount (20%)';
        }
        if (category === 'pwd') {
            return 'PWD Discount (20%)';
        }
        return 'Discount';
    }

    function renderReceipt() {
        var d = receiptData;
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        var sep = '<div style="color:#000; letter-spacing:2px;">--------------------------------------</div>';
        var balance = Math.max(0, d.totalCost - d.totalPaid);
        var includedGuests = Math.max(0, d.guestCount - d.extraAdults - d.extraChildren);
        var guestBreakdown = '<div style="display:flex; justify-content:space-between;"><span>Included Guests:</span><strong>' + includedGuests + '</strong></div>' +
            (d.extraAdults > 0 ? '<div style="display:flex; justify-content:space-between;"><span>Extra Adult' + (d.extraAdults === 1 ? '' : 's') + ':</span><strong>' + d.extraAdults + '</strong></div>' : '') +
            (d.extraChildren > 0 ? '<div style="display:flex; justify-content:space-between;"><span>Child' + (d.extraChildren === 1 ? '' : 'ren') + ':</span><strong>' + d.extraChildren + '</strong></div>' : '');
        var discountLabel = getDiscountLabel(d.discountCategory);
        var discountRow = d.discountAmount > 0 ? '<div style="display:flex; justify-content:space-between; color:#15803d;"><span>' + discountLabel + ':</span><strong>-' + formatCurrency(d.discountAmount) + '</strong></div>' : '';

        var breakdownHtml = '<div style="margin:12px 0 4px; font-size:13px; font-weight:800; letter-spacing:1px; text-align:center;">PAYMENT BREAKDOWN</div>';
        if (d.payments.length === 0) {
            breakdownHtml += '<div style="text-align:center; font-style:italic;">No payments recorded</div>';
        } else {
            d.payments.forEach(function (p, i) {
                breakdownHtml += '<div style="text-align:left; margin-bottom:8px;">' +
                    '<div style="display:flex; justify-content:space-between;"><span>' + (i + 1) + '. ' + Security.escapeHTML(p.method) + '</span><strong>' + formatCurrency(p.amount) + '</strong></div>';
                if (p.method === 'Front Desk Cash' && p.tendered > p.amount) {
                    breakdownHtml += '<div style="display:flex; justify-content:space-between; color:#666; font-size:11px; padding-left:12px;"><span>Amount Tendered:</span><span>' + formatCurrency(p.tendered) + '</span></div>';
                    breakdownHtml += '<div style="display:flex; justify-content:space-between; color:#666; font-size:11px; padding-left:12px;"><span>Change:</span><span>' + formatCurrency(p.change) + '</span></div>';
                }
                breakdownHtml += '</div>';
            });
        }

        var preview = document.getElementById('receiptPreview');
        if (!preview) {
            return;
        }

        preview.innerHTML =
            '<div style="font-size:16px; font-weight:800; letter-spacing:1px; margin-top:8px;">SANTA FE BEACH CLUB</div>' +
            '<div style="font-size:12px; color:#666;">Bantayan Island, Cebu</div>' +
            '<div style="font-size:12px; color:#666; margin-bottom:8px;">Official Payment Receipt</div>' +
            sep +
            '<div style="text-align:left; margin:8px 0;">' +
                '<div style="display:flex; justify-content:space-between;"><span>Receipt #:</span><strong>' + Security.escapeHTML(d.rcpt) + '</strong></div>' +
                '<div style="display:flex; justify-content:space-between;"><span>Invoice #:</span><strong>' + Security.escapeHTML(d.inv) + '</strong></div>' +
                '<div style="display:flex; justify-content:space-between;"><span>Date & Time:</span><strong>' + dateStr + ', ' + timeStr + '</strong></div>' +
            '</div>' +
            sep +
            '<div style="text-align:left; margin:8px 0;">' +
                '<div style="display:flex; justify-content:space-between;"><span>Guest Name:</span><strong>' + Security.escapeHTML(d.guest) + '</strong></div>' +
                '<div style="display:flex; justify-content:space-between;"><span>Accommodation:</span><strong>' + Security.escapeHTML(d.room) + '</strong></div>' +
                guestBreakdown +
            '</div>' +
            sep +
            '<div style="text-align:left; margin:8px 0;">' +
                (d.discountAmount > 0 ? '<div style="display:flex; justify-content:space-between; color:#666;"><span>Original Stay Cost:</span><strong>' + formatCurrency(d.totalCost + d.discountAmount) + '</strong></div>' : '') +
                discountRow +
                '<div style="display:flex; justify-content:space-between; color:#666;"><span>Total Booking Cost:</span><strong>' + formatCurrency(d.totalCost) + '</strong></div>' +
                (balance > 0.01 ? '<div style="display:flex; justify-content:space-between; color:#d32f2f; margin-top:4px;"><span>Remaining Balance:</span><strong>' + formatCurrency(balance) + '</strong></div>' : '') +
            '</div>' +
            sep +
            breakdownHtml +
            sep +
            '<div style="margin:12px 0 4px; font-size:15px; font-weight:800; letter-spacing:1px;">TOTAL PAID</div>' +
            '<div style="font-size:22px; font-weight:900; letter-spacing:1px; margin-bottom:8px; color:#7C533C;">' + formatCurrency(d.totalPaid) + '</div>' +
            sep +
            '<div style="text-align:left; margin:8px 0; font-weight:700;">' +
                (balance > 0.01
                    ? '<div style="display:flex; justify-content:space-between; color:#d32f2f;"><span>Status:</span><strong>PARTIAL / BALANCE DUE</strong></div>'
                    : '<div style="display:flex; justify-content:space-between; color:#2E7D32;"><span>Status:</span><strong>PAID IN FULL</strong></div>') +
            '</div>' +
            sep +
            '<div style="text-align:left; margin:8px 0;">' +
                '<div style="display:flex; justify-content:space-between;"><span>Staff:</span><strong>' + Security.escapeHTML(RECEPTIONIST_NAME) + '</strong></div>' +
            '</div>' +
            sep +
            '<div style="color:#666; font-size:12px; margin-top:10px;">Thank you for staying with us!<br>Have a safe trip!</div>' +
            '<div style="color:#888; font-size:11px; margin-top:8px;">This is an official receipt.</div>';
    }

    function doPrintReceipt() {
        var d = receiptData;
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        var balance = Math.max(0, d.totalCost - d.totalPaid);
        var includedGuests = Math.max(0, d.guestCount - d.extraAdults - d.extraChildren);
        var guestBreakdown = '<div class="row"><span>Included Guests:</span><strong>' + includedGuests + '</strong></div>' +
            (d.extraAdults > 0 ? '<div class="row"><span>Extra Adult' + (d.extraAdults === 1 ? '' : 's') + ':</span><strong>' + d.extraAdults + '</strong></div>' : '') +
            (d.extraChildren > 0 ? '<div class="row"><span>Child' + (d.extraChildren === 1 ? '' : 'ren') + ':</span><strong>' + d.extraChildren + '</strong></div>' : '');
        var discountLabel = getDiscountLabel(d.discountCategory);
        var discountRow = d.discountAmount > 0 ? '<div class="row"><span>' + discountLabel + ':</span><strong>-' + formatCurrency(d.discountAmount) + '</strong></div>' : '';
        var sep = '--------------------------------------';

        var breakdownHtml = '<div class="center total-label">PAYMENT BREAKDOWN</div>';
        if (d.payments.length === 0) {
            breakdownHtml += '<div class="center" style="font-style:italic; margin-bottom:6px;">No payments recorded</div>';
        } else {
            d.payments.forEach(function (p, i) {
                breakdownHtml += '<div class="row"><span>' + (i + 1) + '. ' + Security.escapeHTML(p.method) + '</span><strong>' + formatCurrency(p.amount) + '</strong></div>';
                if (p.method === 'Front Desk Cash' && p.tendered > p.amount) {
                    breakdownHtml += '<div class="row" style="color:#666; font-size:11px; padding-left:12px;"><span>Amount Tendered:</span><strong>' + formatCurrency(p.tendered) + '</strong></div>';
                    breakdownHtml += '<div class="row" style="color:#666; font-size:11px; padding-left:12px; margin-bottom:4px;"><span>Change:</span><strong>' + formatCurrency(p.change) + '</strong></div>';
                }
            });
        }

        var printWin = window.open('', '', 'width=420,height=700');
        if (!printWin) {
            return;
        }

        printWin.document.write('<html>' +
            '<head>' +
            '<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">' +
            '<link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">' +
            '<title>Receipt - ' + Security.escapeHTML(d.rcpt) + '</title>' +
            '<style>' +
                '@page { size: 80mm auto; margin: 0; }' +
                '* { box-sizing: border-box; margin: 0; padding: 0; }' +
                'body { font-family: "Courier New", Courier, monospace; background: #fff; color: #000; padding: 16px 8px; font-size: 13px; line-height: 1.6; width: 80mm; }' +
                '@media print { body { width: 80mm; padding: 8px 0; } }' +
                '.center { text-align: center; }' +
                '.sep { color: #000; letter-spacing: 2px; margin: 6px 0; text-align: center; }' +
                '.row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; }' +
                '.brand { font-size: 16px; font-weight: 800; letter-spacing: 1px; }' +
                '.total-label { font-size: 15px; font-weight: 800; letter-spacing: 1px; margin-top: 10px; margin-bottom: 6px; }' +
                '.total-amount { font-size: 20px; font-weight: 900; letter-spacing: 1px; margin-bottom: 6px; }' +
            '</style>' +
            '</head>' +
            '<body>' +
                '<div class="center">' +
                    '<div class="brand">SANTA FE BEACH CLUB</div>' +
                    '<div class="subtitle">Official Payment Receipt</div>' +
                '</div>' +
                '<div class="sep">' + sep + '</div>' +
                '<div class="row"><span>Receipt #:</span><strong>' + Security.escapeHTML(d.rcpt) + '</strong></div>' +
                '<div class="row"><span>Date & Time:</span><strong>' + dateStr + ', ' + timeStr + '</strong></div>' +
                '<div class="sep">' + sep + '</div>' +
                '<div class="row"><span>Guest Name:</span><strong>' + Security.escapeHTML(d.guest) + '</strong></div>' +
                '<div class="row"><span>Accommodation:</span><strong>' + Security.escapeHTML(d.room) + '</strong></div>' +
                guestBreakdown +
                '<div class="sep">' + sep + '</div>' +
                (d.discountAmount > 0 ? '<div class="row"><span>Original Stay Cost:</span><strong>' + formatCurrency(d.totalCost + d.discountAmount) + '</strong></div>' : '') +
                discountRow +
                '<div class="row"><span>Total Cost:</span><strong>' + formatCurrency(d.totalCost) + '</strong></div>' +
                (balance > 0.01 ? '<div class="row" style="color:#d32f2f;"><span>Remaining Balance:</span><strong>' + formatCurrency(balance) + '</strong></div>' : '') +
                '<div class="sep">' + sep + '</div>' +
                breakdownHtml +
                '<div class="sep">' + sep + '</div>' +
                '<div class="center">' +
                    '<div class="total-label">TOTAL PAID</div>' +
                    '<div class="total-amount">' + formatCurrency(d.totalPaid) + '</div>' +
                '</div>' +
                '<div class="sep">' + sep + '</div>' +
                '<div class="row"><span>Status:</span><strong>' + (balance > 0.01 ? 'PARTIAL / BALANCE DUE' : 'PAID IN FULL') + '</strong></div>' +
                '<div class="sep">' + sep + '</div>' +
                '<div class="row"><span>Staff:</span><strong>' + Security.escapeHTML(RECEPTIONIST_NAME) + '</strong></div>' +
                '<script>window.onload = function() { window.print(); }<' + '/script>' +
            '</body>' +
            '</html>');
        printWin.document.close();
    }

    function openRefundModal(payId, guestName, amount) {
        document.getElementById('refundPaymentId').value = payId;
        document.getElementById('refundModalInfo').innerHTML =
            '<strong>Guest:</strong> ' + Security.escapeHTML(guestName) + '<br>' +
            '<strong>Amount:</strong> ' + Security.escapeHTML(amount) + '<br>' +
            '<strong>Invoice:</strong> INV-100' + Security.escapeHTML(String(payId));
        var selectEl = document.getElementById('refundReasonSelect');
        selectEl.name = 'refund_reason';
        selectEl.value = '';
        document.getElementById('customReasonWrap').style.display = 'none';
        document.getElementById('customReasonInput').value = '';
        document.getElementById('refundOverlay').classList.add('active');
    }

    function closeRefundModal() {
        document.getElementById('refundOverlay').classList.remove('active');
    }

    function submitRefund() {
        var reasonSelect = document.getElementById('refundReasonSelect');
        var customInput = document.getElementById('customReasonInput');
        var reason = reasonSelect.value;

        if (!reason) {
            SiteDialog.alert('Please select a reason for the refund.');
            return;
        }
        if (reason === 'Other') {
            reason = customInput.value.trim();
            if (!reason) {
                SiteDialog.alert('Please describe the reason for the refund.');
                return;
            }
            var form = document.getElementById('refundForm');
            var existingHidden = form.querySelector('input[type="hidden"][name="refund_reason"]');
            if (!existingHidden) {
                existingHidden = document.createElement('input');
                existingHidden.type = 'hidden';
                existingHidden.name = 'refund_reason';
                form.appendChild(existingHidden);
            }
            existingHidden.value = reason;
            reasonSelect.name = '';
        }

        document.getElementById('refundForm').submit();
    }

    function openRejectModal(payId, guestName, invoiceNum) {
        document.getElementById('rejectPaymentId').value = payId;
        document.getElementById('rejectModalInfo').innerHTML =
            '<strong>Guest:</strong> ' + Security.escapeHTML(guestName) + '<br>' +
            '<strong>Invoice:</strong> ' + Security.escapeHTML(invoiceNum);
        var selectEl = document.getElementById('rejectReasonSelect');
        selectEl.name = 'reject_reason';
        selectEl.value = '';
        document.getElementById('rejectCustomReasonWrap').style.display = 'none';
        document.getElementById('rejectCustomReasonInput').value = '';
        document.getElementById('rejectOverlay').classList.add('active');
    }

    function closeRejectModal() {
        document.getElementById('rejectOverlay').classList.remove('active');
    }

    function submitReject() {
        var reasonSelect = document.getElementById('rejectReasonSelect');
        var customInput = document.getElementById('rejectCustomReasonInput');
        var reason = reasonSelect.value;

        if (!reason) {
            SiteDialog.alert('Please select a reason for the rejection.');
            return;
        }
        if (reason === 'Other') {
            reason = customInput.value.trim();
            if (!reason) {
                SiteDialog.alert('Please describe the reason for the rejection.');
                return;
            }
            var form = document.getElementById('rejectForm');
            var existingHidden = form.querySelector('input[type="hidden"][name="reject_reason"]');
            if (!existingHidden) {
                existingHidden = document.createElement('input');
                existingHidden.type = 'hidden';
                existingHidden.name = 'reject_reason';
                form.appendChild(existingHidden);
            }
            existingHidden.value = reason;
            reasonSelect.name = '';
        }

        document.getElementById('rejectForm').submit();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var searchInput = document.getElementById('paymentSearch');
        var statusFilter = document.getElementById('paymentStatusFilter');
        var tableRows = document.querySelectorAll('.reservations-table tbody tr');

        function filterTable() {
            var searchVal = searchInput ? searchInput.value.toLowerCase() : '';
            var statusVal = statusFilter ? statusFilter.value.toLowerCase() : '';

            tableRows.forEach(function (row) {
                var text = row.innerText.toLowerCase();
                var statusBadge = row.querySelector('.status-badge');
                var rowStatus = statusBadge ? statusBadge.innerText.toLowerCase() : '';

                var matchesSearch = text.includes(searchVal);
                var matchesStatus = statusVal === '' || rowStatus.includes(statusVal);

                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterTable);
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', filterTable);
        }

        var requestedSearch = new URLSearchParams(window.location.search).get('search');
        if (searchInput && requestedSearch) {
            searchInput.value = requestedSearch;
            filterTable();
            searchInput.focus();
        }

        var refundSelect = document.getElementById('refundReasonSelect');
        if (refundSelect) {
            refundSelect.addEventListener('change', function () {
                document.getElementById('customReasonWrap').style.display = (this.value === 'Other') ? 'block' : 'none';
            });
        }

        var rejectSelect = document.getElementById('rejectReasonSelect');
        if (rejectSelect) {
            rejectSelect.addEventListener('change', function () {
                document.getElementById('rejectCustomReasonWrap').style.display = (this.value === 'Other') ? 'block' : 'none';
            });
        }

        document.querySelectorAll('img[data-fallback-src]').forEach(function (img) {
            img.addEventListener('error', function handleLogoFallback() {
                if (!this.dataset.fallbackApplied) {
                    this.dataset.fallbackApplied = '1';
                    this.src = this.dataset.fallbackSrc;
                }
            });
        });

        document.addEventListener('click', function (e) {
            var actionEl = e.target.closest('[data-action]');
            if (actionEl) {
                var act = actionEl.dataset.action;
                if (act === 'open-proof') {
                    openProofImageModal(actionEl.dataset.url || '', actionEl.dataset.ref || '');
                    return;
                }
                if (act === 'open-reject') {
                    openRejectModal(actionEl.dataset.payId, actionEl.dataset.guest || '', actionEl.dataset.invoice || '');
                    return;
                }
                if (act === 'open-receipt') {
                    openReceiptModal(actionEl);
                    return;
                }
                if (act === 'open-refund') {
                    openRefundModal(actionEl.dataset.payId, actionEl.dataset.guest || '', actionEl.dataset.amount || '');
                    return;
                }
                if (act === 'submit-refund') {
                    submitRefund();
                    return;
                }
                if (act === 'submit-reject') {
                    submitReject();
                    return;
                }
                if (act === 'print-receipt') {
                    doPrintReceipt();
                    return;
                }
            }

            var closeEl = e.target.closest('[data-close-modal]');
            if (closeEl) {
                var target = closeEl.dataset.closeModal;
                if (target === 'proofOverlay') closeProofModal();
                if (target === 'receiptOverlay') closeReceiptModal();
                if (target === 'refundOverlay') closeRefundModal();
                if (target === 'rejectOverlay') closeRejectModal();
                return;
            }

            if (e.target && e.target.classList && e.target.classList.contains('gcash-overlay')) {
                if (e.target.id === 'proofOverlay') closeProofModal();
                if (e.target.id === 'receiptOverlay') closeReceiptModal();
                if (e.target.id === 'refundOverlay') closeRefundModal();
                if (e.target.id === 'rejectOverlay') closeRejectModal();
            }
        });
    });
})();
