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
use E20R\MailChimp\Membership_Support;

class Member_Handler {
	
	/**
	 * Static instance
	 *
	 * @var null|Member_Handler
	 * @access private
	 */
	private static $instance = null;
	
	private $member_modules = array();
	
	/**
	 * Member_Handler constructor.
	 */
	private function __construct() {
		
		// if ( is_null( self::$instance ) ) {
		//	self::$instance = $this;
		// }
	}
	
	/**
	 * Return or instantiate the Member_Handler class
	 *
	 * @return Member_Handler|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * Identify (find) the Membership Plugin configuration to load
	 */
	public function load_member_plugin_support() {
		
		$utils = Utilities::get_instance();
		$utils->log( "Attempt to load supported membership plugin hooks" );
		
		$mc_api = MailChimp_API::get_instance();
		
		global $e20r_mailchimp_plugins;
		
		// $membership_plugin = $mc_api->get_option( 'membership_plugin' );
		
		foreach ( $e20r_mailchimp_plugins as $slug => $plugin_settings ) {
			$this->load_membership_filters( $plugin_settings, $plugin_settings['plugin_slug'] );
		}
	}
	
	/**
	 * Trigger load of the Membership Plugin support class & load its hooks & filters
	 *
	 * @param array $plugin_settings
	 */
	private function load_membership_filters( $plugin_settings, $slug ) {
		
		$utils = Utilities::get_instance();
		
		$utils->log( "Loading membership plugin support for {$plugin_settings['class_name']}/{$slug}" );
		$plugin_path = '\\E20R\\MailChimp\\Membership_Support\\' . $plugin_settings['class_name'];
		
		$this->member_modules[ $slug ] = $plugin_path::get_instance();
	}
	
	/**
	 * Initialize the plugin (membership specific stuff, mostly)
	 */
	public function load_plugin() {
		
		// Load API and utilities
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
		$utils->log( "Loading the base class load_plugin method");
		
		// Check that API is loaded
		if ( empty( $mc_api ) ) {
			
			$utils->add_message( __( "Unable to load MailChimp API interface", Controller::plugin_slug ), 'error', 'backend' );
			
			return;
		}
		
		// Configure API key
		$mc_api->set_key();
		$this->load_member_plugin_support();
		
		// Configure any default merge tags and listsubscribe fields
		add_filter( 'e20r-mailchimp-merge-tag-settings', array( $this, 'default_merge_field_settings' ), 10, 2 );
		add_filter( 'e20r-mailchimp-user-defined-merge-tag-fields',
			array( Merge_Fields::get_instance(), 'admin_defined_listsubscribe', ),
			999,
			3
		);
		add_filter( 'e20r-mailchimp-user-defined-merge-tag-fields', array( $this, 'default_listsubscribe_fields' ), 10, 3 );
		
		//On the checkout page?
		$on_checkout_page = apply_filters( 'e20r-mailchimp-on-membership-checkout-page', false );
		$member_list      = $mc_api->get_option( 'members_list' );
		$additional_lists = $mc_api->get_option( 'additional_lists' );
		
		//Configure the user_register hook (outside of a membership plugin.
		if ( ( ! empty( $member_list ) || ! empty( $additional_lists ) ) && ! $on_checkout_page ) {
			add_action( 'user_register', array( User_Handler::get_instance(), 'user_register' ) );
		}
		
		add_action( 'e20r-mailchimp-membership-level-update',
			array( Interest_Groups::get_instance(), 'create_categories_for_membership' ),
			10,
			1
		);
		
		add_action( 'e20r-mailchimp-plugin-activation', array( $this, 'create_default_groups' ) );
		
		do_action( 'e20r-mailchimp-membership-plugin-load', $on_checkout_page );
	}
    
