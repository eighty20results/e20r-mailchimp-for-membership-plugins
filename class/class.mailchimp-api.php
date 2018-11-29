<?php
/*
 * License:

	Copyright 2016-2017 - Eighty / 20 Results by Wicked Strong Chicks, LLC (thomas@eighty20results.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace E20R\MailChimp;

use E20R\Utilities\Utilities;
use E20R\Utilities\Cache;

/**
 * Class MailChimp_API
 * @version 2.1
 */
class MailChimp_API {
	
	/**
	 * @var string $api_key API Key used to access MailChimp API server
	 */
	private static $api_key;
	
	/**
	 * @var string $api_url The base URL to the MailChimp API server
	 */
	private static $api_url;
	
	/**
	 * @var string $dc The datacenter (xxYY where XX is country code, YY is numeric) identifier
	 */
	private static $dc;
	
	/**
	 * @var MailChimp_API $class Instance of this class
	 */
	private static $class;
	
	/**
	 * @var string $user_agent User Agent string for the API class
	 */
	private static $user_agent = 'WordPress/e20r_mailchimp;https://eighty20results.com';
	
	/**
	 * @var array $options Options for the MailChimp lists
	 */
	private $options = array();
	
	/**
	 * @var array $url_args Arguments to use for URI to MailChimp server
	 */
	private $url_args;
	
	/**
	 * @var array $all_lists Lists retrieved from the MC API server
	 */
	private $all_lists = array();
	
	/**
	 * @var string $subscriber_id MD5 encoded ID (email) for a specific subscriber
	 */
	private $subscriber_id;
	
	/**
	 * @var array $error_msg - Array of error messages to list/display
	 */
	private $error_msg = array();
	
	/**
	 * @var array $error_class - Array of CSS classes to use when listing/displaying error messages
	 */
	private $error_class = array();
	
	/**
     * Timeout for the remote request
     *
	 * @var int $default_timeout
	 */
	private $default_timeout = 10;
	
	/**
	 * API constructor - Configure the settings, if the API key gets passed on instantiation.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		
		// Fix the 'groupings setting once it has been converted to interest group(s).
		add_filter( 'e20r-mailchimp-user-defined-merge-tag-fields', array(
			$this,
			'fix_listsubscribe_fields',
		), - 1, 3 );
		
		$this->default_timeout = apply_filters( 'e20r-mailchimp-api-http-timeout', 10 );
	}
	
	/**
	 * Static function to return the datacenter identifier for the API/MailChimp user
	 *
	 * @return string   Mailchimp datacenter identifier
	 */
	public static function get_mc_dc() {
		return self::$dc;
	}
	
	/**
	 * Return the currently saved arguments to use with a wp_remote*() operation
	 *
	 * @return array|null
	 */
	public function get_request_args() {
		
		return ! empty( $this->url_args ) ? $this->url_args : null;
	}
	
	/**
	 * Fetch subscriber specific data from MailChimp.com server
	 *
	 * @param null          $list_id   - Mailchimp list ID
	 * @param \WP_User|null $user_data - User to get info for
	 * @param array|null    $fields    - List of fields to return data for
	 *
	 * @return array|bool|mixed|object - Member information for the specified MC list, or on error false.
	 *
	 */
	public function get_listinfo_for_member( $list_id = null, \WP_User $user_data = null, $fields = null ) {
		
		$utils = Utilities::get_instance();
		
		if ( empty( $list_id ) ) {
			$utils->add_message( __( "Need to specify the list ID to receive member info", Controller::plugin_slug, 'error', 'backend' ) );
			
			return false;
		}
		
		$utils->log( "Try to load from cache first..." );
		$member_info = Cache::get( "{$list_id}_user_{$user_data->ID}", 'e20rmc_user_cache' );
		
		if ( empty( $member_info ) ) {
			
			$utils->log( "Have to load from upstream MailChimp.com server for {$user_data->ID}" );
			
			$url = $this->get_api_url( "/lists/{$list_id}/members/" . $this->subscriber_id( $user_data->user_email ) );
			
			$request = $this->build_request( 'GET', null );
			
			$resp = $this->execute( $url, $request ); // $resp = wp_remote_get( $url, $request );
			$code = wp_remote_retrieve_response_code( $resp );
			
			if ( is_wp_error( $resp ) || 200 > $code || 300 <= $code ) {
				
				$errors = $this->process_error( $resp );
				
				$msg = "Error getting info for {$user_data->ID}: {$errors->title} - {$errors->detail}";
				$utils->log( $msg );
				// $utils->add_message( $msg, 'error', 'backend' );
				
				$member_info = false;
			} else {
				
				$member_info = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
			}
			
			if ( ! empty( $member_info ) ) {
				$utils->log( "Updating cache info for {$user_data->ID}/{$list_id}" );
				Cache::set( "{$list_id}_user_{$user_data->ID}", $member_info, 120, 'e20rmc_user_cache' );
			}
		}
		
		return $member_info;
	}
	
	/**
	 * Build the API request URL to use for the MailChimp API server
	 *
	 * @param null|string $extra
	 *
	 * @return string
	 */
	public function get_api_url( $extra = null ) {
		
		$url = self::$api_url;
		
		if ( ! is_null( $extra ) ) {
			$url .= $extra;
		}
		
		return $url;
	}
	
