<?php
// Small WordPress boundary stubs; parsing and handler code run unchanged.
define('ABSPATH', __DIR__);
$options = [];
$allowed = true;
$response = ['code' => 200, 'body' => file_get_contents(__DIR__ . '/fixtures/search.html')];
class JsonResponse extends RuntimeException {
    public $success;
    public $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $autoload = null) { global $options; $options[$key] = $value; return true; }
function check_ajax_referer($action, $field) {}
function current_user_can($capability) { global $allowed; return $allowed; }
function wp_send_json_success($data = null) { throw new JsonResponse(true, $data); }
function wp_send_json_error($data, $status = null) { throw new JsonResponse(false, $data); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function esc_url_raw($value) { return preg_match('~^https?://~', $value) ? trim($value) : ''; }
function wp_http_validate_url($value) { return filter_var($value, FILTER_VALIDATE_URL); }
function wp_get_current_user() { return (object) ['user_login' => 'editor-name']; }
function wp_safe_remote_post($url, $args) { global $response, $last_request; $last_request = $args; return $response; }
function wp_safe_remote_get($url, $args) { global $response; return $response; }
function wp_remote_retrieve_body($value) { return $value['body']; }
function wp_remote_retrieve_response_code($value) { return $value['code']; }
function is_wp_error($value) { return $value instanceof RuntimeException; }
require __DIR__ . '/../includes/class-madara-fetcher.php';
require __DIR__ . '/../includes/class-madara-handler.php';
set_error_handler(function($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$count = 0;
function check($condition, $message) { global $count; if (!$condition) { throw new RuntimeException($message); } $count++; }
function ajax($method, $post = []) {
    $_POST = $post;
    try { Madara_Handler::$method(); } catch (JsonResponse $result) { return $result; }
    throw new RuntimeException('Expected JSON response from ' . $method);
}

$items = Madara_Fetcher::get_manga_data();
check(count($items) === 1, 'Malformed items are skipped');
check($items[0]['title'] === 'A novel & its sequel', 'Entities decode correctly');
check($items[0]['cover_image'] === 'https://example.org/cover.jpg', 'Non-lazy cover works');
check($items[0]['description'] === 'Another title', 'Unknown heading does not shift fields');
check($items[0]['genres'] === 'Fantasy, Action', 'Genres use matching sibling');
check($items[0]['status'] === 'Ongoing', 'Status is extracted');
check($items[0]['latest_chapter'] === '', 'Missing optional fields remain empty');
check($last_request['body']['page'] === 0 && $last_request['body']['vars']['paged'] === 1, 'Madara uses zero-based load-more offset');
check(!isset($last_request['sslverify']), 'TLS verification remains enabled');
Madara_Fetcher::get_manga_data(2, '', 'rating');
check($last_request['body']['vars']['orderby']['query_avarage_reviews'] === 'DESC', 'Rating uses named meta clauses');
check(isset($last_request['body']['vars']['meta_query']['query_total_reviews']), 'Rating clauses are top-level');

foreach (['manga' => 19, 'novel' => 24] as $kind => $fields) {
    $options = [];
    Madara_Handler::save_manga_rules(['url' => ' https://example.org/title/ ', 'content_type' => $kind]);
    $key = 'ums_' . $kind . '_generic_list';
    $rule = $options[$key][0];
    check(count($rule) === $fields, $kind . ' field count');
    check($rule[0] === 'https://example.org/title/' && $rule[6] === 'editor-name', 'URL and current author');
    $offset = $kind === 'novel' ? 1 : 0;
    check($rule[12 + $offset] === '0', 'max_manga position');
    check($rule[13 + $offset] === '', 'chapter_warning is not accidentally enabled');
    check($rule[14 + $offset] === '1', 'comments position');
    if ($kind === 'novel') { check($rule[12] === '1' && $rule[23] === '0', 'Novel-specific fields'); }
}
$options = ['madara_content_type' => 'novel'];
$result = ajax('search_manga');
check($result->success && $result->data['items'][0]['content_type'] === 'novel', 'Search retains novel destination');
$url = $items[0]['url'];
$result = ajax('add_manga', ['add_manga_url' => $url]);
check($result->success && count($options['ums_novel_generic_list']) === 1, 'Novel goes to novel rules');
check(!isset($options['ums_manga_generic_list']), 'Novel does not create manga rules');
$result = ajax('search_manga');
check($result->data['items'] === [] && $result->data['has_more'], 'Imported source page is filtered but pagination continues');
$options['madara_manga_list'] = [$items[0]];
check(!ajax('add_manga', ['add_manga_url' => $url])->success, 'Duplicate checked across both rule lists');
$options['ums_novel_generic_list'] = [false, ['https://other.example/title']];
check(ajax('search_manga')->success, 'Deleted UMS rule slots do not cause warnings');
$allowed = false;
foreach (['search_manga', 'load_more_manga', 'add_manga', 'save_manga_fetch_url'] as $method) {
    check(!ajax($method)->success, $method . ' requires administrator capability');
}
$allowed = true;
check(!ajax('save_manga_fetch_url', ['manga_fetch_url' => 'https://example.org', 'content_type' => 'invalid'])->success, 'Reject unknown destination');
check(ajax('save_manga_fetch_url', ['manga_fetch_url' => 'https://example.org/wp-admin/admin-ajax.php', 'content_type' => 'novel'])->success, 'Save destination');
check($options['madara_content_type'] === 'novel' && $options['madara_manga_list'] === [], 'Settings reset old cache');
$response = ['code' => 503, 'body' => 'Unavailable'];
check(!ajax('load_more_manga')->success, 'HTTP error returns JSON instead of filtering error as manga');
$response = ['code' => 200, 'body' => ''];
$result = ajax('search_manga');
check($result->success && !$result->data['has_more'], 'Empty page ends pagination');
$response['body'] = '0';
check(Madara_Fetcher::get_manga_data() === [], 'WordPress zero ends pagination');
$response['body'] = '<html>Access denied</html>';
check(isset(Madara_Fetcher::get_manga_data()['error']), 'Unexpected page is reported');
$response['body'] = '<h1>Novel</h1>';
check(Madara_Fetcher::get_manga_details('https://example.org')['title'] === 'Novel', 'Details tolerate missing optional elements');
$response['body'] = '<div class="invi no-posts"><!-- --></div>';
check(Madara_Fetcher::get_manga_data() === [], 'Madara no-posts marker ends pagination');
Madara_Fetcher::get_manga_data(0, '', 'a_z');
check($last_request['body']['vars']['orderby'] === 'title' && !isset($last_request['body']['vars']['meta_key']), 'A-Z uses title without excluding missing view metadata');
echo "Passed $count PHP regression checks.\n";
