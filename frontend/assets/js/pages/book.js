/**
 * book.js — Public multi-step booking flow interactions:
 * payment method switching, Senior/PWD discount toggle, file upload magic-byte
 * validation, PDF confirmation download, Edit Room modal, availability calendar
 * modal, and country-to-calling-code synchronization.
 */
function bkSelectPayment(method) {
    var paymongoForm = document.getElementById('paymongo-form');
    var paymongoOpt  = document.getElementById('opt-paymongo');
    var bankForm     = document.getElementById('bank-form');
    var bankOpt      = document.getElementById('opt-bank');
    var gcashForm    = document.getElementById('online-form');
    var gcashOpt     = document.getElementById('opt-online');

    // Reset all
    if (paymongoForm) {
        paymongoForm.style.display = 'none';
        paymongoForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
    }
    if (paymongoOpt)  paymongoOpt.classList.remove('bk-pay-option--active');
    if (bankForm) {
        bankForm.style.display = 'none';
        bankForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
    }
    if (bankOpt)      bankOpt.classList.remove('bk-pay-option--active');
    if (gcashForm) {
        gcashForm.style.display = 'none';
        gcashForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
    }
    if (gcashOpt)     gcashOpt.classList.remove('bk-pay-option--active');

    if (method === 'PayMongo Online') {
        if (paymongoForm) {
            paymongoForm.style.display = 'block';
            paymongoForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
        }
        if (paymongoOpt)  paymongoOpt.classList.add('bk-pay-option--active');
    } else if (method === 'Bank Deposit') {
        if (bankForm) {
            bankForm.style.display = 'block';
            bankForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
        }
        if (bankOpt)   bankOpt.classList.add('bk-pay-option--active');
    } else if (method === 'GCash QR' || method === 'Online Payment') {
        if (gcashForm) {
            gcashForm.style.display = 'flex';
            gcashForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
        }
        if (gcashOpt)  gcashOpt.classList.add('bk-pay-option--active');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var category = document.getElementById('discountCategory');
    if (category) {
        var updateDiscount = function() {
            var requested = category.value !== 'regular';
            var sampleCategory = category.value === 'pwd' ? 'PWD' : 'Senior Citizen';
            document.getElementById('sampleIdCategory').textContent = sampleCategory;
            var sampleImage = document.getElementById('sampleIdImage');
            sampleImage.src = category.value === 'pwd' ? 'assets/images/pwd-id-sample.png' : 'assets/images/senior-id-sample.png';
            sampleImage.alt = sampleCategory + ' ID illustration with fictional name, photo placeholder and ID number. SAMPLE — NOT VALID.';
            document.getElementById('discountIdGuide').open = requested;
            document.getElementById('discountFields').hidden = !requested;
            var payments = document.getElementById('regularPaymentFields');
            payments.disabled = requested;
            payments.hidden = requested;
            document.getElementById('regularPaymentSchedule').hidden = requested;
            document.getElementById('regularSummaryTotal').hidden = requested;
            document.getElementById('regularSummaryDeposit').hidden = requested;
            document.getElementById('regularSummaryTotal').style.display = requested ? 'none' : '';
            document.getElementById('regularSummaryDeposit').style.display = requested ? 'none' : '';
            document.getElementById('discountSummary').hidden = !requested;
            ['eligibleGuest', 'discountId', 'discountConsent'].forEach(function(id) {
                var input = document.getElementById(id);
                input.disabled = !requested;
                input.required = requested;
            });
            var form = category.closest('form');
            form.querySelector('input[name="action"]').value = requested ? 'request_discount' : 'confirm_booking';
            form.querySelector('button[type="submit"]').textContent = requested ? 'Submit booking & discount request' : 'Confirm and book';
        };
        category.addEventListener('change', updateDiscount);
        updateDiscount();
    }

    var radios = document.querySelectorAll('input[name="payment_method"]');
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                bkSelectPayment(this.value);
            }
        });
    });

    var options = document.querySelectorAll('.bk-pay-option');
    options.forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.closest('.bk-card-form')) {
                return;
            }
            var radio = opt.querySelector('input[name="payment_method"]');
            if (radio) {
                radio.checked = true;
                bkSelectPayment(radio.value);
            }
        });
    });

    // Initialize the correct state on load
    var checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (checkedRadio) {
        bkSelectPayment(checkedRadio.value);
    }
});

