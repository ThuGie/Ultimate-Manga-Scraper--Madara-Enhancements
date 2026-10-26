const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');
const jquery = require('jquery');

async function setup() {
    const dom = new JSDOM(`<!doctype html><form id="url-settings-form"><input id="manga-fetch-url" value="https://example.org/wp-admin/admin-ajax.php"><button id="save-url-settings" type="button"></button></form>
      <select id="content-type"><option value="novel">Novel</option></select>
      <form id="search-form"><input id="search-query"><select id="search-type"><option value="new">New</option></select><button id="search-button" type="button"></button></form>
      <input id="auto-load-more" type="checkbox"><p id="manga-count"></p><table id="manga-table"><tbody></tbody></table><button id="load-more"></button>`,
      {url: 'https://admin.example.org', runScripts: 'outside-only'});
    const $ = jquery(dom.window);
    const requests = [];
    const alerts = [];
    dom.window.jQuery = $;
    dom.window.madaraEnhancements = {ajaxUrl: '/ajax', nonce: 'test'};
    dom.window.alert = value => alerts.push(value);
    $.ajax = options => {
        const deferred = $.Deferred();
        const promise = deferred.promise();
        promise.abort = () => deferred.reject({}, 'abort');
        requests.push({options, deferred});
        return promise;
    };
    dom.window.eval(fs.readFileSync('assets/js/madara-enhancements.js', 'utf8'));
    await new Promise(resolve => $(resolve));
    await new Promise(resolve => setTimeout(resolve, 0));
    return {$, requests, alerts, dom};
}
const item = {title: '\"><img src=x onerror=alert(1)>', url: 'https://example.org/novel/a', cover_image: 'javascript:alert(1)', genres: 'Fantasy'};
function resolve(request, items, hasMore = true) {
    request.deferred.resolve({success: true, data: {items, has_more: hasMore}});
}

test('first manual load starts at zero and concurrent loads are ignored', async () => {
    const {$, requests, dom} = await setup();
    $('#load-more').trigger('click');
    $('#load-more').trigger('click');
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.data.page, 0);
    resolve(requests[0], [item]);
    $('#load-more').trigger('click');
    assert.equal(requests[1].options.data.page, 1);
    resolve(requests[1], [item], false);
    assert.equal($('#manga-table tbody tr').length, 1);
    assert.equal($('#load-more').prop('disabled'), true);
    assert.equal($('#manga-table img').length, 1);
    assert.equal($('#manga-table img').attr('src'), '');
    assert.equal($('#manga-table img').attr('onerror'), undefined);
    assert.equal($('#manga-table td').eq(1).text(), item.title);
    dom.window.close();
});

test('a failed page is retried without skipping results', async () => {
    const {$, requests, dom} = await setup();
    $('#load-more').trigger('click');
    requests[0].deferred.reject({}, 'error');
    $('#load-more').trigger('click');
    assert.equal(requests[1].options.data.page, 0);
    resolve(requests[1], [], true);
    assert.equal($('#load-more').prop('disabled'), false);
    $('#load-more').trigger('click');
    assert.equal(requests[2].options.data.page, 1);
    dom.window.close();
});

test('new searches abort stale requests and clear displayed results', async () => {
    const {$, requests, alerts, dom} = await setup();
    $('#load-more').trigger('click');
    $('#search-query').val('different title');
    $('#search-form').trigger('submit');
    assert.equal(requests.length, 2);
    assert.equal(requests[0].deferred.state(), 'rejected');
    assert.equal(requests[1].options.data.query, 'different title');
    assert.equal(requests[1].options.data.page, 0);
    assert.deepEqual(alerts, []);
    resolve(requests[1], [] , false);
    assert.equal($('#manga-table tbody tr').length, 0);
    dom.window.close();
});

test('settings send the novel destination and reload from page zero', async () => {
    const {$, requests, dom} = await setup();
    $('#save-url-settings').trigger('click');
    assert.equal(requests[0].options.data.content_type, 'novel');
    requests[0].deferred.resolve({success: true});
    assert.equal(requests[1].options.data.page, 0);
    resolve(requests[1], [item]);
    $('#manga-table form').trigger('submit');
    assert.equal(requests[2].options.data.add_manga_url, item.url);
    requests[2].deferred.resolve({success: true});
    assert.equal($('#manga-table tbody tr').length, 0);
    assert.equal($('#manga-count').text(), 'Titles loaded: 0');
    dom.window.close();
});

test('auto-load starts an empty table and stops when disabled', async () => {
    const {$, requests, dom} = await setup();
    $('#auto-load-more').prop('checked', true).trigger('change');
    assert.equal(requests.length, 1);
    resolve(requests[0], [], true);
    $('#auto-load-more').prop('checked', false).trigger('change');
    await new Promise(resolve => setTimeout(resolve, 250));
    assert.equal(requests.length, 1);
    assert.equal($('#load-more').css('display') === 'none', false);
    dom.window.close();
});
