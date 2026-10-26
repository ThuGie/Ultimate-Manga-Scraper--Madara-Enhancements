<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/*
 * Class Madara_Handler
 * Handles the admin interface and AJAX actions for the Ultimate Manga Scraper: Madara Enhancements plugin.
 */
class Madara_Handler {
    // Initialize hooks and actions
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']); // Add admin menu item
        add_action('wp_ajax_load_more_manga', [__CLASS__, 'load_more_manga']); // Handle AJAX request for loading more manga
        add_action('wp_ajax_add_manga', [__CLASS__, 'add_manga']); // Handle AJAX request for adding manga
        add_action('wp_ajax_search_manga', [__CLASS__, 'search_manga']); // Handle AJAX request for searching manga
        add_action('wp_ajax_save_manga_fetch_url', [__CLASS__, 'save_manga_fetch_url']); // Handle AJAX request for saving manga fetch URL
    }

    // Add menu item for the plugin in the WordPress admin
    public static function add_menu() {
        add_menu_page(
            'Ultimate Manga Scraper: Madara Enhancements', // Page title
            'Madara Enhancements', // Menu title
            'manage_options', // Capability required to access the page
            'madara-enhancements', // Menu slug
            [__CLASS__, 'admin_page'] // Callback function to display the page
        );
    }

    // Display the admin page
    public static function admin_page() {
        $content_type = get_option('madara_content_type', 'manga');
        $manga_fetch_url = get_option('manga_fetch_url', 'https://manhuaus.com/wp-admin/admin-ajax.php'); // Get the current manga fetch URL

        ?>
        <div class="wrap">
            <h1>Ultimate Manga Scraper: Madara Enhancements</h1>
            
            <!-- URL Settings Form -->
            <form id="url-settings-form">
                <h2>Set Manga Fetch URL</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="manga-fetch-url">Manga Fetch URL</label></th>
                        <td><input name="manga-fetch-url" type="text" id="manga-fetch-url" value="<?php echo esc_attr($manga_fetch_url); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" id="save-url-settings">Save URL</button>
            </form>

            <p><label for="content-type">Import destination</label>
                <select id="content-type">
                    <option value="manga" <?php selected($content_type, 'manga'); ?>>Generic Manga Scraper</option>
                    <option value="novel" <?php selected($content_type, 'novel'); ?>>Generic Web Novels Scraper</option>
                </select>
                Choose the source's content type, then click Save URL before searching.
            </p>
            <!-- Search Form -->
            <hr />
            <form id="search-form">
                <h2>Search titles</h2>
                <label for="search-query">Search Query</label>
                <input type="text" id="search-query" name="search-query" class="regular-text" />
                <label for="search-type">Search Type</label>
                <select id="search-type" name="search-type">
                    <option value="new">New</option>
                    <option value="latest">Latest</option>
                    <option value="trending">Trending</option>
                    <option value="most_viewed">Most Viewed</option>
                    <option value="rating">Rating</option>
                    <option value="a_z">A-Z</option>
                    <option value="relevance">Relevance</option>
                </select>
                <button type="button" class="button button-secondary" id="search-button">Search</button>
            </form>

            <!-- Manga List -->
            <hr />
            <div>
                <label for="auto-load-more">
                    <input type="checkbox" id="auto-load-more" /> Enable Auto Load More
                </label>
                <p id="manga-count">Titles loaded: 0</p>
            </div>
            <table class="widefat" id="manga-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Cover Image</th>
                        <th>Description</th>
                        <th>Genres</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Latest Chapter</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <button id="load-more" class="button button-secondary">Load More</button> <!-- Load More Button -->
        </div>
        <?php
    }

    private static function check_ajax_access() {
        check_ajax_referer('madara_enhancements_nonce', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to manage scraper rules.', 403);
            exit;
        }
    }

    private static function existing_urls() {
        $rules = array_merge(
            (array) get_option('ums_manga_generic_list', []),
            (array) get_option('ums_novel_generic_list', [])
        );
        $urls = [];
        foreach ($rules as $rule) {
            if (is_array($rule) && isset($rule[0])) {
                $urls[] = trim($rule[0]);
            }
        }
        return $urls;
    }

    // Search and pagination share the same validation and cache handling.
    public static function load_more_manga() {
        self::search_manga();
    }

    // Handle AJAX request to add manga
    public static function add_manga() {
        self::check_ajax_access();

        $url = isset($_POST['add_manga_url']) ? esc_url_raw(wp_unslash($_POST['add_manga_url'])) : null; // Get the manga URL

        $manga_list = get_option('madara_manga_list', []); // Get the current manga list

        if ($url !== null) {
            $manga_to_add = array_filter($manga_list, function ($manga) use ($url) {
                return $manga['url'] === $url;
            });

            if (!empty($manga_to_add)) {
                $manga_to_add = array_values($manga_to_add)[0];

                // Check if the manga already exists in the saved rules
                $existing_urls = self::existing_urls();
                foreach ($existing_urls as $existing_url) {
                    if ($existing_url === trim($manga_to_add['url'])) {
                        wp_send_json_error('Manga already exists.');
                        return;
                    }
                }

                self::save_manga_rules($manga_to_add); // Save manga rules

                // Remove the manga from the list after adding
                $manga_list = array_filter($manga_list, function ($manga) use ($url) {
                    return $manga['url'] !== $url;
                });
                update_option('madara_manga_list', $manga_list); // Update the manga list

                wp_send_json_success(); // Send success response
            } else {
                wp_send_json_error('Manga not found.'); // Send error response if manga not found
            }
        } else {
            wp_send_json_error('Invalid request.'); // Send error response if the request is invalid
        }
    }

    // Save manga rules
    public static function save_manga_rules($manga) {
        $is_novel = isset($manga['content_type']) && $manga['content_type'] === 'novel';
        $option = $is_novel ? 'ums_novel_generic_list' : 'ums_manga_generic_list';
        $rules = (array) get_option($option, []);

        // Keep this order aligned with UMS's Generic Manga Scraper form.
        $new_rule = [
            trim($manga['url']), // location
            '24', // schedule (hours)
            '1', // active
            (new DateTime())->modify('-24 hours -5 minutes')->format('Y-m-d H:i:s'), // last_run
            '1000', // max chapters
            'publish', // submit_status
            wp_get_current_user()->user_login, // post_author
            '', // default_tags
            '', // default_category
            'genre', // auto_categories
            'genre', // auto_tags
            '', // use_phantom
            '0', // max_manga (unlimited)
            '', // chapter_warning
            '1', // enable_comments
            '0', // enable_pingback
            '0', // get_date
            '0', // rule_translate
            '0', // no_translate_title
        ];
        if ($is_novel) {
            // The novel form inserts reverse_chapters before max_manga.
            array_splice($new_rule, 12, 0, ['1']);
            $new_rule = array_merge($new_rule, [
                '', // chapter_slug
                '0', // phantom_wait
                '', // always_first
                '0', // strip_images
            ]);
        }
        $rules[] = $new_rule;
        update_option($option, $rules, false);
    }

    public static function search_manga() {
        self::check_ajax_access();
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $page = isset($_POST['page']) ? max(0, intval($_POST['page'])) : 0;
        $content_type = get_option('madara_content_type', 'manga');
        $manga_list = Madara_Fetcher::get_manga_data($page, $query, $type);
        if (isset($manga_list['error'])) {
            wp_send_json_error($manga_list['error']);
            return;
        }

        $existing_urls = self::existing_urls();
        $filtered = [];
        $cached = $page === 0 ? [] : (array) get_option('madara_manga_list', []);
        $seen = array_column($cached, 'url');
        foreach ($manga_list as $manga) {
            if (!in_array(trim($manga['url']), $existing_urls, true) && !in_array($manga['url'], $seen, true)) {
                $manga['content_type'] = $content_type;
                $filtered[] = $manga;
                $seen[] = $manga['url'];
            }
        }
        update_option('madara_manga_list', array_merge($cached, $filtered), false);
        // An empty filtered page can still have more source pages.
        wp_send_json_success(['items' => $filtered, 'has_more' => count($manga_list) > 0]);
    }

    // Handle AJAX request to save the manga fetch URL
    public static function save_manga_fetch_url() {
        self::check_ajax_access();
        
        $manga_fetch_url = isset($_POST['manga_fetch_url']) ? esc_url_raw(wp_unslash($_POST['manga_fetch_url'])) : ''; // Get the manga fetch URL
        
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'manga';
        if (!in_array($content_type, ['manga', 'novel'], true)) {
            wp_send_json_error('Invalid content type.');
            return;
        }
        if ($manga_fetch_url && wp_http_validate_url($manga_fetch_url)) {
            update_option('madara_content_type', $content_type);
            update_option('madara_manga_list', [], false);
            update_option('manga_fetch_url', $manga_fetch_url); // Update the manga fetch URL option
            wp_send_json_success(); // Send success response
        } else {
            wp_send_json_error('Invalid URL.'); // Send error response if the URL is invalid
        }
    }
}
