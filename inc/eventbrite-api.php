<?php

namespace Eventbrite\Inc;

class Eventbrite {

	//add eventbrite info here
	protected static $apiKey = 'XXXXXXXXXXXXXXXXX';
	protected static $apiSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
	protected static $bearer_token = 'XXXXXXXXXXXXXXXXX';
	protected static $user_id = 'XXXXXXXXXXXX';

	protected static $endpoint = 'https://www.eventbriteapi.com/v3/users/XXXXXXXXXXXX/owned_events/';
	
	var $url_remap = array();
	var $fetch_attachments = true;

	public function get_events() {
		
		$url = add_query_arg(
			array(
				'status' => 'live',
				'token' => self::$apiKey
			),
			self::$endpoint
		);
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . self::$bearer_token
			)
		) );

		$r = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $r ) {
			foreach ( $r->events as $raw_event ) {
				$eventObj = $this::map_event_keys( $raw_event );
				$this::save( $eventObj );
			}
		}

	}

	public function map_event_keys( $api_event ) {
		$event = array();
		$event['ID']           = ( isset( $api_event->id ) )                   ? $api_event->id                          : '';
		$event['post_title']   = ( isset( $api_event->name->text ) )           ? $api_event->name->text                  : '';
		$event['post_content'] = ( isset( $api_event->description->html ) )    ? $api_event->description->html           : '';
		$event['post_date']    = ( isset( $api_event->created ) )              ? $api_event->created                     : '';
		$event['url']          = ( isset( $api_event->url ) )                  ? $api_event->url                         : '';
		$event['logo_url']     = ( isset( $api_event->logo_url ) )             ? $api_event->logo_url                    : '';
		$event['post_status']  = ( isset( $api_event->status ) )               ? $api_event->status                      : '';
		$event['start']        = ( isset( $api_event->start->local ) )         ? date_create( $api_event->start->local ) : '';
		$event['end']          = ( isset( $api_event->end->local ) )           ? date_create( $api_event->end->local )   : '';
		$event['post_author']  = ( isset( $api_event->organizer->name ) )      ? $api_event->organizer->name             : '';
		$event['organizer_id'] = ( isset( $api_event->organizer->id ) )        ? $api_event->organizer->id               : '';
		$event['venue']        = ( isset( $api_event->venue->name ) )          ? $api_event->venue->name                 : '';
		$event['venue_id']     = ( isset( $api_event->venue->id ) )            ? $api_event->venue->id                   : '';
		$event['public']       = ( isset( $api_event->listed ) )               ? $api_event->listed                      : '';
		$event['category']		 = ( isset( $api_event->category->short_name ) ) ? $api_event->category->short_name        : '';
		$event['tags']         = ( isset( $api_event->subcategory->name ) )    ? $api_event->subcategory->name           : '';
		$event['format']       = ( isset( $api_event->format->name ) )         ? $api_event->format->name                : '';         

		return (object) $event;
	}

	public function save( $eventObj ) {

		global $wpdb;

		//Check to see if this event has actually been added. 
		$foundId = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_EventBriteId' AND meta_value=$eventObj->ID");

		$post_id = $foundId[0]->post_id;
		if ( $post_id ) {
			wp_update_post( array(
				'ID' => $foundId, 
				'post_title' => $eventObj->post_title,
				'post_content' => $eventObj->post_content,
				'post_date'		=> $eventObj->post_date,
				'post_author' => 1,
			) );

		} else {

			$post_id = wp_insert_post( array(
				'post_type' => 'tribe_events',
				'post_title' => $eventObj->post_title,
				'post_content' => $eventObj->post_content,
				'post_status' => 'publish',
				'post_date'		=> $eventObj->post_date,
				'post_author' => 1,
			));

			//Static meta
			update_post_meta( $post_id, '_EventShowTickets', 'yes' );
			update_post_meta( $post_id, '_EventVenueID', 304 ); //hardcoded to ID of Amazing Things venue post
			update_post_meta( $post_id, '_EventRegister', 'yes' );
			update_post_meta( $post_id, '_EventOrigin', 'eventbrite-tickets' );

			//only want to run this once. 
			if ( $eventObj->logo_url ) {
				$this->transfer_image( $post_id, $eventObj );
			}
			
		}

		update_post_meta( $post_id, '_EventStartDate', date_format( $eventObj->start, 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_EventEndDate', date_format( $eventObj->end, 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_EventBriteId', $eventObj->ID );
		update_post_meta( $post_id, '_EventURL', esc_url( $eventObj->url ) );

		if ( $eventObj->category ) {
			wp_set_object_terms( $post_id, $eventObj->category, 'tribe_events_cat' );
		}

		if ( $eventObj->tags ) {
			wp_set_object_terms( $post_id, $eventObj->tags, 'post_tag', false );
		}

		if ( $eventObj->format ) {
			wp_set_object_terms( $post_id, $eventObj->format, 'event-type' );
		}
		
	}

	function transfer_image( $post_id, $eventObj ) {

		$file = $eventObj->logo_url;
		$parent_id = $post_id;
		$filetype = wp_check_filetype( basename( $file ) );
		$upload_dir = wp_upload_dir();

		$attachment = array(
			'guid'           => $upload_dir['url'] . '/' . basename( $file ), 
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'upload_date'    => $eventObj->post_date,
		);

		$raw = wp_remote_get( $file );
		$upload = wp_upload_bits( basename($file), 0, $raw['body'], $attachment['upload_date'] );

		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		update_post_meta( $post_id, '_thumbnail_id', $attach_id );
		
	}

}
