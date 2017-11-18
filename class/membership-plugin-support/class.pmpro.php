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

use E20R\MailChimp\Interest_Groups;
use E20R\MailChimp\MailChimp_API;
use E20R\MailChimp\Member_Handler;
use E20R\MailChimp\Controller;
use E20R\MailChimp\Merge_Fields;
use E20R\Utilities\Utilities;

class PMPro extends Membership_Plugin {
	
	/**
	 * Static instance
	 *
	 * @var null|PMPro
	 * @access private
	 */
	private static $instance = null;
	
	protected $plugin_type = 'pmpro';
	
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
		
	    $utils = Utilities::get_instance();
	    $utils->log("Processing the 'load_hooks' method for the PMPro plugin");
	    
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
		$utils->log("Processing the 'plugin_load' method for PMPro");
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
            
            $utils->log("Should load PMPro filters");
            
			add_action( 'e20r-mailchimp-init-default-groups', array( $this, 'init_default_groups' ), 10 , 0 );
			add_filter( 'e20r-mailchimp-membership-plugin-prefix', array( $this, 'set_prefix' ), 10, 1 );
			
			add_filter( 'e20r-mailchimp-membership-plugin-present', array( $this, 'has_membership_plugin' ), 10, 1 );
			add_filter( 'e20r-mailchimp-all-membership-levels', array( $this, 'all_membership_level_defs' ), 10, 2 );
			add_filter( 'e20r-mailchimp-member-merge-field-values', array( $this, 'set_mf_values_for_member' ), 10, 4 );
			add_filter( 'e20r-mailchimp-member-merge-field-defs', array( $this, 'set_mf_definition' ), 10, 3 );
			add_filter( 'e20r-mailchimp-membership-list-all-members', array( $this, 'list_members_for_update'), 10, 1 );
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
			add_filter( 'e20r-mailchimp-interest-category-label', array( $this, 'get_interest_cat_label'), 10 , 1);
			add_filter( 'e20r-mailchimp-membership-new-user-level', array( $this, 'get_new_level_ids' ), 10, 3 );
			add_filter( 'e20r-mailchimp-non-active-statuses', array( $this, 'statuses_inactive_membership' ), 10, 1 );
			add_filter( 'e20r-mailchimp-user-defined-merge-tag-fields', array( $this, 'compatibility_merge_tag_fields' ), 10, 3 );
			
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
				
				add_action(
					'pmpro_after_change_membership_level',
					array( Member_Handler::get_instance(), 'on_add_to_new_level', ),
					99,
					3
				);
				
