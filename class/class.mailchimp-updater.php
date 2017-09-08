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

class Mailchimp_Updater extends E20R_Background_Process {
	
	protected $action = 'mailchimp_updater';
	
	/**
	 * Task handler for MailChimp account updater (background job)
	 *
	 * @param \stdClass $member
	 *
	 * @return mixed
	 */
	public function task( $member ) {
		
		$mc_api     = MailChimp_API::get_instance();
		$controller = Controller::get_instance();
		
		$utils    = Utilities::get_instance();
		$ig_class = Interest_Groups::get_instance();
		
		$members_lists = $mc_api->get_option( "level_{$member->membership_id}_lists" );
		$list_info    = $mc_api->get_option( "level_{$member->membership_id}_interests" );
		$user         = get_userdata( $member->user_id );
		// $list_id                = array_pop( $members_lists );
		// $member_ig_category_ids = array_keys( $list_info[ $list_id ] );
		
		if ( empty( $user ) ) {
			
			$utils->log( "The user doesn't exist???" );
			
			return false;
		}
		
		/**
		 * @filter e20r_mailchimp_list_statuses_to_update - The Mailchimp.com user/account statuses that can be updated
		 *
		 * @param array $apply_to_statuses - Default: array( 'subscribed' ). Supported: array( 'subscribed', 'unsubscribed', 'cleaned', 'pending' )
		 */
		$apply_to_statuses = apply_filters( 'e20r_mailchimp_list_statuses_to_update', array( 'subscribed' ) );
		
		foreach ( $members_lists as $list_id ) {
			
			$info        = $mc_api->get_listinfo_for_member( $list_id, $user );
			$acct_status = isset( $info->status ) ? $info->status : "Unknown";
			
			// Only process users who are in the list already, and have an active subscription
			if ( ! empty( $info ) && in_array( $info->status, $apply_to_statuses ) ) {
				
				// Update member account (but don't add any failures to the banner messages)
				if ( true === $controller->subscribe( $list_id, $user, $member->membership_id ) ) {
					$utils->log( "Updated settings for {$user->user_email} on list {$list_id} for level {$member->membership_id}" );
				}
			} else {
				$utils->log( "No upstream account found for {$user->user_email}/{$user->ID}. Status: {$acct_status}" );
			}
		}
		
		return false;
	}
	
	/**
	 * Set the status of the background job to completed.
	 */
	public function complete() {
		
		$utils = Utilities::get_instance();
		
		$utils->log( "Background processing complete for Membership list" );
		
		if ( defined('WP_DEBUG' ) && true === WP_DEBUG && defined( 'E20R_MC_TESTING' ) && true === E20R_MC_TESTING) {
			$updated_value = 0;
		} else {
			$updated_value = 1;
		}
		
		update_option( 'e20r_mailchimp_old_updated', $updated_value );
		
		// Clean up.
		parent::complete();
	}
}