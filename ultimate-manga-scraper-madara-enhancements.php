<?php
/*
Plugin Name: Ultimate Manga Scraper: Madara Enhancements
Description: Adds extra support for the Madara theme to the Ultimate Web Novel & Manga Scraper.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if required theme and plugins are active
function madara_enhancements_check_dependencies() {
    $theme = wp_get_theme();
    if ('Madara' != $theme->name && 'Madara' != $theme->parent_theme) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>This plugin requires the "Madara" theme to be installed and active on this site before it can function! Please install it from here: <a href="https://mangabooth.com/product/wp-madara/">Madara Theme</a></p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
        return;
    }
    if (!class_exists('WP_MANGA_STORAGE')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>This plugin requires the "Madara Core" plugin to be installed and active on this site before it can function! Please install it from here: <a href="https://mangabooth.com/product/madara-core/">Madara Core Plugin</a></p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
        return;
    }
    if (!is_plugin_active('ultimate-manga-scraper/ultimate-manga-scraper.php')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>This plugin requires the "Ultimate Web Novel & Manga Scraper" plugin to be installed and active on this site before it can function! Please install it from here: <a href="https://coderevolution.ro/web-novel-and-manga-scraper/">Ultimate Web Novel & Manga Scraper Plugin</a></p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
        return;
    }
}
add_action('admin_init', 'madara_enhancements_check_dependencies');

// Include the necessary classes for the plugin functionality
include_once plugin_dir_path(__FILE__) . 'includes/class-madara-fetcher.php';
include_once plugin_dir_path(__FILE__) . 'includes/class-madara-handler.php';

// Enqueue scripts for the admin page
function madara_enhancements_enqueue_scripts($hook) {
    // Only enqueue scripts on the specific admin page
    if ($hook != 'toplevel_page_madara-enhancements') {
        return;
    }
    wp_enqueue_script('madara-enhancements', plugin_dir_url(__FILE__) . 'assets/js/madara-enhancements.js', array('jquery'), '1.0', true);
    wp_localize_script('madara-enhancements', 'madaraEnhancements', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('madara_enhancements_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'madara_enhancements_enqueue_scripts');

// Initialize the handler class to set up hooks and actions
add_action('init', ['Madara_Handler', 'init']);