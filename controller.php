<?php

namespace Eventbrite;

class Controller {

	static $i = null;

	public static function i() {
		if ( is_null( self::$i ) ) {
			self::$i = new Controller;
		}
		return self::$i;
	}

	public function add_actions() {

		//hack to force refresh eventbrite sync
		add_action( 'update_option_permalink_structure', function() {
			delete_transient( 'eventbrite_valid' );
		});

		add_action( 'init', function() {
			//delete_transient( 'eventbrite_valid' ); //for testing
			$transient_valid = get_transient( 'eventbrite_valid' );

			if ( !$transient_valid ) {
				$eventbrite = new \Eventbrite\Inc\Eventbrite();
				$eventbrite->get_events();
				set_transient( 'eventbrite_valid', true, HOUR_IN_SECONDS );
			}

		}, 99 );

	}
}

?>