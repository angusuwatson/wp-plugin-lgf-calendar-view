/**
 * LGF Calendar View - AJAX Navigation + Notes
 */
(function($) {
    'use strict';

    $(function() {
        var restUrl = lgfCalendar.restUrl;
        var nonce = lgfCalendar.nonce;

        function getContainer() {
            return $('.lgf-calendar-container');
        }

        function getNotesStorageKey(month, year) {
            return 'lgfCalendarNotes:' + year + '-' + String(month).padStart(2, '0');
        }

        function loadNotes() {
            var $container = getContainer();
            if (!$container.length) {
                return;
            }

            var month = $container.find('.calendar-note-input').first().data('note-month');
            var year = $container.find('.calendar-note-input').first().data('note-year');
            if (!month || !year) {
                return;
            }

            var raw = window.localStorage.getItem(getNotesStorageKey(month, year));
            var notes = {};

            if (raw) {
                try {
                    notes = JSON.parse(raw) || {};
                } catch (e) {
                    notes = {};
                }
            }

            $container.find('.calendar-note-input').each(function() {
                var date = $(this).data('note-date');
                $(this).val(notes[date] || '');
            });
        }

        function saveNote($input) {
            var month = $input.data('note-month');
            var year = $input.data('note-year');
            var date = $input.data('note-date');
            if (!month || !year || !date) {
                return;
            }

            var storageKey = getNotesStorageKey(month, year);
            var raw = window.localStorage.getItem(storageKey);
            var notes = {};

            if (raw) {
                try {
                    notes = JSON.parse(raw) || {};
                } catch (e) {
                    notes = {};
                }
            }

            notes[date] = $input.val();
            window.localStorage.setItem(storageKey, JSON.stringify(notes));
        }

        function loadMonth(month, year, pushState) {
            var $container = getContainer();
            if (!$container.length) {
                return;
            }

            $.ajax({
                url: restUrl,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                    $container.addClass('loading');
                },
                data: {
                    month: month,
                    year: year
                },
                success: function(response) {
                    if (response && response.html) {
                        $container.replaceWith(response.html);
                        loadNotes();

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
                complete: function() {
                    getContainer().removeClass('loading');
                }
            });
        }

        $(document).on('click', '.lgf-calendar-container .calendar-nav .button, .lgf-calendar-container .calendar-month-tab', function(e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href) {
                return;
            }

            var url = new URL(href, window.location.origin);
            var month = url.searchParams.get('month');
            var year = url.searchParams.get('year');
            if (!month || !year) {
                return;
            }

            loadMonth(month, year, true);
        });

        $(document).on('input change', '.lgf-calendar-container .calendar-note-input', function() {
            saveNote($(this));
        });

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.month && e.state.year) {
                loadMonth(e.state.month, e.state.year, false);
            } else {
                var params = new URLSearchParams(window.location.search);
                var month = params.get('month');
                var year = params.get('year');
                if (month && year) {
                    loadMonth(month, year, false);
                }
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

        loadNotes();
    });
})(jQuery);
