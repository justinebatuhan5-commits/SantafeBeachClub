/* Live Availability & Rates Calendar for rooms.php */
(function() {
    var cfg = window.SF_ROOMS_CAL_CONFIG || {};
    var initialCheckin = cfg.initialCheckin || '';
    var initialCheckout = cfg.initialCheckout || '';
    var todayIso = cfg.todayIso || '';
    var currentCalYear = Number(cfg.currentCalYear) || new Date().getFullYear();
    var currentCalMonth = Number(cfg.currentCalMonth) || (new Date().getMonth() + 1);
    var currentRoomType = cfg.currentRoomType || 'any';
    var selectedCheckin = initialCheckin;
    var selectedCheckout = initialCheckout;
    var pickPhase = 'idle';
    var hoverDate = '';
    var noticeText = '';
    var calendarLoaded = false;
    var lastIsRatePrefix = true;
    var dayCache = {};
    var loadedMonthData = null;

    var monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    var shortMonthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    var typeLabelsMap = {
        any: 'All Rooms',
        standard_room: 'Standard Room',
        standard_king: 'Standard Family',
        cottage: 'Beach Cottage',
        beachview_duplex: 'Beachview Duplex',
        seaview_duplex: 'Seaview Duplex',
        beach_villa: 'Beach Villa',
        villa: 'Luxury Villa'
    };

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, function(ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
        });
    }

    function formatDisplayDate(iso) {
        if (!iso) return 'Select date';
        var parts = iso.split('-');
        if (parts.length !== 3) return iso;
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(d.getTime())) return iso;
        return shortMonthNames[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    function formatShortDate(iso) {
        if (!iso) return '-';
        var parts = iso.split('-');
        if (parts.length !== 3) return iso;
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(d.getTime())) return iso;
        return shortMonthNames[d.getMonth()] + ' ' + d.getDate();
    }

    function addDaysIso(iso, delta) {
        var parts = iso.split('-');
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        d.setDate(d.getDate() + delta);
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function diffNights(cin, cout) {
        if (!cin || !cout || cout <= cin) return 0;
        var p1 = cin.split('-'), p2 = cout.split('-');
        var d1 = new Date(Number(p1[0]), Number(p1[1]) - 1, Number(p1[2]));
        var d2 = new Date(Number(p2[0]), Number(p2[1]) - 1, Number(p2[2]));
        return Math.max(0, Math.round((d2 - d1) / 86400000));
    }

    function getCachedDay(dateIso) {
        return dayCache[currentRoomType + '|' + dateIso] || null;
    }

    function hasSoldOutNightBetween(startIso, endIso) {
        var cursor = startIso;
        var guard = 0;
        while (cursor < endIso && guard < 180) {
            var info = getCachedDay(cursor);
            if (info && (info.status === 'sold_out' || info.available <= 0)) {
                return true;
            }
            cursor = addDaysIso(cursor, 1);
            guard++;
        }
        return false;
    }

    function estimateStayRateText(cin, cout) {
        var nights = diffNights(cin, cout);
        if (nights <= 0) return '';
        var sum = 0;
        var counted = 0;
        var cursor = cin;
        for (var i = 0; i < nights && i < 90; i++) {
            var info = getCachedDay(cursor);
            if (info && info.min_rate > 0) {
                sum += Number(info.min_rate);
                counted++;
            }
            cursor = addDaysIso(cursor, 1);
        }
        if (counted === nights && sum > 0) {
            var scopeLabel = typeLabelsMap[currentRoomType] || 'Rooms';
            var prefix = lastIsRatePrefix ? 'Est. from ' : 'Est. ';
            return prefix + '\u20B1' + Math.round(sum).toLocaleString('en-PH') + ' (' + scopeLabel + ')';
        }
        return 'Viewing rates for ' + (typeLabelsMap[currentRoomType] || 'All Rooms');
    }

    function updateHeaderPill() {
        var pill = document.getElementById('sf-cal-header-pill');
        if (!pill) return;
        var nights = diffNights(selectedCheckin, selectedCheckout);
        if (selectedCheckin && selectedCheckout && nights > 0) {
            pill.innerHTML = '<span class="sf-cal-active-dot" aria-hidden="true"></span><span>' +
                escHtml(formatShortDate(selectedCheckin) + ' - ' + formatShortDate(selectedCheckout)) +
                ' &middot; ' + nights + ' ' + (nights === 1 ? 'night' : 'nights') + '</span>';
        } else if (selectedCheckin) {
            pill.innerHTML = '<span class="sf-cal-active-dot" aria-hidden="true"></span><span>Check-in: ' +
                escHtml(formatShortDate(selectedCheckin)) + ' (pick check-out)</span>';
        }
    }

    function updateSummaryAndHint() {
        var hintEl = document.getElementById('sf-cal-pick-hint');
        var datesEl = document.getElementById('sf-cal-summary-dates');
        var nightsEl = document.getElementById('sf-cal-summary-nights');
        var estEl = document.getElementById('sf-cal-summary-est');
        var applyBtn = document.getElementById('sf-cal-apply-btn');

        if (hintEl) {
            if (noticeText) {
                hintEl.className = 'sf-cal-pick-hint is-picking';
                hintEl.innerHTML = '<span class="sf-cal-pick-pulse" aria-hidden="true"></span><span>' + escHtml(noticeText) + '</span>';
            } else if (pickPhase === 'checkout' && selectedCheckin) {
                hintEl.className = 'sf-cal-pick-hint is-picking';
                hintEl.innerHTML = '<span class="sf-cal-pick-pulse" aria-hidden="true"></span><span>Check-in: <strong>' + escHtml(formatDisplayDate(selectedCheckin)) + '</strong> &mdash; Now select your check-out date.</span>';
            } else {
                hintEl.className = 'sf-cal-pick-hint';
                hintEl.innerHTML = '<span>Viewing <strong>' + escHtml(typeLabelsMap[currentRoomType] || 'All Rooms') + '</strong> &middot; Click any available date to start a new stay range.</span>';
            }
        }

        var nights = diffNights(selectedCheckin, selectedCheckout);
        if (datesEl && nightsEl && estEl && applyBtn) {
            if (selectedCheckin && selectedCheckout && nights > 0) {
                datesEl.textContent = formatDisplayDate(selectedCheckin) + ' \u2192 ' + formatDisplayDate(selectedCheckout);
                nightsEl.textContent = nights + (nights === 1 ? ' night' : ' nights');
                nightsEl.style.display = 'inline-block';
                estEl.textContent = estimateStayRateText(selectedCheckin, selectedCheckout);
                applyBtn.disabled = false;
            } else if (selectedCheckin) {
                datesEl.textContent = 'Check-in: ' + formatDisplayDate(selectedCheckin);
                nightsEl.textContent = 'Select check-out';
                nightsEl.style.display = 'inline-block';
                estEl.textContent = 'Pick a later date on the calendar to set check-out';
                applyBtn.disabled = true;
            }
        }

        updateHeaderPill();
    }

    function updateCellSelectionClasses() {
        var container = document.getElementById('sf-cal-days');
        if (!container) return;
        var activeEnd = (pickPhase === 'checkout' && hoverDate && hoverDate > selectedCheckin && !hasSoldOutNightBetween(selectedCheckin, hoverDate))
            ? hoverDate
            : selectedCheckout;

        container.querySelectorAll('.sf-cal-day[data-date]').forEach(function(cell) {
            var dateKey = cell.getAttribute('data-date');
            var isStart = (selectedCheckin && dateKey === selectedCheckin);
            var isEnd = (selectedCheckout && dateKey === selectedCheckout && pickPhase === 'idle');
            var inRange = (selectedCheckin && selectedCheckout && pickPhase === 'idle' && dateKey > selectedCheckin && dateKey < selectedCheckout);
            var inPreview = (pickPhase === 'checkout' && activeEnd && dateKey > selectedCheckin && dateKey <= activeEnd);
            var isCheckoutOnly = (pickPhase === 'checkout' && cell.getAttribute('data-sold') === '1' && dateKey > selectedCheckin && !hasSoldOutNightBetween(selectedCheckin, dateKey));

            cell.classList.toggle('sf-cal-day--start', !!isStart);
            cell.classList.toggle('sf-cal-day--end', !!isEnd);
            cell.classList.toggle('sf-cal-day--in-range', !!inRange);
            cell.classList.toggle('sf-cal-day--preview', !!inPreview);
            cell.classList.toggle('sf-cal-day--checkout-only', !!isCheckoutOnly);

            var tagEl = cell.querySelector('.sf-cal-day-tag');
            if (tagEl) {
                if (isStart) {
                    tagEl.textContent = 'IN';
                    tagEl.style.display = '';
                } else if (isEnd) {
                    tagEl.textContent = 'OUT';
                    tagEl.style.display = '';
                } else if (isCheckoutOnly) {
                    tagEl.textContent = 'OUT OK';
                    tagEl.style.display = '';
                } else if (dateKey === todayIso) {
                    tagEl.textContent = 'Today';
                    tagEl.style.display = '';
                } else {
                    tagEl.textContent = '';
                    tagEl.style.display = 'none';
                }
            }
        });
    }

    function syncSearchInputs() {
        var cinInput = document.getElementById('be-checkin');
        var coutInput = document.getElementById('be-checkout');
        if (cinInput && coutInput && selectedCheckin && selectedCheckout) {
            cinInput.value = selectedCheckin;
            coutInput.min = addDaysIso(selectedCheckin, 1);
            coutInput.value = selectedCheckout;
        }
    }

    function handleDateClick(dateKey, dayItem) {
        noticeText = '';
        if (dayItem.is_past) return;

        var canUseAsCheckoutOnly = (
            pickPhase === 'checkout' &&
            dateKey > selectedCheckin &&
            dayItem.status === 'sold_out' &&
            !hasSoldOutNightBetween(selectedCheckin, dateKey)
        );

        if (dayItem.status === 'sold_out' && !canUseAsCheckoutOnly) {
            return;
        }

        if (pickPhase === 'idle') {
            selectedCheckin = dateKey;
            selectedCheckout = '';
            pickPhase = 'checkout';
            hoverDate = '';
        } else {
            if (dateKey <= selectedCheckin) {
                selectedCheckin = dateKey;
                selectedCheckout = '';
                pickPhase = 'checkout';
                hoverDate = '';
            } else if (hasSoldOutNightBetween(selectedCheckin, dateKey)) {
                if (dayItem.status !== 'sold_out') {
                    selectedCheckin = dateKey;
                    selectedCheckout = '';
                    pickPhase = 'checkout';
                    hoverDate = '';
                    noticeText = 'Range included a sold-out night. Check-in restarted on ' + formatDisplayDate(dateKey) + '.';
                }
            } else {
                selectedCheckout = dateKey;
                pickPhase = 'idle';
                hoverDate = '';
                syncSearchInputs();
            }
        }

        renderLoadedMonth();
        updateSummaryAndHint();
    }

    function renderLoadedMonth() {
        var container = document.getElementById('sf-cal-days');
        if (!container || !loadedMonthData || !loadedMonthData.success) return;

        var year = Number(loadedMonthData.year);
        var month = Number(loadedMonthData.month);
        var isRatePrefix = !!loadedMonthData.is_rate_prefix;
        lastIsRatePrefix = isRatePrefix;

        container.innerHTML = '';
        var firstDayIndex = new Date(year, month - 1, 1).getDay();
        for (var pad = 0; pad < firstDayIndex; pad++) {
            var emptyCell = document.createElement('div');
            emptyCell.className = 'sf-cal-pad';
            emptyCell.setAttribute('aria-hidden', 'true');
            container.appendChild(emptyCell);
        }

        Object.keys(loadedMonthData.days).forEach(function(dateKey) {
            var dayItem = loadedMonthData.days[dateKey];
            var cell = document.createElement('button');
            cell.type = 'button';
            cell.setAttribute('data-date', dateKey);

            var stateClass = 'sf-cal-day--avail';
            if (dayItem.is_past) {
                stateClass = 'sf-cal-day--past';
                cell.disabled = true;
            } else if (dayItem.status === 'sold_out') {
                stateClass = 'sf-cal-day--sold';
                cell.setAttribute('data-sold', '1');
            } else if (dayItem.status === 'low_stock') {
                stateClass = 'sf-cal-day--low';
            }

            cell.className = 'sf-cal-day ' + stateClass + (dateKey === todayIso ? ' sf-cal-day--today' : '');

            var rateNumber = Number(dayItem.min_rate || 0);
            var rateHtml = '';
            var stockText = '';
            if (dayItem.is_past) {
                rateHtml = '<span class="sf-cal-day-rate">&mdash;</span>';
                stockText = 'Past';
            } else if (dayItem.status === 'sold_out') {
                rateHtml = rateNumber > 0
                    ? '<span class="sf-cal-day-rate">&#8369;' + rateNumber.toLocaleString('en-PH') + '</span>'
                    : '<span class="sf-cal-day-rate">&mdash;</span>';
                stockText = 'Sold out';
            } else {
                var prefixHtml = isRatePrefix ? '<span class="sf-cal-rate-prefix">From</span>' : '';
                rateHtml = rateNumber > 0
                    ? '<span class="sf-cal-day-rate">' + prefixHtml + '&#8369;' + rateNumber.toLocaleString('en-PH') + '</span>'
                    : '<span class="sf-cal-day-rate">Open</span>';
                stockText = dayItem.available + ' left';
            }

            cell.innerHTML =
                '<span class="sf-cal-day-top">' +
                    '<span class="sf-cal-day-num">' + Number(dayItem.day) + '</span>' +
                    '<span class="sf-cal-day-tag sf-cal-day-tag--today" style="display:none;"></span>' +
                '</span>' +
                '<span class="sf-cal-day-meta">' +
                    rateHtml +
                    '<span class="sf-cal-day-stock">' + escHtml(stockText) + '</span>' +
                '</span>';

            if (!dayItem.is_past) {
                cell.setAttribute('aria-label',
                    formatDisplayDate(dateKey) + ': ' +
                    (dayItem.status === 'sold_out' ? 'Sold out' : (dayItem.available + ' rooms available, from PHP ' + rateNumber))
                );
                cell.addEventListener('click', function() {
                    handleDateClick(dateKey, dayItem);
                });
                cell.addEventListener('mouseenter', function() {
                    if (pickPhase === 'checkout' && dateKey > selectedCheckin) {
                        hoverDate = dateKey;
                        updateCellSelectionClasses();
                    }
                });
            }

            container.appendChild(cell);
        });

        updateCellSelectionClasses();
    }

    function loadGuestCalendar(year, month) {
        var titleEl = document.getElementById('cal-month-title');
        var container = document.getElementById('sf-cal-days');
        if (!titleEl || !container) return;

        titleEl.textContent = monthNames[month - 1] + ' ' + year;
        container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 28px 12px; color: #776B62; font-size: 13px;">Loading live room inventory &amp; nightly rates...</div>';

        var apiUrl = (typeof SF_BASE_URL !== 'undefined' && SF_BASE_URL ? SF_BASE_URL : '..') +
            '/backend/api/availability.php?action=get_month_matrix&year=' + year +
            '&month=' + month + '&room_type=' + encodeURIComponent(currentRoomType);

        fetch(apiUrl, { credentials: 'same-origin' })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data || !data.success) {
                    container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 24px 12px; color: #9F2D2D; font-size: 13px;">Unable to load calendar data right now.</div>';
                    return;
                }
                loadedMonthData = data;
                Object.keys(data.days).forEach(function(dKey) {
                    dayCache[currentRoomType + '|' + dKey] = data.days[dKey];
                });
                renderLoadedMonth();
                updateSummaryAndHint();
            })
            .catch(function() {
                container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 24px 12px; color: #9F2D2D; font-size: 13px;">Failed to connect to availability service.</div>';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var prevBtn = document.getElementById('cal-prev-month');
        var nextBtn = document.getElementById('cal-next-month');
        var todayBtn = document.getElementById('sf-cal-today-btn');
        var toggleBtn = document.getElementById('rooms-cal-toggle');
        var calendarBody = document.getElementById('rooms-calendar-body');
        var pillsContainer = document.getElementById('sf-cal-type-pills');
        var resetBtn = document.getElementById('sf-cal-reset-btn');
        var applyBtn = document.getElementById('sf-cal-apply-btn');
        var daysGrid = document.getElementById('sf-cal-days');

        if (daysGrid) {
            daysGrid.addEventListener('mouseleave', function() {
                if (pickPhase === 'checkout' && hoverDate) {
                    hoverDate = '';
                    updateCellSelectionClasses();
                }
            });
        }

        if (pillsContainer) {
            pillsContainer.querySelectorAll('.sf-cal-type-pill').forEach(function(pill) {
                pill.addEventListener('click', function() {
                    var nextType = pill.getAttribute('data-cal-room-type') || 'any';
                    if (nextType === currentRoomType) return;
                    currentRoomType = nextType;
                    noticeText = '';
                    pillsContainer.querySelectorAll('.sf-cal-type-pill').forEach(function(p) {
                        var active = p === pill;
                        p.classList.toggle('is-active', active);
                        p.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                    loadGuestCalendar(currentCalYear, currentCalMonth);
                });
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                currentCalMonth--;
                if (currentCalMonth < 1) { currentCalMonth = 12; currentCalYear--; }
                loadGuestCalendar(currentCalYear, currentCalMonth);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                currentCalMonth++;
                if (currentCalMonth > 12) { currentCalMonth = 1; currentCalYear++; }
                loadGuestCalendar(currentCalYear, currentCalMonth);
            });
        }

        if (todayBtn) {
            todayBtn.addEventListener('click', function() {
                var tp = todayIso.split('-');
                currentCalYear = Number(tp[0]);
                currentCalMonth = Number(tp[1]);
                loadGuestCalendar(currentCalYear, currentCalMonth);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                selectedCheckin = initialCheckin;
                selectedCheckout = initialCheckout;
                pickPhase = 'idle';
                hoverDate = '';
                noticeText = '';
                syncSearchInputs();
                var p = initialCheckin.split('-');
                var targetY = Number(p[0]), targetM = Number(p[1]);
                if (targetY !== currentCalYear || targetM !== currentCalMonth) {
                    currentCalYear = targetY;
                    currentCalMonth = targetM;
                    loadGuestCalendar(currentCalYear, currentCalMonth);
                } else {
                    renderLoadedMonth();
                    updateSummaryAndHint();
                }
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                if (!selectedCheckin || !selectedCheckout || selectedCheckout <= selectedCheckin) return;
                syncSearchInputs();
                var form = document.querySelector('.be-bar-form');
                if (form) form.submit();
            });
        }

        if (toggleBtn && calendarBody) {
            toggleBtn.addEventListener('click', function() {
                var opening = calendarBody.hidden;
                calendarBody.hidden = !opening;
                toggleBtn.setAttribute('aria-expanded', String(opening));
                toggleBtn.querySelector('span').textContent = opening ? 'Hide availability calendar' : 'View availability calendar';
                if (opening && !calendarLoaded) {
                    calendarLoaded = true;
                    loadGuestCalendar(currentCalYear, currentCalMonth);
                }
            });
        }

        updateSummaryAndHint();
    });
})();
