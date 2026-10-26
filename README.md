# Ultimate Manga Scraper: Madara Enhancements

Browse a Madara site's titles from WordPress and add import rules to Ultimate Web Novel & Manga Scraper (UMS).

## Requirements

- The [Madara theme](https://mangabooth.com/product/wp-madara/) or a Madara child theme.
- Madara Core.
- [Ultimate Web Novel & Manga Scraper](https://coderevolution.ro/web-novel-and-manga-scraper/).
- PHP 7.4 or later with the DOM extension.

Upload this plugin's folder to `wp-content/plugins/`, then activate it in WordPress.

## Usage

1. Open **Madara Enhancements** in the WordPress admin menu.
2. Enter the source's AJAX URL, for example `https://wuxiaworld.site/wp-admin/admin-ajax.php`.
3. Choose **Import destination**:
   - **Generic Manga Scraper** for image-based manga.
   - **Generic Web Novels Scraper** for text novels, including WuxiaWorld.
4. Click **Save URL** to save both settings.
5. Search by title and choose a sort order. Click **Add title** next to a result to create its rule.
6. Use **Load More**, or enable **Auto Load More** to fetch more results as you scroll.

The destination is an explicit choice for the source. Both kinds of content use Madara's `wp-manga` post type, so that post type alone cannot identify a novel. On mixed sites, search and import one content type at a time, saving the destination before each search.

New rules run every 24 hours, publish under the administrator adding the rule, and allow up to 1,000 chapters. Edit these settings in UMS after adding a title. Titles already present in either generic rule list are hidden from results.

## Updating from 1.0

Select the correct destination before importing novels. This fixes [issue #1](https://github.com/ThuGie/Ultimate-Manga-Scraper--Madara-Enhancements/issues/1).

Existing rules are left in place. If a novel was previously added to **Generic Manga Scraper**, remove its incorrect rule in UMS, select **Generic Web Novels Scraper** here, and add it again. Back up your rules first if you have customized them.

Version 1.1.0 also corrects the rule field positions, handles missing source metadata and HTTP errors, checks administrator permissions on AJAX actions, escapes source data when building table rows, and prevents overlapping pagination requests. TLS certificate checks remain enabled.

## Development

Run the regression checks from the repository root:

```sh
php tests/run.php
npm ci --ignore-scripts
npm test
```

JavaScript dependencies are only needed for tests. They are not shipped to WordPress. GitHub Actions runs the PHP checks on 7.4, 8.3, and 8.4 and the browser behavior tests on Node 22.

See [COMPATIBILITY.md](COMPATIBILITY.md) for the reference sources and rule field mapping. The tests use WordPress boundary stubs; they do not replace testing on a WordPress installation with your licensed UMS and Madara versions. Sites may also block remote requests or customize their templates.

## License

GPLv3 or later. Maintained by ThuGie.
