<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartTube_YouTube_API {

    /**
     * Get videos from a playlist.
     */
    public static function get_playlist_videos( $playlist_id, $limit = 50 ) {
        $cache_key = 'stube_pl_' . md5( $playlist_id . '_' . $limit );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return array_slice( $cached, 0, $limit );

        $settings = SmartTube::get_settings();
        $api_key  = $settings['api_key'];
        if ( empty( $api_key ) ) return [];

        $videos = [];
        $page_token = '';

        while ( count( $videos ) < $limit ) {
            $url = add_query_arg( [
                'part'       => 'snippet,contentDetails',
                'playlistId' => $playlist_id,
                'maxResults'  => min( 50, $limit - count( $videos ) ),
                'pageToken'   => $page_token,
                'key'         => $api_key,
            ], 'https://www.googleapis.com/youtube/v3/playlistItems' );

            $resp = wp_remote_get( $url, [ 'timeout' => 15 ] );
            if ( is_wp_error( $resp ) ) break;

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( empty( $data['items'] ) ) break;

            foreach ( $data['items'] as $item ) {
                $snippet = $item['snippet'] ?? [];
                $vid_id  = $snippet['resourceId']['videoId'] ?? '';
                if ( empty( $vid_id ) ) continue;

                $thumbs = $snippet['thumbnails'] ?? [];
                $thumb  = $thumbs['maxresdefault']['url']
                       ?? $thumbs['high']['url']
                       ?? $thumbs['medium']['url']
                       ?? $thumbs['default']['url']
                       ?? '';

                $videos[] = [
                    'id'        => $vid_id,
                    'title'     => $snippet['title'] ?? '',
                    'thumbnail' => $thumb,
                    'date'      => $snippet['publishedAt'] ?? '',
                    'position'  => $snippet['position'] ?? 0,
                ];
            }

            $page_token = $data['nextPageToken'] ?? '';
            if ( empty( $page_token ) ) break;
        }

        // Get durations in batch
        if ( ! empty( $videos ) ) {
            $videos = self::enrich_with_duration( $videos, $api_key );
        }

        $ttl = max( 5, (int) $settings['cache_ttl'] ) * MINUTE_IN_SECONDS;
        set_transient( $cache_key, $videos, $ttl );

        return $videos;
    }

    /**
     * Get latest videos from a channel.
     */
    public static function get_channel_latest( $limit = 10 ) {
        $settings   = SmartTube::get_settings();
        $channel_id = $settings['channel_id'];
        $api_key    = $settings['api_key'];
        if ( empty( $channel_id ) || empty( $api_key ) ) return [];

        $cache_key = 'stube_latest_' . md5( $channel_id . $limit );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        // Get uploads playlist ID
        $ch_url = add_query_arg( [
            'part' => 'contentDetails',
            'id'   => $channel_id,
            'key'  => $api_key,
        ], 'https://www.googleapis.com/youtube/v3/channels' );

        $resp = wp_remote_get( $ch_url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $resp ) ) return [];

        $ch_data = json_decode( wp_remote_retrieve_body( $resp ), true );
        $uploads_id = $ch_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
        if ( empty( $uploads_id ) ) return [];

        $videos = self::get_playlist_videos( $uploads_id, $limit );

        $ttl = max( 5, (int) $settings['cache_ttl'] ) * MINUTE_IN_SECONDS;
        set_transient( $cache_key, $videos, $ttl );

        return $videos;
    }

    /**
     * Get all playlists from a channel.
     */
    public static function get_channel_playlists() {
        $settings   = SmartTube::get_settings();
        $channel_id = $settings['channel_id'];
        $api_key    = $settings['api_key'];
        if ( empty( $channel_id ) || empty( $api_key ) ) return [];

        $cache_key = 'stube_playlists_' . md5( $channel_id );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $playlists = [];
        $page_token = '';

        while ( true ) {
            $url = add_query_arg( [
                'part'       => 'snippet,contentDetails',
                'channelId'  => $channel_id,
                'maxResults'  => 50,
                'pageToken'   => $page_token,
                'key'         => $api_key,
            ], 'https://www.googleapis.com/youtube/v3/playlists' );

            $resp = wp_remote_get( $url, [ 'timeout' => 15 ] );
            if ( is_wp_error( $resp ) ) break;

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( empty( $data['items'] ) ) break;

            foreach ( $data['items'] as $item ) {
                $playlists[] = [
                    'id'         => $item['id'],
                    'title'      => $item['snippet']['title'] ?? '',
                    'thumbnail'  => $item['snippet']['thumbnails']['high']['url'] ?? '',
                    'videoCount' => $item['contentDetails']['itemCount'] ?? 0,
                    'date'       => $item['snippet']['publishedAt'] ?? '',
                ];
            }

            $page_token = $data['nextPageToken'] ?? '';
            if ( empty( $page_token ) ) break;
        }

        set_transient( $cache_key, $playlists, HOUR_IN_SECONDS );

        return $playlists;
    }

    /**
     * Enrich videos with duration from videos.list API.
     */
    private static function enrich_with_duration( $videos, $api_key ) {
        $ids = array_column( $videos, 'id' );
        $chunks = array_chunk( $ids, 50 );

        $durations = [];

        foreach ( $chunks as $chunk ) {
            $url = add_query_arg( [
                'part' => 'contentDetails,statistics',
                'id'   => implode( ',', $chunk ),
                'key'  => $api_key,
            ], 'https://www.googleapis.com/youtube/v3/videos' );

            $resp = wp_remote_get( $url, [ 'timeout' => 15 ] );
            if ( is_wp_error( $resp ) ) continue;

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            foreach ( ( $data['items'] ?? [] ) as $item ) {
                $durations[ $item['id'] ] = [
                    'duration'  => self::parse_duration( $item['contentDetails']['duration'] ?? '' ),
                    'views'     => $item['statistics']['viewCount'] ?? 0,
                ];
            }
        }

        foreach ( $videos as &$v ) {
            $v['duration'] = $durations[ $v['id'] ]['duration'] ?? '';
            $v['views']    = $durations[ $v['id'] ]['views'] ?? 0;
        }

        return $videos;
    }

    /**
     * Convert ISO 8601 duration to readable format.
     */
    private static function parse_duration( $iso ) {
        if ( empty( $iso ) ) return '';
        preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m );
        $h = (int) ( $m[1] ?? 0 );
        $min = (int) ( $m[2] ?? 0 );
        $s = (int) ( $m[3] ?? 0 );

        if ( $h > 0 ) {
            return sprintf( '%d:%02d:%02d', $h, $min, $s );
        }
        return sprintf( '%d:%02d', $min, $s );
    }

    /**
     * Clear all caches.
     */
    public static function clear_cache() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_stube_%' OR option_name LIKE '_transient_timeout_stube_%'" );
    }
}
