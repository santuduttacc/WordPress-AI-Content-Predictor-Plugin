<?php

namespace WP_Writer\Admin;

class Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
    }

    public function admin_menu() {
        add_menu_page(
            'WP Writer', // Page title
            'WP Writer', // Menu title
            'manage_options', // Capability
            'wp_writer', // Menu slug
            array( $this, 'general_settings_page' ), // Function to display the main page
            WP_WRITER_DIR_URL.'assets/images/star-icon-trans.svg', // Icon URL
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
                            <li><a href="?page=wp_writer_general_settings&tab=scan_history" class="<?=$tab=='scan_history'?'active':'';?>"><img src="<?=WP_WRITER_DIR_URL.'assets/images/scan-history.svg'?>" /> Scan History</a></li>
                            <li><a href="?page=wp_writer_general_settings&tab=publish_settings" class="<?=$tab=='publish_settings'?'active':'';?>"><img src="<?=WP_WRITER_DIR_URL.'assets/images/publish-settings.svg'?>" /> Publish Settings</a></li>
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

}
?>