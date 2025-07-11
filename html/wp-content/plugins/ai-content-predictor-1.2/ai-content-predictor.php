<?php
/*
Plugin Name: AI Content Predictor
Description: Predicts whether post content is AI-generated, human-generated, or mixed, and disables the Publish button if fully AI-generated.
Version: 1.2
Author: Santu Dutta
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'PLUGIN_DIR', plugin_dir_url( __FILE__ ) );
define( 'AICP_VERSION', 1.2 );
define( 'AICP_DETECT_THRESHOLD_DEFAULT', 20 );
define( 'AICP_PLAGIARISM_DETECT_THRESHOLD_DEFAULT', 20 );

class AI_Content_Predictor {

    public $version;

    public function __construct() {
        $this->version = AICP_VERSION;
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_aicp_analyze_content', array( $this, 'analyze_content' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
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
        if ( $post_type === 'post' && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
            $aicp_detect_threshold = get_option('aicp_settings')['aicp_detect_threshold'] ?? AICP_DETECT_THRESHOLD_DEFAULT;

            if ( $this->is_gutenberg_editor() ) {
                wp_enqueue_script( 'aicp-gutenberg-script', PLUGIN_DIR . 'js/aicp-gutenberg.js', array( 'jquery' ), $this->version, true );
            } else {
                wp_enqueue_script( 'aicp-tinymce-script', PLUGIN_DIR . 'js/aicp-tinymce.js', array( 'jquery' ), $this->version, true );
            }
        
            wp_localize_script( 'aicp-gutenberg-script', 'aicpData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'aicp_detect_threshold' => $aicp_detect_threshold
            ));
            wp_localize_script( 'aicp-tinymce-script', 'aicpData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'aicp_detect_threshold' => $aicp_detect_threshold
            ));
        }

        wp_enqueue_script( 'aicp-settings-script', PLUGIN_DIR . 'js/aicp-settings.js', array( 'jquery' ), $this->version, true );
        wp_enqueue_style( 'aicp-style', PLUGIN_DIR . 'css/aicp-style.css' );
        
        // Conditionally add Font Awesome if running on localhost
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css' );
        }
    }

    public function admin_menu() {
        add_menu_page(
            'WP Writer', // Page title
            'WP Writer', // Menu title
            'manage_options', // Capability
            'wp_writer', // Menu slug
            array( $this, 'general_settings_page' ), // Function to display the main page
            'dashicons-admin-generic', // Icon URL
            6 // Position
        );

        add_submenu_page(
            'wp_writer', // Parent slug
            'General Settings', // Page title
            'General Settings', // Menu title
            'manage_options', // Capability
            'wp_writer_general_settings', // Menu slug
            array( $this, 'general_settings_page' ) // Function to display the submenu page
        );
    }

    public function general_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h2>WP Writer - General Settings</h2>
            <div id="wp-writer-sidebar" style="float: left; width: 20%;">
                <ul>
                    <li><a href="?page=wp_writer_general_settings&tab=scan_history">Scan History</a></li>
                    <li><a href="?page=wp_writer_general_settings&tab=publish_settings">Publish Settings</a></li>
                </ul>
            </div>
            <div id="wp-writer-content" style="float: left; width: 75%;">
                <?php
                $tab = isset($_GET['tab']) ? $_GET['tab'] : 'scan_history';
                if ($tab == 'scan_history') {
                    $this->scan_history_page();
                } else {
                    $this->publish_settings_page();
                }
                ?>
            </div>
            <div style="clear: both;"></div>
        </div>
        <?php
    }

    public function scan_history_page() {
        ?>
        <!-- <h3>Scan History</h3> -->
        <p>Display scan history content here.</p>
        <?php
    }

    public function publish_settings_page() {
        ?>
        <!-- <h3>Publish Settings</h3> -->
        <form action='options.php' method='post'>
            <?php
            settings_fields( 'aicp' );
            do_settings_sections( 'aicp' );
            submit_button();
            ?>
        </form>
        <?php
    }

    public function settings_init() {
        register_setting( 'aicp', 'aicp_settings' );

        add_settings_section(
            'aicp_detect_threshold_section',
            __( 'Acceptance Threshold', 'aicp' ),
            array( $this, 'detect_threshold_section_callback' ),
            'aicp'
        );

        add_settings_field( 
            'aicp_detect_threshold',
            __( 'AI Detection Score', 'aicp' ),
            array( $this, 'ai_detect_threshold_input' ),
            'aicp', 
            'aicp_detect_threshold_section' 
        );

        add_settings_field(
            'plagiarism_detect_threshold',
            __( 'Plagiarism Detection Score', 'aicp' ),
            array( $this, 'plagiarism_detect_threshold_input' ),
            'aicp', 
            'aicp_detect_threshold_section' 
        );
    }

    public function detect_threshold_section_callback() {
        echo __( 'How much margin of error would you allow your publisher to pass in order to automatically approve a post?', 'aicp' );
    }

    public function ai_detect_threshold_input() {
        $options = get_option( 'aicp_settings' );
        $aicp_detect_threshold = esc_attr( $options['aicp_detect_threshold'] ?? AICP_DETECT_THRESHOLD_DEFAULT );
        ?>
        <input type="range" id="aicp_detect_threshold" name="aicp_settings[aicp_detect_threshold]" min="0" max="100" value="<?php echo $aicp_detect_threshold; ?>">
        <output><?php echo $aicp_detect_threshold; ?></output>
        <?php
    }

    public function plagiarism_detect_threshold_input() {
        $options = get_option( 'aicp_settings' );
        $plagiarism_detect_threshold = esc_attr( $options['plagiarism_detect_threshold'] ?? AICP_PLAGIARISM_DETECT_THRESHOLD_DEFAULT ); // Default to 50 if not set
        ?>
        <input type="range" id="plagiarism_detect_threshold" name="aicp_settings[plagiarism_detect_threshold]" min="0" max="100" value="<?php echo $plagiarism_detect_threshold; ?>">
        <output><?php echo $plagiarism_detect_threshold; ?></output>
        <?php
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
            'timeout' => 20
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

    public function deactivation_check() {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to deactivate this plugin.');
        }
    }

    public static function uninstall_check() {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to uninstall this plugin.');
        }

        delete_option( 'aicp_settings' );
    }
}

$AI_Content_Predictor = new AI_Content_Predictor();
?>
