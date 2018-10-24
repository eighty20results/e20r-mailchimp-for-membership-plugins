<?php
/**
 * Copyright (c) 2017 - Eighty / 20 Results by Wicked Strong Chicks.
 * ALL RIGHTS RESERVED
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace E20R\MailChimp;

use E20R\Utilities\Utilities;

class Merge_Fields {
	
	/**
	 * @var null|Merge_Fields
	 */
	private static $instance = null;
	
	/**
	 * Merge_Fields constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return or instantiate the Merge_Fields class
	 *
	 * @return Merge_Fields|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * Fetch Merge Fields for specified list ID
	 *
	 * @param string $list_id
	 *
	 * @return mixed
	 */
	public function get( $list_id ) {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		/**
		 * Local definition for list settings (merge fields & interest categories)
		 * @since 2.1
		 *
		 *    {@internal Format of $mcapi_list_settings configuration:
		 *  array(
		 *      $list_id => stdClass(),
		 *                  -> = string
		 *                  ->merge_fields = array( '<merge_field_id>' => mergefield object )
		 *                  ->add_interests = array( $interest_id => boolean, $interest_id => boolean ),
		 *                  ->interest_categories = array(
		 *                          $category_name =>   stdClass(),
		 *                                              ->id
		 *                                              ->interests = array(
		 *                                                      $interest_id => $interest_name,
		 *                                                      $interest_id => $interest_name,
		 *                                              )
		 *                          $category_name =>   [...],
		 *                 )
		 *      $list_id => [...],
		 *  )}}
		 */
		$list_settings = $mc_api->get_list_conf_by_id( $list_id );
		
		/**
		 * Always used in on the site together with the 'e20r_mailchimp_merge_fields' filter.
		 *
		 * @filter e20r-mailchimp-merge-tag-settings - List of MailChimp merge fields to use & how they're supposed to be configured
		 *
		 * @param array  $filter_definitions Default:
		 *                                   array(
		 *                                   array(
		 *                                   'name' => <string>,
		 *                                   'type' => ['text'|'number'|etc],
		 *                                   'public' => [true|false]
		 *                                   ),
		 *                                   [...]
		 *                                   );
		 * @param string $list_id            - the MailChimp identifier for the mailing list
		 *
		 */
		$new_merge_fields = apply_filters( 'e20r-mailchimp-merge-tag-settings', $list_settings->merge_fields, $list_id );
		
		foreach ( $new_merge_fields as $key => $field_def ) {
			
			if ( false === $this->in_merge_fields( $field_def['tag'], $field_def['type'], $list_settings->merge_fields ) ) {
				
				// Get the default merge field definition
				$settings = $this->merge_field_def( $field_def['name'], $list_id );
				
				$utils->log( "Adding merge field to upstream server: {$settings['name']} for type {$settings['type']}" );
				
				$upstream_field = $this->add( $list_id, $settings['name'], $settings['type'], $settings['public'] );
				
				if ( ! empty( $upstream_field ) ) {
					// Add it locally as well
					$list_settings->merge_fields = array_combine(
						array_merge(
							array_keys( $list_settings->merge_fields ),
							array_keys( $upstream_field )
						),
						array_merge(
							array_values( $list_settings->merge_fields ),
							array_values( $upstream_field )
						)
					);
				}
			}
			$utils->log( "Current (updated) merge field settings: " . print_r( $list_settings->merge_fields, true ) );
		}
		
		$mc_api->save_list_conf( $list_settings, null, $list_id );
		
		return $list_settings->merge_fields;
	}
	
	/**
	 * Check if a merge field is in an array of merge fields
	 *
	 * @param   string    $field_tag
	 * @param   string    $field_tag
	 * @param   \stdClass $comparison_field
	 *
	 * @return  boolean|\stdClass
	 */
	public function in_merge_fields( $field_tag, $field_type, $comparison_field ) {
		
		$utils = Utilities::get_instance();
		
		if ( empty( $comparison_field ) ) {
			return false;
		}
		
		$utils->log( "Processing: {$field_tag}/{$field_type}" );
		
		if ( ( isset( $comparison_field->tag ) && $comparison_field->tag === $field_tag && $field_type == $comparison_field->type ) ) {
			
			$utils->log( "Found {$field_tag}/{$field_type} in supplied field definition" );
			
			return $comparison_field;
		}
		
		return false;
	}
	
	/**
	 * Generate a default merge field definition to use with the MC API
	 *
	 * @param string $field_name Then name of the merge field
	 *
	 * @return array    array( 'name' => $field_name, 'type' => $default_type, 'visible' => $default_visibility )
	 */
	private function merge_field_def( $requested_tag, $list_id ) {
		
		$mc_api           = MailChimp_API::get_instance();
		$utils            = Utilities::get_instance();
		$list_settings    = $mc_api->get_list_conf_by_id( $list_id );
		$available_fields = apply_filters( 'e20r-mailchimp-merge-tag-settings', $list_settings->merge_fields, null );
		$field_def        = null;
		
		/**
		 * @filter e20r-mailchimp-default-merge-tag-visibility - Configure the default visibility for a new merge field
		 *
		 * @param bool   $visibility Default: false (not shown in MailChimp forms)
		 * @param string $list_id
		 */
		$default_visibility = apply_filters( 'e20r-mailchimp-default-merge-tag-visibility', false, $list_id );
		
		/**
		 * @filter e20r-mailchimp-default-merge-tag-field-type - Configure the default visibility for a new merge field
		 *
		 * @param string $field_type Default: 'text'
		 * @param string $list_id
		 */
		$default_type = apply_filters( 'e20r-mailchimp-default-merge-tag-field-type', 'text', $list_id );
		
		// Look for an existing definition for the requested field tag/merge field tag
		foreach ( $available_fields as $key => $field ) {
			
			if ( $field['tag'] === $requested_tag ) {
				$utils->log( "Found {$requested_tag}: " . print_r( $field, true ) );
				$field_def = $field;
				break;
			}
		}
		
		$new_field = array();
		
		// Process the field definition
		if ( ! empty( $field_def ) ) {
			
			foreach ( $field_def as $key => $setting ) {
				
				switch ( $key ) {
					case 'type':
						
						if ( empty( $setting ) ) {
							$new_field[ $key ] = $default_type;
						} else {
							$new_field[ $key ] = $setting;
						}
						
						break;
					case 'public':
						
						if ( empty( $setting ) ) {
							$new_field[ $key ] = $default_visibility;
						} else {
							$new_field[ $key ] = $setting;
						}
						
						break;
					default:
						$new_field[ $key ] = $setting;
				}
			}
		} else {
			$utils->log( "No definition found so using default values" );
			$new_field = array(
				'tag'    => $requested_tag,
				'name'   => $requested_tag,
				'type'   => $default_type,
				'public' => $default_visibility,
			);
		}
		
		return $new_field;
	}
	
	/**
	 * Add a merge field to a list (very basic)
	 *
	 * @param string $name        - The Merge Field Name
	 * @param string $tag         - The Merge Field Tag
	 * @param string $type        - The Merge Field Type (text, number, date, birthday, address, zip code, phone,
	 *                            website)
	 * @param mixed  $public      - Whether the field should show on the subscribers MailChimp profile. Defaults to
	 *                            false.
	 * @param string $list_id     - The MC list ID
	 *
	 * @return mixed - Merge field or false
	 * @since 2.0.0
	 */
	public function add( $list_id, $tag, $name, $type = null, $public = false ) {
		
		//default type to text
		if ( empty( $type ) ) {
			$type = 'text';
		}
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
		if ( strlen( $tag ) > 10 ) {
			$utils->add_message( sprintf( __( "Membership tag (%s) exceeds 10 characters. Will truncate it.", Controller::plugin_slug ), $tag ), 'warning', 'backend' );
			
			
		}
		//prepare request
		$new_field = array(
			'tag'    => $tag,
			'name'   => $name,
			'type'   => $type,
			'public' => $public,
		);
		
		// Build the API request to transmit
		$add_args = $mc_api->build_request( 'POST', $utils->encode( $new_field ) );
		
		$utils->log( "Adding {$tag}/{$name} to mailchimp server: " . print_r( $add_args, true ) );
		
		// Build the API URL for the request
		$url = $mc_api->get_api_url( "/lists/{$list_id}/merge-fields/" );
		
		// Connect to the upstream API server
		$response = wp_remote_request( $url, $add_args );
		$code     = wp_remote_retrieve_response_code( $response );
		
		$merge_field = array();
		
		$utils->log( "Response Code: " . print_r( $code, true ) );
		
		// Check API status/response
		if ( 200 > $code || 300 <= $code ) {
			
			$error_body = $utils->decode_response( wp_remote_retrieve_body( $response ) );
			
			$utils->log( "Error: " . print_r( $error_body, true ) );
			
			if ( ! empty( $error_body->title ) && false === stripos( $error_body->title, 'Invalid Resource' ) ) {
				
				$msg = wp_remote_retrieve_response_message( $response );
				
				$utils->log( "Add Merge Field - Status: {$code}, Error Message: {$error_body->detail}, From {$url}" );
				$utils->add_message( "{$error_body->title}: {$error_body->detail}", 'error', 'backend' );
				
				return false;
			}
			
			// The field is already present upstream...
			$all_upstream = $mc_api->get_cache( $list_id, 'merge_fields', false );
			
			$merge_fields = array();
			foreach ( $all_upstream as $u_field ) {
				
				$utils->log( "Looking for {$tag} in {$u_field['tag']} upstream definition" );
				$merge_fields[ $u_field['tag'] ] = $this->merge_field_def( $u_field['tag'], $list_id );
				$merge_fields[ $u_field['tag'] ] = $u_field;
			}
			
			$utils->log( "Saving updated merge fields from upstream for {$list_id}: " . print_r( $merge_fields, true ) );
			$utils->log( "{$tag} exists on the MailChimp server: " . print_r( $merge_fields[ $tag ], true ) );
			
		} else {
			// API request was successful
			$field_def = $utils->decode_response( wp_remote_retrieve_body( $response ) );
			$utils->log( "New Merge Field definition for {$tag}: " . print_r( $field_def, true ) );
			$tag                  = $field_def->tag;
			$merge_fields[ $tag ] = (array) $field_def;
			unset( $merge_fields[ $tag ]['_links'] );
			unset( $merge_fields[ $tag ]['list_id'] );
			
			$utils->log( "Using Merge Field definition for {$tag}: " . print_r( $merge_fields[ $tag ], true ) );
		}
		
		return $merge_fields[ $tag ];
	}
	
	/**
	 * Get previously defined merge fields for a list (via MC API)
	 *
	 * @param string $list_id - The MC list ID
	 * @param bool   $force   - Whether to force a read/write
	 *
	 * @return mixed - False if error | Merge fields for the list_id
	 * @since 2.0.0
	 */
	public function get_from_remote( $list_id, $force = false ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
		$list_settings = $mc_api->get_list_conf_by_id( $list_id );
		$limit         = $mc_api->get_option( 'mc_api_fetch_list_limit' );
		$max           = ! empty( $limit ) ? $limit : apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 );
		
		// Prepare to access the API
		$url          = $mc_api->get_api_url( "/lists/{$list_id}/merge-fields/?count={$max}" );
		$default_args = $mc_api->build_request( 'GET', null );
		
		
		// Connect to mailchimp.com
		$response = wp_remote_request( $url, $default_args );
		$code     = wp_remote_retrieve_response_code( $response );
		
		// Check the returned response
		if ( 200 > $code || 300 <= $code ) {
			$utils->add_message( wp_remote_retrieve_response_message( $response ), 'error', 'backend' );
			
			return false;
		}
		
		$body   = $utils->decode_response( wp_remote_retrieve_body( $response ) );
		$fields = ! empty( $body->merge_fields ) ? $body->merge_fields : array();
		
		$utils->log( "Received merge fields? " . ( ! empty( $body->merge_fields ) && ! empty( $fields ) ? 'Yes' : 'No' ) );
		
		if ( empty( $list_settings ) ) {
			
			$list_settings = new \stdClass();
		}
		
		if ( ! empty( $list_settings ) && empty( $list_settings->merge_fields ) ) {
			
			$list_settings->merge_fields = array();
		}
		
		$utils->log( "Received " . count( $fields ) . " merge fields from upstream server." );
		
		foreach ( $fields as $field ) {
			
			// Clean up list (if needed)
			if ( ! empty( $list_settings->merge_fields[ $field->tag ] ) ) {
				unset( $list_settings->merge_fields[ $field->tag ] );
			}
			
			// Save the field definition (stdClass)
			$list_settings->merge_fields[ $field->tag ] = (array) $field;
			unset( $list_settings->merge_fields[ $field->tag ]['_links'] );
			unset( $list_settings->merge_fields[ $field->tag ]['list_id'] );
		}
		
		$utils->log( "Updating local merge field list settings for {$list_id}." );
		
		// Update the MailChimp API settings for list (no autoload)
		$mc_api->save_list_conf( $list_settings, null, $list_id );
		$mc_api->set_cache( $list_id, 'merge_fields', $list_settings->merge_fields );
		
		return $list_settings->merge_fields;
		
	}
	
	/**
	 * Add/configure merge fields for the specified list ID (and the user object)
	 *
	 * @param   string     $list_id    -- The ID of the MC mailing list
	 * @param   \WP_User   $user       - User object
	 * @param   null|array $level_ids  - The membership level(s) the user has
	 * @param   false|bool $cancelling - Whether or not we're processing a membership cancellation
	 *
	 * @return array|null  {
	 *      Merge field array w/field name & data value.
	 * @type    string     $name       Merge Field name
	 * @type    string     $value      Merge Field value
	 * }
	 */
	public function populate( $list_id, $user, $level_ids = null, $cancelling = false ) {
		
		$utils            = Utilities::get_instance();
		$mc_api           = MailChimp_API::get_instance();
		$level            = null;
		$membership_level = null;
		
		$has_membership_system = apply_filters( 'e20r-mailchimp-membership-plugin-present', false );
		
		$data_fields = array();
		$level       = null;
		
		foreach ( $level_ids as $level_id ) {
			
			$level = apply_filters( 'e20r-mailchimp-get-membership-level-definition', $level, $level_id );
			
			if ( true === $has_membership_system && ! empty( $level ) ) {
				
				$utils->log( "User {$user->user_email} has an an active membership level" );
			}
			
			/**
			 * Always used in on the site together with the 'e20r-mailchimp-merge-tag-settings' filter.
			 *
			 * @filter  e20r-mailchimp-user-defined-merge-tag-fields - The merge field value/data array to submit to the MailChimp distribution list
			 * @uses    e20r-mailchimp-merge-tag-settings - The field definitions
			 *
			 * @param array     $field_values - Array: array( 'FIELDNAME' => value, 'FIELDNAME2' => value, ... )
			 * @param \WP_USer  $user         - User object we're processing for
			 * @param string    $list_id      - The MailChimp identifier for the Mailing list
			 * @param \stdClass $level        - The membership level definition for this user's primary membership level
			 *
			 * @since   1.0
			 */
			$new_data_fields = apply_filters( "e20r-mailchimp-user-defined-merge-tag-fields", array(), $user, $list_id, $level_id );
			$data_fields     = array_combine(
				array_merge(
					array_keys( $data_fields ),
					array_keys( $new_data_fields )
				),
				array_merge(
					array_values( $data_fields ),
					array_values( $new_data_fields )
				)
			);
		}
		
		if ( empty( $level_ids ) ) {
			/**
			 * Always used in on the site together with the 'e20r-mailchimp-merge-tag-settings' filter.
			 *
			 * @filter  e20r-mailchimp-user-defined-merge-tag-fields - The merge field value/data array to submit to the MailChimp distribution list for the user
			 * @uses    e20r-mailchimp-merge-tag-settings - The field definitions
			 *
			 * @param array    $field_values - Array: array( 'FIELDNAME' => value, 'FIELDNAME2' => value, ... )
			 * @param \WP_USer $user         - User object we're processing for
			 * @param string   $list_id      - The MailChimp identifier for the Mailing list
			 * @param null     $level        - The membership level definition for this user's primary membership level
			 *
			 * @since   1.0
			 */
			$new_data_fields = apply_filters( "e20r-mailchimp-user-defined-merge-tag-fields", array(), $user, $list_id, null );
			$data_fields     = array_combine(
				array_merge(
					array_keys( $data_fields ),
					array_keys( $new_data_fields )
				),
				array_merge(
					array_values( $data_fields ),
					array_values( $new_data_fields )
				)
			);
		}
		
		// Only (possibly) add new merge fields upstream if we're adding user to new/updating list.
		if ( false === $cancelling ) {
			
			foreach ( $level_ids as $level_id ) {
				$this->maybe_push_merge_fields( $list_id, $level_id );
			}
		}
		
		$utils->log( "User specified merge field data for {$list_id} and user {$user->ID}: " . print_r( $data_fields, true ) );
		
		// Skip old groupings (API v2 feature)?
		foreach ( $data_fields as $key => $value ) {
			
			if ( 'groupings' === strtolower( $key ) ) {
				
				// clear from merge field list
				unset( $data_fields[ $key ] );
			}
		}
		
		return $data_fields;
	}
	
	/**
	 * Push merge field definitions that we suspect do no yet exist upstream (on mailchimp.com)
	 *
	 * @param string $list_id  The MailChimp.com List ID (alphanumeric)
	 * @param int    $level_id The Membership Level ID (if it exists)
	 */
	public function maybe_push_merge_fields( $list_id, $level_id = null ) {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		
		$utils->log( "Loading settings for list: {$list_id}" );
		
		// Get local cache/config for merge fields
		$list_settings = $mc_api->get_list_conf_by_id( $list_id );
		
		/**
		 * Always used in on the site together with the 'e20r-mailchimp-user-defined-merge-tag-fields' filter.
		 *
		 * @filter  e20r-mailchimp-merge-tag-settings - The merge field value/data array to submit to the MailChimp distribution list
		 * @uses    e20r-mailchimp-user-defined-merge-tag-fields - The field definitions
		 *
		 * @param array  $field_values - Array: array( 'FIELDNAME' => $settings, 'FIELDNAME2' => $settings, ... )
		 * @param string $list_id      - The MailChimp identifier for the Mailing list
		 * @param int    $level_id     - The membership level ID to select field settings for (if applicable)
		 *
		 * @since   1.0
		 */
		$field_defs = apply_filters( 'e20r-mailchimp-merge-tag-settings', $list_settings->merge_fields, $list_id, $level_id );
		
		$utils->log( "User specified merge field config for {$list_id}: " . print_r( $field_defs, true ) );
		
		// Check whether there are upstream merge fields we need to worry about
		if ( empty( $list_settings->merge_fields ) ) {
			
			$utils->log( "Attempting to load merge field settings from cache (if active)" );
			$list_settings->merge_fields = $mc_api->get_cache( $list_id, 'merge_fields' );
		}
		
		$local_fields = $list_settings->merge_fields;
		
		$utils->log( "Have " . count( $local_fields ) . " merge fields configured locally" );
		
		$is_compared = false;
		
		// Identify any configured merge fields that are (now) stored locally (and remove them from the user's list)
		if ( ! empty( $field_defs ) ) {
			
			foreach ( $field_defs as $key => $field ) {
				
				if ( ! empty( $local_fields ) && isset( $local_fields[ $field['tag'] ] ) ) {
					$utils->log( "Clearing the tag value ({$field['tag']}) for user requested field since it's already saved locally/remotely" );
					$is_compared = true;
					unset( $field_defs[ $key ] );
				}
			}
			
			$to_remote = $field_defs;
		}
		
		// check if there's a difference between what we have stored & what the user has specified in filters, etc.
		if ( empty( $to_remote ) && false == $is_compared ) {
			$utils->log( "Using all of the fields received by the filter" );
			$to_remote = apply_filters( 'e20r-mailchimp-merge-tag-settings', $list_settings->merge_fields, $list_id );
			
		} else {
			$utils->log( "Found " . count( $to_remote ) . " new field definitions to add upstream..." );
		}
		
		// update the server configuration for the merge fields
		if ( ! empty( $to_remote ) ) {
			
			$utils->log( "Attempting to add the following merge fields: " . print_r( $to_remote, true ) );
			$utils->log( "Config changed so have to sync upstream" );
			$this->sync_config_to_remote( $list_id, $to_remote );
		}
	}
	
	/**
	 * Configure merge fields for Mailchimp (uses filter)
	 *
	 * @param       string $list_id     - The MC list ID
	 * @param       array  $list_fields - Array of merge fields we don't think are defined on the MailChimp server
	 *
	 * @type   array       $list_fields (
	 * @type   string      $tag         tag
	 * @type   string      $tag         name
	 * @type   string|null $type        field type
	 * @type   bool        $public      Whether the field is to be hidden (false)
	 *      )
	 *
	 * @return      array                  - Merge field list
	 *
	 * @since 2.1
	 */
	public function sync_config_to_remote( $list_id, $list_fields = array() ) {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		$list_settings  = $mc_api->get_list_conf_by_id( $list_id );
		$upstream_field = array();
		
		// Nothing to be done.
		if ( empty( $list_fields ) && empty( $list_settings->merge_fields ) ) {
			$utils->log( "No fields to process!" );
			
			return array();
		}
		
		// Process all user defined and default merge fields to see if we need to update the upstream server.
		foreach ( $list_fields as $key => $field ) {
			
			$utils->log( "We need to add {$field['tag']}?" );
			
			// Get the default merge field definition
			$new_def = $this->merge_field_def( $field['tag'], $list_id );
			
			$utils->log( "Adding merge field to upstream server: {$new_def['tag']} of type {$new_def['type']}" );
			
			$upstream_field[ $new_def['tag'] ] = $this->add( $list_id, $new_def['tag'], $new_def['name'], $new_def['type'], $new_def['public'] );
			
			if ( ! empty( $upstream_field ) ) {
				// Add it locally as well
				$list_settings->merge_fields = array_combine(
					array_merge(
						array_keys( $list_settings->merge_fields ),
						array_keys( $upstream_field )
					),
					array_merge(
						array_values( $list_settings->merge_fields ),
						array_values( $upstream_field )
					)
				);
			}
		}
		
		$mc_api->save_list_conf( $list_settings, null, $list_id );
		$mc_api->clear_cache( $list_id, 'merge_fields' );
		
		$utils->log( "Returning " . count( $list_settings->merge_fields ) . " merge fields from remote update operation" );
		
		return $list_settings->merge_fields;
	}
	
	/**
	 * Load data if the user has specified a
	 *
	 * @param array       $fields
	 * @param \WP_User    $user
	 * @param null|string $list_id
	 *
	 * @return array
	 */
	public function admin_defined_listsubscribe( $fields, $user, $list_id = null ) {
		
		$utils = Utilities::get_instance();
		
		global $current_user;
		
		if ( empty( $user ) ) {
			
			$user_id = $utils->get_variable( 'user_id', null );
			
			if ( empty( $user_id ) ) {
				$user_id = $current_user->ID;
			}
			
			$user = get_userdata( $user_id );
		}
		
		
		if ( ! empty( $user ) && ! isset( $user->membership_levels ) ) {
			$user->membership_levels = pmpro_getMembershipLevelsForUser( $user->ID, true );
		}
		
		if ( ! empty( $user ) && ! isset( $user->membership_level ) ) {
			$user->membership_level = pmpro_getMembershipLevelForUser( $user->ID, true );
		}
		
		$prefix   = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		$level_id = isset( $user->membership_level->id ) ? $user->membership_level->id : null;
		
		// Try to locate the level ID for the user (even if they're cancelling)
		if ( ! empty( $user ) && empty( $level_id ) ) {
			
			$utils->log( "No membership level found for {$user->ID}" );
			// TODO: Grab the most recent membership level (or last order) for the user (by filter)
			
			if ( empty( $level_id ) ) {
				//$levels_to_unsubscribe_from, $user_id, $current_user_level_ids, $statuses
				$previous_level_ids = apply_filters( 'e20r-mailchimp-user-old-membership-levels', array(), $user->ID, $level_id, array( 'cancelled' ) );
				if ( ! empty( $previous_level_ids ) ) {
					
					// Grab the first level ID from the previous level ID list (most recent)
					$level_id = array_shift( $previous_level_ids );
				} else {
					$utils->log( "Error: Unable to locate the user's previous 'membership' level/product order" );
					
					// Giving up!
					return $fields;
				}
			}
		}
		
		$mc_api         = MailChimp_API::get_instance();
		$settings_class = MC_Settings::get_instance();
		
		if ( ! empty( $user ) && isset( $user->membership_level->id ) ) {
			$level_settings = $mc_api->get_option( "level_{$prefix}_{$level_id}_merge_fields" );
		}
		
		// Tro to grab the list ID from settings
		if ( empty( $list_id ) && ! empty( $level_id ) ) {
			
			$list_ids = $mc_api->get_option( "level_{$prefix}_{$level_id}_lists" );
			
			if ( empty( $list_ids ) ) {
				$utils->log( "No level specific list found for {$level_id}" );
				$list_ids = $mc_api->get_option( "members_list" );
			}
			
			// Grab the first entry in the array of list IDs
			$list_id = array_shift( $list_ids );
		}
		
		
		$list_settings = $mc_api->get_list_conf_by_id( $list_id );
		
		if ( empty( $level_settings[ $list_id ] ) ) {
			$utils->log( "No settings defined for {$level_id}" );
			
			return $fields;
		}
		
		$utils->log( "Merge field level settings for {$level_id}: " . print_r( $level_settings[ $list_id ], true ) );
		$meta_defs = $settings_class->get_user_meta_keys();
		$value     = null;
		
		foreach ( $level_settings[ $list_id ] as $mf_tag => $meta_key ) {
			
			if ( - 1 === intval( $meta_key ) ) {
				continue;
			} else {
				
				// Process the definition (load the data from the user's usermeta if it exists)
				$meta_field = $meta_defs[ $meta_key ];
				
				$tmp          = get_user_meta( $user->ID, $meta_key, false );
				$has_multiple = ( count( $tmp ) > 1 ? true : false );
				
				$utils->log( "{$meta_key} has multiple values for {$user->ID}: " . ( ( count( $tmp ) > 1 ? 'Yes' : 'No' ) ) );
				
				if ( true == $has_multiple ) {
					$value = $tmp;
				}
				
				if ( false === $has_multiple || true === $meta_field->is_serialized ) {
					$value = get_user_meta( $user->ID, $meta_key, true );
				}
				
				$utils->log( "{$meta_key} is set to: " . print_r( $value, true ) );
				$fields[ $mf_tag ] = $value;
			}
		}
		
		$utils->log( "Setting merge tag values for {$user->ID}: " . print_r( $fields, true ) );
		
		return $fields;
	}
	
	/**
	 * Add to the local settings data if the merge field is updated and doesn't exist locally.
	 *
	 * @param string $list_id
	 * @param string $tag
	 * @param array  $field_def
	 *
	 * @return mixed
	 */
	private function maybe_add_to_local( $list_id, $tag, $field_def ) {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		$list_settings = $mc_api->get_list_conf_by_id( $list_id );
		$fixed         = false;
		
		foreach ( $list_settings->merge_fields as $key => $field ) {
			
			if ( empty( $key ) ) {
				$fixed = true;
				$utils->log( "Fixing merge field settings" );
				unset( $list_settings->merge_fields[ $key ] );
			}
		}
		
		if ( true === $fixed ) {
			$utils->log( "Forcing update of list settings for {$list_id}" );
			$mc_api->save_list_conf( $list_settings, null, $list_id );
			$mc_api->clear_cache( $list_id, 'merge_fields' );
		}
		
		$additional_lists = $mc_api->get_option( 'additional_lists' );
		
		if ( ! empty( $additional_lists ) && in_array( $list_id, $additional_lists ) ) {
			$utils->log( "Processing one of the 'additional' lists, so don't mess with merge fields, etc." );
			
			return $list_settings->merge_fields;
		}
		
		if ( ! in_array( $field_def['tag'], array_keys( $list_settings->merge_fields ) ) ) {
			
			$utils->log( "{$field_def['tag']} is not found in the local list of merge fields" );
			$list_settings->merge_fields = $this->sync_config_to_remote( $list_id, array( $field_def ) );
			
			$utils->log( "Current field definitions: " . print_r( $list_settings->merge_fields, true ) );
		}
		
		return $list_settings->merge_fields;
	}
	
	/**
	 * Replace whitespace (or any other fixes) required for MailChimp Merge Fields
	 *
	 * @param array $field_def
	 *
	 * @return array
	 */
	private function fix_names( $field_def ) {
		
		$field_def['name'] = preg_replace( '/\s+/', '', $field_def['name'] );
		
		return $field_def;
	}
	
}