/**
 * LGF Calendar View - AJAX Navigation
 */
(function($) {
    'use strict';

    // On DOM ready
    $(function() {
        var $container = $('.lgf-calendar-container');
        if (!$container.length) return;

        var $tableWrapper = $container.find('.lgf-calendar-view');
        var restUrl = lgfCalendar.restUrl;
        var nonce = lgfCalendar.nonce;

        // Intercept nav clicks
        $container.on('click', '.calendar-nav .button', function(e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href) return;

            // Parse month and year from URL query
            var url = new URL(href, window.location.origin);
            var month = url.searchParams.get('month');
            var year = url.searchParams.get('year');
            if (!month || !year) return;

            loadMonth(month, year, true);
        });

        // Load month via AJAX
        function loadMonth(month, year, pushState) {
            $.ajax({
                url: restUrl,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                    $tableWrapper.addClass('loading');
                },
                data: {
                    month: month,
                    year: year
                },
                success: function(response) {
                    if (response && response.html) {
                        $tableWrapper.html(response.html);
                        if (pushState) {
                            var newUrl = new URL(window.location);
                            newUrl.searchParams.set('month', month);
                            newUrl.searchParams.set('year', year);
                            window.history.pushState({ month: month, year: year }, '', newUrl);
                        }
                    } else {
                        alert('Failed to load calendar data.');
                    }
                },
                error: function() {
                    alert('Error loading calendar. Please try again.');
                },
                complete: function() {
                    $tableWrapper.removeClass('loading');
                }
            });
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.month && e.state.year) {
                loadMonth(e.state.month, e.state.year, false);
            } else {
                // No state, reload the page (maybe initial state)
                // We could also parse current URL
                var params = new URLSearchParams(window.location.search);
                var month = params.get('month');
                var year = params.get('year');
                if (month && year) {
                    loadMonth(month, year, false);
                }
            }
        });

        // Initial push state for current month to enable back button
        (function() {
            var params = new URLSearchParams(window.location.search);
            var month = params.get('month');
            var year = params.get('year');
            if (month && year) {
                // Replace initial state with month/year
                window.history.replaceState({ month: month, year: year }, '', window.location);
            }
        })();
    });
})(jQuery);
