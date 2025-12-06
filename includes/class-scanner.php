<?php
class Smart_Cleaner_Scanner {

    private $time_limit = 15; // Max execution time per batch in seconds
    private $start_time;

    public function __construct() {
        $this->start_time = time();
    }

    /**
     * Scan a single post for broken links and images.
     *
     * @param int $post_id The ID of the post to scan.
     * @param int $link_offset The index of the link to start checking from.
     * @return array Result of the scan.
     */
    public function scan_post( $post_id, $link_offset = 0 ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array( 'error' => 'Post not found' );
        }

        $content = $post->post_content;
        if ( empty( $content ) ) {
            return array(
                'links'  => array(),
                'images' => array(),
                'next_offset' => null, // Done
            );
        }

        return $this->get_broken_items( $content, $link_offset );
    }

    /**
     * Parse content and find broken items with limits.
     */
    public function get_broken_items( $content, $start_offset = 0 ) {
        $broken_links  = array();
        $broken_images = array();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        // Collect all items first
        $all_items = array();
        
        $links = $dom->getElementsByTagName( 'a' );
        foreach ( $links as $link ) {
            $all_items[] = array( 'type' => 'link', 'node' => $link );
        }

        $images = $dom->getElementsByTagName( 'img' );
        foreach ( $images as $img ) {
            $all_items[] = array( 'type' => 'image', 'node' => $img );
        }

        $total_items = count( $all_items );
        $current_offset = 0;
        $next_offset = null;

        foreach ( $all_items as $index => $item ) {
            if ( $index < $start_offset ) {
                continue;
            }

            // Check time limit
            if ( ( time() - $this->start_time ) >= $this->time_limit ) {
                $next_offset = $index;
                break;
            }

            $node = $item['node'];
            if ( $item['type'] === 'link' ) {
                $href = $node->getAttribute( 'href' );
                if ( $this->should_check_url( $href ) ) {
                    $status = $this->check_url( $href );
                    if ( $this->is_broken( $status ) ) {
                        $broken_links[] = array(
                            'url'    => $href,
                            'text'   => $node->nodeValue,
                            'status' => $status,
                        );
                    }
                }
            } else {
                $src = $node->getAttribute( 'src' );
                if ( $this->should_check_url( $src ) ) {
                    $status = $this->check_url( $src );
                    if ( $this->is_broken( $status ) ) {
                        $broken_images[] = array(
                            'url'    => $src,
                            'alt'    => $node->getAttribute( 'alt' ),
                            'status' => $status,
                        );
                    }
                }
            }
        }

        return array(
            'links'  => $broken_links,
            'images' => $broken_images,
            'next_offset' => $next_offset,
        );
    }

    /**
     * Check if a URL should be checked.
     */
    private function should_check_url( $url ) {
        if ( empty( $url ) ) return false;
        if ( strpos( $url, '#' ) === 0 ) return false;
        if ( strpos( $url, 'mailto:' ) === 0 ) return false;
        if ( strpos( $url, 'tel:' ) === 0 ) return false;
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
             if ( strpos( $url, '/' ) === 0 ) return true; 
             return false;
        }
        return true;
    }

    /**
     * Check the HTTP status of a URL.
     */
    private function check_url( $url ) {
        if ( strpos( $url, '/' ) === 0 ) {
            $url = home_url( $url );
        }

        $args = array(
            'timeout'     => 2, // Reduced timeout
            'redirection' => 2,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'body'        => null,
            'cookies'     => array(),
            'sslverify'   => false,
        );

        $response = wp_remote_head( $url, $args );

        if ( is_wp_error( $response ) ) {
            $response = wp_remote_get( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            return 'error';
        }

        return wp_remote_retrieve_response_code( $response );
    }

    private function is_broken( $status ) {
        if ( $status === 'error' ) return true;
        if ( intval( $status ) >= 400 ) return true;
        return false;
    }
}
