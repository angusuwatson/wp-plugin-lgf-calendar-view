(function($) {
    'use strict';

    $(function() {
        var restUrl = lgfCalendar.restUrl;
        var dailyNotesUrl = lgfCalendar.dailyNotesUrl;
        var bookingUrl = lgfCalendar.bookingUrl;
        var nonce = lgfCalendar.nonce;
        var saveTimers = {};

        function getContainer() {
            return $('.lgf-calendar-container');
        }

        function request(options) {
            return $.ajax($.extend({}, options, {
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                    if (typeof options.beforeSend === 'function') {
                        options.beforeSend(xhr);
                    }
                }
            }));
        }

        function setSavingState($el, state) {
            $el.toggleClass('is-saving', state === 'saving');
            $el.toggleClass('is-error', state === 'error');
        }

        function loadMonth(month, year, pushState) {
            var $container = getContainer();
            if (!$container.length) {
                return;
            }

            request({
                url: restUrl,
                method: 'GET',
                data: { month: month, year: year, context: lgfCalendar.context || 'frontend' },
                beforeSend: function() { $container.addClass('loading'); },
                success: function(response) {
                    if (response && response.html) {
                        $container.replaceWith(response.html);
                        if (pushState) {
                            var newUrl = new URL(window.location.href);
                            newUrl.searchParams.set('month', month);
                            newUrl.searchParams.set('year', year);
                            window.history.pushState({ month: month, year: year }, '', newUrl.toString());
                        }
                    } else {
                        alert('Failed to load calendar data: empty response.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    alert('Error loading calendar (status: ' + status + '). See console for details.');
                },
                complete: function() { getContainer().removeClass('loading'); }
            });
        }

        function debounceSave(key, callback) {
            clearTimeout(saveTimers[key]);
            saveTimers[key] = setTimeout(callback, 350);
        }

        function saveDailyNote($input) {
            var date = $input.data('note-date');
            if (!date) return;
            setSavingState($input, 'saving');
            request({
                url: dailyNotesUrl,
                method: 'POST',
                data: { date: date, note: $input.val() },
                success: function() { setSavingState($input, 'done'); },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    setSavingState($input, 'error');
                }
            });
        }

        function getOverlayPayload($input) {
            var $table = $input.closest('table');
            var bookingId = $input.data('booking-id') || $input.closest('.calendar-occupancy-editor').data('booking-id');
            var roomId = $input.data('room-id') || $input.closest('.calendar-occupancy-editor').data('room-id');
            var reservedRoomId = $input.data('reserved-room-id') || $input.closest('.calendar-occupancy-editor').data('reserved-room-id');

            return {
                booking_id: bookingId,
                room_id: roomId,
                reserved_room_id: reservedRoomId,
                booking_note: $table.find('.calendar-booking-note-input[data-reserved-room-id="' + reservedRoomId + '"]').first().val() || '',
                manual_guest_name: $table.find('.calendar-booking-input[data-field="manual_guest_name"][data-reserved-room-id="' + reservedRoomId + '"]').first().val() || '',
                manual_adults: $table.find('.occupancy-part-input[data-field="manual_adults"][data-reserved-room-id="' + reservedRoomId + '"]').first().val() || '',
                manual_children: $table.find('.occupancy-part-input[data-field="manual_children"][data-reserved-room-id="' + reservedRoomId + '"]').first().val() || '',
                extras_formula: $table.find('.calendar-extras-input[data-reserved-room-id="' + reservedRoomId + '"]').first().val() || '',
                manual_tarif: $table.find('.calendar-money-input[data-field="manual_tarif"][data-reserved-room-id="' + reservedRoomId + '"]').first().val() || '',
                manual_commission: $table.find('.calendar-money-input[data-field="manual_commission"][data-reserved-room-id="' + reservedRoomId + '"]').first().val() || ''
            };
        }

        function saveBookingOverlay($input) {
            var payload = getOverlayPayload($input);
            if (!payload.reserved_room_id) return;
            setSavingState($input, 'saving');
            request({
                url: bookingUrl,
                method: 'POST',
                data: payload,
                success: function(response) {
                    setSavingState($input, 'done');
                    if ($input.hasClass('calendar-extras-input')) {
                        var $editor = $input.closest('.calendar-extras-editor');
                        if (response && typeof response.extras_formula !== 'undefined') {
                            $input.val(response.extras_formula || '');
                        }
                        $editor.find('.calendar-extras-display').text(response && response.extras_total !== null && Number(response.extras_total) > 0 ? Number(response.extras_total).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €' : '');
                        $editor.removeClass('is-editing');
                    }
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    setSavingState($input, 'error');
                }
            });
        }

        $(document).on('click', '.lgf-calendar-container .calendar-nav .button, .lgf-calendar-container .calendar-month-tab', function(e) {
            var href = $(this).attr('href');
            if (!href || lgfCalendar.context === 'admin') return;
            e.preventDefault();
            var url = new URL(href, window.location.origin);
            var month = url.searchParams.get('month');
            var year = url.searchParams.get('year');
            if (month && year) loadMonth(month, year, true);
        });

        $(document).on('input', '.lgf-calendar-container .calendar-note-input', function() {
            var $input = $(this);
            debounceSave('note:' + $input.data('note-date'), function() { saveDailyNote($input); });
        });

        $(document).on('input', '.lgf-calendar-container .calendar-booking-input', function() {
            setSavingState($(this), 'done');
        });

        $(document).on('click', '.lgf-calendar-container .calendar-extras-display', function() {
            var $editor = $(this).closest('.calendar-extras-editor');
            $editor.addClass('is-editing');
            $editor.find('.calendar-extras-input').trigger('focus').trigger('select');
        });

        $(document).on('change blur', '.lgf-calendar-container .calendar-booking-input', function() {
            var $input = $(this);
            var reservedRoomId = $input.data('reserved-room-id') || $input.closest('.calendar-occupancy-editor').data('reserved-room-id');
            debounceSave('booking:' + reservedRoomId, function() { saveBookingOverlay($input); });
        });

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.month && e.state.year) {
                loadMonth(e.state.month, e.state.year, false);
            } else {
                var params = new URLSearchParams(window.location.search);
                var month = params.get('month');
                var year = params.get('year');
                if (month && year) loadMonth(month, year, false);
            }
        });

        (function() {
            var params = new URLSearchParams(window.location.search);
            var month = params.get('month');
            var year = params.get('year');
            if (month && year) {
                window.history.replaceState({ month: month, year: year }, '', window.location.href);
            }
        })();
    });
})(jQuery);
