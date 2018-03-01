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
use E20R\Utilities\Cache;

class Interest_Groups {
	
	/**
	 * @var null|Interest_Groups
	 */
	private static $instance = null;
	
	private static $options;
	
	/**
	 * Return the Interest ID for the "Cancelled" Membership Level
	 *
	 * @param string $list_id
	 *
	 * @return null|string
	 */
	public function get_cancelled_for_list( $list_id ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
		$list_config = $mc_api->get_list_conf_by_id( $list_id );
		
		foreach ( $list_config->interest_categories as $category ) {
			
			if ( false !== stripos( $category->name, __( 'Membership Levels', Controller::plugin_slug ) ) ) {
				
				foreach ( $category->interests as $interest_id => $name ) {
					if ( false !== stripos( $name, __( "Cancelled", Controller::plugin_slug ) ) ) {
						$utils->log( "Found ID for the 'Cancelled' Interest Group: {$interest_id} " );
						
						return $interest_id;
					}
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Create a category Request Object for the specified mailchimp list ID
	 *
	 * @param string $name
	 * @param string $list_id
	 *
	 * @return \stdClass
	 */
	public function create_category( $name, $list_id ) {
		
		$request       = new \stdClass();
		$request->name = $name;
		$request->type = apply_filters( 'e20r_mailchimp_list_interest_category_type', 'checkboxes', $list_id );
		
		return $request;
	}
	
	/**
	 * Test whether a named category exists for the specific level ID
	 *
	 * @param int    $level_id
	 * @param string $name
	 *
	 * @return bool|array
	 */
	public function has_category( $level_id, $name ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		$prefix = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		
		$level_lists = $mc_api->get_option( "level_{$prefix}_{$level_id}_lists" );
		
		if ( empty( $level_lists ) ) {
			
			$utils->log( "No {$name} group found locally for level ({$level_id}) list " );
			$level_lists = $mc_api->get_option( 'members_list' );
		}
		
		$list_id     = array_pop( $level_lists );
		$list_config = $mc_api->get_list_conf_by_id( $list_id );
		
		$utils->log("List ID is {$list_id}...");
		
		// Load categories from remote server
		if ( empty( $list_config ) ) {
			
			$utils->log("NO list configuration for {$list_id} found...");
			
			$categories                       = $mc_api->get_cache( $list_id, 'interest_groups', false );
			$list_config                      = new \stdClass();
			$list_config->interest_categories = array();
			
			/**
			 * @since 1.2.1 - BUG FIX: Would attempt to process Interest Categories when none were present.
			 */
			if ( empty( $categories ) ) {
				return false;
			}
			
			foreach ( $categories as $category ) {
				$list_config->interest_categories[ $category->id ] = $category;
				$list_config->interest_categories[ $category->id ]->name = $category->title;
			}
		}
		
		$categories = isset( $list_config->interest_categories ) ? $list_config->interest_categories : array();
		
		foreach ( $categories as $cat_id => $interest_cat ) {
			
			if ( false !== stripos( $interest_cat->name, $name ) ) {
				
				$utils->log("Found {$name} so loading its interests");
				$remote_category = $mc_api->get_cache( "{$list_id}-{$cat_id}", 'interests', false );
				
				if ( ! empty( $remote_category ) ) {
					return array( $cat_id => $interest_cat );
				}
			}
		}
		
		$utils->log( "Did not find {$name}" );
		
		return false;
	}
	
	/**
	 * Return all membership level names as interest labels().
	 *
	 * @return array
	 */
	public function levels_as_interests() {
		
		$utils = Utilities::get_instance();
		
		$has_membership_system = apply_filters( 'e20r-mailchimp-membership-plugin-present', false );
		$interests             = array();
		$membership_levels     = array();
		
		$utils->log( "Processing levels as interests..." );
		
		if ( true === $has_membership_system ) {
			$membership_levels = apply_filters( 'e20r-mailchimp-all-membership-levels', $membership_levels );
		}
		
		foreach ( $membership_levels as $level_id => $level ) {
			$interests[] = trim( $level->name );
		}
		
		// Append the "cancelled" level ig
		$interests[] = __( "Cancelled", Controller::plugin_slug );
		
		return $interests;
	}
	
	/**
	 * Create local and remote interest category for Memberships and include the membership levels + "Cancelled" as the
	 * interests to choose from
	 *
	 * @param null|mixed $level_id
	 */
	public function create_categories_for_membership( $level_id = null ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		$prefix = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		
		$utils->log( "Attempting to create default groups for specified service" );
		
		$level_ids = array();
		
		if ( is_null( $level_id ) ) {
			$member_levels = apply_filters( 'e20r-mailchimp-all-membership-levels', array() );
		} else if ( ! is_array( $level_id ) ) {
			$level_ids = array( $level_id );
		} else {
			$level_ids = $level_id;
		}
		
		if ( ! empty( $member_levels ) ) {
			
			foreach ( $member_levels as $level ) {
				$level_ids[] = $level->id;
			}
		}
		
		$ig_levels = $this->levels_as_interests();
		
		foreach ( $level_ids as $l_id ) {
			
			$list_data = $mc_api->get_option( "level_{$prefix}_{$l_id}_lists" );
			
			if ( empty( $list_data ) ) {
				$list_data = $mc_api->get_option( 'members_list' );
			}
			
			$list_id = array_pop( $list_data );
			
			if ( empty($list_id)) {
			    $utils->log("No list ID found. Skipping");
			    continue;
            }
            
			$settings        = $mc_api->get_list_conf_by_id( $list_id );
			$local_interests = $mc_api->get_option( "level_{$prefix}_{$l_id}_interests" );
			
			$t_category    = false;
			$r_category    = false;
			$category_name = apply_filters( 'e20r-mailchimp-interest-category-label', null );
			
			$utils->log( "Fetched list option data: {$list_id} and 'Level' ID {$l_id}" );
			
			if ( false === ( $t_category = $this->has_category( $l_id, $category_name ) ) ) {
				
				$utils->log( "Adding {$category_name} category for 'level' (ID: {$l_id}) and list (ID: {$list_id})" );
				$category   = $this->create_category( $category_name, $list_id );
				$t_category = $this->add_remote_category( $list_id, $category, null );
			}
			
			$utils->log( ( "Settings for {$list_id} contains a name? " . ( ! empty( $settings->name ) ? 'Yes' : 'No' ) ) );
			
			// If there are no list specific settings
			if ( empty( $settings->name ) ) {
				
				$all_lists = $mc_api->get_all_lists();
				
				$utils->log( "Adding list specific settings for {$all_lists[ $list_id ]['name']}" );
				$settings                      = new \stdClass();
				$settings->name                = $all_lists[ $list_id ]['name'];
				$settings->interest_categories = array();
				$settings->merge_fields        = array();
			}
			
			// array( $category_id => stdClass( $category_settings ) )
			if ( is_array( $t_category ) ) {
				$r_category = array_pop( $t_category );
			}
			
			if ( false !== $r_category && is_object( $r_category ) ) {
				
				$settings->interest_categories[ $r_category->id ] = $r_category;
				$interests                                        = array();
				$cat_interests                                    = isset( $settings->interest_categories[ $r_category->id ] ) ? $settings->interest_categories[ $r_category->id ]->interests : array();
				
				foreach ( $ig_levels as $order => $ig_name ) {
					
					$utils->log( "Processing Interest for level {$l_id}: {$ig_name}" );
					
					if ( ! in_array( $ig_name, $cat_interests ) ) {
						
						$tmp_interest = $this->add_remote_interest_to_category( $list_id, $r_category->id, $ig_name );
						
						$utils->log( "Adding '{$ig_name}' to the interests list for {$r_category->name}: " . print_r( $tmp_interest, true ) );
						if ( false !== $tmp_interest && is_array( $tmp_interest ) ) {
							
							$interest = array_pop( $tmp_interest );
							$utils->log( "Saving interest locally" );
							$settings->interest_categories[ $r_category->id ]->interests = array_merge( $settings->interest_categories[ $r_category->id ]->interests, array( $interest->id => $interest->name ) );
						}
					}
				}
				
				if ( ! empty( $settings->interest_categories[ $r_category->id ]->interests ) ) {
					
					$utils->log( "Saving interests for {$r_category->id} to {$list_id} settings" );
					$mc_api->save_list_conf( $settings, null, $list_id );
				}
			}
			
			$utils->log( "Fetched, or found pre-configured, interests for {$l_id}" );
		}
	}
	
	/**
	 * Configure interests for the user in MailChimp list
	 *
	 * @param   string     $list_id        ID of MC list
	 * @param   \WP_User   $user           User object
	 * @param   null|array $level_ids      The membership level(s) the user has / had (if cancelling)
	 * @param   bool       $cancelling     Whether the user is cancelling their membership level or not.
	 *
	 * @return  array       $interests {
	 *      Array of interests to assign the user to
	 *
	 * @type   string      $interest_id    ID of the interest for the list ($list_id)
	 * @type   boolean     $assign_to_user Whether to assign the interest to the user for the $list_id
	 * }
	 */
	public function populate( $list_id, $user, $level_ids = null, $cancelling = false ) {
		
		$mc_api    = MailChimp_API::get_instance();
		$utils     = Utilities::get_instance();
		$prefix    = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		$interests = array();
		
		$utils->log( "Number of levels the user has: " . count( $level_ids ) );
		
		$upstream      = $mc_api->get_listinfo_for_member( $list_id, $user );
		$old_interests = isset( $upstream->interests ) ? (array) $upstream->interests : array();
		
		$utils->log( "Upstream Interest Group config for {$user->ID}: " . print_r( $old_interests, true ) );
		
		// Clear the list of interests (since we're updating).
		foreach ( array_keys( $old_interests ) as $old_int_id ) {
			
			if ( true == $old_interests[ $old_int_id ] ) {
				$interests[ $old_int_id ] = false;
			}
		}
		
		if ( ! empty( $level_ids ) ) {
			
			foreach ( $level_ids as $level_id ) {
				
				$interest_option = $mc_api->get_option( "level_{$prefix}_{$level_id}_interests" );
				
				// Make sure the option exists.
				if ( ! empty( $interest_option ) && isset( $interest_option[ $list_id ] ) ) {
					
					foreach ( $interest_option[ $list_id ] as $category_id => $interest_list ) {
						
						if ( ! empty( $interest_list ) && is_array( $interest_list ) ) {
							
							foreach ( $interest_list as $interest => $enabled ) {
								
								if ( true === (bool) $enabled && true === $cancelling ) {
									$enabled = false;
								}
								// assign the interest to this user Id(filtered, but set to true by default).
								$interests[ $interest ] = (bool) apply_filters( 'e20r_mailchimp_api_assign_interest_to_user', $enabled, $user, $interest, $list_id, $level_id );
							}
						} else if ( ! is_array( $interest_list ) ) {
							$utils->log( "Warning: Interest Group is not an array and contains: " . print_r( $interest_list, true ) );
						}
					}
					
					$utils->log( "Returning interest groups level {$level_id} and list {$list_id}: " . print_r( $interests, true ) );
				}
			}
			
		} else {
			$utils->log( "No levels to process for populate function!" );
		}
		
		if ( true === $cancelling ) {
			$utils->log( "User is not an active member & needs to be configured as 'cancelled' " );
			$cancelled_id               = $this->get_cancelled_for_list( $list_id );
			$interests[ $cancelled_id ] = true;
			$utils->log( "Returning interest groups for list {$list_id}: " . print_r( $interests, true ) );
		}
		
		return apply_filters( 'e20r_mailchimp_api_add_interests_to_user', $interests, $user, $list_id );
	}
	
	/**
	 * Update the list of interests belonging to the $list_id mailing list for the $cat_id interest category on the
	 * MailChimp server.
	 *
	 * @since       2.1
	 *
	 * @param       string $list_id   - ID of the MailChimp distribution list
	 * @param       string $cat_id    - ID of the Interest Cateogry belonging to $list_id
	 * @param       array  $interests - array( $interest_id => $interest_name )
	 *
	 * @return      bool
	 */
	public function remote_edit( $list_id, $cat_id, $interests ) {
		
		// patch all existing interest categories to MC servers
		$mc    = MailChimp_API::get_instance();
		$utils = Utilities::get_instance();
		
		$args  = $mc->build_request( 'PATCH' );
		$i_url = $mc->get_api_url( "/lists/{$list_id}/interest-categories/{$cat_id}/interests/" );
		
		foreach ( $interests as $id => $name ) {
			
			$url          = $mc->get_api_url( "/lists/{$list_id}/interest-categories/{$cat_id}/interests/{$id}" );
			$args['body'] = $utils->encode( array( 'name' => $name, ) );
			
			// handle v2 conversion
			if ( false !== stripos( $id, 'new_ic_' ) ) {
				$args['method'] = "POST";
				$i_url          = "{$url}";
			} else {
				$args['method'] = "PATCH";
				$i_url          = "{$url}/{$id}";
			}
			
			$utils->log( "MCAPI: Updating interest '{$name}' (id: {$id}) for category {$cat_id} in list {$list_id} on the MailChimp server" );
			
			$resp = wp_remote_request( $i_url, $args );
			$code = wp_remote_retrieve_response_code( $resp );
			
			if ( 200 > $code || 300 <= $code ) {
				
				$utils->add_message( wp_remote_retrieve_response_message( $resp ), 'error', 'backend' );
				
				return false;
			}
		}
		
		return true;
	}
	
	/*
	// FIXME: May not be needed (get_remote_category() )
	public function get_remote_category( $list_id, $category_id ) {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		$limit = $mc_api->get_option( 'mc_api_fetch_list_limit' );
		$max   = ! empty( $limit ) ? $limit : apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 );
		
		$url  = $mc_api->get_api_url( "/lists/{$list_id}/interest-categories/{$category_id}/" );
		$args = $mc_api->build_request( 'GET', null );
		
		$resp = wp_remote_request( $url, $args );
		$code = wp_remote_retrieve_response_code( $resp );
		
		if ( 200 > $code || 300 <= $code ) {
			
			$msg = wp_remote_retrieve_response_message( $resp );
			$utils->add_message( $msg, 'error', 'backend' );
			$utils->log( $msg );
			
			return false;
		}
		
		$category = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
		
		if ( ! empty( $category ) ) {
			$int_cat            = new \stdClass();
			$int_cat->id        = $category->id;
			$int_cat->type      = $category->type;
			$int_cat->name      = $category->title;
			
			$utils->log("Loading {$category->title} interests");
			$int_cat->interests = $mc_api->get_cache( "{$list_id}-{$category->id}", 'interests' );
			
			return $int_cat;
		}
		
		return false;
	}
	*/
	/**
	 * Return all interest categories for the specified list ID
	 *
	 * @since 2.1
	 *
	 * @param       string $list_id MailChimp List ID
	 * @param       bool   $force   Whether to force the refresh from upstream
	 *
	 * @return      mixed           False = error | array( interest-category-id => object[1], )
	 *
	 * @see   http://developer.mailchimp.com/documentation/mailchimp/reference/lists/interest-categories/ - Docs for
	 *        Interest Categories on MailChimp
	 */
	public function get_from_remote( $list_id, $force = false ) {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		$utils->log( "Loading from MailChimp Server(s)" );
		$limit = $mc_api->get_option( 'mc_api_fetch_list_limit' );
		$max   = ! empty( $limit ) ? $limit : apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 );
		
		// get all existing interest categories from MC servers
		$url  = $mc_api->get_api_url( "/lists/{$list_id}/interest-categories/?count={$max}" );
		$args = $mc_api->build_request( 'GET', null );
		
		$utils->log( "Fetching interest categories for {$list_id} from the MailChimp servers" );
		
		$resp = wp_remote_request( $url, $args );
		$code = wp_remote_retrieve_response_code( $resp );
		
		if ( 200 > $code || 300 <= $code ) {
			
			$msg = wp_remote_retrieve_response_message( $resp );
			$utils->add_message( $msg, 'error', 'backend' );
			$utils->log( $msg );
			
			return false;
		}
		
		$group   = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
		$int_cat = array();
		
		// Save the interest category information we (may) need
		foreach ( $group->categories as $cat ) {
			
			$int_cat[ $cat->id ]            = new \stdClass();
			$int_cat[ $cat->id ]->id        = $cat->id;
			$int_cat[ $cat->id ]->type      = $cat->type;
			$int_cat[ $cat->id ]->name      = $cat->title;
			$int_cat[ $cat->id ]->interests = $mc_api->get_cache( "{$list_id}-{$cat->id}", 'interests' );
		}
		
		if ( ! empty( $int_cat ) ) {
			
			$mc_api->set_cache( $list_id, 'interest_groups', $int_cat );
			$mcapi_list_settings = $mc_api->get_list_conf_by_id();
			
			if ( empty( $mcapi_list_settings ) ) {
				$mcapi_list_settings = $mc_api->create_default_list_conf( $list_id );
			}
			
			if ( empty( $mcapi_list_settings[ $list_id ] ) ) {
				$mcapi_list_settings += $mc_api->create_default_list_conf( $list_id );
			}
			
			$mcapi_list_settings[ $list_id ]->interest_categories = $int_cat;
			
			$utils->log( "Saving all settings for all lists. " );
			$mc_api->save_list_conf( $mcapi_list_settings );
		}
		
		return $int_cat;
	}
	
	/**
	 * Read all interests for an interest category from the MailChimp server
	 *
	 * @since 2.1
	 *
	 * @param   string $list_id ID of the Distribution List on MailChimp server
	 * @param   string $cat_id  ID of the Interest Category on MailChimp server
	 *
	 * @return  array|bool      Array of interest names & IDs
	 */
	public function get_interests_for_category( $list_id, $cat_id ) {
		
		$mc    = MailChimp_API::get_instance();
		$utils = Utilities::get_instance();
		
		if ( null === ( $interests = Cache::get( "{$list_id}-{$cat_id}", 'e20r_mc_api' ) ) ) {
			
			$limit = $mc->get_option( 'mc_api_fetch_list_limit' );
			$max   = ! empty( $limit ) ? $limit : apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 );
			
			$url  = $mc->get_api_url( "/lists/{$list_id}/interest-categories/{$cat_id}/interests/?count={$max}" );
			$args = $mc->build_request( 'GET', null );
			
			$utils->log( "Fetching interests for category {$cat_id} in list {$list_id} from the MailChimp servers" );
			
			$resp = wp_remote_request( $url, $args );
			$code = wp_remote_retrieve_response_code( $resp );
			
			if ( 200 > $code || 300 <= $code ) {
				$utils->add_message( wp_remote_retrieve_response_message( $resp ), 'error', 'backend' );
				
				$utils->log( wp_remote_retrieve_response_message( $resp ) );
				
				return false;
			}
			
			$i_list = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
			
			$interests = array();
			
			foreach ( $i_list->interests as $interest ) {
				$interests[ $interest->id ] = $interest->name;
			}
			
			if ( ! empty( $interests ) ) {
				$utils->log( "Found " . count( $interests ) . " interest(s) for {$cat_id}" );
				// HACK: Using list_id:cat_id as the identifier for interests within an interest category
				Cache::set( "{$list_id}-{$cat_id}", $interests , MINUTE_IN_SECONDS, 'e20r_mc_api' );
			}
		}
		
		return $interests;
	}
	
	/**
	 * Determine whether a specific interest group name is already defined on the local server
	 *
	 * @param string $ig_name Name of interest group to (attempt to) find
	 * @param array  $ig_list List of interest groups to compare against
	 *
	 * @return bool|string      Returns the ID of the interest category if found.
	 */
	public function in_interest_groups( $ig_name, $ig_list ) {
		
		if ( empty( $ig_list ) ) {
			return false;
		}
		
		foreach ( $ig_list as $id => $ig_obj ) {
			
			if ( $ig_obj->name == $ig_name ) {
				return $id;
			}
		}
		
		return false;
	}
	
	/**
	 * Updates server side interest categories for a mailing list (id)
	 *
	 * @since 2.1
	 *
	 * @param   string $list_id - ID for the MC mailing list
	 *
	 * @return  boolean
	 */
	public function sync_config_to_remote( $list_id ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
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
		$mcapi_list_settings = $mc_api->get_list_conf_by_id();
		
		// if there are no stored list settings
		if ( empty( $mcapi_list_settings ) ) {
			
			$mcapi_list_settings = array();
		}
		
		$all_lists = $mc_api->get_all_lists();
		
		$utils->log( "Processing for " . count( $all_lists ) . " lists" );
		
		// If there are no list specific settings
		if ( ! isset( $mcapi_list_settings[ $list_id ]->name ) ) {
			
			$utils->log( "Adding list specific settings for {$all_lists[ $list_id ]['name']}" );
			$mcapi_list_settings[ $list_id ]                      = new \stdClass();
			$mcapi_list_settings[ $list_id ]->name                = $all_lists[ $list_id ]['name'];
			$mcapi_list_settings[ $list_id ]->interest_categories = array();
			$mcapi_list_settings[ $list_id ]->merge_fields        = array();
		}
		
		// Try to populate the interest groups.
		if ( empty( $mcapi_list_settings[ $list_id ]->interest_categories ) ) {
			$utils->log( "Loading Interest Categories from cache" );
			$mcapi_list_settings[ $list_id ]->interest_categories = $mc_api->get_cache( $list_id, 'interest_groups' );
		}
		
		// Try to populate the merge fields
		if ( empty( $mcapi_list_settings[ $list_id ]->merge_fields ) ) {
			$utils->log( "Loading Merge Fields from cache" );
			$mcapi_list_settings[ $list_id ]->merge_fields = $mc_api->get_cache( $list_id, 'merge_fields' );
		}
		
		/**
		 * Always used in on the site together with the 'e20r_mailchimp_merge_fields' filter.
		 *
		 * @filter e20r-mailchimp-merge-tag-settings - The merge field value/data array to submit to the MailChimp distribution list
		 *
		 * @param array         $field_values - Array: array( 'FIELDNAME' => $field_value, 'FIELDNAME2' => $field_value2, ... )
		 * @param \WP_User|null $user         - The user object to fetch data for/about
		 * @param string        $list_id      - The MailChimp identifier for the Mailing list
		 */
		$user_merge_fields = apply_filters( 'e20r-mailchimp-merge-tag-settings', array(), null, $list_id );
		$v2_category_def   = array();
		$is_converted      = get_option( 'e20r_mc_ics_converted', false );
		
		if ( ! empty( $user_merge_fields ) && empty( $is_converted ) ) {
			
			$utils->log( "Checking for v2 - v3 Interest Group conversion..." );
			
			// Reload & force remote sync
			$mcapi_list_settings[ $list_id ]->interest_categories = $mc_api->get_cache( $list_id, 'interest_groups', true );
			
			$utils->log( "Found " . count( $mcapi_list_settings[ $list_id ]->interest_categories ) . " cached interest categories" );
			foreach ( $user_merge_fields as $field_name => $value ) {
				
				if ( 'groupings' == strtolower( $field_name ) ) {
					
					// do we have an old-style interest group definition?
					$utils->log( "Found v2 style interest category definition" );
					$v2_category_def = $value;
				}
			}
		}
		// look for categories
		$category_type = apply_filters( 'e20r_mailchimp_list_interest_category_type', 'checkboxes', $list_id );
		
		// process & convert any MCAPI-v2-style interest groups (groupings) aka interest categories.
		if ( ! empty( $v2_category_def ) ) {
			
			$new_ics = array();
			
			foreach ( $v2_category_def as $key => $grouping_def ) {
				
				foreach ( $grouping_def['groups'] as $group_name ) {
					
					// Only add if not already present in list.
					if ( false === $this->in_interest_groups( $group_name, $mcapi_list_settings[ $list_id ]->interest_categories ) ) {
						
						$utils->log( "Need to add Interest group ( {$group_name} ) as it's not defined locally yet" );
						$new_ic            = new \stdClass();
						$new_ic->type      = $category_type;
						$new_ic->id        = null;
						$new_ic->name      = $group_name;
						$new_ic->interests = array();
						
						$new_ics["new_ic_{$group_name}"] = $new_ic;
						$new_ic                          = null;
					}
				}
				
				$utils->log( "New Interest Categories: " . print_r( $new_ics, true ) );
				$utils->log( "Existing local ICs: " . print_r( $mcapi_list_settings[ $list_id ]->interest_categories, true ) );
				
				if ( is_array( $mcapi_list_settings[ $list_id ]->interest_categories ) ) {
					$mcapi_list_settings[ $list_id ]->interest_categories = array_merge( $mcapi_list_settings[ $list_id ]->interest_categories, $new_ics );
				} else {
					$mcapi_list_settings[ $list_id ]->interest_categories = $new_ics;
				}
				// Add new category definition to local list
				// TODO: Add 'Cancelled Membership" Interest Group ID to $mcapi_list_settings[$list_id]->cancelled_id
			}
		}
		
		// Update server unknown interest categories are found locally
		if ( ! empty( $new_ics ) ) {
			
			// Update the on-server (MailChimp server) interest category definition(s) for the system
			foreach ( $mcapi_list_settings[ $list_id ]->interest_categories as $id => $category ) {
				
				// Do we have a new or (likely) existing category
				if ( false !== strpos( $id, 'new_ic_' ) ) {
					
					$ic = $this->add_remote_category( $list_id, $category, $mcapi_list_settings[ $list_id ]->interest_categories );
					
					if ( false !== $ic && true !== $ic ) {
						// Append the new interest category as configured upstream
						$mcapi_list_settings[ $list_id ]->interest_categories = array_merge( $mcapi_list_settings[ $list_id ]->interest_categories, $ic );
					}
					
					// Already defined
					if ( true === $ic ) {
						
						$utils->log( "Interest Group {$category->name} is already defined..." );
						
						if ( false === $this->in_interest_groups( $category, $mcapi_list_settings[ $list_id ]->interest_categories ) ) {
							$new_ic = array( $category->name => $category );
							
							$mcapi_list_settings[ $list_id ]->interest_categories = array_merge( $mcapi_list_settings[ $list_id ]->interest_categories, $new_ic );
						}
					}
					
					// Clear "dummy" setting/info.
					unset( $mcapi_list_settings[ $list_id ]->interest_categories[ $id ] );
				}
			}
			
			$utils->log( "Interest Group settings: " . print_r( $mcapi_list_settings[ $list_id ]->interest_categories, true ) );
			
			if ( ! empty( $mcapi_list_settings[ $list_id ]->interest_categories ) ) {
				$mc_api->set_cache( $list_id, 'interest_groups', $mcapi_list_settings[ $list_id ]->interest_categories );
			}
			
			update_option( 'e20r_mc_ics_converted', true, 'no' );
		}
		
		$utils->log( "Updating list settings for {$list_id}." );
		
		// Update the MailChimp API settings for all lists (no autoload)
		$mc_api->save_list_conf( $mcapi_list_settings );
		
		// update_option( 'e20rmcapi_list_settings', $mcapi_list_settings, false );
		
		return true;
	}
	
	/**
	 * Add interest category to upstream MailChimp API server
	 *
	 * @param string $list_id
	 * @param object $category
	 * @param array  $settings
	 *
	 * @return bool|array
	 */
	public function add_remote_category( $list_id, $category, $settings = array() ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
		// patch all existing interest categories to MC servers
		
		$url  = $mc_api->get_api_url( "/lists/{$list_id}/interest-categories" );
		$args = $mc_api->build_request( 'POST' );
		
		$utils->log( "Adding interest category {$category->name} to the MailChimp servers: " . print_r( $category, true ) );
		
		$ic_url = $url;
		// $args['method'] = 'POST'; // Allows us to add
		
		// look for categories
		$category_type = apply_filters( 'e20r_mailchimp_list_interest_category_type', 'checkboxes', $list_id );
		
		$request = array(
			'title' => $category->name,
			'type'  => ( $category->type != $category_type ? $category_type : $category->type ),
		);
		
		$args['body'] = $utils->encode( $request );
		
		$resp = wp_remote_request( $ic_url, $args );
		$code = wp_remote_retrieve_response_code( $resp );
		
		if ( 200 > $code || 300 <= $code ) {
			
			$msg   = wp_remote_retrieve_response_message( $resp );
			$error = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
			
			$utils->log( "Error Response returned: " . print_r( $msg, true ) );
			$utils->log( "Error Response returned: " . print_r( $error, true ) );
			
			if ( 'Bad Request' === $msg && 'Invalid Resource' === $error->title && false !== stripos( $error->detail, 'already exists' ) ) {
				$utils->log( "Interest Category {$category->name} is already present for {$list_id}" );
				
				return true;
			}
			
			$utils->log( "Error adding interest category: {$msg} for request: " . print_r( $request, true ) );
			$utils->add_message( $msg, 'error', 'backend' );
			
			return false;
			
		} else {
			
			// if ( 200 == $resp_code ) {
			
			$cat = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
			$utils->log( "Added {$cat->title} on the MailChimp Server: {$cat->id}" );
			
			
			$settings[ $cat->id ]            = new \stdClass();
			$settings[ $cat->id ]->name      = $cat->title;
			$settings[ $cat->id ]->type      = $cat->type;
			$settings[ $cat->id ]->id        = $cat->id;
			$settings[ $cat->id ]->interests = array();
			
			return $settings;
			// }
		}
	}
	
	/**
	 * Add a new interest to an Interest Category for list (list id)
	 *
	 * @param string $list_id
	 * @param string $category_id
	 * @param string $interest
	 *
	 * @return bool|\stdClass
	 */
	public function add_remote_interest_to_category( $list_id, $category_id, $interest ) {
		
		$interest = trim( $interest );
		
		$mc    = MailChimp_API::get_instance();
		$utils = Utilities::get_instance();
		
		// patch all existing interest categories to MC servers
		$url = $mc->get_api_url( "/lists/{$list_id}/interest-categories/{$category_id}/interests" );
		
		$utils->log( "Adding interest {$interest} to {$category_id} on the MailChimp servers: " );
		
		$ic_url = $url;
		
		$request = array(
			'name' => $interest,
		);
		
		$args = $mc->build_request( 'POST', $utils->encode( $request ) );
		
		$resp = wp_remote_request( $ic_url, $args );
		$code = wp_remote_retrieve_response_code( $resp );
		
		if ( 200 > $code || 300 <= $code ) {
			
			$msg   = wp_remote_retrieve_response_message( $resp );
			$error = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
			
			if ( 'Bad Request' === $msg && 'Invalid Resource' === $error->title && false !== stripos( $error->detail, 'already exists' ) ) {
				$utils->log( "MCAPI: Interest '{$interest}' is already present for category {$category_id} in list {$list_id}" );
				
				return true;
			}
			
			$utils->log( "Error adding interest '{$interest}' to {$category_id}: {$msg} for request: " . print_r( $request, true ) );
			$utils->add_message( $msg, 'error', 'backend' );
			
			return false;
			
		} else {
			
			$int = $utils->decode_response( wp_remote_retrieve_body( $resp ) );
			
			$utils->log( "Added interest {$int->name} on the MailChimp Server: {$int->id}" );
			
			$settings[ $int->id ]              = new \stdClass();
			$settings[ $int->id ]->name        = $int->name;
			$settings[ $int->id ]->list_id     = $int->list_id;
			$settings[ $int->id ]->category_id = $int->category_id;
			$settings[ $int->id ]->id          = $int->id;
			
			return $settings;
		}
	}
	
	/**
	 * Interest_Groups constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return or instantiate the Interest_Groups class
	 *
	 * @return Interest_Groups|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			
			self::$instance = new self;
		}
		
		return self::$instance;
	}
}


