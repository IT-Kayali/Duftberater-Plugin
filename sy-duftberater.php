<?php
/**
 * Plugin Name: SY Duftberater
 * Plugin URI: https://it-kayali.de/
 * Description: Interaktiver Premium-Parfum-Berater mit Multi-Step-Frontend, intelligenter Matching-Logik, editierbaren Fragen und Parfum-Verwaltung im Backend.
 * Version: 1.28.30
 * Author: IT-Kayali
 * Author URI: https://it-kayali.de/
 * Text Domain: sy-duftberater
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SY_DUFTBERATER_VERSION', '1.28.30' );
define( 'SY_DUFTBERATER_DATASET_VERSION', 'sy-liste-2026-07-01-matrix-v1' );
define( 'SY_DUFTBERATER_FILE', __FILE__ );
define( 'SY_DUFTBERATER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SY_DUFTBERATER_URL', plugin_dir_url( __FILE__ ) );

require_once SY_DUFTBERATER_PATH . 'includes/class-sy-duftberater.php';

function sy_duftberater_boot_plugin() {
	$plugin = new SY_Duftberater();
	$plugin->init();
}

sy_duftberater_boot_plugin();
