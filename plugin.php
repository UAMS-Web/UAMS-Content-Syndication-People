<?php
/*
Plugin Name: UAMSWP Content Syndication People
Plugin URI: -
Description: Retrieve people for display from people.uams.edu.
Author: uams, Todd McKee, MEd
Author URI: https://web.uams.edu/
Version: 1.0.0
*/

namespace UAMS\ContentSyndicate\People;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'uamswp_content_syndication_shortcodes', 'UAMS\ContentSyndicate\People\activate_shortcodes' );
/**
 * Activates the uamswp_people shortcode.
 *
 * @since 1.0.0
 */
function activate_shortcodes() {
	include_once( dirname( __FILE__ ) . '/includes/class-uams-syndication-shortcode-people.php' );

	// Add the [uamswp_people] shortcode to pull calendar events.
	new \UAMS_Syndication_Shortcode_People();
}
