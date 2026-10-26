<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/*
 * Class Madara_Fetcher
 * Fetches manga data from the specified URL.
 */
class Madara_Fetcher {
    /**
     * Fetch manga data from the specified URL.
     *
     * @param int $page The page number to fetch.
     * @param string $query The search query.
     * @param string $type The type of search (new, latest, trending, etc.).
     * @return array The list of manga data or an error message.
     */
    public static function get_manga_data($page = 0, $query = '', $type = '') {
        // Get the manga fetch URL from options
        $url = get_option('manga_fetch_url', 'https://manhuaus.com/wp-admin/admin-ajax.php');

        // Prepare the query variables
        $vars = [
            's' => $query,
            'orderby' => 'meta_value_num',
            'paged' => 1,
            'template' => 'search',
            'meta_query' => [],
            'post_type' => 'wp-manga',
            'post_status' => 'publish',
            'meta_key' => '_latest_update',
            'order' => 'desc',
            'manga_archives_item_layout' => 'big_thumbnail'
        ];

        // Adjust search parameters based on the specified type
        switch ($type) {
            case 'new':
                $vars['orderby'] = 'date';
                unset($vars['meta_key']);
                break;
            case 'latest':
                $vars['orderby'] = 'meta_value_num';
                $vars['meta_key'] = '_latest_update';
                $vars['order'] = 'desc';
                break;
            case 'trending':
                $vars['orderby'] = 'meta_value_num';
                $vars['meta_key'] = '_wp_manga_week_views_value';
                $vars['order'] = 'desc';
                break;
            case 'most_viewed':
                $vars['orderby'] = 'meta_value_num';
                $vars['meta_key'] = '_wp_manga_views';
                $vars['order'] = 'desc';
                break;
            case 'rating':
                $vars['orderby'] = ['query_avarage_reviews' => 'DESC', 'query_total_reviews' => 'DESC'];
                unset($vars['meta_key']);
                $vars['meta_query'] = [
                    'query_avarage_reviews' => [
                        'key' => '_manga_avarage_reviews'
                    ],
                    'query_total_reviews' => [
                        'key' => '_manga_total_votes'
                    ]
                ];
                break;
            case 'a_z':
                $vars['orderby'] = 'title';
                unset($vars['meta_key']);
                $vars['order'] = 'ASC';
                break;
            case 'relevance':
                $vars['orderby'] = 'relevance';
                unset($vars['meta_key']);
                break;
        }

        $response = wp_safe_remote_post($url, [
            'timeout' => 30,
            'body' => [
                'action' => 'madara_load_more',
                'page' => max(0, (int) $page),
                'template' => 'madara-core/content/content-search',
                'vars' => $vars,
            ],
        ]);
        return self::parse_response($response, false);
    }

    public static function get_manga_details($url) {
        return self::parse_response(wp_safe_remote_get($url, ['timeout' => 30]), true);
    }

    private static function parse_response($response, $details) {
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return ['error' => 'The source returned HTTP ' . wp_remote_retrieve_response_code($response) . '.'];
        }
        $html = wp_remote_retrieve_body($response);
        if (trim($html) === '') {
            return $details ? ['error' => 'The source returned an empty page.'] : [];
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        if ($details) {
            $title = self::text($xpath, '//h1');
            if ($title === '') {
                return ['error' => 'No title found on the source page.'];
            }
            return [
                'title' => $title,
                'cover_image' => self::image($xpath, '//div[' . self::has_class('thumb') . ']//img'),
                'description' => self::text($xpath, '//div[' . self::has_class('description-summary') . ']'),
                'genres' => self::text($xpath, '//div[' . self::has_class('genres-content') . ']'),
                'status' => self::text($xpath, '//div[' . self::has_class('post-status') . ']//div[' . self::has_class('summary-content') . ']'),
            ];
        }
        $items = $xpath->query('//div[' . self::has_class('c-tabs-item__content') . ']');
        $list = [];
        foreach ($items as $item) {
            $title = $xpath->query('.//div[' . self::has_class('post-title') . ']//a', $item)->item(0);
            if (!$title || trim($title->textContent) === '' || !$title->getAttribute('href')) {
                continue;
            }
            $data = [
                'title' => trim($title->textContent),
                'url' => esc_url_raw($title->getAttribute('href')),
                'cover_image' => self::image($xpath, './/div[' . self::has_class('tab-thumb') . ']//img', $item),
                'description' => '', 'genres' => '', 'status' => '',
                'last_updated' => self::text($xpath, './/div[' . self::has_class('post-on') . ']', $item),
                'latest_chapter' => self::text($xpath, './/div[' . self::has_class('latest-chap') . ']//a', $item),
            ];
            foreach ($xpath->query('.//div[' . self::has_class('summary-heading') . ']', $item) as $heading) {
                $label = strtolower(rtrim(trim($heading->textContent), ':'));
                // Pair each heading with its sibling, including unknown headings.
                $value = self::text($xpath, 'following-sibling::div[' . self::has_class('summary-content') . ']', $heading);
                if ($label === 'alternative') {
                    $data['description'] = $value;
                } elseif ($label === 'genres' || $label === 'status') {
                    $data[$label] = $value;
                }
            }
            if ($data['url'] !== '') {
                $list[] = $data;
            }
        }
        // Madara returns an empty fragment (or "0") at the end of pagination.
        $end_marker = $xpath->query('//*[' . self::has_class('no-posts') . ']')->length > 0;
        if (!$list && !$end_marker && trim($html) !== '0' && trim($html) !== '-1') {
            return ['error' => 'No recognizable Madara results found. Check the source URL.'];
        }
        if (trim($html) === '-1') {
            return ['error' => 'The source rejected the request.'];
        }
        return $list;
    }

    private static function has_class($name) {
        return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")';
    }

    private static function text($xpath, $query, $context = null) {
        $node = $xpath->query($query, $context)->item(0);
        return $node ? trim($node->textContent) : '';
    }

    private static function image($xpath, $query, $context = null) {
        $node = $xpath->query($query, $context)->item(0);
        if (!$node) {
            return '';
        }
        foreach (['data-src', 'data-lazy-src', 'src'] as $attribute) {
            if (trim($node->getAttribute($attribute)) !== '') {
                return esc_url_raw(trim($node->getAttribute($attribute)));
            }
        }
        return '';
    }
}
