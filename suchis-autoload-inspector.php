<?php
/**
 * Plugin Name: Suchis Autoload Inspector
 * Plugin URI: https://www.derperformer.com/wordperessplugin/suchis-autoload-inspector-wordpress-optionen/
 * Description: Analyse und vorsichtige Verwaltung automatisch geladener WordPress-Optionen.
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Christian Suchanek
 * License: GPL-2.0-or-later
 * Text Domain: suchis-autoload-inspector
 */
if (!defined('ABSPATH')) { exit; 
}
require_once __DIR__ . '/includes/class-sai-admin.php';
add_action(
    'plugins_loaded', static function () {
        new SAI_Admin(); 
    }
);

// Direct documentation link in this plugin's row on the Plugins screen.
add_filter(
    'plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
        $links[] = '<a href="' . esc_url('https://www.derperformer.com/wordperessplugin/suchis-autoload-inspector-wordpress-optionen/') . '">Anleitung</a>';
        return $links;
    }
);