	/**
	 * Generate a subscriber ID hash used by MailChimp.com to identify the user
	 *
	 * @param string $user_email
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function subscriber_id( $user_email ) {
		$this->subscriber_id = md5( strtolower( $user_email ) );
		
		return $this->subscriber_id;
	}
	
	/**
	 * Return headers to use for request
	 *
	 * @param null|string $command - The HTTP command (GET|PATCH|POST|DEL)
	 * @param null|array  $body    - The HTTP request body
	 *
	 * @return array
	 *
	 * @since v2.6 - ENHANCEMENT: Updated PHPDoc block for Mailchimp_API::build_request() method
	 */
	public function build_request( $command = null, $body = null ) {
		
		if ( is_null( $command ) ) {
			$command = 'GET';
		}
		
		$request = array(
			'method'     => $command,
			'user-agent' => self::$user_agent,
			'timeout'    => isset( $this->url_args['timeout'] ) ? $this->url_args['timeout'] : $this->default_timeout,
			'headers'    => $this->url_args['headers'],
		);
		
		if ( ! empty( $body ) ) {
			$request['body'] = $body;
		}
		
		return $request;
	}
	
	/**
	 * Trigger remote call (use time of call to calculate appropriate timeout value for the remote request)
	 *
	 * @param string $url
	 * @param array  $request
	 *
	 * @return array|\WP_Error
	 *
	 * @since v2.11 - ENHANCEMENT: Dynamic update of remote request timeout value(s)
	 */
	private function execute( $url, $request ) {
		
		$utils = Utilities::get_instance();
		$utils->log( "Using URL: {$url}" );
		
		$start_of_request = current_time( 'timestamp' );
		$request['body'] = isset( $request['body'] ) ? json_encode( $request['body'] ) : null;
		
		$utils->log( "Executing remote request... " . print_r( $request, true ) );
		
		$response = wp_remote_request( $url, $request );
		$utils->log( "Returned from wp_remote_request(). Response contains error? " . ( is_wp_error( $response ) ? 'Yes' : 'No' ) );
		
		$end_of_request = current_time( 'timestamp' );
		
		$current_timeout_limit = floor( ( isset( $request['timeout'] ) ? $request['timeout'] : $this->default_timeout ) * 0.8 );
		$time_of_request       = ( $end_of_request - $start_of_request );
		$max_php_execution     = ( intval( ini_get( 'max_execution_time' ) ) * 0.5 );
		
		$utils->log( "Request took {$time_of_request} seconds..." );
		
		if ( $current_timeout_limit >= $max_php_execution ) {
			$msg = __( 'Error: Timeout >= half of PHP Max Execution Time!', Controller::plugin_slug );
			$utils->log( $msg );
			$utils->add_message( $msg, 'error', 'backend' );
		}
		
		// Extend the timeout value (dynamically)?
		if ( $current_timeout_limit < $time_of_request && $current_timeout_limit < $max_php_execution ) {
			$utils->log( "Have to extend the timeout value ({$time_of_request}) by 50%" );
			$this->url_args['timeout'] = $time_of_request + ceil( $time_of_request * .5 );
		}
		
		return $response;
	}
	
	/**
	 * Process the response (error)
	 *
	 * @param \WP_Error|string $response
	 *
	 * @return \stdClass
	 */
	private function process_error( $response ) {
		
		$utils = Utilities::get_instance();
		
		if ( is_wp_error( $response ) ) {
			$errors         = new \stdClass();
			$errors->detail = implode( ', ', $response->get_error_messages() );
			$errors->title  = sprintf( 'Status: %s', implode( ', ', $response->get_error_codes() ) );
			
		} else {
			$errors = $utils->decode_response( $response['body'] );
		}
		
		return $errors;
	}
	
	/**
	 * Update the users information on the Mailchimp servers
	 *
	 * NOTE: if email address gets updated, the user will get unsubscribed and resubscribed!!!
	 *
	 * @param null          $list_id  - The MC list ID
	 * @param \WP_User|null $old_user - Pre-update WP_User info
	 * @param \WP_User|null $new_user - post-update WP_User Info
	 *
	 * @return bool - Success/failure during update operation
	 *
	 * @since 1.0.0
	 */
	public function update_list_member( $list_id = null, \WP_User $old_user = null, \WP_User $new_user = null ) {
		
		$utils         = Utilities::get_instance();
		$mf_controller = Merge_Fields::get_instance();
		$ig_controller = Interest_Groups::get_instance();
		
		$email_type      = apply_filters( 'e20r_mailchimp_default_mail_type', 'html' );
		$merge_fields    = $mf_controller->populate( $list_id, $new_user );
		$interest_groups = $ig_controller->populate( $list_id, $new_user );
		
		
		if ( $old_user->user_email != $new_user->user_email ) {
			
			$retval = $this->delete( $list_id, $old_user );
			
			// Don't use double opt-in since the user is already subscribed.
			$retval = $retval && $this->subscribe( $list_id, $new_user, $merge_fields, $interest_groups, $email_type, false );
			
			if ( false === $retval ) {
				
				$utils->add_message( __( "Error while updating email address!", Controller::plugin_slug ), 'error', 'backend' );
			}
			
			return $retval;
		}
		
		return $this->remote_user_update( $new_user, $list_id, $merge_fields, $interest_groups );
	}
	
