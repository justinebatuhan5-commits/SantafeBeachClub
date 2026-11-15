(function() {
    var config = window.SFBC_ROOMS_CONFIG || {};
    var extraPersonLimits = config.extraPersonLimits || {};

    window.hotelData = config.hotelData || {
        checkin: '',
        checkout: '',
        nights: 1,
        guests: 1,
        rooms: [],
        bookings: []
    };

    var selectedRooms = {};
    var extraPersonsByType = {};
    var dateStripOffsets = {};
    var currentCalType = '';
    var currentCalLabel = '';
    var currentCalPrice = 0;
    var currentCalYear = new Date().getFullYear();
    var currentCalMonth = new Date().getMonth();

    function initDateRangeSync() {
        var checkin = document.getElementById('be-checkin');
        var checkout = document.getElementById('be-checkout');
        if (!checkin || !checkout) return;
        checkin.addEventListener('change', function() {
            var next = new Date(checkin.value);
            next.setDate(next.getDate() + 1);
            var minStr = next.toISOString().split('T')[0];
            checkout.min = minStr;
            if (checkout.value < minStr) {
                checkout.value = minStr;
            }
        });
    }

    /* Tab switching */
    function switchTab(tab, btn) {
        document.querySelectorAll('.room-tab').forEach(function(b) {
            b.classList.remove('room-tab--active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.setAttribute('aria-pressed', 'true');
        btn.classList.add('room-tab--active');

        var target = document.getElementById('tab-' + tab);
        if (!target) return;

        var header = document.querySelector('.site-navbar, .main-header');
        var headerHeight = header ? Math.ceil(header.getBoundingClientRect().bottom) : 0;
        var topOffset = headerHeight + 24;
        var targetTop = window.scrollY + target.getBoundingClientRect().top - topOffset;

        window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
    }

    function syncRoomTabsOffset() {
        var header = document.querySelector('.site-navbar, .main-header');
        if (!header) return;
        var headerBottom = Math.ceil(header.getBoundingClientRect().bottom);
        document.documentElement.style.setProperty('--rooms-tabs-top', (headerBottom + 10) + 'px');
        document.documentElement.style.setProperty('--rooms-sticky-tabs-top', (headerBottom + 10) + 'px');
        document.documentElement.style.setProperty('--rooms-summary-top', (headerBottom + 24) + 'px');
    }

    function roomSelectChanged(sel) {
        var roomId = sel.value;
        var baseUrl = sel.dataset.baseBookUrl || sel.dataset.bookUrl;
        if (roomId) {
            sel.dataset.bookUrl = baseUrl + '&room_id=' + encodeURIComponent(roomId);
        } else {
            sel.dataset.bookUrl = baseUrl;
        }
        updateSummary();
    }

    function updateSummary() {
        selectedRooms = {};
        document.querySelectorAll('.rate-room-select').forEach(function(sel) {
            var qty = sel.value ? 1 : 0;
            if (qty > 0) {
                var key = sel.dataset.type;
                var selectedOpt = sel.options[sel.selectedIndex];
                var roomNum = selectedOpt ? (selectedOpt.dataset.roomNumber || '') : '';
                var baseLabel = sel.dataset.label || '';
                selectedRooms[key] = {
                    typeName: baseLabel,
                    roomNum: roomNum,
                    label: baseLabel + (roomNum ? ' \u00B7 Room ' + roomNum : ''),
                    price: parseFloat(sel.dataset.price),
                    extraPrice: parseFloat(sel.dataset.extraPrice || '0'),
                    nights: parseInt(sel.dataset.nights, 10) || 1,
                    qty: 1,
                    bookUrl: sel.dataset.bookUrl
                };
            }
        });
        syncExtraPersonsPanels();
        renderSummary();
    }

    function syncExtraPersonsPanels() {
        Object.keys(extraPersonsByType).forEach(function(type) {
            var panel = document.getElementById('extra-panel-' + type);
            if (panel && panel.classList.contains('rate-extra-panel--open')) {
                renderExtraPersonsPanel(type);
            } else {
                updateExtraPersonsSummary(type);
            }
        });
    }

    function escapeRoomText(value) {
        var span = document.createElement('span');
        span.textContent = value == null ? '' : String(value);
        return span.innerHTML;
    }

    function renderSummary() {
        var placeholder = document.getElementById('bs-placeholder');
        var selectedDiv = document.getElementById('bs-selected-rooms');
        var totalEl = document.getElementById('bs-total');
        var depositPill = document.getElementById('bs-deposit-pill');
        var depositAmtEl = document.getElementById('bs-deposit-amount');
        var nightsMetaEl = document.getElementById('bs-nights');
        var bookBtn = document.getElementById('btn-book-now');
        var clearLink = document.getElementById('bs-clear-link');

        var keys = Object.keys(selectedRooms);
        var grandTotal = 0;
        var baseNights = nightsMetaEl ? (parseInt(nightsMetaEl.dataset.baseNights, 10) || 1) : 1;

        if (keys.length === 0) {
            if (placeholder) placeholder.style.display = 'block';
            if (selectedDiv) selectedDiv.style.display = 'none';
            if (totalEl) totalEl.innerHTML = '&#8369; 0.00';
            if (depositPill) depositPill.style.display = 'none';
            if (nightsMetaEl) nightsMetaEl.textContent = baseNights + ' night' + (baseNights > 1 ? 's' : '');
            if (bookBtn) bookBtn.disabled = true;
            if (clearLink) clearLink.style.visibility = 'hidden';
            var mobileBar = document.getElementById('mobile-book-bar');
            if (mobileBar) mobileBar.classList.remove('mobile-book-bar--active');
            document.body.classList.remove('has-mobile-bar');
            return;
        }

        clearLink.style.visibility = 'visible';
        placeholder.style.display = 'none';
        var totalRooms = keys.reduce(function(sum, k) { return sum + (Number(selectedRooms[k].qty) || 0); }, 0);
        var activeNights = (selectedRooms[keys[0]] && Number(selectedRooms[keys[0]].nights)) ? Number(selectedRooms[keys[0]].nights) : baseNights;
        if (nightsMetaEl) {
            nightsMetaEl.textContent = activeNights + ' night' + (activeNights > 1 ? 's' : '') + ' \u00B7 ' + totalRooms + ' room' + (totalRooms !== 1 ? 's' : '');
        }
        selectedDiv.style.display = 'flex';
        selectedDiv.innerHTML = '';

        keys.forEach(function(key) {
            var r = selectedRooms[key];
            var personList = extraPersonsByType[key] || [];
            var totalAdults = personList.reduce(function(sum, v) { return sum + (parseInt(v.adults, 10) || 0); }, 0);
            var totalChildren = personList.reduce(function(sum, v) { return sum + (parseInt(v.children, 10) || 0); }, 0);

            var roomSubtotal = r.price * r.nights * r.qty;
            var extraSubtotal = totalAdults * r.extraPrice;
            var subtotal = roomSubtotal + extraSubtotal;
            grandTotal += subtotal;

            var el = document.createElement('div');
            el.className = 'bs-room-line';
            var roomTitle = r.typeName || r.label || '';
            var nightsCount = Number(r.nights) || baseNights;
            var metaParts = [];
            if (r.roomNum) metaParts.push(/^Room\b/i.test(String(r.roomNum)) ? String(r.roomNum) : ('Room ' + r.roomNum));
            metaParts.push(nightsCount + ' night' + (nightsCount > 1 ? 's' : ''));
            el.innerHTML =
                '<div class="bs-room-main">' +
                    '<span class="bs-room-name"><span class="bs-room-qty">' + Number(r.qty) + '&times;</span> ' + escapeRoomText(roomTitle) + '</span>' +
                    '<span class="bs-room-unit">' + escapeRoomText(metaParts.join(' \u00B7 ')) + '</span>' +
                '</div>' +
                '<span class="bs-room-subtotal">&#8369; ' + roomSubtotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';
            selectedDiv.appendChild(el);

            if (extraSubtotal > 0) {
                var extraEl = document.createElement('div');
                extraEl.className = 'bs-room-line bs-room-line--extra';
                extraEl.innerHTML =
                    '<div class="bs-room-main"><span class="bs-room-name">Extra adults (' + totalAdults + ')</span></div>' +
                    '<span class="bs-room-subtotal">+&#8369; ' + extraSubtotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';
                selectedDiv.appendChild(extraEl);
            }
            if (totalChildren > 0) {
                var childEl = document.createElement('div');
                childEl.className = 'bs-room-line bs-room-line--extra';
                childEl.innerHTML =
                    '<div class="bs-room-main"><span class="bs-room-name">Children (' + totalChildren + ')</span></div>' +
                    '<span class="bs-room-subtotal">Free</span>';
                selectedDiv.appendChild(childEl);
            }
        });

        totalEl.innerHTML = '&#8369; ' + grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (depositAmtEl) {
            depositAmtEl.innerHTML = '&#8369; ' + (grandTotal * 0.5).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (depositPill) {
            depositPill.style.display = 'flex';
        }
        bookBtn.disabled = false;

        var activeMobileBar = document.getElementById('mobile-book-bar');
        var mobileCount = document.getElementById('mobile-bar-count');
        var mobileTotal = document.getElementById('mobile-bar-total');
        if (activeMobileBar) {
            var mobileRoomCount = keys.reduce(function(sum, k) { return sum + selectedRooms[k].qty; }, 0);
            activeMobileBar.classList.add('mobile-book-bar--active');
            document.body.classList.add('has-mobile-bar');
            mobileCount.textContent = mobileRoomCount + ' room' + (mobileRoomCount > 1 ? 's' : '') + ' selected';
            mobileTotal.innerHTML = '&#8369; ' + grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    function clearSelection(e) {
        e.preventDefault();
        document.querySelectorAll('.rate-room-select').forEach(function(sel) { sel.value = ''; });
        selectedRooms = {};
        extraPersonsByType = {};
        document.querySelectorAll('.rate-extra-status').forEach(function(el) {
            el.style.display = 'none';
            el.textContent = '';
        });
        document.querySelectorAll('.rate-extra-persons-label').forEach(function(label) {
            label.textContent = 'add extra persons (0)';
        });
        document.querySelectorAll('.rate-extra-panel').forEach(function(panel) {
            panel.classList.remove('rate-extra-panel--open');
        });
        document.querySelectorAll('.rate-extra-persons').forEach(function(btn) {
            btn.classList.remove('rate-extra-persons--open');
        });
        renderSummary();
    }

    function proceedToBook() {
        var keys = Object.keys(selectedRooms);
        if (keys.length === 0) return;
        var targetUrl = selectedRooms[keys[0]].bookUrl;
        var totalAdults = 0;
        var totalChildren = 0;
        keys.forEach(function(k) {
            var personList = extraPersonsByType[k] || [];
            personList.forEach(function(v) {
                totalAdults += (parseInt(v.adults, 10) || 0);
                totalChildren += (parseInt(v.children, 10) || 0);
            });
        });
        targetUrl += '&extra_adults=' + encodeURIComponent(totalAdults);
        targetUrl += '&extra_children=' + encodeURIComponent(totalChildren);
        window.location.href = targetUrl;
    }

    function toggleExtraPersons(btn) {
        var type = btn.getAttribute('data-type');
        var panel = document.getElementById('extra-panel-' + type);
        var isOpen = panel.classList.contains('rate-extra-panel--open');

        var rateCard = btn.closest('.rate-card');
        if (rateCard && panel.parentElement !== rateCard) {
            rateCard.appendChild(panel);
        }

        document.querySelectorAll('.rate-extra-panel').forEach(function(item) {
            item.classList.remove('rate-extra-panel--open');
        });
        document.querySelectorAll('.rate-extra-persons').forEach(function(item) {
            item.classList.remove('rate-extra-persons--open');
        });

        if (!isOpen) {
            renderExtraPersonsPanel(type);
            panel.classList.add('rate-extra-panel--open');
            btn.classList.add('rate-extra-persons--open');
        }
    }

    function getSelectedRoomQty() {
        return 1;
    }

    function renderExtraPersonsPanel(type) {
        var qty = getSelectedRoomQty(type);
        var rowsWrap = document.getElementById('extra-rows-' + type);
        var limits = extraPersonLimits[type] || { adults: 0, children: 0 };
        var values = extraPersonsByType[type] || [];
        values = values.slice(0, qty);
        while (values.length < qty) {
            values.push({ adults: 0, children: 0 });
        }
        values.forEach(function(item) {
            item.adults = Math.min(limits.adults, Math.max(0, parseInt(item.adults, 10) || 0));
            item.children = Math.min(limits.children, Math.max(0, parseInt(item.children, 10) || 0));
        });
        extraPersonsByType[type] = values;

        rowsWrap.innerHTML = '';
        values.forEach(function(item, index) {
            var row = document.createElement('div');
            row.className = 'rate-extra-row';

            var label = document.createElement('div');
            label.className = 'rate-extra-room-label';
            label.textContent = 'Room ' + (index + 1);

            var adultWrap = document.createElement('div');
            adultWrap.className = 'rate-extra-select-wrap';
            var adultSelect = document.createElement('select');
            adultSelect.className = 'rate-extra-select';
            Array.from({ length: limits.adults + 1 }, function(_, count) { return count; }).forEach(function(count) {
                var opt = document.createElement('option');
                opt.value = count;
                opt.textContent = count + ' adult' + (count !== 1 ? 's' : '');
                if (count === (item.adults || 0)) opt.selected = true;
                adultSelect.appendChild(opt);
            });
            adultSelect.onchange = function() {
                updateExtraPersonCount(type, index, 'adults', this.value);
            };
            var chevron1 = document.createElement('span');
            chevron1.className = 'rate-extra-select-chevron';
            chevron1.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
            adultWrap.appendChild(adultSelect);
            adultWrap.appendChild(chevron1);

            var childWrap = document.createElement('div');
            childWrap.className = 'rate-extra-select-wrap';
            var childSelect = document.createElement('select');
            childSelect.className = 'rate-extra-select';
            Array.from({ length: limits.children + 1 }, function(_, count) { return count; }).forEach(function(count) {
                var opt = document.createElement('option');
                opt.value = count;
                opt.textContent = count + ' child' + (count === 1 ? '' : 'ren');
                if (count === (item.children || 0)) opt.selected = true;
                childSelect.appendChild(opt);
            });
            childSelect.onchange = function() {
                updateExtraPersonCount(type, index, 'children', this.value);
            };
            var chevron2 = document.createElement('span');
            chevron2.className = 'rate-extra-select-chevron';
            chevron2.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
            childWrap.appendChild(childSelect);
            childWrap.appendChild(chevron2);

            row.appendChild(label);
            row.appendChild(adultWrap);
            if (limits.children > 0) row.appendChild(childWrap);
            rowsWrap.appendChild(row);
        });

        updateExtraPersonsSummary(type);
    }

    function updateExtraPersonCount(type, index, field, value) {
        var values = extraPersonsByType[type] || [];
        if (!values[index]) values[index] = { adults: 0, children: 0 };
        values[index][field] = parseInt(value, 10) || 0;
        extraPersonsByType[type] = values;
        updateExtraPersonsSummary(type);
    }

    function updateExtraPersonsSummary(type) {
        var values = extraPersonsByType[type] || [];
        var totalAdults = values.reduce(function(sum, v) { return sum + (parseInt(v.adults, 10) || 0); }, 0);
        var totalChildren = values.reduce(function(sum, v) { return sum + (parseInt(v.children, 10) || 0); }, 0);
        var totalPersons = totalAdults + totalChildren;

        var totalEl = document.getElementById('extra-total-' + type);
        var breakdownEl = document.getElementById('extra-breakdown-' + type);
        var statusEl = document.getElementById('extra-status-' + type);
        var btn = document.querySelector('.rate-extra-persons[data-type="' + type + '"]');
        var roomSelect = document.querySelector('.rate-room-select[data-type="' + type + '"]');
        var extraRate = roomSelect ? (parseFloat(roomSelect.getAttribute('data-extra-price') || '0')) : 0;
        var extraCharge = totalAdults * extraRate;

        if (breakdownEl) {
            breakdownEl.innerHTML = '';
            if (totalPersons > 0) {
                values.forEach(function(v, index) {
                    var adultCost = (v.adults || 0) * extraRate;
                    var bRow = document.createElement('div');
                    bRow.className = 'rate-extra-breakdown-row';
                    bRow.innerHTML =
                        '<div class="rate-extra-breakdown-col">' + (index + 1) + '</div>' +
                        '<div class="rate-extra-breakdown-col">' + (adultCost > 0 ? ('\u20B1 ' + adultCost.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '-') + '</div>' +
                        '<div class="rate-extra-breakdown-col">-</div>';
                    breakdownEl.appendChild(bRow);
                });
            }
        }

        if (totalEl) {
            totalEl.textContent = extraCharge > 0
                ? ('\u20B1 ' + extraCharge.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
                : (totalPersons > 0 ? 'Free / Included' : '-');
        }

        if (btn) {
            var label = btn.querySelector('.rate-extra-persons-label');
            if (label) {
                label.textContent = 'add extra persons (' + totalPersons + ')';
            }
        }

        if (statusEl) {
            var parts = [];
            if (totalAdults > 0) parts.push(totalAdults + ' adult' + (totalAdults > 1 ? 's' : ''));
            if (totalChildren > 0) parts.push(totalChildren + ' child' + (totalChildren === 1 ? '' : 'ren'));
            if (parts.length > 0) {
                statusEl.textContent = parts.join(' and ') + ' has been added.';
                statusEl.style.display = 'block';
            } else {
                statusEl.textContent = '';
                statusEl.style.display = 'none';
            }
        }

        renderSummary();
    }

    function setRoomModalImage(src, alt, index, total, thumbEl) {
        var mainImage = document.getElementById('room-modal-main-image');
        var counter = document.getElementById('room-modal-counter');
        mainImage.src = src;
        mainImage.alt = alt;
        counter.textContent = (index + 1) + '/' + total;

        document.querySelectorAll('.room-modal-thumb').forEach(function(thumb) {
            thumb.classList.remove('room-modal-thumb--active');
        });
        if (thumbEl) {
            thumbEl.classList.add('room-modal-thumb--active');
        }
    }

    function openRoomModal(btn) {
        var modal = document.getElementById('room-modal');
        var name = btn.getAttribute('data-room-name') || '';
        var bed = btn.getAttribute('data-room-bed') || '';
        var desc = btn.getAttribute('data-room-desc') || '';
        var photos = [];
        var amenities = [];

        try {
            photos = JSON.parse(btn.getAttribute('data-room-photos') || '[]');
        } catch (e) {
            photos = [];
        }
        try {
            amenities = JSON.parse(btn.getAttribute('data-room-amenities') || '[]');
        } catch (e) {
            amenities = [];
        }

        document.getElementById('room-modal-title').textContent = name.toUpperCase();
        document.getElementById('room-modal-bed').textContent = bed;
        document.getElementById('room-modal-description').textContent = desc;
        var amenitiesWrap = document.getElementById('room-modal-amenities-wrap');
        var amenitiesEl = document.getElementById('room-modal-amenities');
        amenitiesEl.innerHTML = '';
        if (amenities.length) {
            amenities.forEach(function(item) {
                var amenity = document.createElement('div');
                amenity.className = 'room-modal-amenity';
                amenity.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span></span>';
                amenity.querySelector('span').textContent = item;
                amenitiesEl.appendChild(amenity);
            });
            amenitiesWrap.style.display = '';
        } else {
            amenitiesWrap.style.display = 'none';
        }

        var thumbsWrap = document.getElementById('room-modal-thumbs');
        thumbsWrap.innerHTML = '';
        if (!photos.length) {
            photos = ['assets/hero-slide-4-hd.png'];
        }

        photos.forEach(function(src, index) {
            var thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'room-modal-thumb' + (index === 0 ? ' room-modal-thumb--active' : '');
            var image = document.createElement('img');
            image.src = src;
            image.alt = name + ' thumbnail ' + (index + 1);
            thumb.appendChild(image);
            thumb.onclick = function() {
                setRoomModalImage(src, name, index, photos.length, thumb);
            };
            thumbsWrap.appendChild(thumb);
        });

        setRoomModalImage(photos[0], name, 0, photos.length, thumbsWrap.querySelector('.room-modal-thumb'));
        modal.classList.add('room-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('body-modal-open');
    }

    function closeRoomModal() {
        var modal = document.getElementById('room-modal');
        if (modal) {
            modal.classList.remove('room-modal--open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('body-modal-open');
    }

    /* Check availability for a room type on a specific date */
    function checkTypeAvailabilityOnDate(type, dateStr) {
        var matchingRooms = (window.hotelData.rooms || []).filter(function(r) {
            return r.type === type && r.status !== 'maintenance';
        });
        var matchingRoomIds = matchingRooms.map(function(r) { return String(r.id); });
        if (matchingRooms.length === 0) {
            return { available: false, count: 0, reason: 'Sold out' };
        }

        var overlappingBookings = (window.hotelData.bookings || []).filter(function(b) {
            return matchingRoomIds.indexOf(String(b.room_id)) !== -1
                && b.check_in <= dateStr
                && b.check_out >= dateStr;
        }).sort(function(a, b) {
            return String(a.check_out).localeCompare(String(b.check_out));
        });

        var availCount = 0;
        matchingRooms.forEach(function(r) {
            var isBooked = (window.hotelData.bookings || []).some(function(b) {
                var roomName = (r.type || '').toLowerCase();
                var bookingName = (b.accommodation_name || '').toLowerCase();
                var normalizedRoomName = roomName.replace(/_/g, ' ');
                var directMatch = b.room_id == r.id && b.check_in <= dateStr && b.check_out >= dateStr;
                var legacyTypeMatch = (!directMatch)
                    && b.check_in <= dateStr
                    && b.check_out >= dateStr
                    && (
                        (typeof b.room_type_id !== 'undefined' && b.room_type_id !== null && String(b.room_type_id) === String(r.id))
                        || bookingName.indexOf(normalizedRoomName) !== -1
                        || bookingName.indexOf(roomName) !== -1
                    );
                return directMatch || legacyTypeMatch;
            });
            if (!isBooked) availCount++;
        });

        return {
            available: availCount > 0,
            count: availCount,
            reason: availCount > 0 ? 'Available' : 'Sold out',
            bookedCheckin: overlappingBookings.length ? overlappingBookings[0].check_in : null,
            bookedCheckout: overlappingBookings.length ? overlappingBookings[0].check_out : null
        };
    }

    function formatDateISO(d) {
        var year = d.getFullYear();
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function formatShortStayDate(isoDate) {
        var d = new Date(isoDate + 'T00:00:00');
        if (isNaN(d.getTime())) return isoDate;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function renderDateStrip(type) {
        var stripEl = document.getElementById('date-strip-' + type);
        if (!stripEl) return;

        var price = parseFloat(stripEl.getAttribute('data-price') || '0');
        var offset = dateStripOffsets[type] || 0;

        var baseDate = new Date(window.hotelData.checkin + 'T00:00:00');
        if (isNaN(baseDate.getTime())) baseDate = new Date();

        stripEl.innerHTML = '';
        var daysToShow = 4;

        for (var i = 0; i < daysToShow; i++) {
            var d = new Date(baseDate);
            d.setDate(d.getDate() + offset + i);
            var iso = formatDateISO(d);
            var avail = checkTypeAvailabilityOnDate(type, iso);

            var dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
            var dayNum = String(d.getDate()).padStart(2, '0');
            var monthName = d.toLocaleDateString('en-US', { month: 'short' });
            var dateLabel = dayName + ' ' + dayNum + ' ' + monthName;

            var isCurrentCheckin = (iso === window.hotelData.checkin);

            var item = document.createElement('div');
            item.className = 'date-strip-item' +
                (!avail.available ? ' date-strip-item--soldout' : '') +
                (isCurrentCheckin ? ' date-strip-item--active' : '');

            var html = '<span class="ds-date">' + dateLabel + '</span>' +
                '<span class="ds-price">&#8369; ' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';

            if (!avail.available) {
                html += '<span class="ds-badge-sold">' + escapeRoomText(avail.reason) + '</span>';
                if (avail.bookedCheckin && avail.bookedCheckout) {
                    html += '<span class="ds-booked-range">' +
                        formatShortStayDate(avail.bookedCheckin) +
                        ' to ' +
                        formatShortStayDate(avail.bookedCheckout) +
                        '</span>';
                }
            }

            item.innerHTML = html;

            if (avail.available) {
                (function(targetDate) {
                    item.onclick = function() {
                        selectDateAndReload(targetDate, type);
                    };
                })(iso);
            }

            stripEl.appendChild(item);
        }
    }

    function shiftDateStrip(type, diff) {
        dateStripOffsets[type] = (dateStripOffsets[type] || 0) + diff;
        renderDateStrip(type);
    }

    function selectDateAndReload(newCheckin, roomType) {
        var nights = window.hotelData.nights || 1;
        var d = new Date(newCheckin + 'T00:00:00');
        d.setDate(d.getDate() + nights);
        var newCheckout = formatDateISO(d);

        var params = new URLSearchParams(window.location.search);
        params.set('checkin', newCheckin);
        params.set('checkout', newCheckout);
        if (roomType) params.set('room_type', roomType);

        window.location.search = params.toString();
    }

    function openAvailabilityCalendar(type, label, price) {
        currentCalType = type;
        currentCalLabel = label;
        currentCalPrice = price;

        var baseDate = new Date(window.hotelData.checkin + 'T00:00:00');
        if (isNaN(baseDate.getTime())) baseDate = new Date();
        currentCalYear = baseDate.getFullYear();
        currentCalMonth = baseDate.getMonth();

        document.getElementById('avail-modal-title').textContent = label;
        document.getElementById('avail-modal-price').textContent = 'From \u20B1 ' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' / night';

        renderCalendarDays();

        var modal = document.getElementById('avail-modal');
        modal.classList.add('avail-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('body-modal-open');
    }

    function closeAvailabilityCalendar() {
        var modal = document.getElementById('avail-modal');
        if (modal) {
            modal.classList.remove('avail-modal--open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('body-modal-open');
    }

    function changeCalMonth(diff) {
        currentCalMonth += diff;
        if (currentCalMonth > 11) {
            currentCalMonth = 0;
            currentCalYear++;
        } else if (currentCalMonth < 0) {
            currentCalMonth = 11;
            currentCalYear--;
        }
        renderCalendarDays();
    }

    function renderCalendarDays() {
        var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('cal-current-month').textContent = monthNames[currentCalMonth] + ' ' + currentCalYear;

        var daysContainer = document.getElementById('avail-modal-days');
        daysContainer.innerHTML = '';

        var firstDayIndex = new Date(currentCalYear, currentCalMonth, 1).getDay();
        var daysInMonth = new Date(currentCalYear, currentCalMonth + 1, 0).getDate();

        var todayStr = formatDateISO(new Date());

        for (var e = 0; e < firstDayIndex; e++) {
            var emptyCell = document.createElement('div');
            emptyCell.className = 'cal-day-cell cal-day-cell--empty';
            daysContainer.appendChild(emptyCell);
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var dateObj = new Date(currentCalYear, currentCalMonth, day);
            var iso = formatDateISO(dateObj);
            var isPast = iso < todayStr;
            var isToday = iso === todayStr;

            var cell = document.createElement('div');
            cell.className = 'cal-day-cell';

            if (isPast) {
                cell.classList.add('cal-day-cell--past');
                cell.innerHTML = '<span class="cal-day-num">' + day + '</span>';
            } else {
                var avail = checkTypeAvailabilityOnDate(currentCalType, iso);
                if (isToday) cell.classList.add('cal-day-cell--today');

                if (avail.available) {
                    cell.classList.add('cal-day-cell--avail');
                    cell.innerHTML =
                        '<span class="cal-day-num">' + day + '</span>' +
                        '<span class="cal-day-price">&#8369; ' + Math.round(currentCalPrice).toLocaleString('en-PH') + '</span>' +
                        '<span class="cal-day-tag cal-day-tag--avail">Avail</span>';

                    (function(targetDate) {
                        cell.onclick = function() {
                            selectDateAndReload(targetDate, currentCalType);
                        };
                    })(iso);
                } else {
                    cell.classList.add('cal-day-cell--sold');
                    var soldRange = '';
                    if (avail.bookedCheckin && avail.bookedCheckout) {
                        soldRange = '<span class="cal-day-booked">' +
                            formatShortStayDate(avail.bookedCheckin) +
                            ' to ' +
                            formatShortStayDate(avail.bookedCheckout) +
                            '</span>';
                    }
                    cell.innerHTML =
                        '<span class="cal-day-num">' + day + '</span>' +
                        '<span class="cal-day-price" style="color:#A39B94;">&#8369; ' + Math.round(currentCalPrice).toLocaleString('en-PH') + '</span>' +
                        '<span class="cal-day-tag cal-day-tag--sold">' + escapeRoomText(avail.reason) + '</span>' +
                        soldRange;
                }
            }

            daysContainer.appendChild(cell);
        }
    }

    // Expose functions used by inline DOM event handlers
    window.switchTab = switchTab;
    window.roomSelectChanged = roomSelectChanged;
    window.clearSelection = clearSelection;
    window.proceedToBook = proceedToBook;
    window.toggleExtraPersons = toggleExtraPersons;
    window.openRoomModal = openRoomModal;
    window.closeRoomModal = closeRoomModal;
    window.shiftDateStrip = shiftDateStrip;
    window.openAvailabilityCalendar = openAvailabilityCalendar;
    window.closeAvailabilityCalendar = closeAvailabilityCalendar;
    window.changeCalMonth = changeCalMonth;

    document.addEventListener('DOMContentLoaded', function() {
        initDateRangeSync();
        syncRoomTabsOffset();
        var cl = document.getElementById('bs-clear-link');
        if (cl) cl.style.visibility = 'hidden';

        document.querySelectorAll('.rate-date-strip').forEach(function(strip) {
            var type = strip.getAttribute('data-type');
            if (type) renderDateStrip(type);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRoomModal();
                closeAvailabilityCalendar();
            }
        });

        window.addEventListener('resize', syncRoomTabsOffset);
        if (window.ResizeObserver) {
            var stickyObserver = new ResizeObserver(syncRoomTabsOffset);
            document.querySelectorAll('.site-navbar, .main-header, .room-tabs-bar').forEach(function(el) { stickyObserver.observe(el); });
        }
        if (document.fonts) document.fonts.ready.then(syncRoomTabsOffset);
    });
})();
