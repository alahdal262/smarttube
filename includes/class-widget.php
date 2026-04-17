<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartTube_Widget {

    public static function init() {
        add_action( 'widgets_init', function() {
            register_widget( 'SmartTube_Latest_Widget' );
        } );
    }
}

class SmartTube_Latest_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct( 'smarttube_latest', 'SmartTube - Latest Videos', [
            'description' => 'Display latest YouTube videos from your channel or playlist',
        ] );
    }

    public function widget( $args, $instance ) {
        $title    = $instance['title'] ?? '';
        $playlist = $instance['playlist'] ?? '';
        $limit    = (int) ( $instance['limit'] ?? 5 );
        $layout   = $instance['layout'] ?? 'list';

        echo $args['before_widget'];

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        if ( ! empty( $playlist ) ) {
            $videos = SmartTube_YouTube_API::get_playlist_videos( $playlist, $limit );
        } else {
            $videos = SmartTube_YouTube_API::get_channel_latest( $limit );
        }

        if ( empty( $videos ) ) {
            echo '<p>No videos found.</p>';
            echo $args['after_widget'];
            return;
        }

        $settings = SmartTube::get_settings();
        $dir = SmartTube::get_direction();
        $mode = $settings['play_mode'];
        ?>
        <div class="stube-widget-list" dir="<?php echo $dir; ?>">
            <?php foreach ( array_slice( $videos, 0, $limit ) as $v ) : ?>
                <a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $v['id'] ); ?>"
                   class="stube-widget-item stube-thumb-link"
                   data-video-id="<?php echo esc_attr( $v['id'] ); ?>"
                   data-play-mode="<?php echo esc_attr( $mode ); ?>">
                    <div class="stube-widget-thumb">
                        <img src="<?php echo esc_url( $v['thumbnail'] ); ?>" alt="<?php echo esc_attr( $v['title'] ); ?>" loading="lazy">
                        <?php if ( ! empty( $v['duration'] ) ) : ?>
                            <span class="stube-duration"><?php echo esc_html( $v['duration'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="stube-widget-info">
                        <span class="stube-widget-title"><?php echo esc_html( $v['title'] ); ?></span>
                        <?php if ( ! empty( $v['date'] ) ) : ?>
                            <span class="stube-widget-date"><?php echo esc_html( date_i18n( 'M j', strtotime( $v['date'] ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title    = $instance['title'] ?? 'Latest Videos';
        $playlist = $instance['playlist'] ?? '';
        $limit    = $instance['limit'] ?? 5;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'playlist' ); ?>">Playlist ID (empty = channel latest):</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'playlist' ); ?>" name="<?php echo $this->get_field_name( 'playlist' ); ?>" value="<?php echo esc_attr( $playlist ); ?>" placeholder="Leave empty for channel latest">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'limit' ); ?>">Number of videos:</label>
            <input type="number" class="tiny-text" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" value="<?php echo esc_attr( $limit ); ?>" min="1" max="20">
        </p>
        <?php
    }

    public function update( $new, $old ) {
        return [
            'title'    => sanitize_text_field( $new['title'] ?? '' ),
            'playlist' => sanitize_text_field( $new['playlist'] ?? '' ),
            'limit'    => absint( $new['limit'] ?? 5 ),
        ];
    }
}