	public static function get_membership_plugin_name( $mp_id ) {
		global $e20r_mailchimp_plugins;
    }
	/**
	 * Load default merge field definition
	 *
	 * @param array $default_fields
	 * @param null  $list_id
	 *
	 * @return array
	 */
	public function default_merge_field_settings( $default_fields, $list_id = null ) {
		
		$member_merge_fields = array();
		$default_fields      = array(
			array( 'tag' => 'FNAME', 'name' => 'First Name', 'type' => 'text', 'public' => false ),
			array( 'tag' => 'LNAME', 'name' => 'Last Name', 'type' => 'text', 'public' => false ),
		);
		
		$member_merge_fields = apply_filters( 'e20r-mailchimp-member-merge-field-defs', $member_merge_fields, $list_id );
		
		if ( ! empty( $member_merge_fields ) ) {
			$default_fields = $default_fields + $member_merge_fields;
		}
		
		return $default_fields;
	}
	
	/**
	 * Membership level as merge values.
	 *
	 * @param array       $default_fields - Merge fields (preexisting)
	 * @param \WP_User    $user   - User object
	 * @param string|null $list_id   - MailChimp List ID
	 *
	 * @return mixed - Array of $merge fields;
	 */
	public function default_listsubscribe_fields( $default_fields, $user, $list_id = null, $level_id = null ) {
		
		if ( empty( $user ) ) {
			return $default_fields;
		}
		
		$membership_mf_values = apply_filters( 'e20r-mailchimp-member-merge-field-values', array(), $user, $list_id, $level_id );
		
		$new_fields = array(
			"FNAME" => isset( $user->first_name ) ? $user->first_name : null,
			"LNAME" => $user->last_name,
		);
		
		if ( ! empty( $membership_mf_values ) ) {
			
			foreach ( $membership_mf_values as $name => $value ) {
				$new_fields[ $name ] = $value;
			}
			
		}
		
		// Add default and membership field values for the specified user
		$default_fields = array_merge_recursive( $default_fields, $new_fields );
		
		return $default_fields;
	}
	
	/**
	 * Preserve info when going off-site for payment w/offsite payment gateway (PayPal Express).
	 * Saves Session variables.
	 */
	public function session_vars() {
		
		// Do we have a session we can use?
		if ( session_id() == '' || ! isset( $_SESSION ) ) {
			return;
		}
		
		if ( isset( $_REQUEST['additional_lists'] ) ) {
			$_SESSION['additional_lists'] = $_REQUEST['additional_lists'];
		}
		
		if ( isset( $_REQUEST['e20r_user_opted_in'] ) ) {
			$_SESSION['e20r_user_opted_in'] = $_REQUEST['e20r_user_opted_in'];
		}
	}
	
	/**
	 * Get the e20r_mc_levels if membership plugin/option is installed
	 */
	public function get_levels( $prefix = 'any' ) {
		
		$utils = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
        $utils->log("Attempting to load level(s)");
        
        // $member_plugin = $mc_api->get_option( 'membership_plugin' );
		
		if ( null === ( $e20r_mc_levels = Cache::get( "e20r_lvls_{$prefix}", 'e20r_mailchimp' ) ) ) {
			
			$e20r_mc_levels = apply_filters( 'e20r-mailchimp-all-membership-levels', array(), $prefix );
			
			if ( ! empty( $e20r_mc_levels ) ) {
				Cache::set( "e20r_lvls_{$prefix}", $e20r_mc_levels, 10 * MINUTE_IN_SECONDS, 'e20r_mailchimp' );
			}
		}
		
		return $e20r_mc_levels;
	}
	
	/**
	 * Remove the cache when a membership level is updated/saved/changed
	 */
	public function clear_levels_cache() {
        $mc_api = MailChimp_API::get_instance();
		$member_plugin = $mc_api->get_option( 'membership_plugin' );
		
		Cache::delete( "e20r_lvls_{$member_plugin}", 'e20r_mailchimp' );
	}
	
