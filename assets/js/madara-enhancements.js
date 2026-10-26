jQuery(function($) {
    var page = -1;
    var loading = false;
    var hasMore = true;
    var request = null;
    var generation = 0;
    var query = '';
    var type = 'new';
    var seen = new Set();

    function updateRows() {
        var rows = $('#manga-table tbody tr');
        rows.each(function(index) { $(this).find('td:first').text(index + 1); });
        $('#manga-count').text('Titles loaded: ' + rows.length);
        $('#load-more').prop('disabled', loading || !hasMore).toggle(!$('#auto-load-more').is(':checked'));
    }

    function safeUrl(value) {
        try {
            var url = new URL(value);
            return /^(https?:)$/.test(url.protocol) ? url.href : '';
        } catch (error) {
            return '';
        }
    }

    function appendItems(items) {
        items.forEach(function(item) {
            if (seen.has(item.url)) { return; }
            seen.add(item.url);
            var row = $('<tr>').appendTo('#manga-table tbody');
            row.append($('<td>'), $('<td>').text(item.title));
            row.append($('<td>').append($('<img>').attr({src: safeUrl(item.cover_image), alt: item.title, width: 100})));
            ['description', 'genres', 'status', 'last_updated', 'latest_chapter'].forEach(function(field) {
                row.append($('<td>').text(item[field]));
            });
            var form = $('<form>').addClass('add-manga-form');
            form.append($('<input>').attr({type: 'hidden', name: 'add_manga_url'}).val(item.url));
            form.append($('<button>').attr('type', 'submit').addClass('button button-primary').text('Add title'));
            row.append($('<td>').append(form));
        });
    }

    function loadPage() {
        if (loading || !hasMore) { return; }
        loading = true;
        updateRows();
        var nextPage = page + 1;
        var succeeded = false;
        var currentGeneration = generation;
        request = $.ajax({
            url: madaraEnhancements.ajaxUrl,
            method: 'POST',
            data: {action: 'search_manga', page: nextPage, query: query, type: type, _ajax_nonce: madaraEnhancements.nonce}
        }).done(function(response) {
            if (currentGeneration !== generation) { return; }
            if (!response.success) {
                alert('Search failed: ' + response.data);
                return;
            }
            succeeded = true;
            page = nextPage;
            hasMore = response.data.has_more;
            appendItems(response.data.items);
        }).fail(function(xhr, status) {
            if (status !== 'abort') { alert('Could not load titles. Please try again.'); }
        }).always(function() {
            if (currentGeneration !== generation) { return; }
            loading = false;
            request = null;
            updateRows();
            // Fill a short viewport, including pages containing only imported titles.
            if (succeeded && hasMore && $('#auto-load-more').is(':checked') && $(document).height() <= $(window).height()) {
                setTimeout(function() {
                    if (currentGeneration === generation && $('#auto-load-more').is(':checked')) { loadPage(); }
                }, 200);
            }
        });
    }

    function resetSearch() {
        generation++;
        if (request) { request.abort(); }
        loading = false;
        page = -1;
        hasMore = true;
        seen.clear();
        $('#manga-table tbody').empty();
        query = $('#search-query').val();
        type = $('#search-type').val();
        updateRows();
        loadPage();
    }

    $('#search-form').on('submit', function(event) { event.preventDefault(); resetSearch(); });
    $('#search-button').on('click', resetSearch);
    $('#load-more').on('click', loadPage);
    $('#auto-load-more').on('change', function() {
        updateRows();
        if ($(this).is(':checked') && $('#manga-table tbody tr').length === 0) { loadPage(); }
    });
    var scrollTimer;
    $(window).on('scroll.madaraEnhancements', function() {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function() {
            if ($('#auto-load-more').is(':checked') && $(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
                loadPage();
            }
        }, 200);
    });

    $('#url-settings-form').on('submit', function(event) { event.preventDefault(); });
    $('#save-url-settings').on('click', function() {
        var button = $(this).prop('disabled', true);
        // Finish or abort the old source request before resetting its server cache.
        generation++;
        if (request) { request.abort(); }
        loading = false;
        $.ajax({
            url: madaraEnhancements.ajaxUrl,
            method: 'POST',
            data: {
                action: 'save_manga_fetch_url',
                manga_fetch_url: $('#manga-fetch-url').val(),
                content_type: $('#content-type').val(),
                _ajax_nonce: madaraEnhancements.nonce
            }
        }).done(function(response) {
            if (response.success) { resetSearch(); }
            else { alert('Could not save settings: ' + response.data); }
        }).fail(function() { alert('Could not save settings. Please try again.'); })
          .always(function() { button.prop('disabled', false); });
    });

    $(document).on('submit', '.add-manga-form', function(event) {
        event.preventDefault();
        var form = $(this);
        var button = form.find('button').prop('disabled', true);
        $.ajax({
            url: madaraEnhancements.ajaxUrl,
            method: 'POST',
            data: {action: 'add_manga', add_manga_url: form.find('input').val(), _ajax_nonce: madaraEnhancements.nonce}
        }).done(function(response) {
            if (response.success) { form.closest('tr').remove(); updateRows(); }
            else { alert('Could not add title: ' + response.data); }
        }).fail(function() { alert('Could not add title. Please try again.'); })
          .always(function() { button.prop('disabled', false); });
    });
    updateRows();
});
