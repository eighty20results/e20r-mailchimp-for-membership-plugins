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

use E20R\MailChimp\MailChimp_API;
use E20R\Utilities\Utilities;

/**
 * Base class for supported Membership Plugin filters
 *
 * Class Membership_Plugin
 *
 * @package E20R\MailChimp\Membership_Support
 */
abstract class Membership_Plugin {
	
	/**
	 * @var null|Membership_Plugin
	 */
	private static $instance = null;
	
	/**
	 * Membership_Plugin constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return or instantiate the Membership_PLugin abstract class
	 *
	 * @return Membership_Plugin|null
	 */
	public static function get_instance() {
		
		return self::$instance;
	}
	
	/**
	 * Test whether or not to load the specified membership plugin support (by plugin slug)
	 *
	 * @param string $plugin_slug
	 *
	 * @return bool
	 */
	public function load_this_membership_plugin( $plugin_slug ) {
		
		$mc_api = MailChimp_API::get_instance();
		$utils = Utilities::get_instance();
		
		$membership_plugin = $mc_api->get_option( 'membership_plugin' );
		
		if ( false === strpos( $plugin_slug, $membership_plugin ) ) {
			$utils->log("Not loading filters for {$plugin_slug}");
			return false;
		}
		
		return true;
	}
	
	/**
	 * Trigger on an update/save operation for a membership plugin 'save the membership level' action
	 *
	 * @param int $level_id
	 */
	public function on_update_membership_level( $level_id ) {
		
		do_action( 'e20r-mailchimp-membership-level-update', $level_id );
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
	abstract public function recent_membership_levels_for_user( $levels_to_unsubscribe_from, $user_id, $current_user_level_ids, $statuses );
	
	/**
	 * Returns the membership level ID that is currently being assigned to the member (during checkout)
	 *
	 * @param int $level_id
	 * @param int $user_id
	 * @param \stdClass $order
	 *
	 * @return mixed
	 */
	abstract public function new_user_level_assigned( $level_id, $user_id, $order );
	
	/**
	 * Load any plugin specific filters needed to manage interest group settings, merge fields, etc
	 *
	 * @param bool $on_checkout_page
	 *
	 * @return mixed
	 */
	abstract public function plugin_load( $on_checkout_page );
	
	/**
	 * Return the Membership Level definition
	 *
	 * @param \stdClass $level_info
	 * @param int $level_id
	 *
	 * @return \stdClass
	 */
	abstract public function get_level_definition( $level_info, $level_id );
	
	/**
	 * Returns an array of (all) membership plugin level definitions
	 *
	 * @param array $levels     Array of stdClass() definitions for the membership levels
	 *
	 * @return array
	 */
	abstract public function all_membership_level_defs( $levels );
	
	/**
	 * Add membership specific filter handlers for the e20r-mailchimp-
	 */
	abstract public function load_hooks();
	
	/**
	 * Add the current plugin to the list of supported plugin options
	 *
	 * @param array $plugin_list
	 *
	 * @return array
	 */
	abstract public function add_supported_plugin( $plugin_list );
	
	/**
	 * Test whether a/the membership module is active
	 *
	 * @param bool $is_active
	 *
	 * @return bool
	 */
	abstract public function has_membership_plugin( $is_active );
	
	/**
	 * Return the primary membership level info for the user ID
	 *
	 * @param \stdClass $level
	 * @param int $user_id
	 *
	 * @return \stdClass|null
	 */
	abstract public function primary_membership_level( $level, $user_id );
	
	/**
	 * Return all membership level IDs the user ID has assigned to them
	 *
	 * @param int[] $level_ids
	 * @param int $user_id
	 *
	 * @return int[]
	 */
	abstract public function membership_level_ids_for_user( $level_ids, $user_id );
	
}