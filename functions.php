<?php
/**
 * Astra child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
/**
 * Enqueue child theme styles
 */
function child_enqueue_styles() {
	$css_file = get_stylesheet_directory() . '/style.css';

	wp_enqueue_style(
		'astra-child-theme-css',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-theme-css' ),
		filemtime( $css_file ),
		'all'
	);
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );



/**
 * Change the breakpoint of the Astra Header Menus
 * 
 * @return int Screen width when the header should change to the mobile header.*/
 
// Update your custom tablet breakpoint below - like return 1024;
add_filter( 'astra_tablet_breakpoint', function() {
    return 1200;
});

// Update your custom mobile breakpoint below - like return 544;
add_filter( 'astra_mobile_breakpoint', function() {
    return 768;
});



// Add custom JavaScript to control Gutenslider speed
function custom_gutenslider_speed_script() {
    ?>
    <script>
    // Wait for window to fully load, then update slider speed
    window.addEventListener('load', function() {
        setTimeout(function() {
            // Find all Gutenslider instances
            const swiperContainers = document.querySelectorAll('.swiper[data-settings]');
            
            swiperContainers.forEach(function(container) {
                if (container.swiper) {
                    const swiperInstance = container.swiper;
                    
                    // Update autoplay delay if autoplay is enabled
                    if (swiperInstance.params.autoplay && typeof swiperInstance.params.autoplay === 'object') {
                        swiperInstance.params.autoplay.delay = 10000; // 10 seconds per slide
                    }
                    
                    // Update transition speed
                    swiperInstance.params.speed = 2000; // 2 seconds transition
                    
                    // Apply changes
                    swiperInstance.update();
                    
                    // Restart autoplay if running
                    if (swiperInstance.autoplay && swiperInstance.autoplay.running) {
                        swiperInstance.autoplay.stop();
                        swiperInstance.autoplay.start();
                    }
                }
            });
        }, 500);
    });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gutenslider_speed_script');




/**
 * Simple Job Board - Sort jobs by date (newest first) + 2 Column Grid Layout
 * Add this to your Astra Child theme's functions.php
 */

// Sort jobs by date for shortcode
add_filter('sjb_output_jobs_args', 'sjb_sort_by_latest_date', 10, 1);
function sjb_sort_by_latest_date($args) {
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
    return $args;
}

// Sort jobs by date for archive page
add_filter('sjb_archive_output_jobs_args', 'sjb_sort_archive_by_latest_date', 10, 1);
function sjb_sort_archive_by_latest_date($args) {
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
    return $args;
}

// Add custom CSS for 2-column grid layout
add_action('wp_head', 'sjb_custom_two_column_grid_css');
function sjb_custom_two_column_grid_css() {
    ?>
    <style>
        /* Simple Job Board - Force 2 Column Grid Layout */
        
        /* Override Bootstrap col-md-4 to create 2 columns instead of 3 */
        .sjb-page .grid-item.col-md-4 {
            width: 50% !important;
            flex: 0 0 50% !important;
            max-width: 50% !important;
        }
        
        /* Ensure proper spacing between columns */
        .sjb-page .row {
            margin-left: -15px;
            margin-right: -15px;
        }
        
        .sjb-page .grid-item {
            padding-left: 15px;
            padding-right: 15px;
            margin-bottom: 30px;
        }
        
        /* Responsive: Stack to 1 column on tablets and mobile */
        @media (max-width: 768px) {
            .sjb-page .grid-item.col-md-4 {
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
        }
        
        /* Optional: Add equal height to job cards for better visual alignment */
        .sjb-page .grid-item .list-data {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .sjb-page .grid-item .v1 {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
    </style>
    <?php
}





// ============================================
// AUTO-GENERATE AND SAVE META DESCRIPTIONS
// (Let Yoast SEO handle the output)
// ============================================

// PART 2: Bulk Generate and SAVE meta descriptions to database
function generate_and_save_meta_descriptions() {
    // Only run for admins and in admin area
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    
    // Check if we should run this (triggered manually via admin page)
    if (!isset($_GET['generate_meta_descriptions'])) {
        return;
    }
    
    // Security check
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'generate_meta_descriptions_nonce')) {
        wp_die('Security check failed');
    }
    
    // Get ALL public post types (including custom post types like jobs)
    $post_types = get_post_types(array('public' => true), 'names');
    
    // Remove attachments from the list
    unset($post_types['attachment']);
    
    // Get all posts/pages/custom post types without meta descriptions
    $args = array(
        'post_type' => $post_types, // Now includes ALL public post types
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    
    $posts = get_posts($args);
    $generated = 0;
    $skipped = 0;
    $by_post_type = array(); // Track stats by post type
    
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $post_type = $post->post_type;
        
        // Initialize counter for this post type
        if (!isset($by_post_type[$post_type])) {
            $by_post_type[$post_type] = array('generated' => 0, 'skipped' => 0);
        }
        
        // Check if meta description already exists
        $existing_desc = '';
        
        // Check Yoast
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!empty($yoast_desc)) {
            $existing_desc = $yoast_desc;
        }
        
        // Check Rank Math
        if (empty($existing_desc)) {
            $rankmath_desc = get_post_meta($post_id, 'rank_math_description', true);
            if (!empty($rankmath_desc)) {
                $existing_desc = $rankmath_desc;
            }
        }
        
        // Skip if already has description
        if (!empty($existing_desc)) {
            $skipped++;
            $by_post_type[$post_type]['skipped']++;
            continue;
        }
        
        // Generate description
        $content = $post->post_content;
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        $description = '';
        
        if (!empty($content) && strlen($content) > 50) {
            // Get first ~155 characters
            $description = substr($content, 0, 155);
            
            // Try to end at sentence
            $last_period = max(
                strrpos($description, '.'),
                strrpos($description, '?'),
                strrpos($description, '!')
            );
            
            if ($last_period > 100) {
                $description = substr($description, 0, $last_period + 1);
            } else {
                $last_space = strrpos($description, ' ');
                if ($last_space !== false) {
                    $description = substr($description, 0, $last_space) . '...';
                } else {
                    $description .= '...';
                }
            }
        } else {
            // Use excerpt as fallback
            $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($content, 25);
            $description = substr($excerpt, 0, 155);
        }
        
        // Ensure minimum length
        if (strlen($description) < 100 && !empty($content)) {
            $description = wp_trim_words($content, 25, '...');
        }
        
        // Last resort: use title if content is too short
        if (strlen($description) < 50) {
            $description = get_the_title($post_id) . ' - ' . get_bloginfo('name');
        }
        
        // Clean up description
        $description = trim($description);
        $description = str_replace(array("\r", "\n", "\t"), ' ', $description);
        $description = preg_replace('/\s+/', ' ', $description);
        
        // Save to appropriate meta field
        if (!empty($description)) {
            // Detect which SEO plugin is active and save accordingly
            if (class_exists('WPSEO_Meta')) {
                // Yoast SEO
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
                $generated++;
                $by_post_type[$post_type]['generated']++;
            } elseif (class_exists('RankMath')) {
                // Rank Math
                update_post_meta($post_id, 'rank_math_description', $description);
                $generated++;
                $by_post_type[$post_type]['generated']++;
            } else {
                // No SEO plugin - save to custom meta field
                update_post_meta($post_id, '_custom_meta_description', $description);
                $generated++;
                $by_post_type[$post_type]['generated']++;
            }
        }
    }
    
    // Store detailed stats for display
    update_option('_meta_desc_generation_stats', $by_post_type);
    
    // Redirect to avoid re-running on refresh
    wp_redirect(admin_url('tools.php?page=generate-meta-descriptions&generated=' . $generated . '&skipped=' . $skipped));
    exit;
}
add_action('admin_init', 'generate_and_save_meta_descriptions');

// PART 3: Add admin menu to trigger generation
function add_meta_generator_menu() {
    add_management_page(
        'Generate Meta Descriptions',
        'Meta Descriptions',
        'manage_options',
        'generate-meta-descriptions',
        'meta_generator_page'
    );
}
add_action('admin_menu', 'add_meta_generator_menu');

function meta_generator_page() {
    // Check for success message
    $generated = isset($_GET['generated']) ? intval($_GET['generated']) : 0;
    $skipped = isset($_GET['skipped']) ? intval($_GET['skipped']) : 0;
    
    if ($generated > 0 || $skipped > 0) {
        $by_post_type = get_option('_meta_desc_generation_stats', array());
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>✅ Meta Descriptions Generated!</strong></p>';
        echo '<p>Total Generated: <strong>' . $generated . '</strong> | Total Skipped: <strong>' . $skipped . '</strong></p>';
        
        if (!empty($by_post_type)) {
            echo '<p><strong>Breakdown by Post Type:</strong></p>';
            echo '<ul>';
            foreach ($by_post_type as $post_type => $stats) {
                $post_type_obj = get_post_type_object($post_type);
                $post_type_name = $post_type_obj ? $post_type_obj->labels->name : $post_type;
                echo '<li>' . esc_html($post_type_name) . ': Generated ' . $stats['generated'] . ', Skipped ' . $stats['skipped'] . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    
    // Detect which SEO plugin is active
    $seo_plugin = 'None';
    if (class_exists('WPSEO_Meta')) {
        $seo_plugin = 'Yoast SEO';
    } elseif (class_exists('RankMath')) {
        $seo_plugin = 'Rank Math';
    }
    
    // Get all public post types to show what will be processed
    $post_types = get_post_types(array('public' => true), 'objects');
    unset($post_types['attachment']);
    
    ?>
    <div class="wrap">
        <h1>🏷️ Auto-Generate Meta Descriptions</h1>
        
        <div class="card" style="max-width: 800px;">
            <h2>Current Setup</h2>
            <table class="widefat" style="max-width: 600px;">
                <tr>
                    <td><strong>SEO Plugin Detected:</strong></td>
                    <td><?php echo esc_html($seo_plugin); ?></td>
                </tr>
                <tr>
                    <td><strong>Meta Tag Output:</strong></td>
                    <td>✅ Handled by <?php echo esc_html($seo_plugin); ?></td>
                </tr>
                <tr>
                    <td><strong>Post Types to Process:</strong></td>
                    <td>
                        <?php 
                        $type_names = array();
                        foreach ($post_types as $post_type) {
                            $type_names[] = $post_type->labels->name;
                        }
                        echo esc_html(implode(', ', $type_names));
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Bulk Generate Meta Descriptions</h2>
            <p>This will automatically generate meta descriptions for all posts, pages, and custom post types that don't have one.</p>
            <p><strong>Note:</strong> This will NOT overwrite existing meta descriptions.</p>
            
            <form method="get" action="<?php echo admin_url('tools.php'); ?>">
                <input type="hidden" name="page" value="generate-meta-descriptions">
                <input type="hidden" name="generate_meta_descriptions" value="1">
                <?php wp_nonce_field('generate_meta_descriptions_nonce'); ?>
                <?php submit_button('Generate Missing Meta Descriptions', 'primary', 'submit', false); ?>
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>How It Works</h2>
            
            <ul>
                <li>✅ Scans all published posts, pages, and custom post types</li>
                <li>✅ Includes: <?php echo esc_html(implode(', ', $type_names)); ?></li>
                <li>✅ Skips any that already have meta descriptions in <?php echo esc_html($seo_plugin); ?></li>
                <li>✅ Generates 120-160 character descriptions from content</li>
                <li>✅ Saves to <?php echo esc_html($seo_plugin); ?> meta fields</li>
                <li>✅ <?php echo esc_html($seo_plugin); ?> will automatically output the <code>&lt;meta name="description"&gt;</code> tags</li>
                <li>✅ You can edit them manually later in the post/page editor</li>
            </ul>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>After Generation</h2>
            <ol>
                <li>Click the button above to generate descriptions</li>
                <li>Review the generated descriptions in your posts/pages (edit in <?php echo esc_html($seo_plugin); ?> section)</li>
                <li>Edit any that need improvement for better SEO</li>
                <li>View page source to verify only ONE <code>&lt;meta name="description"&gt;</code> tag is present</li>
                <li>Submit your sitemap to Bing Webmaster Tools</li>
            </ol>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Verify Output</h2>
            <p>To verify meta descriptions are working correctly:</p>
            <ol>
                <li>Visit any page on your site</li>
                <li>Right-click and select "View Page Source"</li>
                <li>Search for <code>&lt;meta name="description"</code></li>
                <li>You should see ONLY ONE tag: <code>&lt;meta name="description" content="..." class="yoast-seo-meta-tag"&gt;</code></li>
            </ol>
        </div>
    </div>
    <?php
}


// ============================================
// INDEXNOW INTEGRATION WITH ADMIN DASHBOARD
// ============================================

// Your IndexNow API Key - REPLACE THIS!
define('INDEXNOW_API_KEY', '93447ba3fa2e4fd38d948ff5a944a930');

// Submit URLs to IndexNow when posts are published or updated
function submit_to_indexnow($post_id, $post, $update) {
    // Only for published posts/pages
    if ($post->post_status !== 'publish') {
        return;
    }
    
    // Skip revisions and autosaves
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    
    // Only submit for post types we care about
    $allowed_post_types = array('post', 'page');
    if (!in_array($post->post_type, $allowed_post_types)) {
        return;
    }
    
    // Get the post URL
    $url = get_permalink($post_id);
    
    // Prepare IndexNow submission
    $indexnow_url = 'https://api.indexnow.org/indexnow?' . http_build_query(array(
        'url' => $url,
        'key' => INDEXNOW_API_KEY
    ));
    
    // Submit to IndexNow
    $response = wp_remote_get($indexnow_url, array(
        'timeout' => 10,
        'sslverify' => true
    ));
    
    // Prepare log entry
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'url' => $url,
        'post_id' => $post_id,
        'post_title' => $post->post_title,
        'action' => $update ? 'updated' : 'published'
    );
    
    // Process response
    if (is_wp_error($response)) {
        $log_entry['status'] = 'error';
        $log_entry['message'] = $response->get_error_message();
        $log_entry['status_code'] = 'N/A';
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $log_entry['status_code'] = $status_code;
        $log_entry['response_body'] = $response_body;
        
        // Determine status based on response code
        if ($status_code == 200) {
            $log_entry['status'] = 'success';
            $log_entry['message'] = 'URL successfully submitted to IndexNow';
            update_post_meta($post_id, '_indexnow_submitted', current_time('mysql'));
        } elseif ($status_code == 202) {
            $log_entry['status'] = 'accepted';
            $log_entry['message'] = 'URL accepted by IndexNow (processing)';
            update_post_meta($post_id, '_indexnow_submitted', current_time('mysql'));
        } elseif ($status_code == 400) {
            $log_entry['status'] = 'error';
            $log_entry['message'] = 'Bad Request - Invalid format or parameters';
        } elseif ($status_code == 403) {
            $log_entry['status'] = 'error';
            $log_entry['message'] = 'Forbidden - Invalid API key';
        } elseif ($status_code == 422) {
            $log_entry['status'] = 'error';
            $log_entry['message'] = 'Unprocessable Entity - URL not owned by site';
        } elseif ($status_code == 429) {
            $log_entry['status'] = 'error';
            $log_entry['message'] = 'Too Many Requests - Rate limit exceeded';
        } else {
            $log_entry['status'] = 'error';
            $log_entry['message'] = 'Unexpected response code: ' . $status_code;
        }
    }
    
    // Save to log
    $log = get_option('indexnow_submission_log', array());
    array_unshift($log, $log_entry); // Add to beginning
    
    // Keep only last 100 entries
    $log = array_slice($log, 0, 100);
    
    update_option('indexnow_submission_log', $log);
    
    // Update statistics
    update_indexnow_stats($log_entry['status']);
}
add_action('save_post', 'submit_to_indexnow', 10, 3);

// Update statistics
function update_indexnow_stats($status) {
    $stats = get_option('indexnow_stats', array(
        'total' => 0,
        'success' => 0,
        'accepted' => 0,
        'error' => 0,
        'last_submission' => ''
    ));
    
    $stats['total']++;
    $stats[$status] = isset($stats[$status]) ? $stats[$status] + 1 : 1;
    $stats['last_submission'] = current_time('mysql');
    
    update_option('indexnow_stats', $stats);
}

// Add admin menu
function indexnow_admin_menu() {
    add_menu_page(
        'IndexNow Dashboard',
        'IndexNow',
        'manage_options',
        'indexnow-dashboard',
        'indexnow_dashboard_page',
        'dashicons-cloud-upload',
        65
    );
    
    add_submenu_page(
        'indexnow-dashboard',
        'IndexNow Logs',
        'Submission Logs',
        'manage_options',
        'indexnow-logs',
        'indexnow_logs_page'
    );
    
    add_submenu_page(
        'indexnow-dashboard',
        'IndexNow Settings',
        'Settings',
        'manage_options',
        'indexnow-settings',
        'indexnow_settings_page'
    );
}
add_action('admin_menu', 'indexnow_admin_menu');

// Dashboard page
function indexnow_dashboard_page() {
    $stats = get_option('indexnow_stats', array(
        'total' => 0,
        'success' => 0,
        'accepted' => 0,
        'error' => 0,
        'last_submission' => 'Never'
    ));
    
    $log = get_option('indexnow_submission_log', array());
    $recent_submissions = array_slice($log, 0, 5);
    
    // Check if API key file exists
    $key_file = ABSPATH . INDEXNOW_API_KEY . '.txt';
    $key_file_exists = file_exists($key_file);
    
    ?>
    <div class="wrap">
        <h1>📊 IndexNow Dashboard</h1>
        
        <!-- API Key Status -->
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>🔑 API Key Status</h2>
            <?php if ($key_file_exists): ?>
                <p style="color: green;">✅ <strong>API Key File Found</strong></p>
                <p>File: <code><?php echo INDEXNOW_API_KEY; ?>.txt</code></p>
                <p>Verify at: <a href="<?php echo home_url('/' . INDEXNOW_API_KEY . '.txt'); ?>" target="_blank">
                    <?php echo home_url('/' . INDEXNOW_API_KEY . '.txt'); ?>
                </a></p>
            <?php else: ?>
                <p style="color: red;">❌ <strong>API Key File NOT Found</strong></p>
                <p>Expected location: <code><?php echo ABSPATH . INDEXNOW_API_KEY . '.txt'; ?></code></p>
                <form method="post" action="">
                    <input type="hidden" name="create_key_file" value="1">
                    <?php wp_nonce_field('create_indexnow_key_file'); ?>
                    <button type="submit" class="button button-primary">Create Key File Now</button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>📈 Submission Statistics</h2>
            <table class="widefat" style="max-width: 600px;">
                <tr>
                    <td><strong>Total Submissions:</strong></td>
                    <td><?php echo $stats['total']; ?></td>
                </tr>
                <tr>
                    <td><strong>✅ Successful:</strong></td>
                    <td style="color: green;"><?php echo $stats['success']; ?></td>
                </tr>
                <tr>
                    <td><strong>⏳ Accepted (Processing):</strong></td>
                    <td style="color: orange;"><?php echo $stats['accepted']; ?></td>
                </tr>
                <tr>
                    <td><strong>❌ Errors:</strong></td>
                    <td style="color: red;"><?php echo $stats['error']; ?></td>
                </tr>
                <tr>
                    <td><strong>Last Submission:</strong></td>
                    <td><?php echo $stats['last_submission']; ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Recent Submissions -->
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>🕒 Recent Submissions (Last 5)</h2>
            <?php if (empty($recent_submissions)): ?>
                <p>No submissions yet. Publish or update a post to test IndexNow!</p>
            <?php else: ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Post Title</th>
                            <th>URL</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_submissions as $entry): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($entry['timestamp'])); ?></td>
                                <td><?php echo esc_html($entry['post_title']); ?></td>
                                <td><a href="<?php echo esc_url($entry['url']); ?>" target="_blank">View</a></td>
                                <td>
                                    <?php if ($entry['status'] == 'success'): ?>
                                        <span style="color: green;">✅ Success</span>
                                    <?php elseif ($entry['status'] == 'accepted'): ?>
                                        <span style="color: orange;">⏳ Accepted</span>
                                    <?php else: ?>
                                        <span style="color: red;">❌ Error</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($entry['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p><a href="<?php echo admin_url('admin.php?page=indexnow-logs'); ?>" class="button">View All Logs</a></p>
        </div>
        
        <!-- Test Submission -->
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>🧪 Test Manual Submission</h2>
            <form method="post" action="">
                <input type="text" name="test_url" placeholder="https://seshng.com/page-to-submit/" style="width: 400px;" required>
                <input type="hidden" name="manual_submit" value="1">
                <?php wp_nonce_field('manual_indexnow_submit'); ?>
                <button type="submit" class="button button-primary">Submit to IndexNow</button>
            </form>
        </div>
    </div>
    <?php
}

// Logs page
function indexnow_logs_page() {
    // Handle clear logs
    if (isset($_POST['clear_logs']) && check_admin_referer('clear_indexnow_logs')) {
        update_option('indexnow_submission_log', array());
        update_option('indexnow_stats', array(
            'total' => 0,
            'success' => 0,
            'accepted' => 0,
            'error' => 0,
            'last_submission' => ''
        ));
        echo '<div class="notice notice-success"><p>Logs cleared successfully!</p></div>';
    }
    
    $log = get_option('indexnow_submission_log', array());
    
    ?>
    <div class="wrap">
        <h1>📋 IndexNow Submission Logs</h1>
        
        <form method="post" style="margin-bottom: 20px;">
            <input type="hidden" name="clear_logs" value="1">
            <?php wp_nonce_field('clear_indexnow_logs'); ?>
            <button type="submit" class="button" onclick="return confirm('Are you sure you want to clear all logs?');">Clear All Logs</button>
        </form>
        
        <?php if (empty($log)): ?>
            <p>No submission logs yet.</p>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Post Title</th>
                        <th>Action</th>
                        <th>URL</th>
                        <th>Status</th>
                        <th>Status Code</th>
                        <th>Message</th>
                        <th>Response Body</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $entry): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($entry['timestamp'])); ?></td>
                            <td><?php echo esc_html($entry['post_title']); ?></td>
                            <td><?php echo esc_html($entry['action']); ?></td>
                            <td><a href="<?php echo esc_url($entry['url']); ?>" target="_blank">View</a></td>
                            <td>
                                <?php if ($entry['status'] == 'success'): ?>
                                    <span style="color: green; font-weight: bold;">✅ Success</span>
                                <?php elseif ($entry['status'] == 'accepted'): ?>
                                    <span style="color: orange; font-weight: bold;">⏳ Accepted</span>
                                <?php else: ?>
                                    <span style="color: red; font-weight: bold;">❌ Error</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($entry['status_code']); ?></td>
                            <td><?php echo esc_html($entry['message']); ?></td>
                            <td><code><?php echo esc_html(isset($entry['response_body']) ? substr($entry['response_body'], 0, 100) : 'N/A'); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Settings page
function indexnow_settings_page() {
    ?>
    <div class="wrap">
        <h1>⚙️ IndexNow Settings</h1>
        
        <div class="card" style="max-width: 800px;">
            <h2>Current Configuration</h2>
            <table class="widefat">
                <tr>
                    <td><strong>API Key:</strong></td>
                    <td><code><?php echo INDEXNOW_API_KEY; ?></code></td>
                </tr>
                <tr>
                    <td><strong>Key File Location:</strong></td>
                    <td><code><?php echo ABSPATH . INDEXNOW_API_KEY . '.txt'; ?></code></td>
                </tr>
                <tr>
                    <td><strong>IndexNow Endpoint:</strong></td>
                    <td><code>https://api.indexnow.org/indexnow</code></td>
                </tr>
                <tr>
                    <td><strong>Monitored Post Types:</strong></td>
                    <td>Posts, Pages</td>
                </tr>
            </table>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>How to Change API Key</h2>
            <ol>
                <li>Generate a new 32-character hexadecimal key</li>
                <li>Edit <code>functions.php</code></li>
                <li>Find: <code>define('INDEXNOW_API_KEY', '...');</code></li>
                <li>Replace the key value</li>
                <li>Delete old key file from website root</li>
                <li>Create new key file (use button on dashboard)</li>
            </ol>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Response Code Reference</h2>
            <table class="widefat">
                <tr><td><strong>200</strong></td><td>✅ OK - URL successfully submitted</td></tr>
                <tr><td><strong>202</strong></td><td>⏳ Accepted - URL received and processing</td></tr>
                <tr><td><strong>400</strong></td><td>❌ Bad Request - Invalid format</td></tr>
                <tr><td><strong>403</strong></td><td>❌ Forbidden - Invalid API key</td></tr>
                <tr><td><strong>422</strong></td><td>❌ Unprocessable - URL not owned by site</td></tr>
                <tr><td><strong>429</strong></td><td>❌ Too Many Requests - Rate limit exceeded</td></tr>
            </table>
        </div>
    </div>
    <?php
}

// Handle manual submission from dashboard
function handle_manual_indexnow_submission() {
    if (isset($_POST['manual_submit']) && check_admin_referer('manual_indexnow_submit')) {
        $test_url = esc_url_raw($_POST['test_url']);
        
        $indexnow_url = 'https://api.indexnow.org/indexnow?' . http_build_query(array(
            'url' => $test_url,
            'key' => INDEXNOW_API_KEY
        ));
        
        $response = wp_remote_get($indexnow_url, array('timeout' => 10));
        
        // Prepare log entry
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'url' => $test_url,
            'post_id' => 'manual',
            'post_title' => 'Manual Submission',
            'action' => 'manual'
        );
        
        // Process response
        if (is_wp_error($response)) {
            $log_entry['status'] = 'error';
            $log_entry['message'] = $response->get_error_message();
            $log_entry['status_code'] = 'N/A';
            $log_entry['response_body'] = '';
            
            add_action('admin_notices', function() use ($response) {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html($response->get_error_message()) . '</p></div>';
            });
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            $log_entry['status_code'] = $status_code;
            $log_entry['response_body'] = $response_body;
            
            // Determine status based on response code
            if ($status_code == 200) {
                $log_entry['status'] = 'success';
                $log_entry['message'] = 'URL successfully submitted to IndexNow';
                
                add_action('admin_notices', function() use ($status_code, $test_url) {
                    echo '<div class="notice notice-success"><p><strong>✅ Success!</strong> URL submitted successfully (Status: ' . $status_code . ')<br>URL: ' . esc_html($test_url) . '</p></div>';
                });
            } elseif ($status_code == 202) {
                $log_entry['status'] = 'accepted';
                $log_entry['message'] = 'URL accepted by IndexNow (processing)';
                
                add_action('admin_notices', function() use ($status_code, $test_url) {
                    echo '<div class="notice notice-success"><p><strong>⏳ Accepted!</strong> URL accepted and being processed (Status: ' . $status_code . ')<br>URL: ' . esc_html($test_url) . '</p></div>';
                });
            } elseif ($status_code == 400) {
                $log_entry['status'] = 'error';
                $log_entry['message'] = 'Bad Request - Invalid format or parameters';
                
                add_action('admin_notices', function() use ($status_code, $response_body) {
                    echo '<div class="notice notice-error"><p><strong>❌ Error ' . $status_code . ':</strong> Bad Request - Invalid format or parameters<br>Response: ' . esc_html($response_body) . '</p></div>';
                });
            } elseif ($status_code == 403) {
                $log_entry['status'] = 'error';
                $log_entry['message'] = 'Forbidden - Invalid API key';
                
                add_action('admin_notices', function() use ($status_code) {
                    echo '<div class="notice notice-error"><p><strong>❌ Error ' . $status_code . ':</strong> Forbidden - Check your API key is correct</p></div>';
                });
            } elseif ($status_code == 422) {
                $log_entry['status'] = 'error';
                $log_entry['message'] = 'Unprocessable Entity - URL not owned by site';
                
                add_action('admin_notices', function() use ($status_code) {
                    echo '<div class="notice notice-error"><p><strong>❌ Error ' . $status_code . ':</strong> URL cannot be verified as belonging to this site</p></div>';
                });
            } elseif ($status_code == 429) {
                $log_entry['status'] = 'error';
                $log_entry['message'] = 'Too Many Requests - Rate limit exceeded';
                
                add_action('admin_notices', function() use ($status_code) {
                    echo '<div class="notice notice-error"><p><strong>❌ Error ' . $status_code . ':</strong> Rate limit exceeded - Try again later</p></div>';
                });
            } else {
                $log_entry['status'] = 'error';
                $log_entry['message'] = 'Unexpected response code: ' . $status_code;
                
                add_action('admin_notices', function() use ($status_code, $response_body) {
                    echo '<div class="notice notice-error"><p><strong>❌ Error:</strong> Unexpected status code ' . $status_code . '<br>Response: ' . esc_html($response_body) . '</p></div>';
                });
            }
        }
        
        // Save to log
        $log = get_option('indexnow_submission_log', array());
        array_unshift($log, $log_entry); // Add to beginning
        
        // Keep only last 100 entries
        $log = array_slice($log, 0, 100);
        
        update_option('indexnow_submission_log', $log);
        
        // Update statistics
        update_indexnow_stats($log_entry['status']);
    }
}
add_action('admin_init', 'handle_manual_indexnow_submission');

// Handle key file creation from dashboard
function handle_key_file_creation() {
    if (isset($_POST['create_key_file']) && check_admin_referer('create_indexnow_key_file')) {
        $key_file = ABSPATH . INDEXNOW_API_KEY . '.txt';
        
        if (file_put_contents($key_file, INDEXNOW_API_KEY)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p><strong>Success!</strong> API key file created.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> Could not create key file. Check file permissions.</p></div>';
            });
        }
    }
}
add_action('admin_init', 'handle_key_file_creation');




/**
 * Fix Simple Job Board search button
 * This runs on every page load - survives plugin updates
 */
add_filter('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Use a function so we can retry if the element isn't ready yet
        function updateSearchButton() {
            const searchBtn = document.querySelector('.sjb-search-button .btn-search');
            
            if (searchBtn) {
                // If it's an input tag (uses value attribute)
                if (searchBtn.tagName === 'INPUT' && searchBtn.value !== 'Search') {
                    searchBtn.value = 'Search';
                } 
                // If it's a button tag (uses inner text)
                else if (searchBtn.tagName === 'BUTTON' && searchBtn.textContent.trim() !== 'Search') {
                    searchBtn.textContent = 'Search';
                }
            }
        }

        // Run immediately on DOM load
        updateSearchButton();
        
        // Optional: Run again slightly later in case of slow plugin rendering
        setTimeout(updateSearchButton, 500);
    });
    </script>
    <?php
}, 20); // Priority 20 ensures it loads later in the footer