	/**
	 * Unsubscribe user from the specified distribution list (MC)
	 *
	 * @param string        $list_id      - MC distribution list ID
	 * @param \WP_User|null $users        - The User's WP_User object
	 * @param array         $merge_fields - The merge tags and values to configure
	 * @param array         $interests    - The Interests to add the user to/remove them from
	 *
	 * @return bool - True/False depending on whether the operation is successful.
	 *
	 * @since 1.0.0
	 * @since 2.5 - ENHANCEMENT: Added 'Mailchimp_API::delete()' method and deprecated Mailchimp_API::unsubscribe()
	 */
	public function delete( $list_id = '', \WP_User $users = null, $merge_fields = null, $interests = null ) {
		
		$utils         = Utilities::get_instance();
		$unsub_setting = $this->get_option( 'unsubscribe' );
		
		// Can't be empty
		if ( empty( $list_id ) || empty( $users ) ) {
			return false;
		}
		
		// Force the emails into an array
		if ( ! is_array( $users ) ) {
			$users = array( $users );
		}
		
		$url     = $this->get_api_url( "/lists/{$list_id}/members" );
		$request = $this->build_request( 'DELETE', null );
		$retval  = true;
		
		foreach ( $users as $user ) {
		 
			switch ( $unsub_setting ) {
				case 2:
					
					if (empty( $merge_fields) || empty( $interests ) ) {
						$last_level_ids = apply_filters( 'e20r-mailchimp-user-last-level', array(), $user->ID  );
					}
					
					if ( empty( $merge_fields ) ) {
						$merge_fields = Merge_Fields::get_instance()->populate( $list_id,$user,$last_level_ids, true );
					}
					
					if ( empty( $interests ) ) {
						$interests = Interest_Groups::get_instance()->populate( $list_id, $user, $last_level_ids,true );
					}
					
					// Update the mailing list interest groups for the user
					$retval = $retval && $this->remote_user_update( $users, $list_id, $merge_fields, $interests, true );
					break;
				
                default:
					// FIXME: Not handling option #1 or 'ALL'!?!
     
					$user_id  = $this->subscriber_id( $user->user_email );
					$user_url = $url . "/{$user_id}";
					
					$resp = $this->execute( $user_url, $request ); // $resp = wp_remote_request( $user_url, $args );
					$code = wp_remote_retrieve_response_code( $resp );
					
					if ( is_wp_error( $resp ) || 200 > $code || 300 <= $code ) {
						
						$errors = $this->process_error( $resp );
						$msg    = sprintf( __( "Unsubscribe Error: %s", Controller::plugin_slug ), $errors->detail );
						
						$utils->log( "Error submitting unsubscribe request to MailChimp.com: {$msg}" );
						$utils->add_message( $msg, 'warning', 'backend' );
						
						$retval = false;
					}
			}
			
			if ( true === $retval ) {
				Cache::delete( "{$list_id}_user_{$user->ID}", 'e20rmc_user_cache' );
			}
		}
		
		return $retval;
	}
	
	/**
	 * Return the value of the named option
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function get_option( $name = null ) {
		
		$utils = Utilities::get_instance();
		
		if ( empty( $this->options ) ) {
			$this->options = get_option( 'e20r_mc_settings' );
		}
		
		if ( ! is_null( $name ) ) {
			
			// New option...
			if ( ! isset( $this->options[ $name ] ) ) {
				
				$defaults = $this->get_default_options();
				$utils->log( "Defaults contain " . count( $defaults ) . " options. Processing {$name}" );
				
				$this->options[ $name ] = isset( $defaults[ $name ] ) ? $defaults[ $name ] : null;
			}
			
			return isset( $this->options[ $name ] ) ? $this->options[ $name ] : null;
		} else {
			if ( empty( $this->options ) ) {
				$this->options = $this->get_default_options();
				update_option( 'e20r_mc_settings', $this->options, false );
			}
			
			return $this->options;
		}
	}
	
	/**
	 * Create the default options/settings
	 */
	public function get_default_options() {
		
		$membership_class = Member_Handler::get_instance();
		$utils            = Utilities::get_instance();
		
		$options = array(
			"api_key"                 => "",
			"double_opt_in"           => 0,
			"unsubscribe"             => 2,
			"members_list"            => array(),
			"additional_lists"        => array(),
			"level_merge_field"       => "",
			"mc_api_fetch_list_limit" => apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 ),
			"last_server_refresh"     => 0,
			"groupings_updated"       => false,
			"membership_plugin"       => null,
			"wcuser"                  => E20R_MAILCHIMP_BILLING_USER,
		);
		
		$utils->log( "Loading the levels from the membership plugin" );
		$prefix = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		
		$levels_list = $membership_class->get_levels( $prefix );
		
		if ( ! empty( $levels_list ) ) {
			
			foreach ( $levels_list as $level ) {
				$options["level_{$prefix}_{$level->id}_lists"]        = array();
				$options["level_{$prefix}_{$level->id}_interests"]    = array();
				$options["level_{$prefix}_{$level->id}_merge_fields"] = array();
			}
		}
		
