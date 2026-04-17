<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartTube_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_ajax_stube_save_settings', [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_stube_fetch_playlists', [ $this, 'fetch_playlists' ] );
        add_action( 'wp_ajax_stube_clear_cache', [ $this, 'clear_cache' ] );
        add_action( 'wp_ajax_stube_load_tab', [ __CLASS__, 'ajax_load_tab' ] );
        add_action( 'wp_ajax_nopriv_stube_load_tab', [ __CLASS__, 'ajax_load_tab' ] );
        add_action( 'wp_ajax_stube_save_links', [ $this, 'save_links' ] );
        add_action( 'wp_ajax_stube_create_categories', [ $this, 'create_categories' ] );
        add_action( 'wp_ajax_stube_save_excluded',       [ $this, 'save_excluded' ] );
    }

    public function menu() {
        add_menu_page( 'SmartTube', 'SmartTube', 'manage_options', 'smarttube', [ $this, 'page' ], 'dashicons-video-alt3', 31 );
    }

    public function assets( $hook ) {
        if ( $hook !== 'toplevel_page_smarttube' ) return;
        wp_enqueue_style( 'stube-admin', STUBE_URL . 'assets/css/admin.css', [], STUBE_VERSION );
        wp_enqueue_script( 'stube-admin', STUBE_URL . 'assets/js/admin.js', [ 'jquery' ], STUBE_VERSION, true );
        wp_localize_script( 'stube-admin', 'stubeAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'stube_nonce' ),
        ] );
    }

    public function page() {
        $s = SmartTube::get_settings();
        $playlists = get_option( 'smarttube_playlists', [] );
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-video-alt3" style="font-size:28px;margin-right:8px;"></span> SmartTube</h1>

            <!-- Settings -->
            <div class="stube-card">
                <h2>Channel Settings</h2>
                <form id="stube-settings-form">
                    <table class="form-table">
                        <tr>
                            <th>YouTube API Key</th>
                            <td><input type="text" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Channel ID</th>
                            <td>
                                <input type="text" name="channel_id" value="<?php echo esc_attr( $s['channel_id'] ); ?>" class="regular-text" placeholder="UCmdgAcBW7sMCOFUoRDpclmQ">
                                <button type="button" id="stube-fetch-playlists" class="button">Fetch Playlists</button>
                            </td>
                        </tr>
                        <tr>
                            <th>Cache Duration (minutes)</th>
                            <td><input type="number" name="cache_ttl" value="<?php echo esc_attr( $s['cache_ttl'] ); ?>" min="5" max="1440" class="small-text"></td>
                        </tr>
                        <tr>
                            <th>Default Columns</th>
                            <td>
                                <select name="columns">
                                    <?php for ( $i = 1; $i <= 6; $i++ ) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected( $s['columns'], $i ); ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Default Video Limit</th>
                            <td><input type="number" name="limit" value="<?php echo esc_attr( $s['limit'] ); ?>" min="1" max="50" class="small-text"></td>
                        </tr>
                        <tr>
                            <th>Play Mode</th>
                            <td>
                                <select name="play_mode">
                                    <option value="lightbox" <?php selected( $s['play_mode'], 'lightbox' ); ?>>Lightbox Popup</option>
                                    <option value="newtab" <?php selected( $s['play_mode'], 'newtab' ); ?>>Open in New Tab</option>
                                    <option value="inline" <?php selected( $s['play_mode'], 'inline' ); ?>>Play Inline</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Background Color</th>
                            <td><input type="color" name="bg_color" value="<?php echo esc_attr( $s['bg_color'] ); ?>" style="width:60px;height:36px;cursor:pointer;"> <input type="text" name="bg_color_text" value="<?php echo esc_attr( $s['bg_color'] ); ?>" class="small-text" style="width:80px;" oninput="this.previousElementSibling.previousElementSibling.value=this.value" > <small>Main background</small></td>
                        </tr>
                        <tr>
                            <th>Header Color</th>
                            <td><input type="color" name="header_color" value="<?php echo esc_attr( $s['header_color'] ); ?>" style="width:60px;height:36px;cursor:pointer;"> <input type="text" name="header_color_text" value="<?php echo esc_attr( $s['header_color'] ); ?>" class="small-text" style="width:80px;" oninput="this.previousElementSibling.previousElementSibling.value=this.value"> <small>Tab bar background</small></td>
                        </tr>
                        <tr>
                            <th>Accent Color</th>
                            <td><input type="color" name="accent_color" value="<?php echo esc_attr( $s['accent_color'] ); ?>" style="width:60px;height:36px;cursor:pointer;"> <input type="text" name="accent_color_text" value="<?php echo esc_attr( $s['accent_color'] ); ?>" class="small-text" style="width:80px;" oninput="this.previousElementSibling.previousElementSibling.value=this.value"> <small>Active tab & button color</small></td>
                        </tr>
                        <tr>
                            <th>Show</th>
                            <td>
                                <label><input type="checkbox" name="show_title" value="1" <?php checked( $s['show_title'] ); ?>> Title</label>
                                <label style="margin-left:15px;"><input type="checkbox" name="show_date" value="1" <?php checked( $s['show_date'] ); ?>> Date</label>
                                <label style="margin-left:15px;"><input type="checkbox" name="show_duration" value="1" <?php checked( $s['show_duration'] ); ?>> Duration</label>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary">Save Settings</button>
                        <button type="button" id="stube-clear-cache" class="button" style="margin-left:10px;">Clear Cache</button>
                        <span id="stube-status" style="margin-left:10px;"></span>
                    </p>
                </form>
            </div>

            <!-- Tabs Builder: Select playlists for tabbed layout -->
            <div class="stube-card">
                <h2>📺 Tabs Builder — Select Playlists for Tabs</h2>
                <p style="color:#646970;">Check the playlists you want as tabs, then copy the generated shortcode below.</p>

                <?php if ( empty( $playlists ) ) : ?>
                    <p>No playlists loaded. Enter your Channel ID above and click "Fetch Playlists".</p>
                <?php else : ?>
                    <!-- Search -->
                    <input type="text" id="stube-pl-search" placeholder="🔍 Search playlists..." style="width:100%;padding:10px;font-size:14px;margin-bottom:12px;border:1px solid #c3c4c7;border-radius:4px;">

                    <!-- Select All / None -->
                    <div style="margin-bottom:10px;">
                        <button type="button" class="button" id="stube-select-all">Select All</button>
                        <button type="button" class="button" id="stube-select-none">Deselect All</button>
                        <span style="margin-left:10px;color:#646970;" id="stube-selected-count">0 selected</span>
                    </div>

                    <!-- Playlist Grid (4 columns like your screenshot) -->
                    <div id="stube-pl-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;max-height:500px;overflow-y:auto;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#f9f9f9;">
                        <?php foreach ( $playlists as $pl ) :
                            if ( $pl['videoCount'] < 1 ) continue;
                        ?>
                            <label class="stube-pl-label" style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#fff;border:1px solid #dcdcde;border-radius:4px;cursor:pointer;transition:all 0.15s;" data-name="<?php echo esc_attr( mb_strtolower( $pl['title'] ) ); ?>">
                                <input type="checkbox" class="stube-pl-check" value="<?php echo esc_attr( $pl['id'] ); ?>" data-title="<?php echo esc_attr( $pl['title'] ); ?>">
                                <span style="font-size:13px;font-weight:500;line-height:1.3;"><?php echo esc_html( $pl['title'] ); ?></span>
                                <small style="margin-right:auto;margin-left:auto;color:#8c8f94;">(<?php echo $pl['videoCount']; ?>)</small>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Generated Shortcode -->
                    <div style="margin-top:16px;padding:16px;background:#1d2327;border-radius:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <strong style="color:#f0a030;font-size:14px;">📋 Generated Shortcode — Click to Copy</strong>
                            <small style="color:#8c8f94;">Paste into any page or widget</small>
                        </div>
                        <code id="stube-generated-shortcode" class="stube-copy" style="display:block;background:#0d0d0d;color:#50d450;padding:14px;border-radius:6px;font-size:13px;cursor:pointer;word-break:break-all;line-height:1.6;direction:ltr;text-align:left;">[smarttube_tabs header="برامج القناة"]</code>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Playlists + Category Linking -->
            <div class="stube-card">
                <h2>📺 All Playlists — Link to Categories</h2>
                <p style="color:#646970;">Link each playlist to a WordPress category. When you add <code>[smarttube_category]</code> shortcode to a category page, it will auto-display the linked playlist videos instead of posts.</p>

                <?php if ( ! empty( $playlists ) ) :
                    $categories = get_categories( [ 'hide_empty' => false, 'orderby' => 'name' ] );
                    $linked = get_option( 'smarttube_playlist_categories', [] ) ?: [];
                    $linked_limits = get_option( 'smarttube_playlist_limits', [] ) ?: [];
                    $excluded_cats = get_option( 'smarttube_excluded_categories', [] ) ?: [];
                    $enabled_playlists = get_option( 'smarttube_enabled_categories', [] ) ?: [];
                ?>

                    <!-- Exclude Categories Section -->
                    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 8px;font-size:15px;">🚫 Excluded Categories — Always Show Posts</h3>
                        <p style="color:#856404;margin:0 0 12px;font-size:13px;">Check categories that should NEVER show YouTube videos — they will always display WordPress posts.</p>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;max-height:250px;overflow-y:auto;padding:8px;background:#fff;border:1px solid #ffe082;border-radius:6px;">
                            <?php foreach ( $categories as $cat ) : ?>
                                <label style="display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:4px;cursor:pointer;font-size:13px;transition:background 0.15s;" class="stube-excl-label">
                                    <input type="checkbox" class="stube-excl-check" value="<?php echo $cat->term_id; ?>" <?php checked( in_array( $cat->term_id, $excluded_cats ) ); ?>>
                                    <span><?php echo esc_html( $cat->name ); ?></span>
                                    <small style="color:#999;margin-right:auto;margin-left:auto;">(<?php echo $cat->count; ?>)</small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <button type="button" class="button" id="stube-excl-all">Exclude All</button>
                            <button type="button" class="button" id="stube-excl-none">Include All</button>
                            <button type="button" class="button button-primary" id="stube-save-excluded">💾 Save Excluded Categories</button>
                            <span id="stube-excl-status" style="font-weight:600;"></span>
                            <span style="color:#856404;font-size:13px;" id="stube-excl-count"><?php echo count( $excluded_cats ); ?> excluded</span>
                        </div>
                    </div>

                    <!-- Unlinked Categories -->
                    <?php
                    $linked_cat_ids = array_values( $linked );
                    $unlinked_cats = array_filter( $categories, function( $c ) use ( $linked_cat_ids, $excluded_cats ) {
                        return ! in_array( $c->term_id, $linked_cat_ids ) && ! in_array( $c->term_id, $excluded_cats );
                    });
                    ?>
                    <?php if ( ! empty( $unlinked_cats ) ) : ?>
                    <div style="background:#fce4ec;border:1px solid #ef9a9a;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 8px;font-size:15px;">⚠️ Unlinked Categories (<?php echo count( $unlinked_cats ); ?>) — Need a playlist</h3>
                        <p style="color:#c62828;margin:0 0 12px;font-size:13px;">These categories have no YouTube playlist linked. Find a suitable playlist for each one in the table below.</p>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                            <?php foreach ( $unlinked_cats as $cat ) : ?>
                                <div style="background:#fff;border:1px solid #ef9a9a;border-radius:4px;padding:8px 10px;font-size:13px;">
                                    <strong><?php echo esc_html( $cat->name ); ?></strong>
                                    <small style="color:#999;display:block;">ID: <?php echo $cat->term_id; ?> · <?php echo $cat->count; ?> posts</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Linked summary -->
                    <?php
                    $linked_count = count( $linked );
                    $enabled_count = count( array_filter( $enabled_playlists ) );
                    $excluded_count = count( $excluded_cats );
                    $total_cats = count( $categories );
                    ?>
                    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:10px 16px;font-size:13px;">
                            ✅ <strong><?php echo $linked_count; ?></strong> linked
                        </div>
                        <div style="background:#e3f2fd;border:1px solid #90caf9;border-radius:6px;padding:10px 16px;font-size:13px;">
                            📺 <strong><?php echo $enabled_count; ?></strong> showing videos
                        </div>
                        <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:10px 16px;font-size:13px;">
                            🚫 <strong><?php echo $excluded_count; ?></strong> excluded
                        </div>
                        <div style="background:#fce4ec;border:1px solid #ef9a9a;border-radius:6px;padding:10px 16px;font-size:13px;">
                            ⚠️ <strong><?php echo count( $unlinked_cats ); ?></strong> unlinked
                        </div>
                        <div style="background:#f5f5f5;border:1px solid #ddd;border-radius:6px;padding:10px 16px;font-size:13px;">
                            📁 <strong><?php echo $total_cats; ?></strong> total categories
                        </div>
                    </div>

                    <!-- Create Categories Button -->
                    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                        <div>
                            <strong style="font-size:14px;">🏷️ Auto-Create Categories</strong>
                            <p style="margin:4px 0 0;color:#555;font-size:13px;">Creates a new WordPress category for each unlinked playlist (skips excluded categories and playlists with less than 3 videos).</p>
                        </div>
                        <button type="button" id="stube-create-cats" class="button button-primary" style="flex-shrink:0;">🏷️ Create Categories for Unlinked Playlists</button>
                    </div>

                    <!-- Filter tabs -->
                    <div style="margin-bottom:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <button type="button" class="button stube-filter-btn active" data-filter="all">All (<?php echo count( $playlists ); ?>)</button>
                        <button type="button" class="button stube-filter-btn" data-filter="linked">✅ Linked (<?php echo $linked_count; ?>)</button>
                        <button type="button" class="button stube-filter-btn" data-filter="unlinked">⚠️ Unlinked (<?php echo count( $playlists ) - $linked_count; ?>)</button>
                        <span style="flex:1;"></span>
                        <input type="text" id="stube-pl-table-search" placeholder="🔍 Search..." style="padding:6px 12px;font-size:13px;border:1px solid #c3c4c7;border-radius:4px;width:200px;">
                    </div>

                    <div style="max-height:600px;overflow-y:auto;border:1px solid #dcdcde;border-radius:6px;">
                    <?php // $enabled_playlists already loaded above ?>
                    <table class="wp-list-table widefat fixed striped" style="font-size:13px;" id="stube-pl-cat-table">
                        <thead style="position:sticky;top:0;background:#fff;z-index:2;">
                            <tr>
                                <th style="width:35px;">Img</th>
                                <th>Playlist</th>
                                <th style="width:40px;">Vids</th>
                                <th style="width:22%;">Category</th>
                                <th style="width:55px;">Limit</th>
                                <th style="width:85px;">Display</th>
                                <th style="width:30%;">Shortcode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $playlists as $pl ) :
                                $pl_id = $pl['id'];
                                $current_cat = $linked[ $pl_id ] ?? '';
                                $current_limit = $linked_limits[ $pl_id ] ?? 12;
                                $is_enabled = isset( $enabled_playlists[ $pl_id ] ) ? (bool) $enabled_playlists[ $pl_id ] : false;
                            ?>
                                <tr data-plname="<?php echo esc_attr( mb_strtolower( $pl['title'] ) ); ?>" data-linked="<?php echo $current_cat ? 'yes' : 'no'; ?>" style="<?php echo $current_cat ? '' : 'opacity:0.6;'; ?>">
                                    <td><img src="<?php echo esc_url( $pl['thumbnail'] ); ?>" style="width:35px;border-radius:3px;"></td>
                                    <td><strong style="font-size:12px;"><?php echo esc_html( $pl['title'] ); ?></strong></td>
                                    <td><?php echo $pl['videoCount']; ?></td>
                                    <td>
                                        <select class="stube-cat-select" data-playlist="<?php echo esc_attr( $pl_id ); ?>" style="width:100%;font-size:11px;">
                                            <option value="">— None —</option>
                                            <?php foreach ( $categories as $cat ) : ?>
                                                <option value="<?php echo $cat->term_id; ?>" <?php selected( $current_cat, $cat->term_id ); ?>>
                                                    <?php echo esc_html( $cat->name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="stube-limit-input" data-playlist="<?php echo esc_attr( $pl_id ); ?>" value="<?php echo esc_attr( $current_limit ); ?>" min="1" max="50" style="width:50px;font-size:11px;">
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ( $current_cat ) : ?>
                                            <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;" title="<?php echo $is_enabled ? 'YouTube Videos' : 'WordPress Posts'; ?>">
                                                <input type="checkbox" class="stube-enable-check" data-playlist="<?php echo esc_attr( $pl_id ); ?>" <?php checked( $is_enabled ); ?>>
                                                <span style="font-size:11px;color:<?php echo $is_enabled ? '#00a32a' : '#d63638'; ?>;font-weight:600;" class="stube-display-label"><?php echo $is_enabled ? '📺 Videos' : '📰 Posts'; ?></span>
                                            </label>
                                        <?php else : ?>
                                            <span style="color:#ccc;font-size:11px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="stube-copy" style="cursor:pointer;font-size:10px;background:#f0f6fc;padding:3px 6px;border-radius:3px;display:inline-block;word-break:break-all;">[smarttube playlist="<?php echo esc_attr( $pl_id ); ?>" limit="<?php echo esc_attr( $current_limit ); ?>"]</code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 16px;background:#f0f6fc;border:1px solid #c3d4e0;border-radius:8px;">
                        <strong style="font-size:13px;">Bulk Actions:</strong>
                        <label style="font-size:13px;display:flex;align-items:center;gap:4px;">
                            Set all limits to
                            <input type="number" id="stube-bulk-limit" value="12" min="1" max="50" style="width:60px;font-size:13px;">
                            <button type="button" id="stube-apply-bulk-limit" class="button">Apply</button>
                        </label>
                        <span style="color:#c3c4c7;">|</span>
                        <button type="button" id="stube-enable-all-linked" class="button" style="color:#00a32a;">📺 Enable All</button>
                        <button type="button" id="stube-disable-all-linked" class="button" style="color:#d63638;">📰 Disable All</button>
                    </div>
                    <p style="margin-top:12px;">
                        <button type="button" id="stube-save-links" class="button button-primary button-hero">💾 Save Category Links</button>
                        <span id="stube-links-status" style="margin-left:12px;font-weight:600;"></span>
                    </p>

                    <!-- Auto-display shortcode info -->
                    <div style="margin-top:16px;background:#f0f6fc;border:1px solid #c3d4e0;border-radius:6px;padding:14px;">
                        <strong>💡 How to use on category pages:</strong>
                        <p style="margin:8px 0 0;color:#555;">Add <code class="stube-copy" style="cursor:pointer;">[smarttube_category]</code> to your category description or use it as a widget. It will automatically detect which category page the visitor is on, find the linked playlist, and display the videos.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Shortcodes -->
            <div class="stube-card">
                <h2>Quick Shortcodes</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;">
                    <div style="background:#e8f5e9;border-radius:6px;padding:14px;">
                        <strong>Latest Videos</strong><br>
                        <code class="stube-copy" style="cursor:pointer;display:block;background:#fff;padding:8px;border-radius:4px;margin-top:6px;">[smarttube latest="10" title="آخر الفيديوهات"]</code>
                    </div>
                    <div style="background:#e0f2f1;border:1px solid #80cbc4;border-radius:6px;padding:14px;">
                        <strong>Programs Grid</strong><br>
                        <code class="stube-copy" style="cursor:pointer;display:block;background:#fff;padding:8px;border-radius:4px;margin-top:6px;">[smarttube_programs auto="true" limit="12" columns="3"]</code>
                    </div>
                    <div style="background:#e3f2fd;border-radius:6px;padding:14px;">
                        <strong>Auto Tabs (All Playlists)</strong><br>
                        <code class="stube-copy" style="cursor:pointer;display:block;background:#fff;padding:8px;border-radius:4px;margin-top:6px;">[smarttube_tabs auto="true" limit="15" header="برامج القناة"]</code>
                    </div>
                </div>
                <div id="stube-copy-toast" style="display:none;position:fixed;bottom:30px;right:30px;background:#1d2327;color:#fff;padding:12px 24px;border-radius:8px;z-index:99999;">✓ Copied!</div>
            </div>
        </div>
        <?php
    }

    // AJAX: Save settings
    public function save_settings() {
        check_ajax_referer( 'stube_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $settings = [
            'api_key'       => sanitize_text_field( $_POST['api_key'] ?? '' ),
            'channel_id'    => sanitize_text_field( $_POST['channel_id'] ?? '' ),
            'cache_ttl'     => absint( $_POST['cache_ttl'] ?? 15 ),
            'columns'       => absint( $_POST['columns'] ?? 3 ),
            'limit'         => absint( $_POST['limit'] ?? 12 ),
            'show_title'    => ! empty( $_POST['show_title'] ),
            'show_date'     => ! empty( $_POST['show_date'] ),
            'show_duration' => ! empty( $_POST['show_duration'] ),
            'play_mode'     => sanitize_text_field( $_POST['play_mode'] ?? 'lightbox' ),
            'thumb_quality' => 'high',
            'bg_color'      => sanitize_hex_color( $_POST['bg_color'] ?? '#14706e' ),
            'header_color'  => sanitize_hex_color( $_POST['header_color'] ?? '#1a1a2e' ),
            'accent_color'  => sanitize_hex_color( $_POST['accent_color'] ?? '#f5a623' ),
        ];

        update_option( 'smarttube_settings', $settings );
        wp_send_json_success( 'Settings saved!' );
    }

    // AJAX: Fetch playlists from channel
    public function fetch_playlists() {
        check_ajax_referer( 'stube_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        SmartTube_YouTube_API::clear_cache();
        $playlists = SmartTube_YouTube_API::get_channel_playlists();

        if ( empty( $playlists ) ) {
            wp_send_json_error( 'No playlists found. Check Channel ID and API Key.' );
        }

        update_option( 'smarttube_playlists', $playlists );
        wp_send_json_success( [ 'count' => count( $playlists ), 'playlists' => $playlists ] );
    }

    // AJAX: Save playlist-category links (receives JSON strings)
    public function save_links() {
        check_ajax_referer( 'stube_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $links   = json_decode( stripslashes( $_POST['links_json'] ?? '{}' ), true ) ?: [];
        $limits  = json_decode( stripslashes( $_POST['limits_json'] ?? '{}' ), true ) ?: [];
        $enabled = json_decode( stripslashes( $_POST['enabled_json'] ?? '{}' ), true ) ?: [];
        $excluded = json_decode( stripslashes( $_POST['excluded_json'] ?? '[]' ), true ) ?: [];

        // Sanitize
        $clean_links = [];
        foreach ( $links as $pl_id => $cat_id ) {
            $clean_links[ sanitize_text_field( $pl_id ) ] = absint( $cat_id );
        }

        $clean_limits = [];
        foreach ( $limits as $pl_id => $limit ) {
            $clean_limits[ sanitize_text_field( $pl_id ) ] = absint( $limit ) ?: 12;
        }

        $clean_enabled = [];
        foreach ( $enabled as $pl_id => $is_on ) {
            $clean_enabled[ sanitize_text_field( $pl_id ) ] = (bool) $is_on;
        }

        $clean_excluded = array_map( 'absint', $excluded );

        // Auto-disable playlists linked to excluded categories
        foreach ( $clean_links as $pl_id => $cat_id ) {
            if ( in_array( $cat_id, $clean_excluded ) ) {
                $clean_enabled[ $pl_id ] = false;
            }
        }

        update_option( 'smarttube_playlist_categories', $clean_links );
        update_option( 'smarttube_playlist_limits', $clean_limits );
        update_option( 'smarttube_enabled_categories', $clean_enabled );
        update_option( 'smarttube_excluded_categories', $clean_excluded );

        $active = count( array_filter( $clean_enabled ) );
        wp_send_json_success( 'Saved! ' . count( $clean_links ) . ' linked, ' . $active . ' showing videos, ' . count( $clean_excluded ) . ' excluded.' );
    }

    // AJAX: Save excluded categories only
    public function save_excluded() {
        check_ajax_referer( 'stube_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $excluded = json_decode( stripslashes( $_POST['excluded_json'] ?? '[]' ), true ) ?: [];
        $excluded = array_map( 'absint', $excluded );

        update_option( 'smarttube_excluded_categories', $excluded );

        // Also disable any playlists linked to excluded categories
        $links   = get_option( 'smarttube_playlist_categories', [] );
        $enabled = get_option( 'smarttube_enabled_categories', [] );
        $disabled_count = 0;

        foreach ( $links as $pl_id => $cat_id ) {
            if ( in_array( (int) $cat_id, $excluded ) ) {
                $enabled[ $pl_id ] = false;
                $disabled_count++;
            }
        }

        update_option( 'smarttube_enabled_categories', $enabled );

        wp_send_json_success( count( $excluded ) . ' categories excluded, ' . $disabled_count . ' playlists auto-disabled.' );
    }

    // AJAX: Create WordPress categories for unlinked playlists (skipping excluded)
    public function create_categories() {
        check_ajax_referer( 'stube_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $playlists = get_option( 'smarttube_playlists', [] );
        $links     = get_option( 'smarttube_playlist_categories', [] );
        $limits    = get_option( 'smarttube_playlist_limits', [] );
        $enabled   = get_option( 'smarttube_enabled_categories', [] );
        $excluded  = get_option( 'smarttube_excluded_categories', [] );

        $created = 0;
        $linked  = 0;

        foreach ( $playlists as $pl ) {
            $pl_id   = $pl['id'];
            $pl_name = trim( $pl['title'] );

            // Skip if already linked
            if ( ! empty( $links[ $pl_id ] ) ) continue;

            // Skip playlists with very few videos
            if ( $pl['videoCount'] < 3 ) continue;

            // Clean name: remove year suffixes, "Copy", etc.
            $clean_name = preg_replace( '/\s*\d{4}\s*$/u', '', $pl_name );
            $clean_name = preg_replace( '/\s*-?\s*(Copy|الموسم\s+\S+)\s*$/ui', '', $clean_name );
            $clean_name = trim( $clean_name );

            if ( empty( $clean_name ) ) continue;

            // Check if a category with this name already exists
            $existing = get_term_by( 'name', $clean_name, 'category' );

            if ( $existing ) {
                // Link to existing category
                $cat_id = $existing->term_id;
            } else {
                // Also check with the full name
                $existing2 = get_term_by( 'name', $pl_name, 'category' );
                if ( $existing2 ) {
                    $cat_id = $existing2->term_id;
                } else {
                    // Create new category
                    $result = wp_insert_term( $clean_name, 'category', [
                        'description' => 'YouTube: ' . $pl_name,
                        'slug'        => sanitize_title( $clean_name ),
                    ] );

                    if ( is_wp_error( $result ) ) {
                        // Might already exist with different casing
                        $existing3 = get_term_by( 'slug', sanitize_title( $clean_name ), 'category' );
                        if ( $existing3 ) {
                            $cat_id = $existing3->term_id;
                        } else {
                            continue;
                        }
                    } else {
                        $cat_id = $result['term_id'];
                        $created++;
                    }
                }
            }

            // Skip if this category is excluded
            if ( in_array( $cat_id, $excluded ) ) continue;

            // Link playlist to category
            $links[ $pl_id ]   = $cat_id;
            $limits[ $pl_id ]  = 12;
            $enabled[ $pl_id ] = true;
            $linked++;
        }

        update_option( 'smarttube_playlist_categories', $links );
        update_option( 'smarttube_playlist_limits', $limits );
        update_option( 'smarttube_enabled_categories', $enabled );

        wp_send_json_success( "Created {$created} new categories, linked {$linked} playlists. Excluded categories were skipped." );
    }

    // AJAX: Load tab content (playlist videos) for frontend
    public static function ajax_load_tab() {
        $playlist_id = sanitize_text_field( $_POST['playlist_id'] ?? '' );
        if ( empty( $playlist_id ) ) {
            echo '<p>Invalid playlist.</p>';
            wp_die();
        }

        $limit = get_option( 'smarttube_playlist_limits', [] );
        $vid_limit = isset( $limit[ $playlist_id ] ) ? (int) $limit[ $playlist_id ] : 12;
        $videos  = SmartTube_YouTube_API::get_playlist_videos( $playlist_id, $vid_limit );
        $settings = SmartTube::get_settings();

        // Find playlist title
        $playlists = get_option( 'smarttube_playlists', [] );
        $pl_title = '';
        foreach ( $playlists as $p ) {
            if ( $p['id'] === $playlist_id ) {
                $pl_title = $p['title'];
                break;
            }
        }

        ob_start();
        SmartTube_Shortcode::render_tab_content( $videos, $settings['play_mode'], $pl_title );
        echo ob_get_clean();
        wp_die();
    }

    // AJAX: Clear cache
    public function clear_cache() {
        check_ajax_referer( 'stube_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        SmartTube_YouTube_API::clear_cache();
        wp_send_json_success( 'Cache cleared!' );
    }

}
