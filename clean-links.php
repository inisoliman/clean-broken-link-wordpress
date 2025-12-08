<?php
/**
 * Plugin Name: Smart Link & Image Cleaner
 * Plugin URI: https://orsozox.com/
 * Description: A smart plugin to scan, detect, and clean broken links and images from your posts automatically with batch processing.
 * Version: 1.0.5
 * Author: Ibrahim Noshy Soliman
 * Author URI: https://t.me/inoshyi
 * Text Domain: smart-cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'SMART_CLEANER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMART_CLEANER_URL', plugin_dir_url( __FILE__ ) );
define( 'SMART_CLEANER_VERSION', '1.0.5' );

// Include Core Classes
require_once SMART_CLEANER_PATH . 'includes/class-scanner.php';
require_once SMART_CLEANER_PATH . 'includes/class-cleaner.php';

// Admin Menu & Assets
function smart_cleaner_admin_menu() {
	add_menu_page(
		__( 'Smart Cleaner', 'smart-cleaner' ),
		__( 'Smart Cleaner', 'smart-cleaner' ),
		'manage_options',
		'smart-cleaner',
		'smart_cleaner_render_admin_page',
		'dashicons-trash',
		80
	);
}
add_action( 'admin_menu', 'smart_cleaner_admin_menu' );

function smart_cleaner_enqueue_assets( $hook ) {
	if ( 'toplevel_page_smart-cleaner' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'smart-cleaner-css', SMART_CLEANER_URL . 'assets/css/style.css', array(), SMART_CLEANER_VERSION );
	wp_enqueue_script( 'smart-cleaner-js', SMART_CLEANER_URL . 'assets/js/script.js', array( 'jquery' ), SMART_CLEANER_VERSION, true );

	wp_localize_script( 'smart-cleaner-js', 'smartCleaner', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'smart_cleaner_nonce' ),
        'saved_progress' => get_option( 'smart_cleaner_progress', false ),
	) );
}
add_action( 'admin_enqueue_scripts', 'smart_cleaner_enqueue_assets' );

// Render Admin Page
function smart_cleaner_render_admin_page() {
	require_once SMART_CLEANER_PATH . 'admin/admin-page.php';
}

// Initialize Classes (Singleton or simple instantiation)
function smart_cleaner_init() {
    // Hooks for AJAX actions
    add_action( 'wp_ajax_smart_cleaner_scan', 'smart_cleaner_handle_scan' );
}
add_action( 'plugins_loaded', 'smart_cleaner_init' );

function smart_cleaner_handle_scan() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );

    $offset      = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $link_offset = isset( $_POST['link_offset'] ) ? intval( $_POST['link_offset'] ) : 0;
    $post_type   = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
    $dry_run     = isset( $_POST['dry_run'] ) ? filter_var( $_POST['dry_run'], FILTER_VALIDATE_BOOLEAN ) : true;
    $batch_size  = 1; 

    $args = array(
        'post_type'      => $post_type === 'all' ? array( 'post', 'page' ) : $post_type,
        'posts_per_page' => $batch_size,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    );

    $query = new WP_Query( $args );
    $total_posts = $query->found_posts;

    if ( ! $query->have_posts() ) {
        wp_send_json_success( array(
            'done' => true,
            'message' => __( 'Scan complete.', 'smart-cleaner' ),
        ) );
    }

    $scanner = new Smart_Cleaner_Scanner();
    $cleaner = new Smart_Cleaner_Cleaner();
    $results = array();
    
    $next_post_offset = $offset;
    $next_link_offset = 0;
    $partial = false;

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();
        
        // Scan with link offset
        $scan_result = $scanner->scan_post( $post_id, $link_offset );
        
        // Check if partial
        if ( isset( $scan_result['next_offset'] ) && $scan_result['next_offset'] !== null ) {
            $partial = true;
            $next_link_offset = $scan_result['next_offset'];
            $next_post_offset = $offset; // Stay on same post
        } else {
            $next_post_offset = $offset + 1;
            $next_link_offset = 0;
        }

        $cleaned = false;
        if ( ! empty( $scan_result['links'] ) || ! empty( $scan_result['images'] ) ) {
            if ( ! $dry_run ) {
                $cleaner->clean_post( $post_id, $scan_result );
                $cleaned = true;
            } else {
                // Store pending deletions
                $pending = get_option( 'smart_cleaner_pending', array() );
                if ( ! isset( $pending[ $post_id ] ) ) {
                    $pending[ $post_id ] = array( 'links' => array(), 'images' => array() );
                }
                foreach ( $scan_result['links'] as $l ) $pending[ $post_id ]['links'][] = $l;
                foreach ( $scan_result['images'] as $i ) $pending[ $post_id ]['images'][] = $i;
                update_option( 'smart_cleaner_pending', $pending, false );
            }
        }

        $results[] = array(
            'post_id' => $post_id,
            'title'   => get_the_title(),
            'scan'    => $scan_result,
            'cleaned' => $cleaned,
            'partial' => $partial,
        );
        
        // If partial, break loop (though batch_size is 1 anyway)
        if ( $partial ) break;
    }
    wp_reset_postdata();

    // Save progress
    $progress_data = array(
        'offset'       => $next_post_offset,
        'link_offset'  => $next_link_offset,
        'post_type'    => $post_type,
        'dry_run'      => $dry_run,
        'total_posts'  => $total_posts,
        'last_updated' => time(),
    );
    update_option( 'smart_cleaner_progress', $progress_data );

    // Calculate percentage
    $percent = 0;
    if ( $total_posts > 0 ) {
        $percent = ( $offset / $total_posts ) * 100;
        // Add partial progress
        if ( $partial ) {
             // Rough estimate: add 0.5% for partial
             $percent += 0.1; 
        } else {
             $percent = ( $next_post_offset / $total_posts ) * 100;
        }
    }

    wp_send_json_success( array(
        'done'        => false,
        'offset'      => $next_post_offset,
        'link_offset' => $next_link_offset,
        'total'       => $total_posts,
        'results'     => $results,
        'percentage'  => min( 100, round( $percent, 1 ) ),
    ) );
}

// Add action to delete pending items
add_action( 'wp_ajax_smart_cleaner_delete_pending', 'smart_cleaner_handle_delete_pending' );
function smart_cleaner_handle_delete_pending() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );
    
    $pending = get_option( 'smart_cleaner_pending', array() );
    if ( empty( $pending ) ) {
        wp_send_json_error( 'No pending items found.' );
    }

    $cleaner = new Smart_Cleaner_Cleaner();
    $count = 0;

    foreach ( $pending as $post_id => $items ) {
        $cleaner->clean_post( $post_id, $items );
        $count += count( $items['links'] ) + count( $items['images'] );
    }

    delete_option( 'smart_cleaner_pending' );
    wp_send_json_success( array( 'count' => $count ) );
}


// Add action to get pending items for report
add_action( 'wp_ajax_smart_cleaner_get_pending', 'smart_cleaner_handle_get_pending' );
function smart_cleaner_handle_get_pending() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );
    
    $pending = get_option( 'smart_cleaner_pending', array() );
    
    $formatted = array();
    foreach ( $pending as $post_id => $items ) {
        $post = get_post( $post_id );
        if ( ! $post ) continue;
        
        $formatted[] = array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'edit_link' => get_edit_post_link( $post_id ),
            'view_link' => get_permalink( $post_id ),
            'links' => $items['links'],
            'images' => $items['images'],
        );
    }
    
    wp_send_json_success( $formatted );
}

// Add action to delete selected posts' items
add_action( 'wp_ajax_smart_cleaner_delete_selected', 'smart_cleaner_handle_delete_selected' );
function smart_cleaner_handle_delete_selected() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );
    
    $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();
    if ( empty( $post_ids ) ) {
        wp_send_json_error( 'No posts selected.' );
    }
    
    $pending = get_option( 'smart_cleaner_pending', array() );
    $cleaner = new Smart_Cleaner_Cleaner();
    $count = 0;
    
    foreach ( $post_ids as $post_id ) {
        if ( isset( $pending[ $post_id ] ) ) {
            $cleaner->clean_post( $post_id, $pending[ $post_id ] );
            $count += count( $pending[ $post_id ]['links'] ) + count( $pending[ $post_id ]['images'] );
            unset( $pending[ $post_id ] );
        }
    }
    
    update_option( 'smart_cleaner_pending', $pending, false );
    wp_send_json_success( array( 'count' => $count ) );
}

// Add action to reset progress
add_action( 'wp_ajax_smart_cleaner_reset', 'smart_cleaner_handle_reset' );
function smart_cleaner_handle_reset() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );
    delete_option( 'smart_cleaner_progress' );
    wp_send_json_success();
}

// Add action to clear report
add_action( 'wp_ajax_smart_cleaner_clear_report', 'smart_cleaner_handle_clear_report' );
function smart_cleaner_handle_clear_report() {
    // Log that function was called
    error_log('Smart Cleaner: clear_report action called');
    
    // Check nonce
    if ( ! check_ajax_referer( 'smart_cleaner_nonce', 'nonce', false ) ) {
        error_log('Smart Cleaner: Nonce verification failed');
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
        return;
    }
    
    error_log('Smart Cleaner: Nonce verified, deleting options');
    
    // Delete both pending items and progress for a completely fresh start
    $deleted_pending = delete_option( 'smart_cleaner_pending' );
    $deleted_progress = delete_option( 'smart_cleaner_progress' );
    
    error_log('Smart Cleaner: Deleted pending=' . ($deleted_pending ? 'yes' : 'no') . ', progress=' . ($deleted_progress ? 'yes' : 'no'));
    
    wp_send_json_success( array( 
        'message' => __( 'Report and progress cleared successfully. You can now start a fresh scan.', 'smart-cleaner' ),
        'deleted_pending' => $deleted_pending,
        'deleted_progress' => $deleted_progress
    ) );
}

// Add action to save settings
add_action( 'wp_ajax_smart_cleaner_save_settings', 'smart_cleaner_handle_save_settings' );
function smart_cleaner_handle_save_settings() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );
    
    $whitelist = isset( $_POST['whitelist'] ) ? sanitize_textarea_field( $_POST['whitelist'] ) : '';
    
    update_option( 'smart_cleaner_whitelist', $whitelist );
    
    wp_send_json_success( array( 
        'message' => __( 'Settings saved successfully.', 'smart-cleaner' ) 
    ) );
}

// Add action to delete broken items from a single post (for batch processing)
add_action( 'wp_ajax_smart_cleaner_delete_single', 'smart_cleaner_handle_delete_single' );
function smart_cleaner_handle_delete_single() {
    check_ajax_referer( 'smart_cleaner_nonce', 'nonce' );
    
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    
    if ( ! $post_id ) {
        wp_send_json_error( 'Invalid post ID' );
    }
    
    $pending = get_option( 'smart_cleaner_pending', array() );
    
    if ( ! isset( $pending[ $post_id ] ) ) {
        wp_send_json_error( 'No pending items for this post' );
    }
    
    $cleaner = new Smart_Cleaner_Cleaner();
    $result = $cleaner->clean_post( $post_id, $pending[ $post_id ] );
    
    $count = count( $pending[ $post_id ]['links'] ) + count( $pending[ $post_id ]['images'] );
    
    // Remove from pending list
    unset( $pending[ $post_id ] );
    update_option( 'smart_cleaner_pending', $pending, false );
    
    wp_send_json_success( array( 
        'count' => $count,
        'post_id' => $post_id
    ) );
}
