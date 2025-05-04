<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Add menu under WooCommerce
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Location Delivery Settings',
        'Location Delivery',
        'manage_options',
        'cld-location-settings',
        'cld_render_settings_page'
    );
});

// Register settings
add_action('admin_init', function() {
    register_setting('cld_settings_group', 'cld_google_maps_api_key');
    register_setting('cld_settings_group', 'cld_locations');
    register_setting('cld_settings_group', 'cld_icon_position');
    register_setting('cld_settings_group', 'cld_text_alignment');
});

function cld_render_settings_page() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
    ?>
    <div class="wrap">
        <h1>Location Delivery Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=cld-location-settings&tab=settings" class="nav-tab <?php echo $active_tab=='settings'?'nav-tab-active':''; ?>">Settings</a>
            <a href="?page=cld-location-settings&tab=instructions" class="nav-tab <?php echo $active_tab=='instructions'?'nav-tab-active':''; ?>">Instructions</a>
        </h2>
        <?php if ($active_tab == 'instructions'): ?>
            <div style="background:#fff;padding:24px 32px 24px 24px;max-width:700px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <h2>How to Use Custom Location Delivery Plugin</h2>
                <ol>
                    <li><strong>Add Locations:</strong> Use the <b>Manage Locations</b> section to add cities/areas, pincodes, <b>optional area name</b>, and upload icons.</li>
                    <li><strong>Google Maps API:</strong> Enter your Google Maps API key to enable the "Detect My Location" feature in the modal (matches by pincode only).</li>
                    <li><strong>Configure Modal Layout:</strong> Choose icon position and text alignment for the location selector modal.</li>
                    <li><strong>Enable Location Selector:</strong> Place the shortcode <code>[cld_location_selector]</code> on any page where you want users to select their delivery location. The modal will only appear on pages with this shortcode.</li>
                    <li><strong>Show Selected Location:</strong> Place the shortcode <code>[cld_selected_location]</code> anywhere (e.g., header) to display the currently selected location (e.g., "Deliver In Pune ▼").</li>
                    <li><strong>Assign Locations to Products:</strong> When editing or adding a product (admin or vendor), select the available locations for that product. Products will only be shown to users who select a matching location.</li>
                    <li><strong>Dokan Vendor Support:</strong> Vendors can assign locations to their products from the Dokan dashboard.</li>
                </ol>
                <h3>Notes</h3>
                <ul>
                    <li>The plugin only filters products and shows the modal on pages where <code>[cld_location_selector]</code> is used.</li>
                    <li>"Detect My Location" uses the Google Maps API and browser GPS to match the user's <b>pincode</b> to your available locations.</li>
                    <li>For best results, add both pincodes and (optionally) area names for each location.</li>
                </ul>
            </div>
        <?php else: ?>
        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php settings_fields('cld_settings_group'); ?>
            <h2>Google Maps API Key</h2>
            <input type="text" name="cld_google_maps_api_key" value="<?php echo esc_attr(get_option('cld_google_maps_api_key')); ?>" style="width:400px;" />
            <h2>Modal Icon/Text Layout</h2>
            <table class="form-table">
                <tr><th>Icon Position</th><td>
                    <select name="cld_icon_position">
                        <option value="above" <?php selected(get_option('cld_icon_position', 'above'), 'above'); ?>>Above Text</option>
                        <option value="beside" <?php selected(get_option('cld_icon_position'), 'beside'); ?>>Beside Text</option>
                    </select>
                </td></tr>
                <tr><th>Text Alignment</th><td>
                    <select name="cld_text_alignment">
                        <option value="center" <?php selected(get_option('cld_text_alignment', 'center'), 'center'); ?>>Center</option>
                        <option value="left" <?php selected(get_option('cld_text_alignment'), 'left'); ?>>Left</option>
                        <option value="right" <?php selected(get_option('cld_text_alignment'), 'right'); ?>>Right</option>
                    </select>
                </td></tr>
            </table>
            <h2>Manage Locations</h2>
            <div id="cld-locations-list">
                <?php cld_render_locations_table(); ?>
            </div>
            <h3>Add New Location</h3>
            <table class="form-table">
                <tr><th>Name</th><td><input type="text" id="cld_new_location_name" /></td></tr>
                <tr><th>Pincode</th><td><input type="text" id="cld_new_location_pincode" /></td></tr>
                <tr><th>Area Name (optional)</th><td><input type="text" id="cld_new_location_area" /></td></tr>
                <tr><th>Icon</th><td><input type="button" class="button" id="cld_upload_icon_btn" value="Upload Icon" /> <input type="hidden" id="cld_new_location_icon" /><span id="cld_icon_preview"></span></td></tr>
            </table>
            <button type="button" class="button button-primary" id="cld_add_location_btn">Add Location</button>
            <?php submit_button(); ?>
        </form>
        <?php endif; ?>
    </div>
    <?php
    cld_locations_admin_js();
}