		return $options;
	}
	
	/**
	 * Update user record on MailChimp server
	 *
	 * @param \WP_User   $user
	 * @param string     $list_id
	 * @param array|null $merge_fields
	 * @param array|null $interests
	 * @param bool       $show_warnings
	 *
	 * @return false;
	 */
	public function remote_user_update( $user, $list_id, $merge_fields = null, $interests = null, $show_warnings = true ) {
		
		$mc_api = MailChimp_API::get_instance();
		
		$utils = Utilities::get_instance();
		
		$subscriber_id = $mc_api->subscriber_id( $user->user_email );
		$url           = $mc_api->get_api_url( "/lists/{$list_id}/members/{$subscriber_id}" );
		
		$args = array(
			'email_address' => $user->user_email,
			'email_type'    => apply_filters( 'e20r_mailchimp_default_mail_type', 'html' ),
		);
		
		// Configure merge fields & interests
		if ( ! empty( $merge_fields ) ) {
			$utils->log( "Have merge fields to update" );
			$args['merge_fields'] = $merge_fields;
		}
		
		if ( ! empty( $interests ) ) {
			$utils->log( "Have interests to update" );
			$args['interests'] = $interests;
		}
		
		$request = $mc_api->build_request( 'PUT', $args );
		$resp    = $this->execute( $url, $request ); // $resp    = wp_remote_request( $url, $request );
		$code = wp_remote_retrieve_response_code( $resp );
		
		if ( is_wp_error( $resp ) || 200 > $code || 300 <= $code ) {
			
			$errors = $this->process_error( $resp );
			
			$msg = sprintf( __( 'Unable to update %s (ID: %d): %s', Controller::plugin_slug ), $user->user_email, $user->ID, $errors->detail );
			
			$utils->log( $msg );
			
			if ( true === $show_warnings ) {
				$utils->add_message( $msg, 'error', 'frontend' );
			}
			
			return false;
		} else {
			$utils->log( "Response code was: {$code} and we received a payload? " . ( ! empty( $resp['body'] ) ? 'Yes' : 'No' ) );
		}
		
		Cache::delete( "{$list_id}_user_{$user->ID}", 'e20rmc_user_cache' );
		
		return true;
	}
	
	/**
	 * Returns the instance of the current class.
	 *
	 * @return MailChimp_API object (active)
	 * @since 2.0.0
	 */
	public static function get_instance() {
		
		if ( is_null( self::$class ) ) {
			self::$class = new self;
		}
		
		return self::$class;
	}
	
	/**
	 * Subscribe user's email address to the specified list.
	 *
	 * @param string        $list_id      -- MC specific list ID
	 * @param \WP_User|null $user         - The WP_User object
	 * @param array|null    $merge_fields - Merge fields (see Mailchimp API docs).
	 * @param array|null    $interests    - The Interests to add the user to/remove them from
	 * @param string|null   $email_type   - The type of message to send (text or html)
	 * @param bool|null     $dbl_opt_in   - Whether the list should use double opt-in or not
	 *
	 * @return bool -- True if successful, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function subscribe( $list_id = '', \WP_User $user = null, $merge_fields = null, $interests = null, $email_type = 'html', $dbl_opt_in = null ) {
		
		$utils = Utilities::get_instance();
		
		if ( is_null( $dbl_opt_in ) ) {
			$dbl_opt_in = $this->get_option( 'double_opt_in' );
		}
		
		// Can't be empty
		$test = (array) ( $user );
		
		if ( empty( $list_id ) || empty( $test ) ) {
			
			$msgt = "error";
			
			if ( empty( $list_id ) ) {
				$msg = __( "No list specified for MailChimp subscribe operation", Controller::plugin_slug );
			}
			
			if ( empty( $test ) ) {
				$msg = __( "No user specified for MailChimp subscribe operation", Controller::plugin_slug );
			}
			
			$utils->add_message( $msg, 'error', 'backend' );
			
			return false;
		}
		
		$client_ip = method_exists( $utils, 'get_client_ip' ) ? $utils->get_client_ip() : null;
		
		//build request
		$request = array(
			'email_type'       => $email_type,
			'email_address'    => $user->user_email,
			'status'           => ( 1 == $dbl_opt_in ? 'pending' : 'subscribed' ),
			'ip_signup'        => $client_ip,
			'timestamp_signup' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
		);
		
		// add populated merge fields (if applicable)
		if ( ! empty( $merge_fields ) ) {
			$request['merge_fields'] = $merge_fields;
		}
		
		// add populated interests, (if applicable)
		if ( ! empty( $interests ) ) {
			$request['interests'] = $interests;
		}
		
		/**
		 * ENHANCEMENT: Recording the opt-in upstream (on MailChimp.com) - Assuming "no consent given"
		 */
		$user_opted_in    = (bool) $utils->get_variable( 'e20r-consent', false );
		$opt_in_timestamp = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
		
		if ( true === $user_opted_in ) {
			$utils->log( "User opted in so log {$client_ip} at {$opt_in_timestamp} to their record on MailChimp.com." );
			$request['ip_opt']        = $client_ip;
			$request['timestamp_opt'] = $opt_in_timestamp;
		}
		
		$args = $this->build_request( 'PUT', $utils->encode( $request ) );
		$url  = $this->get_api_url( "/lists/{$list_id}/members/" . $this->subscriber_id( $user->user_email ) );
		
		//Connect to api server
		$resp = $this->execute( $url, $args ); // $resp = wp_remote_request( $url, $args );
		$code = wp_remote_retrieve_response_code( $resp );
		
		if ( is_wp_error( $resp ) || 200 > $code || 300 <= $code ) {
			
			$errors = $this->process_error( $resp );
			
			// Try updating the Merge Fields & Interest Groups on the MailChimp Server(s).
			/*
			if ( true === $this->update_server_settings( $list_id, $merge_fields, $interests ) ) {
				
				// retry the update with updated interest & merge groups
				$resp = $this->execute( $url, $args ); // $resp = wp_remote_request( $url, $args );
				$code = wp_remote_retrieve_response_code( $resp );
				
				if ( 200 > $code || 300 <= $code ) {
					
					
					$utils->log( "Error submitting subscription request to {$url}: " . print_r( $utils->decode_response( $resp['body'] ), true ) );
					$utils->add_message( wp_remote_retrieve_response_message( $resp ), 'error', 'backend' );
					
					return false;
				}
				
			} else { */
			
			$utils->log( "Error submitting subscription request to MailChimp.com!" );
			
			$GLOBALS['e20r_mc_error_msg'] = array( 'title' => $errors->title, 'msg' => $errors->detail );
			$utils->log( "Error info: " . print_r( $errors, true ) );
			
			if ( defined( 'DOING_AJAX' ) && false === DOING_AJAX ) {
				$utils->add_message( $errors->title, 'error', 'backend' );
			}
			
			return false;
			/* } */
		}
		
		$GLOBALS['e20r_mc_error_msg'] = null;
		
		Cache::delete( "{$list_id}_user_{$user->ID}", 'e20rmc_user_cache' );
		
		return true;
	}
	
	/**
	 * Compatibility layer
	 *
	 * @param string          $list_id
	 * @param \WP_User[]|null $users
	 * @param array|null      $merge_fields
	 * @param array|null      $interests
	 *
	 * @return bool
	 * @since v2.5 - DEPRECATED: Mailchimp_API::unsubscribe() since v2.5
	 */
	public function unsubscribe( $list_id = '', $users = null, $merge_fields = null, $interests = null ) {
		
		_deprecated_function( __FUNCTION__, '2.5', 'Mailchimp_API::delete()' );
		
		return $this->delete( $list_id, $users, $merge_fields, $interests );
	}
	
	/**
	 * Early filter for 'e20r-mailchimp-user-defined-merge-tag-fields' once the groupings parameter has been processed.
	 *
	 * @param array         $fields  Array of MailChimp Merge Fields defined by the user
	 * @param \WP_User|null $user
	 * @param string|null   $list_id MailChimp mailing list identifier
	 *
	 * @return array
	 */
	public function fix_listsubscribe_fields( $fields, $user = null, $list_id = null ) {
		
		$update_option = $this->get_option( 'groupings_updated' );
		
		// Only process if the 'GROUPINGS' Setting has been converted to an interest group
		if ( ! empty( $update_option ) ) {
			
			if ( in_array( 'groupings', array_keys( $fields ) ) ) {
				unset( $fields['groupings'] );
			}
			
			if ( in_array( 'GROUPINGS', array_keys( $fields ) ) ) {
				unset( $fields['GROUPINGS'] );
			}
		}
		
		return $fields;
	}
	
	/**
	 * Clear the cache for the specific list and data/cache type
	 *
	 * @param string $list_id
	 * @param string $type
	 */
	public function clear_cache( $list_id, $type ) {
		
		switch ( $type ) {
			case 'merge_fields':
				$cache_key = "e20rmc_mf_{$list_id}";
				break;
			case 'interest_groups':
				$cache_key = "e20rmc_ig_{$list_id}";
				break;
			case 'interests':
				$cache_key = "{$list_id}";
				break;
			case 'list_info':
				$cache_key = 'list_info';
				break;
			
			default:
				$cache_key = null;
		}
		
		Cache::delete( $cache_key, 'e20r_mc_api' );
	}
	
	/**
	 * Returns an array of all lists created for the the API key owner
	 *
	 * @param bool $force - Whether to force a list load from the MailChimp.com API server
	 *
	 * @return mixed - Array of all lists, array of lists the user email belongs to, null (no lists defined).
	 *
	 * @since 1.0.0
	 */
	public function get_all_lists( $force = false ) {
		
		$utils = Utilities::get_instance();
		$utils->log( "Looking for lists in the system. Do we force it? " . ( $force ? 'yes' : 'no' ) );
		if ( empty( $this->all_lists ) || true === $force ) {
			
			// Load from local cache (if possible)
			$this->all_lists = get_option( 'e20r_mc_lists', array() );
			
			// Load from Mailchimp.com
			if ( empty( $this->all_lists ) || true === $force ) {
				
				$utils->log( "Loading lists from Mailchimp" );
				
				$api_key = $this->get_option( 'api_key' );
				
				if ( ! empty( $api_key ) ) {
					$utils->log( "Loading lists (nothing configured)" );
					$this->connect();
					$this->load_lists( $force );
				}
			}
		}
		
		return $this->all_lists;
	}
	
	/**
	 * Connect to Mailchimp API services, test the API key & fetch any existing lists.
	 *
	 * @param bool $force
	 *
	 * @return bool - True if able to conenct to MailChimp API services.
	 * @since 1.0.0
	 */
	public function connect( $force = false ) {
		
		$utils = Utilities::get_instance();
		
		if ( false === $this->set_key() ) {
			
			$utils->log( "MailChimp API settings missing!" );
			$msg = sprintf( __( 'Please configure your MailChimp.com settings (API key, etc) on the %1$sE20R MailChimp Settings%2$s page', Controller::plugin_slug ),
				sprintf( '<a href="%1$s" target="_blank">', add_query_arg( 'page', 'e20r_mc_settings', admin_url( 'options-general.php' ) ) ),
				'</a>'
			);
			$utils->add_message( $msg, 'error', 'backend' );
			
			return false;
		}
		
		/**
		 * Set the number of lists to return from the MailChimp server.
		 *
		 * @since 1.0.0
		 *
		 * @param   int $max_lists - Max number of lists to return
		 */
		$limit = $this->get_option( 'mc_api_fetch_list_limit' );
		$max   = ! empty( $limit ) ? $limit : apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 );
		
		/**
		 * Return immediately if we have cached info
		 */
		if ( null !== ( $list_info = Cache::get( 'list_info', 'e20r_mc_api' ) ) ) {
			return true;
		}
		
		$url      = $this->get_api_url( "/lists/?count={$max}" );
		$request  = $this->build_request( 'GET', null );
		$response = $this->execute( $url, $request );
		$code     = wp_remote_retrieve_response_code( $response );
		
		// Fix: is_wp_error() appears to be unreliable since WordPress v4.5
		if ( is_wp_error( $request ) || 200 > $code || 300 <= $code ) {
			
			$utils->log( "Something wrong with the request?!?" );
			
			$errors = $this->process_error( $request );
			
			switch ( wp_remote_retrieve_response_code( $response ) ) {
				case 401:
					$msg = sprintf(
						'%s: <p><em>%s</em> %s',
						__( 'Sorry, but MailChimp was unable to verify your API key. MailChimp gave this response', Controller::plugin_slug ),
						$errors->detail,
						__( 'Please enter your MailChimp API key again.', Controller::plugin_slug )
					);
					
					$utils->add_message( $msg, 'error', 'backend' );
					$utils->log( $msg );
					
					return false;
					break;
				
				default:
					$msg = sprintf(
						__(
							'Error communicating with the MailChimp servers: %1$s',
							Controller::plugin_slug
						),
						sprintf( '<p><em>%s</em></p>', $errors->detail )
					);
					
					$utils->add_message( $msg, 'error', 'backend' );
					$utils->log( $msg );
					
					return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Set the API key for Mailchimp & configure headers for requests.
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function set_key() {
		
		if ( ! empty( $this->options ) ) {
			return true;
		}
		
		$this->options = get_option( "e20r_mc_settings", array() );
		
		// Save the API key
		if ( ! isset( $this->options['api_key'] ) || empty( $this->options['api_key'] ) ) {
			return false;
		}
		
		self::$api_key = $this->options['api_key'];
		
		$this->url_args = array(
			'timeout' => $this->default_timeout,
			'headers' => array(
				'Authorization' => 'Basic ' . self::$api_key,
			),
		);
		
		// The MailChimp datacenter that the key belongs to.
		list( , self::$dc ) = explode( '-', self::$api_key );
		
		// Build the URL based on the datacenter
		self::$api_url    = "https://" . self::$dc . ".api.mailchimp.com/3.0";
		self::$user_agent = apply_filters( 'e20r_mailchimp_api_user_agent', self::$user_agent );
		
		return true;
	}
	
	/**
	 * Load list info from MailChimp (or cache)
	 *
	 * @param bool $force
	 *
	 * @return bool
	 */
	public function load_lists( $force = false ) {
		
		$utils = Utilities::get_instance();
		
		/**
		 * Set the number of lists to return from the MailChimp server.
		 *
		 * @since 1.0.0
		 *
		 * @param   int $max_objects - Max number of objects to return (15 is default)
		 *
		 * @uses  e20r_mailchimp_list_fetch_limit (deprecated)
		 * @uses  e20r_mailchimp_object_fetch_limit
		 */
		$limit     = $this->get_option( 'mc_api_fetch_list_limit' );
		$cache_key = $this->generate_cache_key( null, 'list_info' );
		$max       = apply_filters( 'e20r_mailchimp_list_fetch_limit', ( ! empty( $limit ) ? $limit : 15 ) );
		$max       = apply_filters( 'e20r_mailchimp_object_fetch_limit', $max );
		$body      = null;
		
		$utils->log( "Looking in the {$cache_key} cache for list info from upstream" );
		
		if ( null === ( $body = Cache::get( $cache_key, 'e20r_mc_api' ) ) ) {
			
			$url      = $this->get_api_url( "/lists/?count={$max}" );
			$request  = $this->build_request( 'GET', null );
			$response = $this->execute( $url, $request ); // $response = wp_remote_get( $url, $this->url_args );
			$code     = wp_remote_retrieve_response_code( $response );
			
			// Fix: is_wp_error() appears to be unreliable since WordPress v4.5
			if ( is_wp_error( $response ) || 200 > $code || 300 <= $code ) {
				
				$errors = $this->process_error( $response );
				
				$utils->log( "Something wrong with the request?!?" );
				
				switch ( $code ) {
					case 401:
						$msg = sprintf(
							'%s: <p><em>%s</em> %s',
							__( 'Sorry, but MailChimp was unable to verify your API key. MailChimp gave this response', Controller::plugin_slug ),
							$errors->detail,
							__( 'Please try entering your API key again.', Controller::plugin_slug )
						);
						
						$utils->add_message( $msg, 'error', 'backend' );
						$utils->log( $msg );
						
						$body = false;
						break;
					
					default:
						$msg = sprintf(
							__(
								'Error communicating with the MailChimp.com servers: %1$s',
								Controller::plugin_slug
							),
							sprintf( '<p><em>%s</em></p>', $errors->detail )
						);
						
						$utils->add_message( $msg, 'error', 'backend' );
						$utils->log( $errors->detail );
						
						$body = false;
				}
			} else {
				
				$body = $utils->decode_response( $response['body'] );
				
				if ( ! isset( $body->lists ) ) {
					$utils->add_message( __( 'No Mailing lists found in your MailChimp account!', Controller::plugin_slug ), 'error', 'backend' );
					$body = false;
				}
			}
		}
		
		if ( empty( $body ) || ! isset( $body->lists ) ) {
			$utils->log( "Error: No list info found upstream (or in the cache)!!!" );
			
			return false;
		}
		
		$utils->log( "Found lists..." );
		
		foreach ( $body->lists as $key => $list ) {
			
			// Grab existing settings
			$list_settings = $this->get_list_conf_by_id( $list->id );
			
			// Create the all_lists member variable
			$this->all_lists[ $list->id ]           = array();
			$this->all_lists[ $list->id ]['id']     = $list->id;
			$this->all_lists[ $list->id ]['web_id'] = $list->id;
			$this->all_lists[ $list->id ]['name']   = $list->name;
			
			// Update the list settings
			$list_settings->name                = $list->name;
			$list_settings->id                  = $list->id;
			$list_settings->interest_categories = $this->get_cache( $list->id, 'interest_groups' );
			$list_settings->merge_fields        = $this->get_cache( $list->id, 'merge_fields' ); // $mf_class->get_from_remote( $list->id, false );
			
			// Save the list configuration to persistent storage
			$this->save_list_conf( $list_settings, null, $list->id );
		}
		
		// Update the setting (keep it fresh
		update_option( 'e20r_mc_lists', $this->all_lists, false );
		
		return true;
	}
	
	/**
	 * Create the cache key to use
	 *
	 * @param string $list_id
	 * @param string $type
	 *
	 * @return null|string
	 */
	public function generate_cache_key( $list_id, $type ) {
		
		$utils = Utilities::get_instance();
		
		switch ( $type ) {
			case 'merge_fields':
				$cache_key = "e20rmc_mf_{$list_id}";
				break;
			case 'interest_groups':
				$cache_key = "e20rmc_ig_{$list_id}";
				break;
			case 'interests':
				
				if ( 1 !== preg_match( "/-/", $list_id ) ) {
					$utils->log( "Unexpected list ID provided for interests: {$list_id}" );
				}
				
				$cache_key = "mcint{$list_id}";
				break;
			case 'list_info':
				$cache_key = "e20rmc_lists";
				break;
			default:
				$cache_key = null;
				$utils->log( "ERROR: Key for cache is NULL. That makes no sense!?!" );
		}
		
		return $cache_key;
	}
	
	/**
	 * Return the mcapi list settings variable data for a specific list, or the entire config
	 *
	 * @param null|string $list_id
	 *
	 * @return array
	 */
	public function get_list_conf_by_id( $list_id = null ) {
		
		$utils = Utilities::get_instance();
		
		$list_conf = get_option( 'e20rmcapi_list_settings', array() );
		
		if ( ! empty( $list_conf ) && ! empty( $list_id ) ) {
			
			$utils->log( "Have a list config and a list ID ({$list_id})..." );
			
			if ( empty( $list_conf[ $list_id ] ) ) {
				$list_conf = $this->create_default_list_conf( $list_id );
			}
			
			return $list_conf[ $list_id ];
		}
		
		if ( empty( $list_conf ) && ! empty( $list_id ) ) {
			$utils->log( "No config found, but have a list ID" );
			$list_conf = $this->create_default_list_conf( $list_id );
			
			return $list_conf[ $list_id ];
		}
		
		if ( empty( $list_conf ) && empty( $list_id ) ) {
			$utils->log( 'Neither a list configuration, nor a list ID used' );
			$list_conf = array();
		}
		
		return $list_conf;
	}
	
	/**
	 * Generate a basic (empty) list configuration entry for a specific list ID
	 *
	 * @param string $list_id
	 *
	 * @return array
	 */
	public function create_default_list_conf( $list_id ) {
		
		$utils = Utilities::get_instance();
		$utils->log( "Loading a default config for {$list_id}" );
		
		$list_settings                                  = array();
		$list_settings[ $list_id ]                      = new \stdClass();
		$list_settings[ $list_id ]->name                = null;
		$list_settings[ $list_id ]->id                  = null;
		$list_settings[ $list_id ]->interest_categories = array();
		$list_settings[ $list_id ]->merge_fields        = array();
		
		return $list_settings;
	}
	
	/**
	 * Get locally cached value(s) for the specific MailChimp data
	 *
	 * @param string $list_id List ID or (for 'interests' type, use "{list_id}-{category_id}")
	 * @param string $type    Field type to get the cache of
	 * @param bool   $force   Force the system to bypass the cache & return the data from the MC server
	 *
	 * @return bool|mixed|null
	 */
	public function get_cache( $list_id, $type, $force = false ) {
		
		$mf_controller = Merge_Fields::get_instance();
		$ig_controller = Interest_Groups::get_instance();
		$utils         = Utilities::get_instance();
		
		$data      = null;
		$cache_key = $this->generate_cache_key( $list_id, $type );
		
		$utils->log( "Using cache key: {$cache_key}." );
		
		if ( ! empty( $cache_key ) && ( ( null === ( $data = Cache::get( $cache_key, 'e20r_mc_api' ) ) ) || true === $force ) ) {
			
			$utils->log( "Invalid or empty cache for {$type}/{$cache_key}. Being forced? " . ( $force ? 'Yes' : 'No' ) );
			
			// Invalid cache, load from MC API server
			switch ( $type ) {
				case 'merge_fields':
					$utils->log( "Loading merge fields from remote server" );
					$data = $mf_controller->get_from_remote( $list_id );
					break;
				
				case 'interest_groups':
					$utils->log( "Loading interest groups from remote server" );
					$data = $ig_controller->get_from_remote( $list_id );
					break;
				
				case 'interests':
					
					$utils->log( "Loading interests from remote server" );
					
					// Using 2 part identifier split by ':' character
					// array[0] = List ID, array[1] = Category ID
					$ids = explode( '-', $list_id );
					
					// If the keys can't be located, return empty
					if ( ! empty( $ids[0] ) && ! empty( $ids[1] ) ) {
						$data = $ig_controller->get_interests_for_category( $ids[0], $ids[1] );
					} else {
						$msg = __( "Unable to extract the required category or list identifier for the MailChimp API server", Controller::plugin_slug );
						$utils->add_message( $msg, 'error', 'backend' );
						$utils->log( $msg );
					}
					break;
				
				case 'list_info':
					$utils->log( "Loading list info from MailChimp.com" );
					$this->load_lists( false );
					$data = $this->all_lists;
					break;
			}
			
			if ( ! empty( $data ) ) {
				// Save for future use
				$this->set_cache( $list_id, $type, $data );
			}
		}
		
		return $data;
	}
	
	/**
	 *  Add data to the list specific API server cache values
	 *
	 * @param string $list_id The list for which we're caching values
	 * @param string $type    Type of field to cache
	 * @param array  $data    Data to add to the cache
	 *
	 * @return bool     Success/failure when saving cache "persistently".
	 */
	public function set_cache( $list_id, $type, $data ) {
		
		$cache_key = $this->generate_cache_key( $list_id, $type );
		
		if ( ! is_null( $cache_key ) ) {
			
			/**
			 * @filter e20r-mailchimp-cache-timeout-secs   Configure timeout value for cached MC API Server info
			 *
			 * @param int $cache_timeout Time before the cache refreshes (Default: HOUR_IN_SECONDS)
			 */
			$cache_timeout = apply_filters( 'e20r-mailchimp-cache-timeout-secs', HOUR_IN_SECONDS );
			
			return Cache::set( $cache_key, $data, $cache_timeout, 'e20r_mc_api' );
		}
		
		return false;
	}
	
	/**
	 * Save the list configuration options ('e20rmcapi_list_settings')
	 *
	 * @param mixed $config
	 * @param null  $key
	 * @param null  $list_id
	 *
	 * @return bool
	 */
	public function save_list_conf( $config, $key = null, $list_id = null ) {
		
		$utils = Utilities::get_instance();
		
		// Assuming $config contains the entire tree
		if ( is_null( $list_id ) && is_null( $key ) ) {
			$utils->log( "Replacing configuration settings" );
			
			return update_option( 'e20rmcapi_list_settings', $config, false );
		} else if ( ! empty( $list_id ) ) {
			
			$settings = $this->get_list_conf_by_id();
			
			if ( ! is_null( $key ) ) {
				$utils->log( "Saving to a specific list/key: {$list_id}/{$key}... " );
				$settings[ $list_id ]->{$key} = $config;
			} else if ( empty( $key ) ) {
				$utils->log( "Replacing all settings for {$list_id}" );
				$settings[ $list_id ] = $config;
			}
			
			return update_option( 'e20rmcapi_list_settings', $settings, false );
		}
		
		return false;
	}
	
	/**
	 * Save the named option (or as all options if no named option is specified)
	 *
	 * @param mixed|array $value The individual setting value, or the full array of settings
	 * @param null|string $name
	 *
	 * @return bool
	 */
	public function save_option( $value, $name = null ) {
		
		// Save a single setting/option
		if ( ! is_null( $name ) && in_array( $name, array_keys( $this->options ) ) ) {
			$this->options[ $name ] = $value;
		} else if ( is_null( $name ) && is_array( $value ) ) {
			
			// Process a list of options received
			foreach ( $value as $key => $setting ) {
				
				// Assuming we got the full options list, but only save variables we know about
				if ( in_array( $key, array_keys( $this->options ) ) ) {
					$this->options[ $key ] = $setting;
				}
			}
		}
		
		// Save to persistent storage & return status to caller
		return update_option( 'e20r_mc_settings', $this->options, false );
	}
	
	/**
	 * Show all error message(s) in /wp-admin/
	 *
	 * @since 2.1 Error message display in admin_notice action
	 */
	public function display_errors() {
		
		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || true !== DOING_AJAX ) ) {
			
			// Process all error message(s) for MailChimp API activities
			foreach ( $this->error_msg as $k => $emsg ) {
				?>
                <div class="notice notice-<?php esc_attr_e( $this->error_class[ $k ] ); ?>">
                    <p><?php esc_attr_e( $emsg ); ?></p>
                </div>
				<?php
			}
			
			// Reset error message list
			$this->error_msg   = array();
			$this->error_class = array();
			
		}
	}
	
	/**
	 * Update interest groups & merge fields on the remote MailChimp server (if possible)
	 *
	 * @param   string $list_id ID of MailChimp list to attempt to update
	 *
	 * @return  bool
	 */
	public function update_server_settings( $list_id ) {
		
		$retVal         = true;
		$interestGroups = Interest_Groups::get_instance();
		$mergeFields    = Merge_Fields::get_instance();
		
		// configure & update interest groups both locally & on MC server
		$retVal = $retVal && $interestGroups->sync_config_to_remote( $list_id );
		
		// configure & update merge fields both locally and on MC server
		$retVal = $retVal && $mergeFields->sync_config_to_remote( $list_id );
		
		return $retVal;
	}
}