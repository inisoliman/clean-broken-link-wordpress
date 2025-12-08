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
                    if ( $this->is_broken( $status, $href ) ) {
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
                    if ( $this->is_broken( $status, $src ) ) {
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
        
        // Check custom whitelist from settings
        $custom_whitelist = get_option( 'smart_cleaner_whitelist', '' );
        if ( ! empty( $custom_whitelist ) ) {
            $domains = array_filter( array_map( 'trim', explode( "\n", $custom_whitelist ) ) );
            foreach ( $domains as $domain ) {
                if ( ! empty( $domain ) && strpos( $url, $domain ) !== false ) {
                    return false; // Skip this URL - it's in the custom whitelist
                }
            }
        }
        
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
            'timeout'     => 5, // Increased from 2 to 5 seconds for slow sites
            'redirection' => 3, // Increased from 2 to 3
            'httpversion' => '1.1', // Changed from 1.0 to 1.1
            'blocking'    => true,
            'headers'     => array(
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Referer'         => home_url(),
                'DNT'             => '1',
                'Connection'      => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ),
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

    /**
     * Check if a status code indicates a broken link.
     * 
     * @param mixed $status HTTP status code or 'error'
     * @param string $url The URL being checked (for domain-specific logic)
     * @return bool True if broken, false otherwise
     */
    private function is_broken( $status, $url = '' ) {
        if ( $status === 'error' ) return true;
        
        $code = intval( $status );
        
        // Handle 403 Forbidden specially
        if ( $code === 403 ) {
            // Known file-sharing and storage sites that block automated requests
            // but are likely working fine
            $safe_domains = array(
                '4shared.com',
                'mediafire.com',
                'mega.nz',
                'drive.google.com',
                'dropbox.com',
                'onedrive.live.com',
                'box.com',
                'sendspace.com',
                'zippyshare.com',
                'uploaded.net',
                'rapidgator.net',
            );
            
            foreach ( $safe_domains as $domain ) {
                if ( strpos( $url, $domain ) !== false ) {
                    return false; // Not broken, just has anti-bot protection
                }
            }
            
            // For other sites, 403 might indicate a real problem
            // but we'll be conservative and not mark as broken
            return false;
        }
        
        // Only treat these as truly broken:
        // 404 Not Found
        // 410 Gone
        // 500+ Server errors
        if ( $code === 404 || $code === 410 || $code >= 500 ) {
            return true;
        }
        
        return false;
    }
}