function cld_render_locations_table() {
    $locations = get_option('cld_locations', array());
    if (empty($locations)) {
        echo '<p>No locations added yet.</p>';
        return;
    }
    echo '<table class="widefat"><thead><tr><th>Name</th><th>Pincode</th><th>Area Name</th><th>Icon</th><th>Action</th></tr></thead><tbody>';
    foreach ($locations as $i => $loc) {
        echo '<tr>';
        echo '<td>' . esc_html($loc['name']) . '</td>';
        echo '<td>' . esc_html($loc['pincode']) . '</td>';
        echo '<td>' . (!empty($loc['area']) ? esc_html($loc['area']) : '-') . '</td>';
        echo '<td>' . (!empty($loc['icon']) ? '<img src="' . esc_url($loc['icon']) . '" style="height:32px;" />' : '') . '</td>';
        echo '<td><button type="button" class="button cld-delete-location" data-index="' . $i . '">Delete</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function cld_locations_admin_js() {
    ?>
    <script>
    jQuery(document).ready(function($){
        // Media uploader for icon
        var mediaUploader;
        $('#cld_upload_icon_btn').on('click', function(e) {
            e.preventDefault();
            if (mediaUploader) { mediaUploader.open(); return; }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Icon',
                button: { text: 'Choose Icon' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#cld_new_location_icon').val(attachment.url);
                $('#cld_icon_preview').html('<img src="'+attachment.url+'" style="height:32px;" />');
            });
            mediaUploader.open();
        });
        // Add location
        $('#cld_add_location_btn').on('click', function(e){
            e.preventDefault();
            var name = $('#cld_new_location_name').val();
            var pincode = $('#cld_new_location_pincode').val();
            var area = $('#cld_new_location_area').val();
            var icon = $('#cld_new_location_icon').val();
            if(!name || !pincode) { alert('Please fill all required fields.'); return; }
            var data = {
                action: 'cld_add_location',
                name: name,
                pincode: pincode,
                area: area,
                icon: icon,
                _ajax_nonce: '<?php echo wp_create_nonce('cld_add_location'); ?>'
            };
            $.post(ajaxurl, data, function(resp){
                if(resp.success){
                    location.reload();
                } else {
                    alert('Error adding location');
                }
            });
        });
        // Delete location
        $('.cld-delete-location').on('click', function(){
            var idx = $(this).data('index');
            var data = {
                action: 'cld_delete_location',
                index: idx,
                _ajax_nonce: '<?php echo wp_create_nonce('cld_delete_location'); ?>'
            };
            $.post(ajaxurl, data, function(resp){
                if(resp.success){
                    location.reload();
                } else {
                    alert('Error deleting location');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handlers for add/delete location
add_action('wp_ajax_cld_add_location', function() {
    check_ajax_referer('cld_add_location');
    $locations = get_option('cld_locations', array());
    $locations[] = array(
        'name' => sanitize_text_field($_POST['name']),
        'pincode' => sanitize_text_field($_POST['pincode']),
        'area' => sanitize_text_field($_POST['area']),
        'icon' => esc_url_raw($_POST['icon'])
    );
    update_option('cld_locations', $locations);
    wp_send_json_success();
});
add_action('wp_ajax_cld_delete_location', function() {
    check_ajax_referer('cld_delete_location');
    $locations = get_option('cld_locations', array());
    $idx = intval($_POST['index']);
    if(isset($locations[$idx])){
        array_splice($locations, $idx, 1);
        update_option('cld_locations', $locations);
        wp_send_json_success();
    }
    wp_send_json_error();
}); 