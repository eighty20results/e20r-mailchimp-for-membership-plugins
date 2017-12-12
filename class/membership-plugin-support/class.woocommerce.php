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

class WooCommerce extends Membership_Plugin {
	
	/**
	 * Static instance
	 *
	 * @var null|WooCommerce
	 * @access private
	 */
	private static $instance = null;
	
	protected $plugin_type = 'woocommerce';
	
	/**
	 * WooCommerce constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return or instantiate the WooCommerce class
	 *
	 * @return WooCommerce|null
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
	 * Action handler to load the Membership specific hooks & filters (WooCommerce)
	 *
	 * @param bool $on_checkout_page
	 *
	 * @return mixed
	 */
	public function plugin_load( $on_checkout_page ) {
		
		$utils = Utilities::get_instance();
        $utils->log("Processing the 'plugin_load' method for WooCommerce");
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
		    $utils->log("Should load WooCommerce filters");
		    
			add_action( 'e20r-mailchimp-init-default-groups', array( $this, 'init_default_groups' ), 10, 0 );
			
			add_filter( 'e20r-mailchimp-membership-plugin-present', array( $this, 'has_membership_plugin' ), 10, 1 );
			add_filter( 'e20r-mailchimp-membership-plugin-prefix', array( $this, 'set_prefix' ), 10, 1 );
			
			add_filter( 'e20r-mailchimp-all-membership-levels', array( $this, 'all_membership_level_defs' ), 10, 2 );
			add_filter( 'e20r-mailchimp-member-merge-field-values', array( $this, 'set_mf_values_for_member' ), 10, 4 );
			add_filter( 'e20r-mailchimp-member-merge-field-defs', array( $this, 'set_mf_definition' ), 10, 3 );
			add_filter( 'e20r-mailchimp-membership-list-all-members', array(
				$this,
				'list_members_for_update',
			), 10, 1 );
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
			
			add_filter( 'e20r-mailchimp-user-old-membership-levels', array(
				$this,
				'recent_membership_levels_for_user',
			), 10, 4 );
			
			// Add "additional lists" option to checkout page for WooCommerce
			add_action( 'woocommerce_after_order_notes', array(
				Member_Handler::get_instance(),
				'view_additional_lists',
			), 10 );
			
			add_action( "edit_product_cat", array( $this, 'on_update_membership_level' ), 10, 2 );
			
			// For WooCommerce product categories (add new entry to the interest group)
			add_action( 'create_product_cat', array( $this, 'added_new_product_category' ), 10, 2 );
			
			// For standard order(s)
			add_action( 'woocommerce_order_status_completed', array( $this, 'order_completed' ), 10, 1 );
			
			// For Subscription(s)
			add_action('woocommerce_subscription_status_active', array( $this, "subscription_added") , 10, 1);
			add_action('woocommerce_subscription_status_on-hold_to_active', array( $this, "subscription_added") , 10, 1);
			
			// For standard order(s)
			add_action( "woocommerce_order_status_refunded", array( $this, 'order_cancelled' ), 10, 1 );
			add_action( "woocommerce_order_status_failed", array( $this, 'order_cancelled' ), 10, 1 );
			add_action( "woocommerce_order_status_on_hold", array( $this, 'order_cancelled' ), 10, 1 );
			add_action( "woocommerce_order_status_cancelled", array( $this, 'order_cancelled' ), 10, 1 );
			
			// For Subscription(s)
			add_action("woocommerce_subscription_status_cancelled", array( $this, "subscription_cancelled") , 10, 1);
			add_action("woocommerce_subscription_status_trash", array( $this, "subscription_cancelled") , 10, 1);
			add_action("woocommerce_subscription_status_expired", array( $this, "subscription_cancelled") , 10, 1);
			add_action("woocommerce_subscription_status_on-hold", array( $this, "subscription_cancelled") , 10, 1);
			add_action("woocommerce_scheduled_subscription_end_of_prepaid_term", array( $this, "subscription_cancelled") , 10, 1);
			
			add_filter( 'e20r-mailchimp-non-active-statuses', array( $this, 'statuses_inactive_membership' ), 10, 1 );
			add_filter( 'e20r-mailchimp-interest-category-label', array( $this, 'get_interest_cat_label' ), 10, 1 );
			add_filter(
				'e20r-mailchimp-membership-new-user-level',
				array( $this, 'get_most_recent_product_cats', ),
				10,
				3
			);
			
		} else {
			
			$utils->log( "Not loading for WooCommerce" );
		}
	}
	
	/**
	 * Return the Membership statuses that signify an inactive 'membership'
	 *
	 * @param $statuses
	 *
	 * @return array
	 */
	public function statuses_inactive_membership( $statuses ) {
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$wc_statuses = wc_get_order_statuses();
			
			// Everything except 'completed' counts as the inactive status(es)
			unset( $wc_statuses['wc-completed'] );
			
			$statuses = array_keys( $wc_statuses );
		}
		
		return $statuses;
	}
	
	/**
	 *
	 * Find and return all product IDs for the most recent WooCommerce order this user made
	 *
	 * @param int[]                       $category_ids
	 * @param \WP_User                    $user
	 * @param \MemberOrder|\WC_Order|null $order_obj
	 *
	 * @return int[]
	 */
	public function get_most_recent_product_cats( $category_ids, $user, $order_obj = null ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$category_ids = array();
			
			$customer_orders = get_posts( array(
					'numberposts' => - 1,
					'meta_key'    => '_customer_user',
					'meta_value'  => $user->ID,
					'post_type'   => wc_get_order_types(),
					'post_status' => array( 'wc-completed' ),
					'date_query'  => array(
						/* 'column' => 'date_modified', */
						'after'  => date( 'Y-m-d', strtotime( '-1 days' ) ),
						'before' => date( 'Y-m-d', strtotime( 'today' ) ),
					),
					'order'       => 'DESC',
					'order_by'    => 'ID',
				)
			);
			
			$utils->log( "Found a total of " . count( $customer_orders ) . " WooCommerce orders for {$user->user_email}" );
			
			//Grab the most recent Order object.
			if ( ! empty( $customer_orders ) ) {
				
				$utils->log( "Processing " . count( $customer_orders ) . " orders for customer" );
				
				foreach ( $customer_orders as $order_record ) {
					
					$utils->log( "Grabbing new category IDs for {$order_record->ID}" );
					
					list( $user_id, $cat_ids ) = $this->get_category_ids( $order_record->ID );
					$category_ids = array_merge( $category_ids, $cat_ids );
				}
				
				// $category_ids = array_unique( $category_ids );
			}
		}
		
		$utils->log( "Returning " . count( $category_ids ) . " WooCommerce product categories for {$user->user_email}" );
		
		return $category_ids;
	}
	
	/**
	 * Load the default Interest Groups for WooCommerce
	 */
	public function init_default_groups() {
		
		$utils = Utilities::get_instance();
		
		$utils->log( "Possibly loading groups to MailChimp for WooCommerce" );
		
		// Only execute if we're configured for the PMPro option
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$utils->log( "Loading product groups for WooCommerce" );
			
			$ig_class = Interest_Groups::get_instance();
			$mg_class = Merge_Fields::get_instance();
            $mc_api      = MailChimp_API::get_instance();
			
			$levels         = $this->all_membership_level_defs( array() );
			$category_label = apply_filters( 'e20r-mailchimp-interest-category-label', null );
			
			foreach ( $levels as $level ) {
				
				if ( false === $ig_class->has_category( $level->id, $category_label ) ) {
					
					$utils->log( "Have to add {$category_label}: {$level->id}/{$level->name}" );
					$ig_class->create_categories_for_membership( $level->id );
				}
				
				$level_lists = $mc_api->get_option( "level_wc_{$level->id}_lists" );
				
				if ( empty( $level_lists ) ) {
					$utils->log( "Warning: No level lists found in level_wc_{$level->id}_lists settings!" );
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
	 * Add the user to the "membership" specific distribution list
	 *
	 * @param $order_id
	 */
	public function order_completed( $order_id ) {
		
		$utils = Utilities::get_instance();
		$mh    = Member_Handler::get_instance();
		
		list( $user_id, $category_ids ) = $this->get_category_ids( $order_id );
		
		foreach ( $category_ids as $category_id ) {
			
			$utils->log( "Adding {$user_id} to mailchimp list for {$category_id}" );
			$mh->on_add_to_new_level( $category_id, $user_id );
		}
		
		unset( $order );
	}
	
	public function subscription_added( $subscription ) {
	
	}
	
	public function subscription_cancelled( $subscription ) {
	
	}
	
	/**
	 * Process order (subscription) cancellations or payment problems
	 *
	 * @param int $order_id
	 */
	public function order_cancelled( $order_id ) {
		
		$utils    = Utilities::get_instance();
		$mh_class = Member_Handler::get_instance();
		
		list( $user_id, $category_ids ) = $this->get_category_ids( $order_id );
		
		foreach ( $category_ids as $category_id ) {
			
			$utils->log( "Deactivating 'membership' list for {$user_id}/{$category_id} " );
			$mh_class->cancelled_membership( 0, $user_id, $category_id );
		}
		
		unset( $order );
	}
	
	/**
	 * Fetch the billing email (and user object) for the order supplied
	 *
	 * @param \WC_Order $order
	 *
	 * @return int
	 */
	private function get_billing_user( $order ) {
		
		$utils = Utilities::get_instance();
		
		$user_id = $order->get_customer_id();
		
		// Get the user's email address (billing email)
		$email = $order->get_billing_email();
		
		// if there's one specified, try to get a WordPress user object for them
		if ( ! empty( $email ) ) {
			$user = get_user_by( 'email', $email );
			
			// Found the user!
			if ( !empty( $user ) ) {
				$user_id = $user->ID;
			} else {
				$utils->log("The user with email {$email} doesn't appear to have an account on this system!");
			}
		}
		
		return $user_id;
	}
	
	/**
	 * Return the list of category IDs that the items in the specified order belong to
	 *
	 * @param int $order_id
	 *
	 * @return array( int, int[] )
	 */
	private function get_category_ids( $order_id ) {
		
		$order        = wc_get_order( $order_id );
		$user_id      = $order->get_customer_id();
		$category_ids = array();
		
		$utils = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		// Find the expected user ID based on the wcuser setting (if the user exists)
		if ( E20R_MAILCHIMP_BILLING_USER === $mc_api->get_option( 'wcuser' ) ) {
			$utils->log("Attempting to load the user object for the billing address user");
			$user_id = $this->get_billing_user( $order );
		}
		
		$order_items = $order->get_items();
		
		// There is an order and it's by a local user
		if ( ! empty( $user_id ) && 0 < count( $order_items ) ) {
			
			foreach ( $order_items as $order_item ) {
				
				$product_id = $order_item['product_id'];
				
				if ( 0 < $product_id ) {
					
					$product      = new \WC_Product( $product_id );
					$category_ids = $product->get_category_ids();
				}
			}
		}
		
		return array( $user_id, $category_ids );
	}
	
	/**
	 * Add a new interest group to MailChimp for the recently added product category
	 *
	 * @param int $term_id
	 * @param int $taxonomy_term_id
	 */
	public function added_new_product_category( $term_id, $taxonomy_term_id ) {
		
		$utils         = Utilities::get_instance();
		$ig_controller = Interest_Groups::get_instance();
		
		$utils->log( "Create new categories (if needed) for {$term_id}" );
		$ig_controller->create_categories_for_membership( $term_id );
	}
	
	/**
	 * Return what to call the default/main interest group for this plugin
	 *
	 * @param string $label
	 *
	 * @return string
	 */
	public function get_interest_cat_label( $label ) {
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			$label = __( 'Product Category', Controller::plugin_slug );
		}
		
		return $label;
	}
	
	/**
	 * Trigger when WooCommerce saves the Membership Level definition (N/A)
	 *
	 * @param int $term_id
	 * @param int $taxonomy_term_id
	 */
	public function on_update_membership_level( $term_id ) {
		
		$ig_controller = Interest_Groups::get_instance();
		$ig_controller->create_categories_for_membership( $term_id );
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
		
		return $level_id;
	}
	
	/**
	 * Check whether we're currently on/have loaded the WooCommerce Checkout page.
	 *
	 * @param bool $on_checkout_page
	 *
	 * @return bool
	 */
	public function is_on_checkout_page( $on_checkout_page ) {
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			$on_checkout_page = is_checkout();
		}
		
		return $on_checkout_page;
	}
	
	/**
	 * Populate the WooCommerce specific membership merge tags
	 *
	 * @param array    $level_fields - Should (only) be an empty array!
	 * @param \WP_User $user         - New member/user object
	 * @param string   $list_id      - ID of the MailChimp list we're processing for
	 *
	 * @return array
	 */
	public function set_mf_values_for_member( $level_fields, $user, $list_id, $level_id ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$utils->log( "Attempting to populate info for WooCommerce customer...: " . print_r( $level_fields, true ) );
			
			if ( isset( $level_fields['FNAME'] ) ) {
				$level_fields['FNAME'] = $utils->get_variable( 'billing_first_name', null );
			}
			
			if ( isset( $level_fields['LNAME'] ) ) {
				$level_fields['LNAME'] = $utils->get_variable( 'billing_last_name', null );
			}
		}
		
		$utils->log( "After configuration for WooCommerce: " . print_r( $level_fields, true ) );
		
		$class = strtolower( get_class( $this ) );
		
		return apply_filters( "e20r-mailchimp-{$class}-user-defined-merge-tag-fields", $level_fields, $user, $list_id );
	}
	
	/**
	 * Return old/previous membership levels the user (recently) had.
	 *
	 * @param \stdClass[] $levels_to_unsubscribe_from
	 * @param int         $user_id
	 * @param int[]       $current_user_level_ids
	 * @param string[]    $statuses
	 *
	 * @return int[]
	 */
	public function recent_membership_levels_for_user( $levels_to_unsubscribe_from, $user_id, $current_user_level_ids, $statuses ) {
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$utils = Utilities::get_instance();
			
			global $wpdb;
			
			$sql = $wpdb->prepare(
				"SELECT p.ID
							FROM wp_posts AS p
							INNER JOIN wp_postmeta AS pm
								ON (p.ID = pm.post_id) AND
								meta_key = '_customer_user' AND
								meta_value = %d
							WHERE p.post_modified > ( NOW() - INTERVAL 15 MINUTE)
							AND p.post_status IN ( [IN] )",
				$user_id
			);
			
			$sql = $utils->prepare_in( $sql, $statuses, '%s' );
			
			$order_ids                  = $wpdb->get_col( $sql );
			$levels_to_unsubscribe_from = array();
			
			$utils->log( "Found " . count( $order_ids ) . " recently updated orders for {$user_id}" );
			
			foreach ( $order_ids as $order_id ) {
				
				list( $uid, $category_ids ) = $this->get_category_ids( $order_id );
				$levels_to_unsubscribe_from = array_merge( $levels_to_unsubscribe_from, $category_ids );
			}
			
			// $levels_to_unsubscribe_from = array_unique( $levels_to_unsubscribe_from );
		}
		
		return $levels_to_unsubscribe_from;
	}
	
	/**
	 * Load list of User IDs, membership IDs and the current status of that membership ID for the user ID (WooCommerce)
	 *
	 * @param array $member_list
	 *
	 * @return array
	 */
	public function list_members_for_update( $member_list ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$member_list = array();
			$order_args  = array(
				'numberposts' => - 1,
				'post_type'   => wc_get_order_types(),
				'post_status' => 'wc-completed',
			);
			
			$order_list = get_posts( $order_args );
			
			foreach ( $order_list as $p_order ) {
				
				list( $user_id, $category_ids ) = $this->get_category_ids( $p_order->ID );
				
				if ( ! empty( $user_id ) ) {
					
					$info          = new \stdClass();
					$info->user_id = $user_id;
					$info->status  = 'active';
					
					$utils->log( "Found " . count( $category_ids ) . " categories for {$info->user_id}" );
					
					foreach ( $category_ids as $cat_id ) {
						
						// Add once for each category ID found
						$info->membership_id = $cat_id;
						$member_list[]       = $info;
					}
				}
			}
		}
		
		return $member_list;
	}
	
	/**
	 * Add field definitions for WooCommerce specific merge tags
	 *
	 * @param $merge_field_defs
	 * @param $list_id
	 *
	 * @return array
	 */
	public function set_mf_definition( $merge_field_defs, $list_id ) {
		
		$class = strtolower( get_class( $this ) );
		
		return apply_filters( "e20r-mailchimp-{$class}-merge-tag-settings", $merge_field_defs, $list_id );
	}
	
	/**
	 * Locate the Product Category data for the specified Taxonomy/Category ID
	 *
	 * @param $term_id
	 *
	 * @return \WP_Term
	 */
	private function get_product_category( $term_id ) {
		
		return get_term_by( 'id', $term_id, 'product_cat' );
	}
	
	/**
	 * Return the Membership Level definition (WooCommerce)
	 *
	 * @param \stdClass $level_info
	 * @param int       $term_id
	 *
	 * @return \stdClass
	 */
	public function get_level_definition( $level_info, $term_id ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$utils->log( "Using WooCommerce plugin for 'Membership' info" );
			$utils->log( "Have an ID (which is a taxonomy ID in our case): {$term_id} " );
			
			$taxonomy = $this->get_product_category( $term_id );
			
			if ( ! empty( $taxonomy ) ) {
				
				$level_info       = new \stdClass();
				$level_info->id   = $term_id;
				$level_info->name = $taxonomy->name;
				
			} else {
				$utils->log( "Error attempting to fetch {$term_id}!!!" );
			}
		}
		
		return $level_info;
	}
	
	
	/**
	 * Add WooCommerce to the list of supported plugin options
	 *
	 * @param array $plugin_list
	 *
	 * @return array
	 */
	public function add_supported_plugin( $plugin_list ) {
		
		if ( ! is_array( $plugin_list ) ) {
			$plugin_list = array();
		}
		
		// Add WooCommerce if not already included
		if ( ! in_array( 'woocommerce', array_keys( $plugin_list ) ) ) {
			$plugin_list['woocommerce'] = array(
				'label' => __( "WooCommerce Shopping Cart", Controller::plugin_slug ),
			);
		}
		
		return $plugin_list;
	}
	
	/**
	 * Identify whether WooCommerce is loaded and active
	 *
	 * @param bool $is_active
	 *
	 * @return bool
	 */
	public function has_membership_plugin( $is_active ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			$utils->log("We're checking that WooCommerce is loaded and active");
			$is_active = function_exists('wc_get_order' );
			$utils->log("WooCommerce is active? " .  ( $is_active ? 'Yes' : 'No' ) );
		}
		
		
		return $is_active;
	}
	
	/**
	 * Load all WooCommerce Membership Level definitions from the DB (return empty)
	 *
	 * @param array $levels
     * @param string $prefix
	 *
	 * @return array
	 */
	public function all_membership_level_defs( $levels, $prefix = 'wc' ) {
		
		$utils = Utilities::get_instance();
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) && 'wc' === $prefix ) {
			
			$utils->log( "Processing WooCommerce 'membership levels'" );
			
			$taxonomy     = 'product_cat';
			$orderby      = 'name';
			$show_count   = true;      // 1 for yes, 0 for no
			$pad_counts   = false;      // 1 for yes, 0 for no
			$hierarchical = true;      // 1 for yes, 0 for no
			$title        = '';
			$empty        = false;
			
			$args = array(
				'taxonomy'     => $taxonomy,
				'orderby'      => $orderby,
				'show_count'   => $show_count,
				'pad_counts'   => $pad_counts,
				'hierarchical' => $hierarchical,
				'title_li'     => $title,
				'hide_empty'   => $empty,
			);
			
			$all_categories = get_categories( $args );
			$woo_levels     = array();
			$utils->log( "Found " . count($all_categories) . " categories for {$taxonomy}");
			
			foreach ( $all_categories as $cat ) {
				
				if ( $cat->category_parent == 0 ) {
					
					// Add parent category to list of Woo "Levels"
					$wt_level       = new \stdClass();
					$wt_level->id   = $cat->term_id;
					$wt_level->name = $cat->name;
					
					$woo_levels[] = $wt_level;
					
					// Check for sub-categories
					$args2 = array(
						'taxonomy'     => $taxonomy,
						'child_of'     => 0,
						'parent'       => $cat->term_id,
						'orderby'      => $orderby,
						'show_count'   => $show_count,
						'pad_counts'   => $pad_counts,
						'hierarchical' => $hierarchical,
						'title_li'     => $title,
						'hide_empty'   => $empty,
					);
					
					$sub_cats = get_categories( $args2 );
					
					if ( ! empty( $sub_cats ) ) {
						
						foreach ( $sub_cats as $sub_category ) {
							
							$wlevel       = new \stdClass();
							$wlevel->id   = $sub_category->term_id;
							$wlevel->name = $sub_category->name;
							
							$woo_levels[] = $wlevel;
						}
					}
				}
			}
			
			if ( ! empty( $woo_levels ) && ! empty( $levels ) ) {
				$levels = array_merge( $levels, $woo_levels );
			} else if ( empty( $levels ) && ! empty( $woo_levels ) ) {
				$levels = $woo_levels;
			}
			
		}
		
		$utils->log( "Returning " . count( $levels ) . " 'levels' as WooCommerce product groups" );
		
		return $levels;
	}
	
	/**
	 * Return the WooCommerce Level info for the main/primary membership
	 *
	 * @param \stdClass $level
	 * @param int       $user_id
	 *
	 * @return null|\stdClass
	 */
	public function primary_membership_level( $level, $user_id ) {
		
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
		
		if ( true === $this->load_this_membership_plugin( 'woocommerce' ) ) {
			
			$utils    = Utilities::get_instance();
			$user     = get_user_by( 'ID', $user_id );
			$statuses = $this->statuses_inactive_membership( array() );
			
			$user_level_ids = $this->recent_membership_levels_for_user( array(), $user->ID, $user_level_ids,$statuses  );
			
			$utils->log( "Found " . count( $user_level_ids ) . " recently updated product categories for {$user_id}" );
		}
		
		return $user_level_ids;
	}
}