// Require reference / receipt before letting the guest submit
(function() {
    var bookingForm = document.querySelector('form[action="book"]');
    var actionField = bookingForm ? bookingForm.querySelector('input[name="action"][value="confirm_booking"]') : null;
    if (!bookingForm || !actionField) return;

    bookingForm.addEventListener('submit', function(e) {
        if (document.getElementById('discountCategory') && document.getElementById('discountCategory').value !== 'regular') return;
        var selectedMethod = bookingForm.querySelector('input[name="payment_method"]:checked');
        if (!selectedMethod) return;

        if (selectedMethod.value === 'Bank Deposit') {
            var bankRefInput = document.getElementById('bankReferenceInput');
            var bankRefError = document.getElementById('bankRefError');
            var bankReceiptInput = document.getElementById('bankReceiptInput');

            if (bankRefInput && bankRefInput.value.trim() === '') {
                e.preventDefault();
                if (bankRefError) bankRefError.style.display = 'block';
                bankRefInput.style.borderColor = '#E65100';
                bankRefInput.focus();
                bankRefInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (bankReceiptInput && bankReceiptInput.files.length === 0) {
                e.preventDefault();
                SiteDialog.alert('Please upload a screenshot or image of your bank receipt.');
                bankReceiptInput.focus();
                bankReceiptInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        } else if (selectedMethod.value === 'GCash QR') {
            var gcashRefInput = document.getElementById('gcashReferenceInput');
            var gcashRefError = document.getElementById('gcashRefError');
            var gcashReceiptInput = document.getElementById('gcashReceiptInput');

            if (gcashRefInput && gcashRefInput.value.trim() === '') {
                e.preventDefault();
                if (gcashRefError) gcashRefError.style.display = 'block';
                gcashRefInput.style.borderColor = '#EF4444';
                gcashRefInput.focus();
                gcashRefInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (gcashReceiptInput && gcashReceiptInput.files.length === 0) {
                e.preventDefault();
                SiteDialog.alert('Please upload a screenshot of your GCash payment receipt.');
                gcashReceiptInput.focus();
                gcashReceiptInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        }
    });

    var bankRefInput = document.getElementById('bankReferenceInput');
    if (bankRefInput) {
        bankRefInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                document.getElementById('bankRefError').style.display = 'none';
                this.style.borderColor = '';
            }
        });
    }

    var gcashRefInput = document.getElementById('gcashReferenceInput');
    if (gcashRefInput) {
        gcashRefInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                var err = document.getElementById('gcashRefError');
                if (err) err.style.display = 'none';
                this.style.borderColor = '';
            }
        });
    }
})();

async function downloadBookingPdf() {
    var cfg = window.SFBC_BOOK_CONFIG || {};
    var ticket = document.querySelector('.ticket-pass');
    var button = document.querySelector('.btn-pdf--header');

    if (!ticket) {
        SiteDialog.alert('Unable to find the booking ticket.');
        return;
    }

    if (!window.html2canvas || !window.jspdf || !window.jspdf.jsPDF) {
        SiteDialog.alert('PDF tools are not ready yet. Please try again in a moment.');
        return;
    }

    var hiddenEls = ticket.querySelectorAll('.pdf-hide');
    var hiddenState = [];
    hiddenEls.forEach(function(el) {
        hiddenState.push({ el: el, display: el.style.display });
        el.style.display = 'none';
    });

    if (button) {
        button.disabled = true;
        button.textContent = 'Downloading...';
    }

    try {
        var canvas = await html2canvas(ticket, {
            scale: 2,
            backgroundColor: '#FFFDF9',
            useCORS: true,
            scrollY: -window.scrollY
        });

        var imgData = canvas.toDataURL('image/png');
        var pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
        var pageWidth = pdf.internal.pageSize.getWidth();
        var pageHeight = pdf.internal.pageSize.getHeight();
        var margin = 10;
        var maxWidth = pageWidth - (margin * 2);
        var maxHeight = pageHeight - (margin * 2);
        var scale = Math.min(maxWidth / canvas.width, maxHeight / canvas.height);
        var finalWidth = canvas.width * scale;
        var finalHeight = canvas.height * scale;
        var x = (pageWidth - finalWidth) / 2;
        var y = margin;

        pdf.addImage(imgData, 'PNG', x, y, finalWidth, finalHeight);
        pdf.save('booking-confirmation-REF-' + (cfg.bookingIdPadded || '000') + '.pdf');
    } catch (error) {
        console.error(error);
        SiteDialog.alert('Unable to download the PDF. Please try again.');
    } finally {
        hiddenState.forEach(function(item) {
            item.el.style.display = item.display;
        });
        if (button) {
            button.disabled = false;
            button.textContent = 'Download PDF';
        }
    }
}

