# Compatibility notes

## References checked

The UMS option names and positional rule fields were compared against its public source checkout:

- [Generic manga form](https://github.com/druvx13/ultimate-manga-scraper/blob/8ccf1900b8a154db7cc244ac8c3285d35342fb4d/res/ums-manga-generic-list.php)
- [Generic novel form](https://github.com/druvx13/ultimate-manga-scraper/blob/8ccf1900b8a154db7cc244ac8c3285d35342fb4d/res/ums-novel-generic-list.php)

Madara's AJAX handler and search template were compared against a public WordPress checkout containing the theme. This is a reference copy, not an official distribution or a guarantee about every installed version:

- [AJAX handler](https://github.com/crackudosmangas/crackudos_mangas_wordpress/blob/ad255350b1931e08e27471169aac3367465498d4/wp-content/themes/madara/app/core.php)
- [Search template](https://github.com/crackudosmangas/crackudos_mangas_wordpress/blob/ad255350b1931e08e27471169aac3367465498d4/wp-content/themes/madara/madara-core/content/content-search.php)
- [Official demo AJAX JavaScript](https://live.mangabooth.com/wp-content/themes/madara/js/ajax.js)

The handler increments the posted `page` before calculating its offset. Our first request therefore sends `page=0`. It accepts the `madara-core/content/content-search` template and may append an element with class `no-posts` when the final page is reached. Empty responses also end pagination.

The search template uses `c-tabs-item__content`, `post-title`, `tab-thumb`, and paired `summary-heading` / `summary-content` elements. Metadata can be absent; extra CSS classes must not break extraction. The Alternative field is an alternative title, not a synopsis; the existing description column displays that field.

Manual HTTP smoke checks against WuxiaWorld returned distinct first and second AJAX pages and an empty response for an out-of-range page. These checks confirm transport and parsing behavior, not a complete import into a running WordPress installation.

## Rule fields

UMS stores numeric arrays, not named objects. Keep these positions intact.

| Field | Manga index | Novel index |
| --- | --- | --- |
| location | 0 | 0 |
| schedule | 1 | 1 |
| active | 2 | 2 |
| last_run | 3 | 3 |
| max | 4 | 4 |
| submit_status | 5 | 5 |
| post_author | 6 | 6 |
| default_tags | 7 | 7 |
| default_category | 8 | 8 |
| auto_categories | 9 | 9 |
| auto_tags | 10 | 10 |
| use_phantom | 11 | 11 |
| reverse_chapters | absent | 12 |
| max_manga | 12 | 13 |
| chapter_warning | 13 | 14 |
| enable_comments | 14 | 15 |
| enable_pingback | 15 | 16 |
| get_date | 16 | 17 |
| rule_translate | 17 | 18 |
| no_translate_title | 18 | 19 |
| chapter_slug | absent | 20 |
| phantom_wait | absent | 21 |
| always_first | absent | 22 |
| strip_images | absent | 23 |

Manga rules go to `ums_manga_generic_list`; novels go to `ums_novel_generic_list`. No existing rules are migrated automatically because this plugin cannot reliably distinguish earlier imports from rules added directly in UMS.