	/**
	 * Unsubscribe (change Interest Group(s) and clear Merge Fields)
	 *
	 * @param int  $level_id
	 * @param  int $user_id
	 * @param null $old_level_id
	 *
	 * @return bool
	 */
	public function cancelled_membership( $level_id, $user_id, $old_level_id = null ) {
		
		$utils = Utilities::get_instance();
		
		if ( 0 != $level_id ) {
			$utils->log( "User is either adding a new level, or changing a level, so we'll return" );
			
			return true;
		}
		
		if ( empty( $old_level_id ) ) {
		    $utils->log("Old level not specified!");
		    return false;
        }
        
		$utils->log( "Cancelling membership level {$old_level_id}" );
		
		$mc_controller = Controller::get_instance();
		$mc_api        = MailChimp_API::get_instance();
		$mf_controller = Merge_Fields::get_instance();
		$ig_controller = Interest_Groups::get_instance();
		
		$prefix      = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		
		$api_key        = $mc_api->get_option( 'api_key' );
		$levels         = null;
		$user_level_ids = array();
		$merge_fields   = null;
		
		$user_level_ids = apply_filters( 'e20r-mailchimp-user-membership-levels', $user_level_ids, $user_id );
        
        $level_lists    = $mc_api->get_option( "level_{$prefix}_{$old_level_id}_lists" );
        
        if ( empty( $level_lists ) ) {
            $utils->log("No level specific lists defined for {$level_id}. Grabbing the default list");
            $level_lists = $mc_api->get_option( "members_list" );
        }
        
        if ( empty( $user_level_ids ) && ! is_array( $user_level_ids ) ) {
			$user_level_ids = array();
		}
		
		if ( ! empty( $old_level_id ) && ! is_array( $user_level_ids ) ) {
			$user_level_ids[] = $old_level_id;
		} else if ( ! empty( $old_level_id ) && is_array( $old_level_id ) ) {
			$user_level_ids = $old_level_id;
		}
		
		// Ignored: additional_lists as the user can opt out separately.
		// FIXME: Change merge list info for additional lists?
		
		if ( ! empty( $level_lists ) && ! empty( $api_key ) ) {
			
			// Load WP_User data
			$list_user = get_userdata( $user_id );
			
			// Unsubscribe from each list (have to make sure)
			foreach ( $level_lists as $list_id ) {
				
				$interests = $ig_controller->populate( $list_id, $list_user, $user_level_ids, true );
				$merge_fields = $mf_controller->populate( $list_id, $list_user, $user_level_ids, true );
				
				$utils->log( "Cancelling subscription for {$list_user->user_email} from {$list_id}" );
				$mc_controller->unsubscribe( $list_id, $list_user, $merge_fields, $interests );
			}
			
			return true;
		}
	}
	
	/**
	 * Subscribe new members to the correct list when their membership level changes
	 *
	 * @param int $level_id     -- ID of membership level
	 * @param int $user_id      -- ID for user
	 * @param int $old_level_id -- ID for the user's previous level (on change or cancellation)
	 *
	 */
	public function on_add_to_new_level( $level_id, $user_id, $old_level_id = null ) {
		
		$utils = Utilities::get_instance();
		$utils->log("Adding user {$user_id} to MailChimp lists for {$level_id}");
		
		// Updating or updating membership level?
		if ( !empty( $level_id ) ) {
   
			clean_user_cache( $user_id );
			
			$utils         = Utilities::get_instance();
			$mc_controller = Controller::get_instance();
			$mc_api        = MailChimp_API::get_instance();
			
			$prefix      = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
			$api_key     = $mc_api->get_option( 'api_key' );
			$level_lists = $mc_api->get_option( "level_{$prefix}_{$level_id}_lists" );
   
			if ( empty( $level_lists ) ) {
			    $utils->log("No level specific lists defined for {$level_id}. Grabbing default list");
			    $level_lists = $mc_api->get_option( "members_list" );
            }
            
            $utils->log( "Adding membership level {$level_id} for user {$user_id} to: " . print_r( $level_lists, true  ) );
            
			// Do we have a list to add the user to?
			if ( ! empty( $level_lists ) && ! empty( $api_key ) ) {
				
				// Load WP_User data
				$list_user = get_userdata( $user_id );
				
				// Subscribe to each list (have to make sure)
				foreach ( $level_lists as $list ) {
					
					$utils->log( "Adding {$list_user->user_email} to {$list}" );
					//subscribe them
					$mc_controller->subscribe( $list, $list_user, $level_id );
				}
				
				$utils->log("Adding user ({$user_id}) to any additional lists requested & configured");
				
				// Add user to any additional lists they selected
				$mc_controller->subscribe_to_additional_lists( $user_id, $level_id );
			}
		}
		$utils->log( "Completed processing of add user to list" );
	}
	
