<?php
/*
Plugin Name: Custom Location Delivery
Description: WooCommerce plugin for location-based delivery selection, product filtering, and Dokan vendor support.
Version: 1.0.0
Author: Sai Akash Neela
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CLD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

date_default_timezone_set('UTC');

// Include admin settings
if ( is_admin() ) {
    require_once CLD_PLUGIN_PATH . 'admin/settings-page.php';
}

// Enqueue assets
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'cld-style', CLD_PLUGIN_URL . 'assets/css/cld-style.css' );
    wp_enqueue_script( 'cld-script', CLD_PLUGIN_URL . 'assets/js/cld-script.js', array('jquery'), null, true );
    $api_key = get_option('cld_google_maps_api_key');
    if ($api_key) {
        wp_add_inline_script('cld-script', 'var cld_maps_api_key = ' . json_encode($api_key) . ';', 'before');
    }
});

// Register activation hook
register_activation_hook( __FILE__, function() {
    // Activation tasks (e.g., create custom tables if needed)
} );

// Global flag to check if shortcode is used
$GLOBALS['cld_shortcode_used'] = false;

// Register shortcode for location selector
add_shortcode('cld_location_selector', function() {
    $GLOBALS['cld_shortcode_used'] = true;
    ob_start();
    cld_location_selector_popup(true); // true = force output
    return ob_get_clean();
});

// Shortcode to display selected location in header or anywhere
add_shortcode('cld_selected_location', function() {
    $default = 'Select Location';
    $out = '<span id="cld-selected-location">' . esc_html($default) . ' ▼</span>';
    $out .= '<script>jQuery(function($){
        var loc = localStorage.getItem("cld_selected_location");
        if(loc) $("#cld-selected-location").text("Deliver In " + loc + " ▼");
        $(document).on("cld_location_changed", function(e, name){
            $("#cld-selected-location").text("Deliver In " + name + " ▼");
        });
    });</script>';
    return $out;
});

// Modified popup function to allow forced output
function cld_location_selector_popup($force = false) {
    if (is_admin()) return;
    if (!$force && empty($GLOBALS['cld_shortcode_used'])) return;
    $locations = get_option('cld_locations', array());
    $icon_position = get_option('cld_icon_position', 'above');
    $text_alignment = get_option('cld_text_alignment', 'center');
    ?>
    <div id="cld-location-modal">
        <div class="cld-modal-content">
            <button id="cld-close-modal">&times;</button>
            <h2>Select Location</h2>
            <div class="cld-location-options">
                <?php foreach($locations as $loc): ?>
                    <div class="cld-location-option" data-pincode="<?php echo esc_attr($loc['pincode']); ?>" data-name="<?php echo esc_attr($loc['name']); ?>" style="text-align:<?php echo esc_attr($text_alignment); ?>;display:flex;flex-direction:<?php echo $icon_position==='beside'?'row':'column'; ?>;align-items:center;justify-content:center;gap:<?php echo $icon_position==='beside'?'10px':'0'; ?>;">
                        <?php if (!empty($loc['icon'])): ?><img src="<?php echo esc_url($loc['icon']); ?>" alt="<?php echo esc_attr($loc['name']); ?>" /><?php endif; ?>
                        <div><?php echo esc_html($loc['name']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button id="cld-detect-location-btn" type="button">Detect My Location</button>
        </div>
    </div>
    <script>
    jQuery(function($){
        if(!localStorage.getItem('cld_selected_location')) $('#cld-location-modal').show();
        $('.cld-location-option').on('click', function(){
            var name = $(this).data('name');
            var pincode = $(this).data('pincode');
            localStorage.setItem('cld_selected_location', name);
            localStorage.setItem('cld_selected_pincode', pincode);
            $(document).trigger('cld_location_changed', [name]);
            $('#cld-location-modal').hide();
            location.reload();
        });
        $('#cld-close-modal').on('click', function(){ $('#cld-location-modal').hide(); });
        // Placeholder for Detect My Location button
        $('#cld-detect-location-btn').on('click', function(){
            alert('Detect My Location coming soon!');
        });
    });
    </script>
    <?php
}

// Remove automatic popup on all pages
remove_action('wp_footer', 'cld_location_selector_popup');

// Only filter products if shortcode is used
add_action('pre_get_posts', function($q) {
    if (is_admin() || !$q->is_main_query() || !($q->is_shop() || $q->is_product_category() || $q->is_product_tag())) return;
    if (empty($GLOBALS['cld_shortcode_used'])) return;
    $loc = isset($_COOKIE['cld_selected_location']) ? $_COOKIE['cld_selected_location'] : (isset($_SERVER['HTTP_X_CLD_LOCATION']) ? $_SERVER['HTTP_X_CLD_LOCATION'] : null);
    if (!$loc && isset($_COOKIE['cld_selected_location'])) $loc = $_COOKIE['cld_selected_location'];
    if (!$loc && isset($_GET['cld_location'])) $loc = sanitize_text_field($_GET['cld_location']);
    if (!$loc && isset($_POST['cld_location'])) $loc = sanitize_text_field($_POST['cld_location']);
    if (!$loc && isset($_SESSION['cld_selected_location'])) $loc = $_SESSION['cld_selected_location'];
    if (!$loc && isset($_COOKIE['cld_selected_location'])) $loc = $_COOKIE['cld_selected_location'];
    if (!$loc) return;
    $q->set('meta_query', array(
        array(
            'key' => '_cld_locations',
            'value' => $loc,
            'compare' => 'LIKE',
        )
    ));
});

// Add meta box to WooCommerce product edit page
add_action('add_meta_boxes', function() {
    add_meta_box('cld_product_locations', 'Available Locations', 'cld_product_locations_meta_box', 'product', 'side');
});
function cld_product_locations_meta_box($post) {
    $locations = get_option('cld_locations', array());
    $selected = get_post_meta($post->ID, '_cld_locations', true);
    if (!is_array($selected)) $selected = array();
    foreach($locations as $loc) {
        $checked = in_array($loc['name'], $selected) ? 'checked' : '';
        echo '<label><input type="checkbox" name="cld_locations[]" value="'.esc_attr($loc['name']).'" '.$checked.'> '.esc_html($loc['name']).'</label><br>';
    }
}
add_action('save_post_product', function($post_id) {
    if (isset($_POST['cld_locations'])) {
        update_post_meta($post_id, '_cld_locations', array_map('sanitize_text_field', $_POST['cld_locations']));
    } else {
        delete_post_meta($post_id, '_cld_locations');
    }
});

// Dokan: Add location selection to vendor product add/edit form
add_action('dokan_new_product_after_product_tags', function() {
    $locations = get_option('cld_locations', array());
    echo '<div class="dokan-form-group"><label><strong>Available Locations</strong></label><br>';
    foreach($locations as $loc) {
        echo '<label style="margin-right:10px;"><input type="checkbox" name="cld_locations[]" value="'.esc_attr($loc['name']).'"> '.esc_html($loc['name']).'</label>';
    }
    echo '</div>';
});
add_action('dokan_product_edit_after_product_tags', function($post, $post_id) {
    $locations = get_option('cld_locations', array());
    $selected = get_post_meta($post_id, '_cld_locations', true);
    if (!is_array($selected)) $selected = array();
    echo '<div class="dokan-form-group"><label><strong>Available Locations</strong></label><br>';
    foreach($locations as $loc) {
        $checked = in_array($loc['name'], $selected) ? 'checked' : '';
        echo '<label style="margin-right:10px;"><input type="checkbox" name="cld_locations[]" value="'.esc_attr($loc['name']).'" '.$checked.'> '.esc_html($loc['name']).'</label>';
    }
    echo '</div>';
}, 99, 2);
add_action('dokan_new_product_added', function($product_id, $postdata) {
    if (isset($_POST['cld_locations'])) {
        update_post_meta($product_id, '_cld_locations', array_map('sanitize_text_field', $_POST['cld_locations']));
    }
}, 10, 2);
add_action('dokan_product_updated', function($product_id, $postdata) {
    if (isset($_POST['cld_locations'])) {
        update_post_meta($product_id, '_cld_locations', array_map('sanitize_text_field', $_POST['cld_locations']));
    } else {
        delete_post_meta($product_id, '_cld_locations');
    }
}, 10, 2);

// ... existing code ... 