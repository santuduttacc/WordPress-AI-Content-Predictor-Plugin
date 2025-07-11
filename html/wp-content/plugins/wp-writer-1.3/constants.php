<?php
// File: constants.php
namespace WP_Writer\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'PLUGIN_DIR', plugin_dir_url( __FILE__ ) );
define( 'WP_WRITER_VERSION', 1.3 );
define( 'WP_WRITER_DETECT_THRESHOLD_DEFAULT', 20 );
define( 'WP_WRITER_PLAGIARISM_DETECT_THRESHOLD_DEFAULT', 20 );
define( 'WP_WRITER_SCAN_RESULT', 'progressBar' ); // Switch to progressBar or progressChart
define( 'WP_WRITER_PLUGIN_ENV', 'development' ); // Set the env development/production

?>