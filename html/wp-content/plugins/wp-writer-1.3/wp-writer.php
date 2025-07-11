<?php
/*
Plugin Name: WP Writer
Description: Predicts whether post content is AI-generated, human-generated, or mixed, and disables the Publish button if fully AI-generated.
Version: 1.3
Author: Santu Dutta
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include the constants.php file
require_once plugin_dir_path( __FILE__ ) . 'constants.php';

use WP_Writer\Constants;

class WP_Writer {

    public $version;

    public function __construct() {
        $this->version = WP_WRITER_VERSION;
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_aicp_analyze_content', array( $this, 'analyze_content' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation_check' ) );
        register_uninstall_hook( __FILE__, array( 'WP_Writer', 'uninstall_check' ) );
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

        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;

        $allowed_roles = get_option( 'aicp_settings' )['aicp_user_roles'] ?? array();

        // Check if the current user is in the allowed roles and  we are on the 'post' post type and in the add/edit post screen
        if ( !empty(array_intersect($user_roles, $allowed_roles)) && $post_type === 'post' && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
            $aicp_detect_threshold = get_option('aicp_settings')['aicp_detect_threshold'] ?? WP_WRITER_DETECT_THRESHOLD_DEFAULT;

            if ( $this->is_gutenberg_editor() ) {
                wp_enqueue_script( 'aicp-gutenberg-script', PLUGIN_DIR . 'assets/js/aicp-gutenberg.js', array( 'jquery' ), $this->version, true );
            }
        
            wp_localize_script( 'aicp-gutenberg-script', 'aicpData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'pluginUrl' => PLUGIN_DIR,
                'aicp_detect_threshold' => $aicp_detect_threshold,
                'loadScanResult' => WP_WRITER_SCAN_RESULT
            ));
        }

        wp_enqueue_script( 'aicp-settings-script', PLUGIN_DIR . 'assets/js/aicp-settings.js', array( 'jquery' ), $this->version, true );

        // Load the css files
        wp_enqueue_style( 'aicp-gutenberg-style', PLUGIN_DIR . 'assets/css/aicp-gutenberg.css' );
        wp_enqueue_style( 'aicp-settings-style', PLUGIN_DIR . 'assets/css/aicp-settings.css' );
        
        // Conditionally add Font Awesome if running on localhost
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css' );
        }

        wp_enqueue_script( 'chart', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery'), '3.3.5', true );
    }

    public function admin_menu() {
        add_menu_page(
            'WP Writer', // Page title
            'WP Writer', // Menu title
            'manage_options', // Capability
            'wp_writer', // Menu slug
            array( $this, 'general_settings_page' ), // Function to display the main page
            PLUGIN_DIR.'assets/images/star-icon-trans.svg', // Icon URL
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

        // Remove the default submenu that is automatically created by add_menu_page
        remove_submenu_page( 'wp_writer', 'wp_writer' );
    }

    public function general_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'publish_settings';
        ?>
        <div class="wrap">
            <h2>WP Writer - General Settings</h2>
                <div class="wrap-inner">
                    <div id="wp-writer-sidebar" style="float: left; width: 20%;">
                        <ul>
                            <li><a href="?page=wp_writer_general_settings&tab=scan_history" class="<?=$tab=='scan_history'?'active':'';?>"><img src="<?=PLUGIN_DIR.'assets/images/scan-history.svg'?>" /> Scan History</a></li>
                            <li><a href="?page=wp_writer_general_settings&tab=publish_settings" class="<?=$tab=='publish_settings'?'active':'';?>"><img src="<?=PLUGIN_DIR.'assets/images/publish-settings.svg'?>" /> Publish Settings</a></li>
                        </ul>
                    </div>

                    <div id="wp-writer-content" class="wp-writer-contentpart" style="float: left; width: 75%;" >
                        <?php
                        if ($tab == 'scan_history') {
                            $this->scan_history_page();
                        } else {
                            $this->publish_settings_page();
                        }
                        ?>
                    </div>
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
            'aicp_publisher_permission_section',
            __( 'Publisher Permissions', 'aicp' ),
            array( $this, 'publisher_permission_section_callback' ),
            'aicp'
        );

        add_settings_section(
            'aicp_detect_threshold_section',
            __( 'Acceptance Threshold', 'aicp' ),
            array( $this, 'detect_threshold_section_callback' ),
            'aicp'
        );

        // Register the new setting field for user roles
        add_settings_field(
            'aicp_user_roles',
            __( '', 'aicp' ),
            array( $this, 'user_roles_input' ),
            'aicp', 
            'aicp_publisher_permission_section'
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

    public function publisher_permission_section_callback() {
        echo __( 'Select user permissions to enable threshold', 'aicp' );
    }
    public function detect_threshold_section_callback() {
        echo __( 'How much margin of error would you allow your publisher to pass in order to automatically approve a post?', 'aicp' );
    }

    public function ai_detect_threshold_input() {
        $options = get_option( 'aicp_settings' );
        $aicp_detect_threshold = esc_attr( $options['aicp_detect_threshold'] ?? WP_WRITER_DETECT_THRESHOLD_DEFAULT );
        ?>
        <input type="range" id="aicp_detect_threshold" name="aicp_settings[aicp_detect_threshold]" min="0" max="100" value="<?php echo $aicp_detect_threshold; ?>">
        <output><?php echo $aicp_detect_threshold; ?></output>
        <?php
    }

    public function plagiarism_detect_threshold_input() {
        $options = get_option( 'aicp_settings' );
        $plagiarism_detect_threshold = esc_attr( $options['plagiarism_detect_threshold'] ?? WP_WRITER_PLAGIARISM_DETECT_THRESHOLD_DEFAULT ); // Default to 50 if not set
        ?>
        <input type="range" id="plagiarism_detect_threshold" name="aicp_settings[plagiarism_detect_threshold]" min="0" max="100" value="<?php echo $plagiarism_detect_threshold; ?>">
        <output><?php echo $plagiarism_detect_threshold; ?></output>
        <?php
    }

    public function user_roles_input() {
        $options = get_option( 'aicp_settings' );
        $selected_roles = $options['aicp_user_roles'] ?? array(); // Fetch selected roles from settings
        $wp_roles = wp_roles()->roles; // Get all WordPress roles
    
        foreach ( $wp_roles as $role_slug => $role_details ) {
            $checked = in_array( $role_slug, $selected_roles ) ? 'checked' : '';
            ?>
            <label>
                <input type="checkbox" name="aicp_settings[aicp_user_roles][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php echo $checked; ?> />
                <?php echo esc_html( $role_details['name'] ); ?>
            </label><br/>
            <?php
        }
    }

    public function analyze_content() {

        if ( WP_WRITER_PLUGIN_ENV == 'production' ) {
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
        } else if ( WP_WRITER_PLUGIN_ENV == 'development' ) {
            $body = '{"version":"2024-08-02-base","scanId":"b29083aa-96cb-488b-bb3c-d73b4ed23be2","documents":[{"average_generated_prob":0,"class_probabilities":{"ai":0.15,"human":0.85,"mixed":0.00306865960772228},"completely_generated_prob":0.10866665199110663,"confidence_category":"high","confidence_score":0.8882646884011711,"confidence_scores_raw":{"identity":{"ai":0.10866665199110663,"human":0.8882646884011711,"mixed":0.00306865960772228}},"confidence_thresholds_raw":{"identity":{"ai":{"low":0.82,"medium":0.92,"reject":0.7},"human":{"low":0.8,"medium":0.88,"reject":0.7},"mixed":{"low":0.75,"medium":0.88,"reject":0.7}}},"overall_burstiness":0,"paragraphs":[{"completely_generated_prob":0.11111110864197533,"num_sentences":1,"start_sentence_index":0}],"predicted_class":"human","sentences":[{"generated_prob":0.0009300839155912399,"perplexity":1214,"sentence":"india","highlight_sentence_for_ai":false}],"writing_stats":[],"result_message":"Our detector is highly confident that the text is written entirely by a human.","result_sub_message":"","document_classification":"HUMAN_ONLY","version":"2024-08-02-base","language":"en","inputText":"india"}],"editorDocumentId":null}';
            $data = json_decode($body, true);

            sleep(1);
            wp_send_json($data, 200);
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

$WP_Writer = new WP_Writer();
?>
