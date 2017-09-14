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

namespace E20R\MailChimp\Membership_Support;

use E20R\MailChimp\Member_Handler;
use E20R\MailChimp\Controller;
use E20R\Utilities\Utilities;

class PMPro extends Membership_Plugin {
	
	/**
	 * Static instance
	 *
	 * @var null|PMPro
	 * @access private
	 */
	private static $instance = null;
	
	/**
	 * PMPro constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return or instantiate the Member_Handler class
	 *
	 * @return PMPro|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
			self::$instance->load_hooks();
		}
		
		return self::$instance;
	}
	
	public function load_hooks() {
		
		add_filter(
			'e20r-mailchimp-supported-membership-plugin-list',
			array( $this, 'add_supported_plugin' ),
			10,
			1
		);
		
		add_action( 'e20r-mailchimp-membership-plugin-load', array( $this, 'plugin_load' ), 10, 1 );
	}
	
	/**
	 * Action handler to load the Membership specific hooks & filters (PMPro)
	 *
	 * @param bool $on_checkout_page
	 *
	 * @return mixed
	 */
	public function plugin_load( $on_checkout_page ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			
			add_filter( 'e20r_mailchimp_membership_plugin_present', array( $this, 'has_membership_plugin' ), 10, 1 );
			add_filter( 'e20r-mailchimp-all-membership-levels', array( $this, 'all_membership_level_defs' ), 10, 1 );
			add_filter( 'e20r_mailchimp_member_merge_field_values', array( $this, 'set_mf_values_for_member' ), 10, 3 );
			add_filter( 'e20r_mailchimp_member_merge_field_defs', array( $this, 'set_mf_definition' ), 10, 3 );
			add_filter( 'e20r_mailchimp_membership_list_all_members', array( $this, 'list_members_for_update'), 10, 1 );
			add_filter(
				'e20r-mailchimp-get-user-membership-level',
				array( $this, 'primary_membership_level' ),
				10,
				2
			);
			add_filter(
				'e20r-mailchimp-user-membership-levels',
				array( $this, 'membership_level_ids_for_user' ),
				10,
				2
			);
			add_filter( 'e20r-mailchimp-get-membership-level-definition',
				array( $this, 'get_level_definition', ),
				10,
				2
			);
			
			add_filter( 'e20r-mailchimp-user-old-membership-levels', array( $this, 'recent_membership_levels_for_user' ), 10, 4 );
			
			// FIXME: Refactor and move the functionality to correct membership support plugin & split w/Membership Handler
			add_action( 'pmpro_checkout_after_tos_fields', array( Member_Handler::get_instance(), 'view_additional_lists' ), 10 );
			
			// FIXME: Refactor and move the functionality to correct membership support plugin & split w/Membership Handler
			add_action( 'pmpro_save_membership_level', array( Member_Handler::get_instance(), 'clear_levels_cache' ), 10 );
			
			// FIXME: Refactor and move the functionality to correct membership support plugin & split w/Membership Handler
			add_action( 'pmpro_paypalexpress_session_vars', array( Member_Handler::get_instance(), 'session_vars' ), 10 );
			
			/** Fixed: on_update_membership_level is refactored and handled by membership support class */
			add_action( 'pmpro_save_membership_level',
				array( $this, 'on_update_membership_level' ),
				10,
				1
			);
			
			//Configure hooks for PMPro levels
			$e20r_mc_levels = $this->all_membership_level_defs( array() );
			
			// FIXME: Refactor and move the functionality to correct membership support plugin & split w/Membership Handler
			if ( ! empty( $e20r_mc_levels ) &&
			     ! $on_checkout_page &&
			     ! has_action(
				     'pmpro_after_change_membership_level',
				     array( Member_Handler::get_instance(), 'update_after_change_membership_level', ) ) ) {
				
				$utils->log( "Adding after_change_membership_level actions" );
				
				add_action( 'pmpro_after_change_membership_level', array(
					Member_Handler::get_instance(),
					'add_new_membership_level',
				), 99, 3 );
				add_action( 'pmpro_after_change_membership_level', array( Member_Handler::get_instance(), 'cancelled_membership' ), 999, 3 );
			}
			
			if ( ! empty( $e20r_mc_levels ) && ! has_action(
					'pmpro_after_checkout',
					array( Member_Handler::get_instance(), 'after_checkout' ) ) ) {
				
				$utils->log( "Adding after_checkout action" );
				add_action( 'pmpro_after_checkout', array( Member_Handler::get_instance(), 'after_checkout' ), 15, 2 );
			}
		} else {
			
			$utils->log( "Not loading for PMPro" );
		}
	}
	
	/**
	 * Trigger when PMPro saves the Membership Level definition
	 *
	 * @param int $level_id
	 */
	public function on_update_membership_level( $level_id ) {
		
		parent::on_update_membership_level( $level_id );
	}
	
	/**
	 * Returns the membership ID number being added/granted to the user
	 *
	 * @param int       $level_id
	 * @param int       $user_id
	 * @param \stdClass $order
	 *
	 * @return int|null
	 */
	public function new_user_level_assigned( $level_id, $user_id, $order ) {
		
		$utils = Utilities::get_instance();
		
		return $utils->get_variable( 'level', null );
	}
	
	/**
	 * Check whether we're currently on/have loaded the PMPro Checkout page.
	 *
	 * @param bool $on_checkout_page
	 *
	 * @return bool
	 */
	public function is_on_checkout_page( $on_checkout_page ) {
		
		return ( isset( $_REQUEST['submit-checkout'] ) || ( isset( $_REQUEST['confirm'] ) && isset( $_REQUEST['gateway'] ) ) );
	}
	
	/**
	 * Populate the PMPro specific membership merge tags
	 *
	 * @param array    $level_fields - Should (only) be an empty array!
	 * @param \WP_User $user         - New member/user object
	 * @param string   $list_id      - ID of the MailChimp list we're processing for
	 *
	 * @return array
	 */
	public function set_mf_values_for_member( $level_fields, $user, $list_id ) {
		
		if ( function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
			
			$level = pmpro_getMembershipLevelForUser( $user->ID );
			
			$level_fields = array(
				"MLVLID"     => isset( $level->id ) ? intval( $level->id ) : null,
				"MEMBERSHIP" => isset( $level->name ) ? $level->name : null,
			);
		}
		
		$class = strtolower( get_class( $this ) );
		return apply_filters( "e20r_mailchimp_{$class}_listsubscribe_fields", $level_fields, $user, $list_id );
	}
	
	/**
	 * Return old/previous membership levels the user (recently) had.
	 *
	 * @param \stdClass[] $levels_to_unsubscribe_from
	 * @param int $user_id
	 * @param int[] $current_user_level_ids
	 * @param string[] $statuses
	 *
	 * @return int[]
	 */
	public function recent_membership_levels_for_user( $levels_to_unsubscribe_from, $user_id, $current_user_level_ids, $statuses ) {
		
		global $wpdb;
		
		if ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			
			if ( ! empty( $current_user_level_ids ) ) {
				$level_in_list = esc_sql( implode( ',', $current_user_level_ids ) );
			} else {
				$level_in_list = 0;
			}
			
			$status_in_list = "'" . implode( "','", $statuses ) . "'";
			
			$sql = $wpdb->prepare(
				"SELECT DISTINCT(pmu.membership_id)
                            FROM {$wpdb->pmpro_memberships_users} AS pmu
                            WHERE pmu.user_id = %d
                              AND pmu.membership_id NOT IN ( {$level_in_list} )
                              AND pmu.status IN ( $status_in_list )
                              AND pmu.modified > NOW() - INTERVAL 15 MINUTE ",
				$user_id
			);
			
			$levels_to_unsubscribe_from = array_merge( $levels_to_unsubscribe_from, $wpdb->get_col( $sql ) );
		}
		
		return $levels_to_unsubscribe_from;
	}
	
	/**
	 * Load list of User IDs, membership IDs and the current status of that membership ID for the user ID (PMPro)
	 *
	 * @param array $member_list
	 *
	 * @return array
	 */
	public function list_members_for_update( $member_list ) {
		
		global $wpdb;
		
		// Grab the PMPro version of the members list from the DB on the system and sort it by user ID & status
		$member_list = $wpdb->get_results( "SELECT DISTINCT mu.user_id, mu.membership_id, mu.status FROM {$wpdb->pmpro_memberships_users} as mu ORDER BY mu.user_id, status" );
		
		return $member_list;
	}
	
	/**
	 * Add field definitions for Paid Memberships Pro specific merge tags
	 *
	 * @param $merge_field_defs
	 * @param $list_id
	 *
	 * @return array
	 */
	public function set_mf_definition( $merge_field_defs, $list_id ) {
		
		$merge_field_defs[] = array_merge(
			$merge_field_defs,
			array(
				array( 'tag' => 'MLVLID', 'name' => 'Membership ID', 'type' => 'number', 'public' => false ),
				array( 'tag' => 'MEMBERSHIP', 'name' => 'Membership', 'type' => 'text', 'public' => false ),
				array( 'tag' => 'ZIP', 'name' => 'Zip code', 'type' => 'text', 'public' => false ),
			)
		);
		
		$class = strtolower( get_class( $this ) );
		
		return apply_filters( "e20r_mailchimp_{$class}_mergefields", $merge_field_defs, $list_id );
	}
	
	/**
	 * Return the Membership Level definition (PMPro)
	 *
	 * @param \stdClass $level_info
	 * @param int       $level_id
	 *
	 * @return \stdClass
	 */
	public function get_level_definition( $level_info, $level_id ) {
		
		if ( function_exists( 'pmpro_getLevel' ) ) {
			$level_info = pmpro_getLevel( $level_id );
		}
		
		return $level_info;
	}
	
	/**
	 * Add PMPro to the list of supported plugin options
	 *
	 * @param array $plugin_list
	 *
	 * @return array
	 */
	public function add_supported_plugin( $plugin_list ) {
		
		if ( ! is_array( $plugin_list ) ) {
			$plugin_list = array();
		}
		
		// Add PMPro if not already included
		if ( ! in_array( 'pmpro', array_keys( $plugin_list ) ) ) {
			$plugin_list['pmpro'] = array(
				'label' => __( "Paid Memberships Pro", Controller::plugin_slug ),
			);
		}
		
		return $plugin_list;
	}
	
	/**
	 * Identify whether PMPro is loaded and active
	 *
	 * @param bool $is_active
	 *
	 * @return bool
	 */
	public function has_membership_plugin( $is_active ) {
		
		return function_exists( 'pmpro_hasMembershipLevel' );
	}
	
	/**
	 * Load all PMPro Membership Level definitions from the DB
	 *
	 * @param array $levels
	 *
	 * @return array
	 */
	public function all_membership_level_defs( $levels ) {
		
		$utils = Utilities::get_instance();
		
		global $wpdb;
		
		$utils->log( "Loading levels cache for PMPro" );
		
		if ( isset( $wpdb->pmpro_membership_levels ) ) {
			$pmp_levels = $wpdb->get_results( "SELECT * FROM {$wpdb->pmpro_membership_levels} ORDER BY id" );
		} else if ( ! isset( $wpdb->pmpro_membership_levels ) ) {
			$pmp_levels = false;
		}
		
		if ( ! empty( $pmp_levels ) ) {
			$levels = array_merge( $levels, $pmp_levels );
		}
		
		return $levels;
	}
	
	/**
	 * Return the PMPro Level info for the main/primary membership
	 *
	 * @param \stdClass $level
	 * @param int       $user_id
	 *
	 * @return null|\stdClass
	 */
	public function primary_membership_level( $level, $user_id ) {
		
		$utils = Utilities::get_instance();
		$level = null;
		
		if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
			$level = pmpro_getMembershipLevelForUser( $user_id );
		}
		
		if ( empty( $level ) ) {
			
			$utils->log( "User {$user_id} doesn't have an active membership level, so no merge fields being processed" );
			
			return null;
		}
		
		return $level;
	}
	
	/**
	 * Returns the assigned/active membership level IDs for the specified user
	 *
	 * @param int[] $user_level_ids
	 * @param int   $user_id
	 *
	 * @return mixed
	 */
	public function membership_level_ids_for_user( $user_level_ids, $user_id ) {
		
		$utils = Utilities::get_instance();
		
		if ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			
			$levels = pmpro_getMembershipLevelsForUser( $user_id );
			
			foreach ( $levels as $level ) {
				$user_level_ids[] = $level->id;
			}
			
			$utils->log( "Loaded " . count( $user_level_ids ) . " current PMPro levels for {$user_id}" );
		}
		
		return $user_level_ids;
	}
}
