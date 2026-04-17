<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Overrides WordPress category pages to show YouTube playlist videos
 * instead of posts when the category is linked and enabled in SmartTube.
 */
class SmartTube_Category_Override {

    public static function init() {
        // Hook early in template loading to check if we should override
        add_action( 'template_redirect', [ __CLASS__, 'maybe_override' ] );
    }

    /**
     * Check if current category page should show YouTube videos.
     */
    public static function maybe_override() {
        if ( ! is_category() ) return;

        $cat_id = get_queried_object_id();
        if ( ! $cat_id ) return;

        // Check if excluded
        $excluded = get_option( 'smarttube_excluded_categories', [] );
        if ( in_array( $cat_id, $excluded ) ) return;

        // Find linked playlist
        $links   = get_option( 'smarttube_playlist_categories', [] );
        $enabled = get_option( 'smarttube_enabled_categories', [] );
        $limits  = get_option( 'smarttube_playlist_limits', [] );

        $playlist_id = '';
        foreach ( $links as $pl_id => $linked_cat ) {
            if ( (int) $linked_cat === $cat_id && ! empty( $enabled[ $pl_id ] ) ) {
                $playlist_id = $pl_id;
                break;
            }
        }

        if ( empty( $playlist_id ) ) return;

        // This category should show YouTube videos!
        $limit = $limits[ $playlist_id ] ?? 12;

        // Store for later use in the hook
        $GLOBALS['smarttube_override'] = [
            'playlist_id' => $playlist_id,
            'cat_id'      => $cat_id,
            'limit'       => $limit,
        ];

        // Hook into Jannah's archive template
        add_action( 'TieLabs/after_archive_title', [ __CLASS__, 'inject_videos' ], 1 );

        // Prevent the normal post loop from showing
        add_action( 'pre_get_posts', [ __CLASS__, 'empty_query' ], 999 );
    }

    /**
     * Make the main query return no posts (so Jannah shows no post grid).
     */
    public static function empty_query( $query ) {
        if ( $query->is_main_query() && $query->is_category() ) {
            $query->set( 'post__in', [ 0 ] );
        }
    }

    /**
     * Inject YouTube playlist videos after the archive title.
     */
    public static function inject_videos() {
        if ( empty( $GLOBALS['smarttube_override'] ) ) return;

        $data = $GLOBALS['smarttube_override'];
        $playlist_id = $data['playlist_id'];
        $limit       = (int) $data['limit'];

        $videos   = SmartTube_YouTube_API::get_playlist_videos( $playlist_id, $limit );
        $settings = SmartTube::get_settings();
        $dir      = SmartTube::get_direction();
        $mode     = $settings['play_mode'];

        if ( empty( $videos ) ) {
            echo '<div style="text-align:center;padding:40px;color:#666;">No videos found.</div>';
            return;
        }

        $featured = $videos[0];
        $grid_videos = array_slice( $videos, 1 );

        // Find playlist name
        $playlists = get_option( 'smarttube_playlists', [] );
        $pl_name = '';
        foreach ( $playlists as $p ) {
            if ( $p['id'] === $playlist_id ) {
                $pl_name = $p['title'];
                break;
            }
        }
        ?>
        <?php $bg = $settings['bg_color'] ?? '#14706e'; ?>
        <div class="stube-cat-page" dir="<?php echo $dir; ?>">

            <!-- Featured Latest Episode -->
            <div class="stube-cat-featured" style="background:<?php echo esc_attr( $bg ); ?>;">
                <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $featured['id'] ); ?>"
                   class="stube-thumb-link stube-cat-feat-link"
                   data-video-id="<?php echo esc_attr( $featured['id'] ); ?>"
                   data-play-mode="<?php echo esc_attr( $mode ); ?>">
                    <div class="stube-cat-feat-text">
                        <?php if ( ! empty( $featured['date'] ) ) : ?>
                            <span class="stube-cat-feat-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $featured['date'] ) ) ); ?></span>
                        <?php endif; ?>
                        <h2 class="stube-cat-feat-title"><?php echo esc_html( $featured['title'] ); ?></h2>
                        <div class="stube-cat-feat-meta">
                            <?php if ( ! empty( $featured['duration'] ) ) : ?>
                                <span>⏱ <?php echo esc_html( $featured['duration'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $featured['views'] ) && $featured['views'] > 0 ) : ?>
                                <span>👁 <?php echo esc_html( number_format_i18n( $featured['views'] ) ); ?> مشاهدة</span>
                            <?php endif; ?>
                        </div>
                        <span class="stube-cat-feat-btn">◀ شاهد الآن</span>
                    </div>
                    <div class="stube-cat-feat-img">
                        <img src="<?php echo esc_url( $featured['thumbnail'] ); ?>" alt="<?php echo esc_attr( $featured['title'] ); ?>">
                        <?php if ( ! empty( $pl_name ) ) : ?>
                            <span class="stube-cat-badge"><?php echo esc_html( $pl_name ); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>

            <!-- Episodes Grid -->
            <?php if ( ! empty( $grid_videos ) ) : ?>
            <div class="stube-cat-grid" style="background:<?php echo esc_attr( $bg ); ?>;">
                <?php foreach ( $grid_videos as $v ) : ?>
                    <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $v['id'] ); ?>"
                       class="stube-thumb-link stube-cat-vid"
                       data-video-id="<?php echo esc_attr( $v['id'] ); ?>"
                       data-play-mode="<?php echo esc_attr( $mode ); ?>">
                        <div class="stube-cat-vid-thumb">
                            <img src="<?php echo esc_url( $v['thumbnail'] ); ?>" alt="<?php echo esc_attr( $v['title'] ); ?>" loading="lazy">
                            <?php if ( ! empty( $v['duration'] ) ) : ?>
                                <span class="stube-duration"><?php echo esc_html( $v['duration'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="stube-cat-vid-info">
                            <span class="stube-cat-vid-title"><?php echo esc_html( $v['title'] ); ?></span>
                            <?php if ( ! empty( $v['date'] ) ) : ?>
                                <span class="stube-cat-vid-date"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v['date'] ) ) ); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