	/**
	 * Add to Checkout page: Optional mailing lists a new member can add/subscribe to
	 */
	public function view_additional_lists() {
  
		$mc_api = MailChimp_API::get_instance();
		$utils  = Utilities::get_instance();
		
		$api_key = $mc_api->get_option( 'api_key' );
		
		// Can we access the MailChimp API?
		if ( ! empty( $api_key ) ) {
   
			if ( empty( $mc_api ) ) {
				
				$utils->add_message( __( "Unable to load MailChimp API interface", Controller::plugin_slug ), 'error', 'frontend' );
				$utils->log("Unable to load MailChimp API class!");
				return;
			}
			
			$mc_api->set_key();
		} else {
			return;
		}
		
		$additional_lists = $mc_api->get_option( 'additional_lists' );
		
		//are there additional lists?
		if ( empty( $additional_lists ) ) {
			$utils->log( "No additional lists found!" );
			
			return;
		}
		
		//okay get through API
		$lists = $mc_api->get_all_lists();
		
		//no lists?
		if ( empty( $lists ) ) {
			$utils->log( "Didn't actually find any lists at all.." );
			
			return;
		}
		
		$utils->log( "Lists on local system: " . print_r( $lists, true ) );
		
		$additional_lists_array = array();
		foreach ( $lists as $list_id => $list_config ) {
			
			if ( ! empty( $additional_lists ) ) {
				
				foreach ( $additional_lists as $additional_list ) {
					
					if ( $list_config['id'] == $additional_list ) {
						$additional_lists_array[] = $list_config;
					}
				}
			}
		}
		
		// $this->add_opt_in_option();
		
		// No additional lists configured? Then return quietly
		if ( empty( $additional_lists_array ) ) {
			$utils->log( "Have no additional lists to worry about" );
			
			return;
		}
		
		?>
        <table id="e20rmc_mailing_lists" class="top1em" width="100%" cellpadding="0" cellspacing="0" border="0">
            <thead>
            <tr>
                <th>
					<?php
					if ( count( $additional_lists_array ) > 1 ) {
						_e( 'Join one or more of our other mailing lists.', Controller::plugin_slug );
					} else {
						_e( 'Join our other mailing list.', Controller::plugin_slug );
					}
					?>
                </th>
            </tr>
            </thead>
            <tbody>
            <tr class="odd">
                <td>
					<?php
					global $current_user;
					$additional_lists_selected = $utils->get_variable( 'additional_lists', array() );
					$saved_user_lists          = get_user_meta( $current_user->ID, "e20r_mc_additional_lists", true );
					
					if ( empty( $additional_lists_selected ) && isset( $_SESSION['additional_lists'] ) ) {
						
						$additional_lists_selected = array_map( 'sanitize_text_field', $_SESSION['additional_lists'] );
						
					} else if ( empty( $additional_lists_selected ) && ! empty( $saved_user_lists ) ) {
						$additional_lists_selected = $saved_user_lists;
					}
					
					$count = 1;
					foreach ( $additional_lists_array as $key => $additional_list ) {
						$current_list = isset( $additional_lists_selected[ ( $count - 1 ) ] ) ? $additional_lists_selected[ ( $count - 1 ) ] : null;
						?>
                        <span class="e20r-additional-list-column">
                            <input type="checkbox" id="additional_lists_<?php esc_attr_e( $count ); ?>" class="e20r-list-checkbox"
                               name="additional_lists[]" style="width: 20px; position: relative; vertical-align: middle;"
                               value="<?php esc_attr_e( $additional_list['id'] ); ?>" <?php checked( $current_list, $additional_list['id'] ); ?> />
                            <label for="additional_lists_<?php esc_attr_e( $count ); ?>" style="display: inline-block; position: relative; margin: 0; vertical-align: middle;"
                               class="e20r-list-entry"><?php esc_attr_e( $additional_list['name'] ); ?></label>
                        </span>
                        <br/>
						<?php
						$count ++;
					}
					?>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}
}