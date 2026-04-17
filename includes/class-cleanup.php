<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartTube_Cleanup {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
        add_action( 'wp_ajax_stube_preview_cat_posts', [ __CLASS__, 'preview_cat_posts' ] );
        add_action( 'wp_ajax_stube_delete_batch',      [ __CLASS__, 'delete_batch' ] );
    }

    public static function menu() {
        add_menu_page(
            'Post Cleanup',
            'Post Cleanup',
            'manage_options',
            'smarttube-cleanup',
            [ __CLASS__, 'page' ],
            'dashicons-trash',
            32
        );
    }

    public static function assets( $hook ) {
        if ( $hook !== 'toplevel_page_smarttube-cleanup' ) return;
        wp_enqueue_style( 'stube-cleanup', STUBE_URL . 'assets/css/admin.css', [], STUBE_VERSION );
        wp_enqueue_script( 'stube-cleanup', STUBE_URL . 'assets/js/cleanup.js', [ 'jquery' ], STUBE_VERSION, true );
        wp_localize_script( 'stube-cleanup', 'stubeCleanup', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'stube_cleanup_nonce' ),
        ] );
    }

    public static function page() {
        $categories = get_categories( [ 'hide_empty' => true, 'orderby' => 'name' ] );
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-trash" style="font-size:28px;margin-right:8px;"></span> Category Post Cleanup</h1>
            <p style="color:#646970;font-size:14px;margin-bottom:20px;">Select categories, preview posts, then delete posts and their featured images permanently — in safe batches.</p>

            <div class="stube-card" style="border-top:4px solid #d63638;">

                <!-- Step 1: Select categories -->
                <h3 style="margin:0 0 10px;">Step 1: Select Categories</h3>

                <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="stube-cleanup-search" placeholder="Search categories..." style="width:250px;padding:6px 10px;font-size:13px;border:1px solid #c3c4c7;border-radius:4px;">
                    <button type="button" id="stube-cleanup-check-all" class="button">Select All</button>
                    <button type="button" id="stube-cleanup-check-none" class="button">Deselect All</button>
                    <span id="stube-cleanup-cat-count" style="color:#646970;font-size:13px;">0 selected</span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;max-height:350px;overflow-y:auto;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#f9f9f9;margin-bottom:16px;">
                    <?php foreach ( $categories as $cat ) : ?>
                        <label class="stube-cleanup-cat-label" style="display:flex;align-items:center;gap:6px;padding:7px 10px;background:#fff;border:1px solid #dcdcde;border-radius:4px;cursor:pointer;font-size:13px;transition:all .15s;" data-name="<?php echo esc_attr( mb_strtolower( $cat->name ) ); ?>">
                            <input type="checkbox" class="stube-cleanup-cat-chk" value="<?php echo $cat->term_id; ?>">
                            <span style="font-weight:500;line-height:1.3;"><?php echo esc_html( $cat->name ); ?></span>
                            <small style="margin-left:auto;color:#8c8f94;">(<?php echo $cat->count; ?>)</small>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#646970;">
                        <input type="checkbox" id="stube-cleanup-all-posts" value="1">
                        Include posts without featured images
                    </label>
                    <button type="button" id="stube-cleanup-preview" class="button button-primary" style="font-size:14px;padding:4px 20px;">Preview Posts</button>
                    <span id="stube-cleanup-preview-status" style="font-size:13px;"></span>
                </div>

                <!-- Step 2: Preview results -->
                <div id="stube-cleanup-results" style="display:none;">
                    <h3 style="margin:0 0 10px;">Step 2: Review & Delete</h3>

                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;padding:12px 16px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;flex-wrap:wrap;">
                        <strong id="stube-cleanup-found" style="font-size:14px;"></strong>
                        <button type="button" id="stube-cleanup-select-all" class="button">Select All</button>
                        <button type="button" id="stube-cleanup-select-none" class="button">Deselect All</button>
                        <span style="flex:1;"></span>
                        <button type="button" id="stube-cleanup-delete" class="button" style="background:#d63638;color:#fff;border-color:#d63638;font-weight:600;font-size:14px;padding:4px 18px;">
                            Delete Selected Posts & Images
                        </button>
                    </div>

                    <!-- Progress bar -->
                    <div id="stube-cleanup-progress" style="display:none;margin-bottom:12px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                            <span id="stube-cleanup-progress-text" style="font-size:13px;color:#646970;"></span>
                            <button type="button" id="stube-cleanup-stop" class="button" style="color:#d63638;border-color:#d63638;font-size:12px;padding:2px 10px;">Stop</button>
                        </div>
                        <div style="width:100%;height:22px;background:#f0f0f1;border-radius:11px;overflow:hidden;border:1px solid #dcdcde;">
                            <div id="stube-cleanup-bar" style="height:100%;background:linear-gradient(90deg,#d63638,#e74c3c);border-radius:11px;transition:width .3s;width:0%;display:flex;align-items:center;justify-content:center;">
                                <span id="stube-cleanup-bar-text" style="color:#fff;font-size:11px;font-weight:600;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Post list -->
                    <div id="stube-cleanup-list" style="max-height:500px;overflow-y:auto;border:1px solid #dcdcde;border-radius:6px;background:#fff;"></div>

                    <!-- Result message -->
                    <div id="stube-cleanup-delete-status" style="margin-top:12px;font-weight:600;font-size:14px;"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Preview posts from one or more categories.
     * Accepts cat_ids as JSON array or single cat_id.
     */
    public static function preview_cat_posts() {
        check_ajax_referer( 'stube_cleanup_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        // Support multiple categories
        $cat_ids = [];
        if ( ! empty( $_POST['cat_ids'] ) ) {
            $cat_ids = json_decode( stripslashes( $_POST['cat_ids'] ), true );
            $cat_ids = array_map( 'absint', (array) $cat_ids );
            $cat_ids = array_filter( $cat_ids );
        }

        if ( empty( $cat_ids ) ) wp_send_json_error( 'No categories selected.' );

        $all = ! empty( $_POST['all_posts'] );

        $args = [
            'category__in'   => $cat_ids,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ];

        if ( ! $all ) {
            $args['meta_query'] = [
                [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ],
            ];
        }

        $query = new WP_Query( $args );

        $posts = [];
        foreach ( $query->posts as $post_id ) {
            $thumb_id  = (int) get_post_thumbnail_id( $post_id );
            $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

            // Get category names for this post
            $post_cats = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );

            $posts[] = [
                'id'        => $post_id,
                'title'     => get_the_title( $post_id ),
                'date'      => get_the_date( 'Y-m-d', $post_id ),
                'status'    => get_post_status( $post_id ),
                'category'  => implode( ', ', $post_cats ),
                'thumb_url' => $thumb_url ?: '',
            ];
        }

        wp_send_json_success( [ 'posts' => $posts, 'total' => count( $posts ) ] );
    }

    /**
     * Delete a small batch of posts (max 5 at a time to avoid WAF 403).
     * POST: post_ids (JSON array, max 5 IDs)
     */
    public static function delete_batch() {
        check_ajax_referer( 'stube_cleanup_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $raw_ids  = json_decode( stripslashes( $_POST['post_ids'] ?? '[]' ), true );
        $post_ids = array_map( 'absint', (array) $raw_ids );
        $post_ids = array_filter( $post_ids );

        // Hard limit per batch
        $post_ids = array_slice( $post_ids, 0, 5 );

        if ( empty( $post_ids ) ) wp_send_json_error( 'No posts.' );

        $deleted_posts  = 0;
        $deleted_images = 0;

        foreach ( $post_ids as $post_id ) {
            if ( ! get_post( $post_id ) ) continue;

            $thumb_id = (int) get_post_thumbnail_id( $post_id );
            if ( $thumb_id ) {
                wp_delete_attachment( $thumb_id, true );
                $deleted_images++;
            }

            wp_delete_post( $post_id, true );
            $deleted_posts++;
        }

        wp_send_json_success( [
            'deleted_posts'  => $deleted_posts,
            'deleted_images' => $deleted_images,
        ] );
    }
}
