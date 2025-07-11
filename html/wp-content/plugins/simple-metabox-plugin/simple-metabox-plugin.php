<?php
/*
Plugin Name: Simple Metabox Plugin
Description: A simple WordPress plugin that adds a metabox to the right side panel.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SimpleMetaboxPlugin
{
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    public function add_meta_box()
    {
        add_meta_box(
            'simple_metabox',           // Unique ID
            'Simple Metabox',           // Box title
            array($this, 'render_meta_box'),  // Content callback, must be of type callable
            'post',                     // Post type
            'side',                     // Context ('side' places it in the right side panel)
            'high'                      // Priority
        );
    }

    public function render_meta_box($post)
    {
        // Add a nonce field so we can check for it later.
        wp_nonce_field('simple_metabox', 'simple_metabox_nonce');

        // Use get_post_meta to retrieve an existing value from the database.
        $value = get_post_meta($post->ID, '_simple_metabox_value', true);

        // Display the form field
        echo '<label for="simple_metabox_field">Meta Value:</label>';
        echo '<input type="text" id="simple_metabox_field" name="simple_metabox_field" value="' . esc_attr($value) . '" size="25" />';
    }

    public function save_meta_box_data($post_id)
    {
        // Check if our nonce is set.
        if (!isset($_POST['simple_metabox_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['simple_metabox_nonce'], 'simple_metabox')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        // Sanitize user input.
        if (!isset($_POST['simple_metabox_field'])) {
            return;
        }

        $my_data = sanitize_text_field($_POST['simple_metabox_field']);

        // Update the meta field in the database.
        update_post_meta($post_id, '_simple_metabox_value', $my_data);
    }
}

new SimpleMetaboxPlugin();