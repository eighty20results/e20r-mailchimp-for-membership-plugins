<?php
/**
 * Copyright (c) 2017-2019 - Eighty / 20 Results by Wicked Strong Chicks.
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

class User_Handler {
	
	/**
	 * @var null|User_Handler
	 */
	private static $instance = null;
	
	/**
	 * User_Handler constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Load User/Profile specific actions & filters
	 */
	public function load_actions() {
     
	    if ( is_admin() || Controller::on_login_page() ) {
	     
		    add_action( 'show_user_profile', array( $this, 'add_profile_fields' ), 12, 2 );
		    add_action( 'edit_user_profile', array( $this, 'add_profile_fields' ), 12, 2 );
		
		    add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		    add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
		
		    add_action( "profile_update", array( $this, "profile_update" ), 20, 2 );
	    }
	}
	
	/**
	 * Subscribe users to lists when they register.
	 *
	 * @param int $user_id
	 */
	public function user_register( $user_id ) {
		
		clean_user_cache( $user_id );
		
		$mc_controller = Controller::get_instance();
        $mc_api = MailChimp_API::get_instance();
        
		$lists = $mc_api->get_option( 'members_list' );
		$api_key = $mc_api->get_option( 'api_key' );
		
		// Is the plugin configured?
		if ( ! empty( $lists ) && ! empty( $api_key ) ) {
			
		    // User data
			$list_user = get_userdata( $user_id );
			
			// Add the user to the selected list(s)
			foreach ( $lists as $list ) {
				$mc_controller->subscribe( $list, $list_user, null );
			}
		}
	}
	
	/**
	 * Change email in MailChimp if a user's email is changed in WordPress
	 *
	 * @param $user_id       (int) -- ID of user
	 * @param $old_user_data -- WP_User object
	 */
	public function profile_update( $user_id, $old_user_data ) {
		
		$new_user_data = get_userdata( $user_id );
		
		//by default only update users if their email has changed
		$update_user = ( $new_user_data->user_email != $old_user_data->user_email );
		
		/**
		 * Filter in case they want to update the user on all updates
		 *
		 * @param bool   $update_user   true or false if user should be updated at Mailchimp
		 * @param int    $user_id       ID of user in question
		 * @param object $old_user_data old data from before this profile update
		 *
		 * @since 2.0.3
		 */
		$update_user = apply_filters( 'e20r_mailchimp_sync_profile', $update_user, $user_id, $old_user_data );
		
		if ( true === $update_user ) {
			
			//get all lists
			$mc_api = MailChimp_API::get_instance();
			
			if ( empty( $mc_api ) ) {
				global $pmpro_msg;
				global $pmpro_msgt;
				
				$pmpro_msg  = __( "Unable to load MailChimp API interface", Controller::plugin_slug );
				$pmpro_msgt = "error";
				
				return;
			}
            
            $mc_api->set_key();
			
			if ( ! empty( $mc_api ) ) {
				
				$lists = $mc_api->get_all_lists();
			}
			
			if ( ! empty( $lists ) ) {
				
				foreach ( $lists as $list ) {
					
					//check for member
					$member = $mc_api->get_listinfo_for_member( $list->id, $old_user_data );
					
					//update member's email and other values (only if user is already subscribed - not pending!)
					if ( 'subscribed' === $member->status ) {
						
						$mc_api->update_list_member( $list->id, $old_user_data, $new_user_data );
					}
				}
			}
		}
	}
	
	/**
	 * Add MailChimp specific user in user profile
	 *
	 * @param \WP_User $user
	 */
	public function add_profile_fields( $user ) {
        
        $mc_api = MailChimp_API::get_instance();
		$additional_lists   = $mc_api->get_option( 'additional_lists' );
		$additional_lists_array = array();
		
		$all_lists     = array();
		
		if ( empty( $additional_lists ) ) {
			$additional_lists = array();
		}
  
		$utils = Utilities::get_instance();
		
		if ( empty( $mc_api ) ) {
   
			$utils->add_message( __( "Unable to load MailChimp API interface", Controller::plugin_slug ), 'error', 'backend' );
			
			return;
		}
        
        $mc_api->set_key();
		
		if ( ! empty( $mc_api ) ) {
			$all_lists = $mc_api->get_all_lists();
		}
		
		// Do we have any lists to display?
		if ( ! empty( $all_lists ) ) {
			
			foreach ( $all_lists as $list ) {
			    
			    // Extract additional lists chosen by this user
                foreach ( $additional_lists as $additional_list ) {
                    
                    if ( $list['id'] == $additional_list ) {
                        
                        $additional_lists_array[] = $list;
                        break;
                    }
                }
			}
		}
		
		if ( empty( $additional_lists_array ) ) {
			return;
		}
		?>
        <h3><?php _e( 'Additional Mailing Lists', Controller::plugin_slug ); ?></h3>

        <table class="form-table">
            <tr>
                <th>
                    <label for="address"><?php _e( 'Mailing Lists', Controller::plugin_slug ); ?>
                    </label></th>
                <td>
					<?php
					global $profileuser;
					$user_additional_lists = get_user_meta( $profileuser->ID, 'e20r_mc_additional_lists', true );
					
					if ( !empty( $user_additional_lists ) && ! is_array( $user_additional_lists) )  {
						$user_additional_lists = array( $user_additional_lists);
					} else if ( empty(  $user_additional_lists ) ) {
						$user_additional_lists = array();
					}
					?>
					<input type="hidden" name="additional_lists_profile" value="1" />
					<select multiple="multiple" name="additional_lists[]" class="e20r-settings-fields">
					<?php
					foreach ( $additional_lists_array as $list ) {
						printf(
						        '<option value="%d" %s>%s</option>',
                                $list['id'],
                                $utils->selected( $list['id'], $user_additional_lists, false ),
                                $list['name']
                        );
					} ?>
					</select>
                </td>
            </tr>
        </table>
		<?php
	}
	
	/**
	 * Save MailChimp fields from User Profile
	 *
	 * @param $user_id
     *
     * @since v2.5 - BUG FIX: Would delete user from MailChimp list when updating user(s) profile (Mailchimp_API::delete() vs Controlller::unsubscribe()
	 */
	public function save_profile_fields( $user_id ) {
		
		$mc_api    = MailChimp_API::get_instance();
		$utils = Utilities::get_instance();
		$controller = Controller::get_instance();
		
		$utils->log("Request from Profile update...");
		
		// Only worry about this if additional lists have been requested
		$additional_user_lists = $utils->get_variable( 'additional_lists', array() );
		
		if ( empty( $additional_user_lists ) ) {
			return;
		}
		
		// All configured additional lists
		$all_additional_lists  = $mc_api->get_option( 'additional_lists' );
		
		// There aren't any additional lists...
		if ( empty( $all_additional_lists ) ) {
		    $all_additional_lists = array();
        }
        
		// Save MailChimp list selections the user made
		update_user_meta( $user_id, 'e20r_mc_additional_lists', $additional_user_lists );
		
		$list_user = get_userdata( $user_id );
		
		// Process additional lists for the user
		if ( ! empty( $all_additional_lists ) ) {
			
			foreach ( $all_additional_lists as $list ) {
				
				// If we find the list in the user selected lists, then subscribe the user
				if ( in_array( $list, $additional_user_lists ) ) {
					
					$mc_api->subscribe( $list, $list_user );
					
				} else { //If we didn't find this list in the user selected lists, try to unsubscribe them
					
					/**
					 * @since v2.5 BUG FIX: Use unsubscribe method, not 'delete'
					 */
					$controller->unsubscribe( $list, $list_user );
				}
			}
		}
	}
	
	/**
	 * Return or instantiate the User_Handler class
	 *
	 * @return User_Handler|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
}