				add_action(
					'pmpro_after_change_membership_level',
					array( Member_Handler::get_instance(), 'cancelled_membership' ),
					999,
					3
				);
			}
			/*
			if ( ! empty( $e20r_mc_levels ) && ! has_action(
					'pmpro_after_checkout',
					array( Member_Handler::get_instance(), 'after_checkout' ) ) ) {
				
				$utils->log( "Adding after_checkout action" );
				add_action( 'pmpro_after_checkout', array( Member_Handler::get_instance(), 'after_checkout' ), 15, 2 );
			}*/
		} else {
			
			$utils->log( "Not loading for PMPro" );
		}
	}
    
    /**
     * Load listsubscribe fields from PMPro MailChimp add-on (assumes the filter exists)
     *
     * @param array $fields
     * @param \WP_User $user
     * @param string $list_id
     *
     * @return array
     */
	public function compatibility_merge_tags( $fields, $user, $list_id ) {
        
        /**
         * Always used in on the site together with the 'e20r-mailchimp-user-defined-merge-tag-fields' filter.
         *
         * @filter  pmpro_mailchimp_listsubscribe_fields - The merge field value/data array to submit to the MailChimp distribution list
         * @uses    e20r-mailchimp-user-defined-merge-tag-fields - The field definitions
         *
         * @param array  $field_values - Array: array( 'FIELDNAME' => $settings, 'FIELDNAME2' => $settings, ... )
         * @param string $list_id      - The MailChimp identifier for the Mailing list
         * @param int    $level_id     - The membership level ID to select field settings for (if applicable)
         *
         * @since   1.0
         */
        
        return apply_filters( 'pmpro_mailchimp_listsubscribe_fields', $fields, $user, $list_id );
	}
 
	/**
	 * Return the Membership statuses that signify an inactive 'membership'
	 * @param $statuses
	 *
	 * @return array
	 */
	public function statuses_inactive_membership( $statuses ) {
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			$statuses = array(
				'admin_changed',
				'admin_cancelled',
				'cancelled',
				'changed',
				'expired',
				'inactive',
			);
		}
		
		return $statuses;
	}
	
	/**
	 * @param int[] $level_ids
	 * @param \WP_User $user
	 * @param \MemberOrder|\WC_Order $order
	 *
	 * @return int[]
	 */
	public function get_new_level_ids( $level_ids, $user, $order ) {
	
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			
			$utils->log("Loading current membership level IDs for {$user->user_email}");
			
			$level_ids = array();
			$user_levels = pmpro_getMembershipLevelsForUser( $user->ID, false );
			
			foreach( $user_levels as $level ) {
				$level_ids[] = $level->id;
			}
		}
		
		$utils->log("Returning " . count( $level_ids ) . " PMPro Membership levels for {$user->user_email}");
		return $level_ids;
	}
	
	/**
	 * Load the default Interest Groups for PMPro
	 */
	public function init_default_groups() {
		
		$utils = Utilities::get_instance();
		
		$utils->log("Possibly loading groups to MailChimp for PMPro");
		
		// Only execute if we're configured for the PMPro option
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			
			$mc_api = MailChimp_API::get_instance();
			$ig_class = Interest_Groups::get_instance();
			$mg_class = Merge_Fields::get_instance();
			
			$levels = $this->all_membership_level_defs( array() );
			
			$label = apply_filters( 'e20r-mailchimp-interest-category-label', null );
			
			foreach( $levels as $level ) {
				
				if ( false === $ig_class->has_category( $level->id, $label ) ) {
					
					$utils->log("Have to add {$label} Group: {$level->id}/{$level->name}");
					$ig_class->create_categories_for_membership( $level->id );
				}
				
				$level_lists = $mc_api->get_option( "level_pmp_{$level->id}_lists" );
				
				if ( empty( $level_lists ) ) {
					$utils->log( "Warning: No level lists found in level_pmp_{$level->id}_lists settings!" );
					$level_lists = $mc_api->get_option( 'members_list' );
				}
				
				foreach ( $level_lists as $list_id ) {
					
					$utils->log( "List config for {$list_id} found" );
					
					// Force update from upstream interest groups
					if ( ! is_null( $list_id ) && false === ( $ig_sync_status = $mc_api->get_cache( $list_id, 'interest_groups', false ) ) ) {
						
						$msg = sprintf( __( "Unable to refresh MailChimp Interest Group information for %s", Controller::plugin_slug ), $level->name );
						$utils->add_message( $msg, 'error', 'backend' );
						
						$utils->log( "Error: Unable to update interest group information for list {$list_id} from API server" );
					}
					
					// Force refresh of upstream merge fields
					if ( ! is_null( $list_id ) && false === ( $mg_sync_status = $mc_api->get_cache( $list_id, 'merge_fields', false ) ) ) {
						
						$msg = sprintf( __( "Unable to refresh MailChimp Merge Field information for %s", Controller::plugin_slug ), $level->name );
						$utils->add_message( $msg, 'error', 'backend' );
						
						$utils->log( "Error: Unable to update merge field information for list {$list_id} from API server" );
					}
				}
			}
		}
	}
	
	/**
	 * Return what to call the default/main interest group for this plugin
	 *
	 * @param string $label
	 *
	 * @return string
	 */
	public function get_interest_cat_label( $label ) {
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			$label = __( 'Membership Levels', Controller::plugin_slug );
		}
		
		return $label;
	}
	
	/**
	 * Trigger when PMPro saves the Membership Level definition
	 *
	 * @param int $level_id
	 */
	public function on_update_membership_level( $level_id ) {
		
		// parent::on_update_membership_level( $level_id );
		$ig_controller = Interest_Groups::get_instance();
		
		$ig_controller->create_categories_for_membership( $level_id );
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			$utils = Utilities::get_instance();
			
			$level_id = $utils->get_variable( 'level', null );
		}
		
		return $level_id;
	}
	
	/**
	 * Check whether we're currently on/have loaded the PMPro Checkout page.
	 *
	 * @param bool $on_checkout_page
	 *
	 * @return bool
	 */
	public function is_on_checkout_page( $on_checkout_page ) {
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			$on_checkout_page = ( isset( $_REQUEST['submit-checkout'] ) || ( isset( $_REQUEST['confirm'] ) && isset( $_REQUEST['gateway'] ) ) );
		}
		
		return $on_checkout_page;
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
	public function set_mf_values_for_member( $level_fields, $user, $list_id, $level_id ) {
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) && !empty( $user ) && !empty( $level_id ) ) {
			
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			
			$utils = Utilities::get_instance();
			global $wpdb;
			
			if ( ! empty( $current_user_level_ids ) ) {
				$level_in_list = esc_sql( implode( ',', $current_user_level_ids ) );
			} else {
				$level_in_list = 0;
			}
			
			$sql = $wpdb->prepare(
				"SELECT DISTINCT(pmu.membership_id)
                            FROM {$wpdb->pmpro_memberships_users} AS pmu
                            WHERE pmu.user_id = %d
                              AND pmu.membership_id NOT IN ( {$level_in_list} )
                              AND pmu.status IN ( [IN] )
                              AND pmu.modified > NOW() - INTERVAL 15 MINUTE
                          ORDER BY pmu.id DESC",
				$user_id
			);
			
			$sql = $utils->prepare_in( $sql, $statuses, '%s' );
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			// Grab the PMPro version of the members list from the DB on the system and sort it by user ID & status
			$member_list = $wpdb->get_results( "SELECT DISTINCT mu.user_id, mu.membership_id, mu.status FROM {$wpdb->pmpro_memberships_users} as mu ORDER BY mu.user_id, status" );
		}
		
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			$merge_field_defs[] = array_merge(
				$merge_field_defs,
				array(
					array( 'tag' => 'MLVLID', 'name' => 'Membership ID', 'type' => 'number', 'public' => false ),
					array( 'tag' => 'MEMBERSHIP', 'name' => 'Membership', 'type' => 'text', 'public' => false ),
				)
			);
		}
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
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
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			
			$utils->log("We're checking that PMPro is loaded and active");
			$is_active = function_exists( 'pmpro_hasMembershipLevel' );
			$utils->log("PMPro is active? " .  ( $is_active ? 'Yes' : 'No' ) );
		}
		return $is_active;
	}
	
	/**
	 * Load all PMPro Membership Level definitions from the DB
	 *
	 * @param array $levels
	 *
	 * @return array
	 */
	public function all_membership_level_defs( $levels, $prefix = 'pmp' ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) && 'pmp' === $prefix ) {
			
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
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
		
		if ( true === $this->load_this_membership_plugin( 'pmpro' ) ) {
			
			$levels = pmpro_getMembershipLevelsForUser( $user_id );
			
			foreach ( $levels as $level ) {
				$user_level_ids[] = $level->id;
			}
			
			$utils->log( "Loaded " . count( $user_level_ids ) . " current PMPro levels for {$user_id}" );
		}
		
		return $user_level_ids;
	}
}
