<?php
/**
 * Plugin Name: Suchis Autoload Inspector
 * Description: Analyse und vorsichtige Verwaltung automatisch geladener WordPress-Optionen.
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Christian Suchanek
 * License: GPL-2.0-or-later
 * Text Domain: suchis-autoload-inspector
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/class-sai-admin.php';
add_action('plugins_loaded', static function () { new SAI_Admin(); });
