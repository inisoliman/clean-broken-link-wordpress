<?php
class Smart_Cleaner_Cleaner {

    public function __construct() {
        // Constructor
    }

    /**
     * Clean a post by removing broken links and images.
     *
     * @param int $post_id The ID of the post to clean.
     * @param array $scan_result The result from the scanner.
     * @return bool True on success, false on failure.
     */
    public function clean_post( $post_id, $scan_result ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $content = $post->post_content;
        if ( empty( $content ) ) {
            return false;
        }

        // Suppress errors
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        // Load HTML with proper encoding
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $modified = false;

        // Remove Broken Links (Keep Text)
        if ( ! empty( $scan_result['links'] ) ) {
            $links_to_remove = array();
            foreach ( $scan_result['links'] as $link_data ) {
                $links_to_remove[] = $link_data['url'];
            }

            $links = $dom->getElementsByTagName( 'a' );
            $nodes_to_modify = array();

            foreach ( $links as $link ) {
                $href = $link->getAttribute( 'href' );
                if ( in_array( $href, $links_to_remove ) ) {
                    $nodes_to_modify[] = $link;
                }
            }

            foreach ( $nodes_to_modify as $node ) {
                // Replace node with its text content (unwrap the link, keep the text)
                $text_node = $dom->createTextNode( $node->textContent );
                $node->parentNode->replaceChild( $text_node, $node );
                $modified = true;
            }
        }

        // Remove Broken Images
        if ( ! empty( $scan_result['images'] ) ) {
            $images_to_remove = array();
            foreach ( $scan_result['images'] as $img_data ) {
                $images_to_remove[] = $img_data['url'];
            }

            $images = $dom->getElementsByTagName( 'img' );
            $nodes_to_remove = array();

            foreach ( $images as $img ) {
                $src = $img->getAttribute( 'src' );
                if ( in_array( $src, $images_to_remove ) ) {
                    $nodes_to_remove[] = $img;
                }
            }

            foreach ( $nodes_to_remove as $node ) {
                $node->parentNode->removeChild( $node );
                $modified = true;
            }
        }

        if ( $modified ) {
            // Save only the body content, not the entire HTML document
            $new_content = '';
            $body = $dom->getElementsByTagName('body')->item(0);
            
            if ( $body ) {
                // Get inner HTML of body
                foreach ( $body->childNodes as $child ) {
                    $new_content .= $dom->saveHTML( $child );
                }
            } else {
                // Fallback: save everything
                $new_content = $dom->saveHTML();
            }
            
            // Remove the XML encoding declaration if present
            $new_content = str_replace( '<?xml encoding="UTF-8">', '', $new_content );
            
            wp_update_post( array(
                'ID'           => $post_id,
                'post_content' => $new_content,
            ) );
            return true;
        }

        return false;
    }
}
