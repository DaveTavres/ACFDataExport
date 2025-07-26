<?php
/**
 * Plugin Name: ACF Data Export
 * Description: Export ACF field data for selected post types to CSV.
 * Version: 1.0
 * Author: ChatGPT for Dave
 */

add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'ACF Data Export',
        'ACF Data Export',
        'manage_options',
        'acf-data-export',
        'acf_data_export_page'
    );
});

function acf_data_export_page() {
    ?>
    <div class="wrap">
        <h1>ACF Data Export</h1>
        <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="acf_data_export_csv">
            <label for="post_type">Select Post Type:</label>
            <select name="post_type" id="post_type">
                <?php
                $post_types = get_post_types(['public' => true], 'objects');
                foreach ($post_types as $post_type) {
                    echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->labels->singular_name) . '</option>';
                }
                ?>
            </select>
            <?php submit_button('Download CSV'); ?>
        </form>
    </div>
    <?php
}

add_action('admin_post_acf_data_export_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    $post_type = sanitize_text_field($_GET['post_type'] ?? '');
    if (!$post_type) {
        wp_die('Post type not specified');
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="acf-data-export-' . $post_type . '.csv"');

    $output = fopen('php://output', 'w');

    // Get all published posts for the selected post type
    $posts = get_posts([
        'post_type' => $post_type,
        'numberposts' => -1,
        'post_status' => 'publish',
    ]);

    if (empty($posts)) {
        fputcsv($output, ['No posts found.']);
        fclose($output);
        exit;
    }

    // Build a list of all ACF field keys actually used
    $all_fields = [];

    foreach ($posts as $post) {
        $fields = get_fields($post->ID);
        if (is_array($fields)) {
            $all_fields = array_merge($all_fields, array_keys($fields));
        }
    }

    $field_keys = array_unique($all_fields);
    sort($field_keys);

    // Write CSV header
    $header = array_merge(['Post ID', 'Post Title'], $field_keys);
    fputcsv($output, $header);

    // Write each post's data
    foreach ($posts as $post) {
        $row = [$post->ID, $post->post_title];
        $fields = get_fields($post->ID);

        foreach ($field_keys as $key) {
            $value = $fields[$key] ?? '';
            if (is_array($value)) {
                $value = json_encode($value); // encode arrays (e.g. repeater fields)
            }
            $row[] = $value;
        }

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
});