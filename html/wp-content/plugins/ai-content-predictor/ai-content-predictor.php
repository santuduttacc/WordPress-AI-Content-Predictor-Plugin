<?php
/*
Plugin Name: AI Content Predictor
Description: Predicts whether post content is AI-generated, human-generated, or mixed, and disables the Publish button if fully AI-generated.
Version: 1.1
Author: Santu Dutta
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'PLUGIN_DIR', plugin_dir_url( __FILE__ ) );
class AI_Content_Predictor {

    public function __construct()
    {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'wp_ajax_aicp_analyze_content', array( $this, 'analyze_content' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation_check' ) );
        register_uninstall_hook( __FILE__, array( 'AI_Content_Predictor', 'uninstall_check' ) );
    }
    public function is_gutenberg_editor() {
        if( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) { 
            return true;
        }
        
        $current_screen = get_current_screen();
        if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
            return true;
        }
        return false;
    }
    public function enqueue_scripts( $hook ) {
        global $post_type;
        // Check if we are on the 'post' post type and in the add/edit post screen
        if ( $post_type !== 'post' || ( 'post.php' !== $hook && 'post-new.php' !== $hook ) ) {
            return;
        }
    
        if ( $this->is_gutenberg_editor() ) {
            wp_enqueue_script( 'aicp-gutenberg-script', PLUGIN_DIR . 'js/aicp-gutenberg.js', array( 'jquery', 'wp-data', 'wp-edit-post' ), '1.1', true );
        } else {
            wp_enqueue_script( 'aicp-script', PLUGIN_DIR . 'js/aicp-script.js', array( 'jquery' ), '1.1', true );
        }
        
        wp_enqueue_style( 'aicp-style', PLUGIN_DIR . 'css/aicp-style.css' );
    
        // Conditionally add Font Awesome if running on localhost
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css' );
        }
    
        wp_localize_script( 'aicp-script', 'aicpData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' )
        ));
        wp_localize_script( 'aicp-gutenberg-script', 'aicpData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' )
        ));
    }
    // Add a meta box
    public function add_meta_box() {
        add_meta_box(
            'aicp_meta_box',
            'AI Content Predictor',
            array( $this, 'meta_box_callback' ),
            'post',
            'side',
            'high'
        );
    }
    public function meta_box_callback() {
        echo '<div id="aicp_progress_bar">
                <div id="aicp_progress_ai" class="aicp_progress"></div>
                <div id="aicp_progress_human" class="aicp_progress"></div>
                <div id="aicp_progress_mixed" class="aicp_progress"></div>
              </div>
              <div id="aicp_result"></div>';
    }
    public function analyze_content() {
        $post_content = str_replace(array("\r", "\n", "\r\n"), '', $_POST['content']);
        $api_key = '7162ea24546949aa8f70fef573e85f60';
        $api_url = 'https://api.gptzero.me/v2/predict/text';
    
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key
            ),
            'body' => json_encode( array(
                'document' => $post_content,
                'version' => '',
                'multilingual' => false
            ) ),
            //'sslverify' => false, // Disable SSL verification
            'timeout' => 20 // Increase the timeout
        ) );
    
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wp_send_json( array('error' => $error_message), 500 ); // Sending a raw JSON response with an error
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
    
            wp_send_json($data, $status_code); // Sending a raw JSON response with the original API response and status code
        }
    }
    // Restrict deactivation to administrators
    public function deactivation_check() {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to deactivate this plugin.');
        }
    }
    // Restrict uninstallation to administrators
    public static function uninstall_check() {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to uninstall this plugin.');
        }

        // Clean up options or custom database tables if needed
        delete_option( 'aicp_settings' );
    }
}

$AI_Content_Predictor = new AI_Content_Predictor();
?>