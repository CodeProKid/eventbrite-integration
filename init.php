<?php
/*
Plugin Name: Eventbrite Auto Integration
Plugin URI: https://github.com/figmints
Description: A WordPress plugin that bridges the gap between eventbrite and events calendar plugin.
Version: 0.1
Author: Ryan Kanner
Author URI: http://figmints.com
License: GPL v2 or newer
*/

add_filter( 'wp_revisions_to_keep', 'eventbrite_limit_revisions', 10, 2 );

function eventbrite_limit_revisions( $num, $post ) {
    return 3;
}

require_once( 'inc/eventbrite-api.php');
//require_once( 'models/eventbrite.php');
require_once( 'controller.php' );

\Eventbrite\Controller::i()->add_actions();