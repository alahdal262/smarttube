<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartTube_Shortcode {

    public static function init() {
        add_shortcode( 'smarttube', [ __CLASS__, 'render' ] );
        add_shortcode( 'smarttube_programs', [ __CLASS__, 'render_programs' ] );
        add_shortcode( 'smarttube_tabs', [ __CLASS__, 'render_tabs' ] );
        add_shortcode( 'smarttube_category', [ __CLASS__, 'render_category' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue() {
        wp_enqueue_style( 'smarttube', STUBE_URL . 'assets/css/frontend.css', [], STUBE_VERSION );
        wp_enqueue_script( 'smarttube', STUBE_URL . 'assets/js/frontend.js', [], STUBE_VERSION, true );
        wp_localize_script( 'smarttube', 'stubeData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );

        // Inject dynamic colors as CSS variables
        $s = SmartTube::get_settings();
        $bg      = $s['bg_color'] ?? '#14706e';
        $header  = $s['header_color'] ?? '#1a1a2e';
        $accent  = $s['accent_color'] ?? '#f5a623';

        wp_add_inline_style( 'smarttube', "
            :root {
                --stube-bg: {$bg};
                --stube-header: {$header};
                --stube-accent: {$accent};
            }
        " );
    }

    /**
     * [smarttube playlist="PLxxx" channel="UCxxx" latest="10" columns="3" limit="12" title="Section Title"]
     */
    public static function render( $atts = [] ) {
        $settings = SmartTube::get_settings();

        $atts = shortcode_atts( [
            'playlist' => '',
            'channel'  => '',
            'latest'   => '',
            'columns'  => $settings['columns'],
            'limit'    => $settings['limit'],
            'title'    => '',
        ], $atts );

        $videos = [];
        $section_title = $atts['title'];

        // Mode 1: Specific playlist(s)
        if ( ! empty( $atts['playlist'] ) ) {
            $playlist_ids = array_map( 'trim', explode( ',', $atts['playlist'] ) );
            foreach ( $playlist_ids as $pl_id ) {
                $pl_videos = SmartTube_YouTube_API::get_playlist_videos( $pl_id, (int) $atts['limit'] );
                $videos = array_merge( $videos, $pl_videos );
            }
        }
        // Mode 2: Channel latest
        elseif ( ! empty( $atts['channel'] ) || ! empty( $atts['latest'] ) ) {
            $limit = ! empty( $atts['latest'] ) ? (int) $atts['latest'] : (int) $atts['limit'];
            $videos = SmartTube_YouTube_API::get_channel_latest( $limit );
        }
        else {
            return '<p style="color:#d63638;">SmartTube: Please specify playlist="" or latest="" attribute.</p>';
        }

        if ( empty( $videos ) ) {
            return '<p>No videos found.</p>';
        }

        // Limit
        $videos = array_slice( $videos, 0, (int) $atts['limit'] );

        return self::render_grid( $videos, $atts, $settings );
    }

    private static function render_grid( $videos, $atts, $settings ) {
        $dir     = SmartTube::get_direction();
        $cols    = max( 1, min( 6, (int) $atts['columns'] ) );
        $mode    = $settings['play_mode'];
        $title   = $atts['title'] ?? '';

        ob_start();
        ?>
        <div class="stube-wrap" dir="<?php echo $dir; ?>">
            <?php if ( ! empty( $title ) ) : ?>
                <h2 class="stube-section-title"><?php echo esc_html( $title ); ?></h2>
            <?php endif; ?>

            <div class="stube-grid stube-cols-<?php echo $cols; ?>">
                <?php foreach ( $videos as $v ) : ?>
                    <div class="stube-item">
                        <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $v['id'] ); ?>"
                           class="stube-thumb-link"
                           data-video-id="<?php echo esc_attr( $v['id'] ); ?>"
                           data-play-mode="<?php echo esc_attr( $mode ); ?>"
                           target="<?php echo $mode === 'newtab' ? '_blank' : '_self'; ?>"
                           rel="noopener">
                            <div class="stube-thumb-wrap">
                                <img class="stube-thumb" src="<?php echo esc_url( $v['thumbnail'] ); ?>"
                                     alt="<?php echo esc_attr( $v['title'] ); ?>" loading="lazy">
                                <div class="stube-play-btn">
                                    <svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55C3.97 2.33 2.27 4.81 1.48 7.74.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="red"/><path d="M45 24L27 14v20" fill="white"/></svg>
                                </div>
                                <?php if ( ! empty( $v['duration'] ) && $settings['show_duration'] ) : ?>
                                    <span class="stube-duration"><?php echo esc_html( $v['duration'] ); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="stube-info">
                            <?php if ( $settings['show_title'] ) : ?>
                                <h3 class="stube-title"><?php echo esc_html( $v['title'] ); ?></h3>
                            <?php endif; ?>
                            <?php if ( $settings['show_date'] && ! empty( $v['date'] ) ) : ?>
                                <span class="stube-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $v['date'] ) ) ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [smarttube_programs playlists="ID1:Name,ID2:Name" columns="3" videos_per="6"]
     * Or auto-fetch from saved playlists:
     * [smarttube_programs auto="true" limit="12"]
     */
    public static function render_programs( $atts = [] ) {
        $settings = SmartTube::get_settings();
        $dir = SmartTube::get_direction();

        $atts = shortcode_atts( [
            'playlists'  => '',     // ID1:Name,ID2:Name
            'auto'       => '',     // "true" to auto-load from saved playlists
            'limit'      => 12,     // Number of playlists to show
            'columns'    => 3,
            'videos_per' => 6,      // Videos per playlist section
            'featured'   => 'true', // Show featured latest video at top
        ], $atts );

        // Get playlists
        $playlist_data = [];

        if ( ! empty( $atts['playlists'] ) ) {
            $items = explode( ',', $atts['playlists'] );
            foreach ( $items as $item ) {
                $parts = explode( ':', trim( $item ), 2 );
                $playlist_data[] = [
                    'id'    => trim( $parts[0] ),
                    'title' => isset( $parts[1] ) ? trim( $parts[1] ) : '',
                ];
            }
        } elseif ( $atts['auto'] === 'true' ) {
            $saved = get_option( 'smarttube_playlists', [] );
            // Filter: only playlists with 5+ videos
            $saved = array_filter( $saved, function( $p ) { return $p['videoCount'] >= 5; } );
            $playlist_data = array_slice( $saved, 0, (int) $atts['limit'] );
        }

        if ( empty( $playlist_data ) ) {
            return '<p>No playlists configured. Use playlists="ID:Name" or auto="true".</p>';
        }

        $mode = $settings['play_mode'];
        $cols = max( 1, min( 4, (int) $atts['columns'] ) );

        ob_start();
        ?>
        <div class="stube-programs-wrap" dir="<?php echo $dir; ?>">

            <?php
            // Featured: Latest video from channel
            if ( $atts['featured'] === 'true' ) :
                $latest = SmartTube_YouTube_API::get_channel_latest( 1 );
                if ( ! empty( $latest ) ) :
                    $feat = $latest[0];
            ?>
            <div class="stube-featured">
                <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $feat['id'] ); ?>"
                   class="stube-thumb-link stube-featured-link"
                   data-video-id="<?php echo esc_attr( $feat['id'] ); ?>"
                   data-play-mode="<?php echo esc_attr( $mode ); ?>">
                    <div class="stube-featured-thumb">
                        <img src="<?php echo esc_url( $feat['thumbnail'] ); ?>" alt="<?php echo esc_attr( $feat['title'] ); ?>" loading="lazy">
                        <div class="stube-play-btn stube-play-btn-lg">
                            <svg viewBox="0 0 68 48" width="80" height="56"><path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55C3.97 2.33 2.27 4.81 1.48 7.74.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="red"/><path d="M45 24L27 14v20" fill="white"/></svg>
                        </div>
                        <?php if ( ! empty( $feat['duration'] ) ) : ?>
                            <span class="stube-duration"><?php echo esc_html( $feat['duration'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="stube-featured-info">
                        <h2 class="stube-featured-title"><?php echo esc_html( $feat['title'] ); ?></h2>
                        <?php if ( ! empty( $feat['date'] ) ) : ?>
                            <span class="stube-featured-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), strtotime( $feat['date'] ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endif; endif; ?>

            <!-- Programs Grid -->
            <div class="stube-programs-grid stube-prog-cols-<?php echo $cols; ?>">
                <?php foreach ( $playlist_data as $pl ) :
                    $pl_id = $pl['id'];
                    $pl_title = $pl['title'] ?? '';
                    $videos = SmartTube_YouTube_API::get_playlist_videos( $pl_id, (int) $atts['videos_per'] );
                    if ( empty( $videos ) ) continue;
                    $first = $videos[0];
                ?>
                <div class="stube-program-card">
                    <!-- Latest episode thumbnail -->
                    <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $first['id'] ); ?>"
                       class="stube-thumb-link stube-program-thumb-link"
                       data-video-id="<?php echo esc_attr( $first['id'] ); ?>"
                       data-play-mode="<?php echo esc_attr( $mode ); ?>">
                        <div class="stube-program-thumb">
                            <img src="<?php echo esc_url( $first['thumbnail'] ); ?>" alt="<?php echo esc_attr( $first['title'] ); ?>" loading="lazy">
                            <div class="stube-play-btn">
                                <svg viewBox="0 0 68 48" width="48" height="34"><path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55C3.97 2.33 2.27 4.81 1.48 7.74.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="red"/><path d="M45 24L27 14v20" fill="white"/></svg>
                            </div>
                            <?php if ( ! empty( $first['duration'] ) ) : ?>
                                <span class="stube-duration"><?php echo esc_html( $first['duration'] ); ?></span>
                            <?php endif; ?>
                            <!-- Program badge -->
                            <?php if ( ! empty( $pl_title ) ) : ?>
                                <span class="stube-program-badge"><?php echo esc_html( $pl_title ); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <!-- Episode info -->
                    <div class="stube-program-info">
                        <h3 class="stube-program-title"><?php echo esc_html( $first['title'] ); ?></h3>
                        <?php if ( ! empty( $first['date'] ) ) : ?>
                            <span class="stube-program-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $first['date'] ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Tabbed programs layout — matches Yemen TV channel programs page design.
     *
     * [smarttube_tabs playlists="ID1:Name,ID2:Name" videos="9" all_label="الكل" header="برامج القناة"]
     * [smarttube_tabs auto="true" limit="15" header="برامج القناة"]
     */
    public static function render_tabs( $atts = [] ) {
        $settings = SmartTube::get_settings();
        $dir  = SmartTube::get_direction();
        $mode = $settings['play_mode'];

        $atts = shortcode_atts( [
            'playlists'  => '',
            'auto'       => '',
            'limit'      => 15,
            'videos'     => 9,
            'header'     => 'برامج القناة',
            'all_label'  => 'الكل',
            'week_label' => 'آخر الأسبوع',
        ], $atts );

        // Build playlist list
        $tabs = [];

        if ( ! empty( $atts['playlists'] ) ) {
            foreach ( explode( ',', $atts['playlists'] ) as $item ) {
                $parts = explode( ':', trim( $item ), 2 );
                $tabs[] = [ 'id' => trim( $parts[0] ), 'title' => trim( $parts[1] ?? '' ) ];
            }
        } elseif ( $atts['auto'] === 'true' ) {
            $saved = get_option( 'smarttube_playlists', [] );
            $saved = array_filter( $saved, function( $p ) { return $p['videoCount'] >= 5; } );
            foreach ( array_slice( $saved, 0, (int) $atts['limit'] ) as $p ) {
                $tabs[] = [ 'id' => $p['id'], 'title' => $p['title'] ];
            }
        }

        if ( empty( $tabs ) ) {
            return '<p>No playlists configured.</p>';
        }

        // Fetch videos for first tab + "all" (latest from channel)
        $all_videos  = SmartTube_YouTube_API::get_channel_latest( (int) $atts['videos'] );
        $first_videos = SmartTube_YouTube_API::get_playlist_videos( $tabs[0]['id'], (int) $atts['videos'] );

        $uid = 'stube_' . wp_rand( 1000, 9999 );

        ob_start();
        ?>
        <div class="stube-tabs-wrap" dir="<?php echo $dir; ?>" id="<?php echo $uid; ?>">

            <!-- Header + Tabs Bar -->
            <div class="stube-tabs-header">
                <h2 class="stube-tabs-title"><?php echo esc_html( $atts['header'] ); ?></h2>
                <div class="stube-tabs-bar">
                    <button class="stube-tab active" data-tab="all" data-playlist=""><?php echo esc_html( $atts['all_label'] ); ?></button>
                    <button class="stube-tab" data-tab="week" data-playlist="week"><?php echo esc_html( $atts['week_label'] ); ?></button>
                    <?php foreach ( $tabs as $i => $tab ) : ?>
                        <button class="stube-tab" data-tab="pl_<?php echo $i; ?>" data-playlist="<?php echo esc_attr( $tab['id'] ); ?>">
                            <?php echo esc_html( $tab['title'] ); ?>
                        </button>
                    <?php endforeach; ?>
                    <span class="stube-tabs-more">...</span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="stube-tabs-content">

                <!-- "All" tab (default) -->
                <div class="stube-tab-panel active" data-panel="all">
                    <?php self::render_tab_content( $all_videos, $mode, 'all' ); ?>
                </div>

                <!-- "Week" tab — same as all for now, will be filtered by JS -->
                <div class="stube-tab-panel" data-panel="week" style="display:none;">
                    <?php self::render_tab_content( $all_videos, $mode, 'week' ); ?>
                </div>

                <!-- Playlist tabs — first loaded, rest loaded on click via JS -->
                <?php foreach ( $tabs as $i => $tab ) : ?>
                    <div class="stube-tab-panel" data-panel="pl_<?php echo $i; ?>" data-playlist-id="<?php echo esc_attr( $tab['id'] ); ?>" style="display:none;">
                        <?php if ( $i === 0 ) : ?>
                            <?php self::render_tab_content( $first_videos, $mode, $tab['title'] ); ?>
                        <?php else : ?>
                            <div class="stube-tab-loading">Loading...</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render content inside a tab panel: featured video + episode grid.
     */
    /**
     * [smarttube_category] — Auto-detect current category and show linked playlist.
     * [smarttube_category cat_id="20"] — Show playlist linked to category ID 20.
     */
    public static function render_category( $atts = [] ) {
        $settings = SmartTube::get_settings();

        $atts = shortcode_atts( [
            'cat_id'  => '',
            'columns' => $settings['columns'],
            'limit'   => '',
        ], $atts );

        // Detect category
        $cat_id = (int) $atts['cat_id'];
        if ( ! $cat_id && is_category() ) {
            $cat_id = get_queried_object_id();
        }

        if ( ! $cat_id ) {
            return '';
        }

        // Find linked playlist for this category
        $links   = get_option( 'smarttube_playlist_categories', [] );
        $limits  = get_option( 'smarttube_playlist_limits', [] );
        $enabled = get_option( 'smarttube_enabled_categories', [] );

        $playlist_id = '';
        foreach ( $links as $pl_id => $linked_cat ) {
            if ( (int) $linked_cat === $cat_id ) {
                $playlist_id = $pl_id;
                break;
            }
        }

        // Check if category is excluded
        $excluded = get_option( 'smarttube_excluded_categories', [] );
        if ( in_array( $cat_id, $excluded ) ) {
            return '';
        }

        // Return empty if no playlist linked or not enabled (show posts instead)
        if ( empty( $playlist_id ) || empty( $enabled[ $playlist_id ] ) ) {
            return '';
        }

        $limit = (int) $atts['limit'] ?: ( $limits[ $playlist_id ] ?? 12 );

        // Use the standard grid shortcode
        return self::render( [
            'playlist' => $playlist_id,
            'columns'  => $atts['columns'],
            'limit'    => $limit,
            'title'    => '',
        ] );
    }

    public static function render_tab_content( $videos, $mode, $badge_text = '' ) {
        if ( empty( $videos ) ) {
            echo '<p style="text-align:center;color:rgba(255,255,255,0.6);padding:40px;">No videos found.</p>';
            return;
        }

        $featured = $videos[0];
        $grid_videos = array_slice( $videos, 1 );
        ?>

        <!-- Featured Video -->
        <div class="stube-tab-featured">
            <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $featured['id'] ); ?>"
               class="stube-thumb-link stube-tab-feat-link"
               data-video-id="<?php echo esc_attr( $featured['id'] ); ?>"
               data-play-mode="<?php echo esc_attr( $mode ); ?>">
                <div class="stube-tab-feat-info">
                    <?php if ( ! empty( $featured['date'] ) ) : ?>
                        <span class="stube-tab-feat-date">📅 <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $featured['date'] ) ) ); ?></span>
                    <?php endif; ?>
                    <h2 class="stube-tab-feat-title"><?php echo esc_html( $featured['title'] ); ?></h2>

                    <?php
                    // Show description from title parsing
                    $site_name = get_bloginfo( 'name' );
                    $desc = '#' . str_replace( ' ', ' #', $site_name );
                    ?>
                    <p class="stube-tab-feat-desc"><?php echo esc_html( $desc ); ?></p>

                    <!-- Meta: duration + views -->
                    <div class="stube-tab-feat-meta">
                        <?php if ( ! empty( $featured['duration'] ) ) : ?>
                            <span>🕐 <?php echo esc_html( $featured['duration'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $featured['views'] ) ) : ?>
                            <span>👁 <?php echo esc_html( number_format_i18n( $featured['views'] ) ); ?></span>
                        <?php endif; ?>
                    </div>

                    <span class="stube-tab-feat-btn">◀ شاهد الآن</span>
                </div>
                <div class="stube-tab-feat-thumb">
                    <img src="<?php echo esc_url( $featured['thumbnail'] ); ?>" alt="<?php echo esc_attr( $featured['title'] ); ?>" loading="lazy">
                    <?php if ( ! empty( $badge_text ) && $badge_text !== 'all' && $badge_text !== 'week' ) : ?>
                        <span class="stube-program-badge"><?php echo esc_html( $badge_text ); ?></span>
                    <?php endif; ?>
                </div>
            </a>
        </div>

        <!-- Episodes Grid (3 columns) -->
        <?php if ( ! empty( $grid_videos ) ) : ?>
        <div class="stube-tab-grid">
            <?php foreach ( $grid_videos as $v ) : ?>
                <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $v['id'] ); ?>"
                   class="stube-thumb-link stube-tab-episode"
                   data-video-id="<?php echo esc_attr( $v['id'] ); ?>"
                   data-play-mode="<?php echo esc_attr( $mode ); ?>">
                    <div class="stube-tab-ep-thumb">
                        <img src="<?php echo esc_url( $v['thumbnail'] ); ?>" alt="<?php echo esc_attr( $v['title'] ); ?>" loading="lazy">
                        <?php if ( ! empty( $v['duration'] ) ) : ?>
                            <span class="stube-duration"><?php echo esc_html( $v['duration'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="stube-tab-ep-info">
                        <?php if ( ! empty( $v['date'] ) ) : ?>
                            <span class="stube-tab-ep-date">⏱ <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v['date'] ) ) ); ?></span>
                        <?php endif; ?>
                        <span class="stube-tab-ep-title"><?php echo esc_html( $v['title'] ); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif;
    }
}
