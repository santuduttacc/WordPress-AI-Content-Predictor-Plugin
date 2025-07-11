<?php

namespace WP_Writer\Core;

class Load_Analyzer {

    private $current_user;
    private $scan_count;

    public function __construct() {
        // Use init hook to ensure current user is available
        add_action( 'init', array( $this, 'initialize_user_data' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_aicp_analyze_content', array( $this, 'analyze_content' ) );
    }

    // This method will be run after WordPress has initialized
    public function initialize_user_data() {
        $this->current_user = wp_get_current_user();
        $this->scan_count = (int) get_user_meta($this->current_user->ID, 'wp_writer_scan_count', true);
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
        $user_roles = $this->current_user->roles;

        $allowed_roles = get_option( 'aicp_settings' )['aicp_user_roles'] ?? array();

        // Check if the current user is in the allowed roles and  we are on the 'post' post type and in the add/edit post screen
        if ( !empty(array_intersect($user_roles, $allowed_roles)) && $post_type === 'post' && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
            $aicp_detect_threshold = get_option('aicp_settings')['aicp_detect_threshold'] ?? WP_WRITER_DETECT_THRESHOLD_DEFAULT;

            if ( $this->is_gutenberg_editor() ) {
                wp_enqueue_script( 'aicp-gutenberg-script', WP_WRITER_DIR_URL . 'assets/js/aicp-gutenberg.js', array( 'jquery' ), WP_WRITER_VERSION, true );
            }
        
            wp_localize_script( 'aicp-gutenberg-script', 'aicpData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'pluginUrl' => WP_WRITER_DIR_URL,
                'aicp_detect_threshold' => $aicp_detect_threshold,
                'loadScanResult' => WP_WRITER_SCAN_RESULT
            ));
        }

        wp_enqueue_script( 'aicp-settings-script', WP_WRITER_DIR_URL . 'assets/js/aicp-settings.js', array( 'jquery' ), WP_WRITER_VERSION, true );

        // Load the css files
        wp_enqueue_style( 'aicp-gutenberg-style', WP_WRITER_DIR_URL . 'assets/css/aicp-gutenberg.css' );
        wp_enqueue_style( 'aicp-settings-style', WP_WRITER_DIR_URL . 'assets/css/aicp-settings.css' );
        
        // Conditionally add Font Awesome if running on localhost
        // if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        //     wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css' );
        // }

        // Conditionally add Font Awesome only if it's not already registered or enqueued
        if ( !wp_style_is( 'font-awesome', 'enqueued' ) && !wp_style_is( 'font-awesome', 'registered' ) ) {
            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css' );
        }

        wp_enqueue_script( 'chart', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery'), '3.3.5', true );
    }

    public function analyze_content() {

        if ( WP_WRITER_PLUGIN_LICENSE == 'free' && $this->scan_count >= WP_WRITER_ALLOWED_SCAN ) {
            wp_send_json(array('error' => 'You have reached the limit of '.WP_WRITER_ALLOWED_SCAN.' scans. Upgrade to a premium plan to continue.'), 403);
            return;
        }

        if ( WP_WRITER_PLUGIN_ENV == 'production' ) {
            $post_content = str_replace(array("\r", "\n", "\r\n"), '', $_POST['content']);
            $this->sendAnalyzeResponse (GPTZERO_API_KEY_LIVE, $post_content);
            
        } else if ( WP_WRITER_PLUGIN_ENV == 'development' ) {
            $post_content = str_replace(array("\r", "\n", "\r\n"), '', $_POST['content']);
            $this->sendAnalyzeResponse (GPTZERO_API_KEY_SANDBOX, $post_content);

        } else {
            $body = '{"version":"2024-08-02-base","scanId":"b29083aa-96cb-488b-bb3c-d73b4ed23be2","documents":[{"average_generated_prob":0,"class_probabilities":{"ai":0.15,"human":0.85,"mixed":0.00306865960772228},"completely_generated_prob":0.10866665199110663,"confidence_category":"high","confidence_score":0.8882646884011711,"confidence_scores_raw":{"identity":{"ai":0.10866665199110663,"human":0.8882646884011711,"mixed":0.00306865960772228}},"confidence_thresholds_raw":{"identity":{"ai":{"low":0.82,"medium":0.92,"reject":0.7},"human":{"low":0.8,"medium":0.88,"reject":0.7},"mixed":{"low":0.75,"medium":0.88,"reject":0.7}}},"overall_burstiness":0,"paragraphs":[{"completely_generated_prob":0.11111110864197533,"num_sentences":1,"start_sentence_index":0}],"predicted_class":"human","sentences":[{"generated_prob":0.0009300839155912399,"perplexity":1214,"sentence":"india","highlight_sentence_for_ai":false}],"writing_stats":[],"result_message":"Our detector is highly confident that the text is written entirely by a human.","result_sub_message":"","document_classification":"HUMAN_ONLY","version":"2024-08-02-base","language":"en","inputText":"india"}],"editorDocumentId":null}';
            $data = json_decode($body, true);

            // Increment the scan count after a successful scan
            //update_user_meta($this->current_user->ID, 'wp_writer_scan_count', ++$this->scan_count);

            //sleep(1);
            wp_send_json($data, 200);
        }
    }

    public function sendAnalyzeResponse ($api_key, $post_content) {
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

            // Increment the scan count after a successful scan
            update_user_meta($this->current_user->ID, 'wp_writer_scan_count', ++$this->scan_count);
    
            wp_send_json($data, $status_code); // Sending a raw JSON response with the original API response and status code
        }
    }
}
?>