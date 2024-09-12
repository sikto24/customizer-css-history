<?php
/*
Plugin Name: Customizer CSS History
Plugin URI: https://github.com/sikto24/customizer-css-history.git
Description: Tracks and restores the last 10 changes in the WordPress Customizer CSS with user details.
Version: 1.0.0
Author: SIKTO
Author URI: https://github.com/sikto24/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: customizer-css-history
Domain Path: /languages
*/


if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Customizer_CSS_History {
    private const HISTORY_LIMIT = 10;
    public function __construct() {
        // Hook into Customizer save action
        add_action('customize_save_after', [$this, 'save_customizer_css_history'], 10, 1);
        // Add admin menu item under Settings
        add_action('admin_menu', [$this, 'add_customizer_css_history_page']);
        // Handle restoration of CSS history
        add_action('admin_init', [$this, 'handle_css_restore']);
        // Show notification after restoring
        add_action('admin_notices', [$this, 'show_restoration_notice']);
        // Enqueue JavaScript
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        // AJAX action for restoring CSS
        add_action('wp_ajax_restore_css_history', [$this, 'ajax_restore_css_history']);
        // Load Text Domain
        add_action('plugins_loaded', [$this, 'customizer_css_history_textdomain']);
    }

    // Load Text Domain
    public function customizer_css_history_textdomain(){
        load_plugin_textdomain('customizer-css-history', FALSE, basename(dirname(__FILE__)) . '/languages/');
    }

    /**
     * Save the Customizer CSS history when changes are published.
     */
    public function save_customizer_css_history($wp_customize) {
        if (!current_user_can('edit_theme_options')) {
            return;
        }

        // Get current Customizer CSS
        $custom_css = wp_get_custom_css();

        // Fetch existing history from options
        $history = get_option('customizer_css_history', []);

        // Add the new entry to the history array
        $history[] = [
            'css' => $custom_css,
            'user' => wp_get_current_user()->user_login,
            'time' => current_time('mysql'),
        ];

        // Limit history to the last 10 entries
        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, -self::HISTORY_LIMIT);
        }

        // Update history option
        update_option('customizer_css_history', $history);
    }

    /**
     * Add Customizer CSS History to the Settings menu.
     */
    public function add_customizer_css_history_page() {
        add_options_page(
            __('Customizer CSS History', 'customizer-css-history'),   // Page title
            __('CSS History', 'customizer-css-history'),              // Menu title
            'manage_options',                                         // Capability
            'customizer-css-history',                                 // Menu slug
            [$this, 'customizer_css_history_page']                    // Callback function
        );
    }

    /**
     * Display the Customizer CSS history page in the admin area.
     */
    public function customizer_css_history_page() {
        $history = get_option('customizer_css_history', []);

        echo '<div class="wrap"><h2>' . __('Customizer CSS History', 'customizer-css-history') . '</h2>';
        echo '<table class="widefat fixed" cellspacing="0"><thead><tr><th>' . __('User', 'customizer-css-history') . '</th><th>' . __('Time', 'customizer-css-history') . '</th><th>' . __('Actions', 'customizer-css-history') . '</th></tr></thead><tbody>';

        foreach (array_reverse($history) as $index => $entry) {
            $css_content = esc_textarea($entry['css']);
            echo '<tr>';
            echo '<td>' . esc_html($entry['user']) . '</td>';
            echo '<td>' . esc_html($entry['time']) . '</td>';
            echo '<td>'
                . '<button class="button restore-button" data-index="' . esc_attr($index) . '" data-css="' . esc_attr($css_content) . '">' . __('Restore', 'customizer-css-history') . '</button> '
                . '<button class="button view-css-button" data-css="' . esc_attr($css_content) . '">' . __('View CSS', 'customizer-css-history') . '</button>'
                . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Handle restoring CSS history.
     */
    public function handle_css_restore() {
        if (isset($_GET['restore']) && current_user_can('edit_theme_options')) {
            $history = get_option('customizer_css_history', []);
            $restore_index = intval($_GET['restore']);

            if (isset($history[$restore_index])) {
                // Restore CSS to the selected version
                wp_update_custom_css_post($history[$restore_index]['css']);

                // Redirect to avoid resubmission
                wp_redirect(admin_url('admin.php?page=customizer-css-history&restored=true'));
                exit;
            }
        }
    }

    /**
     * Display a notification after CSS restoration.
     */
    public function show_restoration_notice() {
        if (isset($_GET['restored'])) {
            echo '<div class="updated"><p>' . __('Customizer CSS restored successfully.', 'customizer-css-history') . '</p></div>';
        }
    }

    public function enqueue_scripts($hook) {   
        if ($hook !== 'settings_page_customizer-css-history') {
            return;
        }
        wp_enqueue_style('customizer-css-history', plugin_dir_url(__FILE__) . 'css/customizer-css-history.css', '1.0.0', true);
        wp_enqueue_script('customizer-css-history', plugin_dir_url(__FILE__) . 'js/customizer-css-history.js', ['jquery'], '1.0.0', true);
        wp_localize_script('customizer-css-history', 'customizer_css_history', [
            'nonce' => wp_create_nonce('customizer_css_history_nonce')
        ]);
    }

    public function ajax_restore_css_history() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'customizer_css_history_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $restore_index = intval($_POST['index']);
        $css_content = stripslashes($_POST['css']);

        $history = get_option('customizer_css_history', []);

        if (isset($history[$restore_index])) {
            wp_update_custom_css_post($css_content);
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Invalid history index']);
        }
    }
}

// Initialize the plugin
new Customizer_CSS_History();
