<?php
/*
Plugin Name: WP Writer
Description: Predicts whether post content is AI-generated, human-generated, or mixed, and disables the Publish button if fully AI-generated.
Version: 1.4.1
Author: Santu Dutta
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include the Composer autoloader
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

define( 'WP_WRITER_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_WRITER_VERSION', '1.4.1' );
define( 'WP_WRITER_DETECT_THRESHOLD_DEFAULT', 20 );
define( 'WP_WRITER_PLAGIARISM_DETECT_THRESHOLD_DEFAULT', 20 );

/** Switch to progressBar or progressChart. */
define( 'WP_WRITER_SCAN_RESULT', 'progressChart' );

/** Set the env development/production. */
define( 'WP_WRITER_PLUGIN_ENV', '' );

/** The production api key (Unlimited access) */
define( 'GPTZERO_API_KEY_LIVE', '7162ea24546949aa8f70fef573e85f60' );

/** Sandbox Api key with limited access. Only used for testing purpose. */
define( 'GPTZERO_API_KEY_SANDBOX', '' );

/** Set the license type free/paid. */
define( 'WP_WRITER_PLUGIN_LICENSE', 'paid' );

/** In free mode customer will get only 10 scan request. */
define( 'WP_WRITER_ALLOWED_SCAN', 10 );

use WP_Writer\Core\Load_Analyzer;
use WP_Writer\Admin\Settings;

class WP_Writer {

    private $load_analyzer;
    private $settings;
    
    public function __construct () {

        // Initialize Load_Analyzer class
        $this->load_analyzer = new Load_Analyzer();

        // Initialize admin settings class if in the admin area
        if ( is_admin() ) {
            $this->settings = new Settings();
        }

        register_activation_hook( __FILE__, array( $this, 'activation_check' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation_check' ) );
        register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall_check' ) );
    }

    public function activation_check() {
        // Do nothing
    }

    public function deactivation_check() {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to deactivate this plugin.');
        }
    }

    public static function uninstall_check() {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to uninstall this plugin.');
        }

        //delete_option( 'aicp_settings' );
    }
}

$WP_Writer = new WP_Writer();

?>
