<?php
/*
 * Plugin Name: Cookie-Plugin
 * Author: Santu Dutta
 * Version: 1.0.0
 * Description: This plugin aims to display a GDPR bar to allow/disallow cookies usage.
 * 
 */

function my_plugin_enqueue_assets() {
    // Enqueue JavaScript libraries
    wp_enqueue_script('js-cookies', 'https://cdnjs.cloudflare.com/ajax/libs/js-cookie/2.2.1/js.cookie.min.js', [], null, true);
    wp_enqueue_script('react', 'https://unpkg.com/react@16/umd/react.production.min.js', [], null, true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@16/umd/react-dom.production.min.js', [], null, true);
    wp_enqueue_script('babel', 'https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/6.26.0/babel.min.js', [], null, true);

    // Enqueue CSS libraries
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css', [], null);
}

// Hook the function into wp_enqueue_scripts action
add_action('wp_enqueue_scripts', 'my_plugin_enqueue_assets');

echo file_get_contents(plugin_dir_path(__FILE__)."/templates/CookieBanner.html");

?>