// File Upload Magic Byte Validation
const MAGIC_SIGNATURES = {
    jpg:  { offset: 0, bytes: [0xFF, 0xD8, 0xFF] },
    jpeg: { offset: 0, bytes: [0xFF, 0xD8, 0xFF] },
    png:  { offset: 0, bytes: [0x89, 0x50, 0x4E, 0x47] },
    pdf:  { offset: 0, bytes: [0x25, 0x50, 0x44, 0x46] }, // %PDF
};

function validateReceiptFile(input, allowedExts, errorDivId) {
    const errorDiv = document.getElementById(errorDivId);
    const file = input.files[0];

    // Hide any previous error
    errorDiv.style.display = 'none';
    input.style.borderColor = '';

    if (!file) return;

    // Step 1 — Check file size (2MB maximum)
    const MAX_SIZE_BYTES = 2 * 1024 * 1024;
    if (file.size > MAX_SIZE_BYTES) {
        const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);
        showFileError(input, errorDiv, 'File is too large (' + sizeInMb + 'MB). Maximum allowed size is 2MB. Please compress or resize your image.');
        return;
    }

    // Step 2 — Check file extension
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExts.includes(ext)) {
        showFileError(input, errorDiv, 'Wrong file type. Only ' + allowedExts.join(', ').toUpperCase() + ' files are accepted.');
        return;
    }

    // Step 3 — Read first 8 bytes and check magic signature
    const reader = new FileReader();
    reader.onload = function(e) {
        const bytes = new Uint8Array(e.target.result);
        const sig = MAGIC_SIGNATURES[ext];

        if (!sig) {
            return;
        }

        const matches = sig.bytes.every((b, i) => bytes[i + sig.offset] === b);

        if (!matches) {
            showFileError(
                input,
                errorDiv,
                'This file is not a real ' + ext.toUpperCase() + ' file. You cannot upload a renamed file (e.g. an .exe or .zip renamed to .' + ext + '). Please upload a genuine screenshot or photo.'
            );
            input.value = '';
        }
    };
    reader.readAsArrayBuffer(file.slice(0, 8));
}

function showFileError(input, errorDiv, message) {
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    input.style.borderColor = '#EF4444';
    input.value = '';
}

(function() {
    var cfg = window.SFBC_BOOK_CONFIG || {};

    // Edit Room Modal
    var editModal    = document.getElementById('editRoomModal');
    var openEditBtn  = document.getElementById('editRoomBtn');
    var closeEditBtn = document.getElementById('closeEditRoomModal');
    var applyEditBtn = document.getElementById('applyEditRoom');

    if (openEditBtn && editModal) {
        openEditBtn.addEventListener('click', function() {
            editModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
    }
    if (closeEditBtn && editModal) {
        closeEditBtn.addEventListener('click', function() {
            editModal.style.display = 'none';
            document.body.style.overflow = '';
        });
    }
    if (applyEditBtn) {
        applyEditBtn.addEventListener('click', function() {
            var adults      = parseInt(document.getElementById('editAdults').value, 10) || 1;
            var roomTypeEl  = document.getElementById('editRoomType');
            var roomType    = roomTypeEl ? roomTypeEl.value : (cfg.roomType || 'standard_room');
            var includedCap = (['beach_villa', 'standard_king'].indexOf(roomType) !== -1) ? 4 : 2;
            var baseGuests  = Math.min(adults, includedCap);
            var extraAdults = Math.max(0, adults - includedCap);
            var params      = new URLSearchParams(window.location.search);
            params.set('step', '1');
            params.set('guests', baseGuests);
            params.set('extra_adults', extraAdults);
            params.set('checkin', cfg.checkin || '');
            params.set('checkout', cfg.checkout || '');
            params.set('room_type', roomType);
            window.location.href = 'book?' + params.toString();
        });
    }

    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                editModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }

    // Availability Calendar Modal
    var calModal     = document.getElementById('availabilityCalendarModal');
    var openCalBtn   = document.getElementById('openAvailabilityCalBtn');
    var closeCalBtn  = document.getElementById('closeAvailCalModal');
    var prevMonthBtn = document.getElementById('book-cal-prev');
    var nextMonthBtn = document.getElementById('book-cal-next');
    var applyCalBtn  = document.getElementById('book-cal-apply-btn');

    var currentYear      = Number(cfg.checkinYear) || new Date().getFullYear();
    var currentMonth     = Number(cfg.checkinMonth) || (new Date().getMonth() + 1);
    var activeRoomType   = cfg.roomType || 'standard_room';
    var selectedCheckIn  = cfg.checkin || '';
    var selectedCheckOut = cfg.checkout || '';
    var pickState        = 0;

    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    function openCal() {
        if (!calModal) return;
        calModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        loadCalData(currentYear, currentMonth);
    }
    function closeCal() {
        if (!calModal) return;
        calModal.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (openCalBtn)  openCalBtn.addEventListener('click', openCal);
    if (closeCalBtn) closeCalBtn.addEventListener('click', closeCal);
    if (calModal) {
        calModal.addEventListener('click', function(e) {
            if (e.target === calModal) closeCal();
        });
    }

    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 1) { currentMonth = 12; currentYear--; }
            loadCalData(currentYear, currentMonth);
        });
    }
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 12) { currentMonth = 1; currentYear++; }
            loadCalData(currentYear, currentMonth);
        });
    }

    function updateSelectionText() {
        var textEl = document.getElementById('book-cal-selection-text');
        if (!textEl) return;
        if (selectedCheckIn && selectedCheckOut) {
            var d1 = new Date(selectedCheckIn);
            var d2 = new Date(selectedCheckOut);
            var diffNights = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
            textEl.textContent = selectedCheckIn + ' \u2192 ' + selectedCheckOut + ' (' + diffNights + ' night' + (diffNights > 1 ? 's' : '') + ')';
        } else if (selectedCheckIn) {
            textEl.textContent = selectedCheckIn + ' \u2192 Select check-out date';
        }
    }

    function loadCalData(year, month) {
        var titleEl = document.getElementById('book-cal-month-title');
        var grid = document.getElementById('book-cal-grid');
        if (!titleEl || !grid) return;

        titleEl.textContent = monthNames[month - 1] + ' ' + year;
        grid.innerHTML = '<div style="grid-column:span 7; text-align:center; padding:25px; color:#9CA3AF; font-size:13px;">Checking room availability...</div>';

        var applicationPath = cfg.applicationPath || '';
        var availabilityUrl = applicationPath + '/backend/api/availability.php?action=get_month_matrix&year=' + year + '&month=' + month + '&room_type=' + encodeURIComponent(activeRoomType);

        fetch(availabilityUrl, { credentials: 'same-origin' })
            .then(function(res) {
                if (!res.ok) throw new Error('Availability API returned HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data || !data.success) {
                    grid.innerHTML = '<div style="grid-column:span 7; text-align:center; color:#EF4444; font-size:13px;">Unable to load matrix.</div>';
                    return;
                }

                grid.innerHTML = '';
                var firstDayIndex = new Date(year, month - 1, 1).getDay();
                for (var pad = 0; pad < firstDayIndex; pad++) {
                    var empty = document.createElement('div');
                    empty.style.cssText = 'height:48px;';
                    grid.appendChild(empty);
                }

                Object.keys(data.days).forEach(function(dateKey) {
                    var item = data.days[dateKey];
                    var cell = document.createElement('div');

                    var isSelected = (dateKey === selectedCheckIn || dateKey === selectedCheckOut);
                    var isInRange = (selectedCheckIn && selectedCheckOut && dateKey > selectedCheckIn && dateKey < selectedCheckOut);

                    var bg = '#ECFDF5';
                    var border = '#A7F3D0';
                    var textColor = '#065F46';
                    var badgeText = item.available + ' left';

                    if (item.is_past) {
                        bg = '#F8FAFC'; border = '#E2E8F0'; textColor = '#94A3B8'; badgeText = 'Past';
                    } else if (item.status === 'sold_out') {
                        bg = '#FEF2F2'; border = '#FECACA'; textColor = '#991B1B'; badgeText = 'Sold Out';
                    } else if (item.status === 'low_stock') {
                        bg = '#FFFBEB'; border = '#FDE68A'; textColor = '#92400E'; badgeText = item.available + ' left';
                    }

                    if (isSelected) {
                        bg = '#84563C'; border = '#84563C'; textColor = '#FFFFFF';
                    } else if (isInRange) {
                        bg = '#F5EBE6'; border = '#D7C4B7'; textColor = '#84563C';
                    }

                    cell.style.cssText = 'height:48px; background:' + bg + '; border:1px solid ' + border + '; border-radius:8px; padding:3px; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:12px; cursor:pointer; transition:all 0.15s; user-select:none;';
                    cell.innerHTML = '<strong style="color:' + (isSelected ? '#FFF' : textColor) + '; font-size:13px;">' + item.day + '</strong>' +
                                     '<span style="font-size:9px; font-weight:700; color:' + (isSelected ? '#FFF' : textColor) + ';">' + badgeText + '</span>';

                    if (!item.is_past && item.status !== 'sold_out') {
                        cell.onmouseover = function() { if (!isSelected) this.style.transform = 'scale(1.06)'; };
                        cell.onmouseout = function() { if (!isSelected) this.style.transform = 'none'; };
                        cell.onclick = function() {
                            if (pickState === 0) {
                                selectedCheckIn = dateKey;
                                selectedCheckOut = '';
                                pickState = 1;
                            } else {
                                if (dateKey <= selectedCheckIn) {
                                    selectedCheckIn = dateKey;
                                    selectedCheckOut = '';
                                    pickState = 1;
                                } else {
                                    selectedCheckOut = dateKey;
                                    pickState = 0;
                                }
                            }
                            updateSelectionText();
                            loadCalData(year, month);
                        };
                    }

                    grid.appendChild(cell);
                });
            })
            .catch(function() {
                grid.innerHTML = '<div style="grid-column:span 7; text-align:center; color:#EF4444; font-size:13px;">Failed to fetch availability.</div>';
            });
    }

    if (applyCalBtn) {
        applyCalBtn.addEventListener('click', function() {
            if (!selectedCheckIn || !selectedCheckOut) {
                SiteDialog.alert('Please select both Check-in and Check-out dates.');
                return;
            }
            var params = new URLSearchParams(window.location.search);
            params.set('step', '1');
            params.set('checkin', selectedCheckIn);
            params.set('checkout', selectedCheckOut);
            params.set('room_type', activeRoomType);
            params.set('guests', String(cfg.guests || 2));
            window.location.href = 'book?' + params.toString();
        });
    }

    // Country change auto-syncs international calling code
    var countrySelect   = document.querySelector('select[name="country"]');
    var phoneCodeSelect = document.querySelector('select[name="phone_code"]');
    var phoneInput      = document.querySelector('input[name="phone"]');

    if (countrySelect && phoneCodeSelect) {
        countrySelect.addEventListener('change', function() {
            var opt = countrySelect.options[countrySelect.selectedIndex];
            if (!opt || !opt.value) return;
            var dialCode = opt.getAttribute('data-dial-code');
            var flag = opt.getAttribute('data-flag') || '';
            if (!dialCode) return;

            var found = false;
            for (var i = 0; i < phoneCodeSelect.options.length; i++) {
                if (phoneCodeSelect.options[i].value === dialCode) {
                    phoneCodeSelect.selectedIndex = i;
                    if (flag) {
                        phoneCodeSelect.options[i].textContent = flag + ' ' + dialCode;
                    }
                    found = true;
                    break;
                }
            }
            if (!found) {
                var newOpt = document.createElement('option');
                newOpt.value = dialCode;
                newOpt.textContent = (flag ? flag + ' ' : '') + dialCode;
                phoneCodeSelect.appendChild(newOpt);
                phoneCodeSelect.value = dialCode;
            }

            if (phoneInput) {
                if (dialCode === '+63') {
                    phoneInput.placeholder = '09171234567';
                } else {
                    phoneInput.placeholder = 'e.g. 12345678';
                }
            }

            phoneCodeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    document.querySelectorAll('.bk-qr-img').forEach(function(img) {
        img.addEventListener('error', function() {
            var panel = img.closest('.ticket-qr-panel');
            if (panel) panel.classList.add('is-missing');
        });
    });

    document.addEventListener('click', function(e) {
        var copyBtn = e.target.closest('.bk-copy-cancel-btn[data-copy-url]');
        if (!copyBtn) return;
        var url = copyBtn.getAttribute('data-copy-url') || '';
        if (!url) return;

        var setCopiedState = function() {
            copyBtn.textContent = 'Copied!';
            copyBtn.classList.add('is-copied');
            window.setTimeout(function() {
                copyBtn.textContent = 'Copy Link';
                copyBtn.classList.remove('is-copied');
            }, 2200);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(setCopiedState).catch(function() {
                window.prompt('Copy this cancellation link:', url);
            });
            return;
        }
        window.prompt('Copy this cancellation link:', url);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (editModal && editModal.style.display === 'flex') {
                editModal.style.display = 'none';
                document.body.style.overflow = '';
            }
            if (calModal && calModal.style.display === 'flex') closeCal();
        }
    });
})();
