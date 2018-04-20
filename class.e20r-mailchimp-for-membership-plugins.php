<?php
/*
Plugin Name: E20R MailChimp Integration for Revenue Tools
Plugin URI: https://eighty20results.com/wordpress-plugins/e20r-mailchimp-for-membership-plugins/
Description: Automatically add users to your MailChimp.com list(s) when they purchase, sign up, or register to get access your site/products. Segment users with Merge Tags and Interest Groups. Include custom user meta data in the merge tags/merge fields. Supports <a href="https://wordpress.org/plugins/paid-memberships-pro/">Paid Memberships Pro</a> and <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a>
Version: 1.4.1
WC requires at least: 3.3
WC tested up to: 3.3.3
Requires at least: 4.9
Tested up to: 4.9.3
Author: Eighty/20 Results <thomas@eighty20results.com>
Author URI: https://eighty20results.com/thomas-sjolshagen/
Developer: Thomas Sjolshagen <thomas@eighty20results.com>
Developer URI: https://eighty20results.com/thomas-sjolshagen/
Text Domain: e20r-mailchimp-for-membership-plugins
Domain Path: /languages
License: GPLv2

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
*
*/

namespace E20R\MailChimp;

use E20R\Utilities\GDPR_Enablement;
use E20R\Utilities\Licensing\Mailchimp_License;
use E20R\Utilities\Utilities;

/**
 * Deny TESTING the "GROUPINGS" entry in the `e20r_mailchimp_merge_fields` suppled array of merge fields
 */

if ( ! defined( 'E20R_MC_TESTING' ) ) {
	define( 'E20R_MC_TESTING', false );
}

if ( ! defined( 'E20R_MAILCHIMP_VERSION' ) ) {
	define( 'E20R_MAILCHIMP_VERSION', '1.4.1' );
}

if ( ! defined( 'E20R_MAILCHIMP_DIR' ) ) {
	define( 'E20R_MAILCHIMP_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'E20R_MAILCHIMP_URL' ) ) {
	define( 'E20R_MAILCHIMP_URL', plugin_dir_url( __FILE__ ) );
}

if ( !defined( 'E20R_MAILCHIMP_NA' ) ) {
	define( 'E20R_MAILCHIMP_NA', -1 );
}

if ( !defined( 'E20R_MAILCHIMP_CURRENT_USER' ) ) {
	define( 'E20R_MAILCHIMP_CURRENT_USER', 0);
}

if ( !defined( 'E20R_MAILCHIMP_BILLING_USER' ) ) {
	define( 'E20R_MAILCHIMP_BILLING_USER', 1);
}

if ( ! class_exists( 'E20R\MailChimp\Controller' ) ) {
	
	class Controller {
		
		/**
		 * Name of plugin directory (plugin slug)
		 */
		const plugin_slug = 'e20r-mailchimp-for-membership-plugins';
		
		/**
		 * @var null|Controller
		 */
		private static $instance = null;
		
		/**
		 * Controller constructor.
		 */
		private function __construct() {
			add_filter( 'e20r-licensing-text-domain', array( $this, 'get_plugin_name' ) );
			add_action( 'init', array( Mailchimp_License::get_instance(), 'load_hooks' ), 99 );
		}
		
		/**
		 * Return the name of this plugin
		 *
		 * @return string
		 */
		public function get_plugin_name() {
			return Controller::plugin_slug;
		}
		
		/**
		 * Returns an instance of the Controller class (or null)
		 *
		 * @return Controller|null
		 */
		public static function get_instance() {
			
			if ( is_null( self::$instance ) ) {
				self::$instance = new self;
			}
			
			return self::$instance;
		}
		
		/**
		 * The plugins_loader hook handler for the E20R MailChimp for PMPro plugin
		 */
		public function plugins_loaded() {
			
			add_action( 'plugins_loaded', array( GDPR_Enablement::get_instance(), 'load_hooks' ), 98 );
			add_action( 'plugins_loaded', array( MC_Settings::get_instance(), 'load_actions' ), 99 );
			
			$plugin = plugin_basename( __FILE__ );
			
			add_filter( "plugin_action_links_{$plugin}", array( $this, 'add_action_links' ), 10, 1 );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
			
			add_action( 'init', array( User_Handler::get_instance(), 'load_actions' ) );
			add_action( "init", array( Member_Handler::get_instance(), "load_plugin" ), -1 );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_styles' ) );
			
			
		}
		
		/**
		 * Load CSS and Javascript to /wp-admin/
		 */
		public function load_admin_styles() {
			
			if ( is_admin() ) {
				wp_enqueue_style( 'e20r-mc-admin', E20R_MAILCHIMP_URL . 'css/e20r-mailchimp-for-membership-plugins-admin.css', array(), E20R_MAILCHIMP_VERSION );
			}
		}
		
		/**
		 * Are we currently on the login or registration page?
		 * (Includes support for Theme My Logins)
		 *
		 * @return bool
		 */
		public static function on_login_page() {
			
			global $post;
			
			$on_login_page = ( $GLOBALS['pagenow'] === 'wp-login.php' && ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] === 'register' );
			$on_login_page = $on_login_page || (isset( $post->post_content ) ? has_shortcode( $post->post_content, 'theme-my-login' ) : false );
			
			return $on_login_page;
		}
		
		/**
		 * Load style(s) for frontend
		 */
		public function load_frontend_styles() {
			wp_enqueue_style( 'e20r-mc', E20R_MAILCHIMP_URL . "css/e20r-mailchimp-for-membership-plugins.css", null, E20R_MAILCHIMP_VERSION );
		}
		/**
		 * Add links to the plugin row meta
		 *
		 * @param $links - Links for plugin
		 * @param $file  - main plugin filename
		 *
		 * @return array - Array of links
		 */
		public function plugin_row_meta( $links, $file ) {
			
			if ( false !== strpos( $file, 'e20r-mailchimp-for-membership-plugins.php' ) ) {
				$new_links = array(
					sprintf(
						'<a href="%1$s" title="%2$s">%3$s</a>',
						esc_url_raw( 'https://eighty20results.com/wordpress-plugins/e20r-mailchimp-for-membership-plugins/' ),
						__( 'View Documentation', Controller::plugin_slug ),
						__( 'Docs', Controller::plugin_slug )
					),
					sprintf(
						'<a href="%1$s" title="%2$s">%3$s</a>',
						esc_url_raw( 'http://eighty20results.com/support/' ),
						__( 'Visit Customer Support Forum', Controller::plugin_slug ),
						__( 'Support', Controller::plugin_slug )
					),
				);
				
				$links = array_merge( $links, $new_links );
			}
			
			return $links;
		}
		
		/**
		 * Add links to the plugin action links
		 *
		 * @param $links (array) - The existing link array
		 *
		 * @return array -- Array of links to use
		 *
		 */
		public function add_action_links( $links ) {
			
			$new_links = array(
				sprintf(
					'<a href="%1$s">%2$s</a>',
					add_query_arg( 'page', 'e20r_mc_settings', get_admin_url( null, 'options-general.php' ) ),
					__( 'Settings', Controller::plugin_slug ) ),
			);
			
			return array_merge( $new_links, $links );
		}
		
		/**
		 * Set Default options when activating plugin
		 */
		public function activation() {
			//get options
			$options = get_option( "e20r_mc_settings", array() );
			
			//defaults
			if ( empty( $options ) ) {
				
				$options = array(
					"api_key"           => "",
					"double_opt_in"     => 0,
					"unsubscribe"       => 2,
					"members_list"      => array(),
					"additional_lists"  => array(),
					"level_merge_field" => "",
				);
				update_option( "e20r_mc_settings", $options );
				
			} else if ( ! empty( $options ) && ! isset( $options['unsubscribe'] ) ) {
				
				$options['unsubscribe'] = 2;
				update_option( "e20r_mc_settings", $options );
			}
			
			do_action( 'e20r-mailchimp-plugin-activation' );
		}
		
		/**
		 * Unsubscribe a user based on their membership level.
		 *
		 * @param int $user_id  (int) - User Id
		 * @param int $level_id (int) - Membership Level Id
		 */
		/*
		public function unsubscribe_from_lists( $user_id, $level_id = null ) {
			
			$utils = Utilities::get_instance();
			$mc    = MailChimp_API::get_instance();
			$unsub = $mc->get_option( 'unsubscribe' );
			
			$utils->log( "Unsubscribe logic during membership change/cancelleation" );
			
			// $options           = get_option( "e20r_mc_settings" );
			$all_lists            = get_option( "e20r_mc_lists" );
			$prefix               = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
			$unsubscribe_lists    = array();
			$level_lists          = array();
			$active_user_levels   = array();
			$current_levels_lists = array();
			
			$current_level_ids = apply_filters( 'e20r-mailchimp-user-membership-levels', array(), $user_id );
			
			if ( ! empty( $current_level_ids ) ) {
				foreach ( $current_level_ids as $level ) {
					$active_user_levels[] = $level->id;
				}
			}
			
			// We won't unsubscribe if the option isn't set
			if ( empty( $unsub ) ) {
				
				$utils->log( "No need to unsubscribe {$user_id} with 'level' ID: {$level_id}" );
				
				return;
			}
			
			// Unsubscribing from all lists, or just the ones associated with a membership level?
			switch ( $unsub ) {
				
				case 'all':
					$unsubscribe_lists = wp_list_pluck( $all_lists, "id" );
					break;
				
				case 1:
				case 2:
					$utils->log( "Processing for unsubscribe or set interest group to Cancelled" );
					
					$unsubscribing_from          = $this->get_levels_to_unsubscribe_from( $user_id );
					$multiple_products_purchased = array_count_values( $unsubscribing_from );
					
					$utils->log( "Found " . count( $unsubscribing_from ) . " to possibly cancel subscriptions for: " . print_r( $unsubscribing_from, true ) );
					
					foreach ( $multiple_products_purchased as $old_level_id => $level_count ) {
						
						$utils->log( "Have {$level_count} products/orders for {$old_level_id}" );
						
						if ( 1 >= $level_count ) {
							
							$lists = $mc->get_option( "level_{$prefix}_{$old_level_id}_lists" );
							$utils->log( "Checking lists for {$old_level_id}: " . print_r( $lists, true ) );
							
							if ( ! empty( $lists ) ) {
								$unsubscribe_lists = array_merge( $unsubscribe_lists, $lists );
							}
						}
					}
					
					$unsubscribe_lists = array_unique( $unsubscribe_lists );
					break;
				
			}
			
			$utils->log( "Have " . count( $unsubscribe_lists ) . " list we should process during unsubscribe." );
			
			// Should we unsubscribe from lists (or are we only setting the interest group(s)?)
			if ( empty( $unsubscribe_lists ) ) {
				$utils->log( "No lists to unsubscribe from! Unsub option is: {$unsub}" );
				
				return;
			}
			
			$utils->log( "Have " . count( $active_user_levels ) . " active levels for {$user_id}" );
			
			if ( ! empty( $active_user_levels ) ) {
				
				foreach ( $active_user_levels as $user_level_id ) {
					
					$active_level_lists = $mc->get_option( "level_{$prefix}_{$user_level_id}_lists" );
					
					if ( ! empty( $active_level_lists ) ) {
						$current_levels_lists = array_merge( $current_levels_lists, $active_level_lists );
					}
				}
			} else {
				$utils->log( "No active user levels... " );
			}
			
			//Don't unsubscribe users for the new level(s), or any additional list they're elected to subscribe to
			$user_additional_lists = get_user_meta( $user_id, 'e20r_mc_additional_lists', true );
			
			if ( ! is_array( $user_additional_lists ) ) {
				
				$user_additional_lists = array();
			}
			
			if ( ! empty( $level_id ) ) {
				
				$tmp_list = $mc->get_option( "level_{$prefix}_{$level_id}_lists" );
				
				if ( is_array( $tmp_list ) ) {
					$level_lists = $tmp_list;
				}
				
			} else if ( true === apply_filters( 'e20r-mailchimp-membership-plugin-present', false ) &&
			            null === $level_id ) {
				
				$tmp_list = $mc->get_option( 'members_list' );
				if ( is_array( $tmp_list ) ) {
					$level_lists = $tmp_list;
				}
			}
			
			$dont_unsubscribe_from = array_merge( $user_additional_lists, $level_lists );
			$list_user             = get_userdata( $user_id );
			
			//unsubscribe
			foreach ( $unsubscribe_lists as $list_id ) {
				
				if ( in_array( $unsub, array( 'all', '1' ) ) ) {
					
					if ( ! in_array( $list_id, $dont_unsubscribe_from ) ) {
						$utils->log( "Unsubscribing {$list_user->ID} from {$list_id}" );
						$mc->unsubscribe( $list_id, $list_user );
					}
					
				} else if ( 2 == $unsub ) {
					
					$utils->log( "Processing 'Cancelled' Interest Group(s) for {$list_id}" );
					
					if ( ! in_array( $list_id, $dont_unsubscribe_from ) ) {
						
						$utils->log( "Setting the Cancelled interest group. Not actually removing Mailchimp user {$list_user->user_email} from {$list_id} " );
						
						if ( false === $mc->subscribe( $list_id, $list_user, array(), array()) ) {
							$utils->log( "Unable to set 'cancelled' for {$list_user->ID} in list {$list_id}" );
						}
						$utils->log( "Updated the settings for {$list_user->ID}" );
					}
				}
				
			}
		}
		*/
		/**
		 * Return the levels to unsubscribe (possible) mailing list(s) from
		 *
		 * @param int $user_id
		 *
		 * @return null|array
		 */
		/*
		public function get_levels_to_unsubscribe_from( $user_id ) {
			
			$levels_to_unsubscribe_from = null;
			$current_user_levels        = array();
			$level_ids                  = array();
			
			// Is a membership plugin installed and active?
			$has_membership_system = apply_filters( 'e20r-mailchimp-membership-plugin-present', false );
			
			// Only makes sense if we're cohabitating with a membership plugin
			if ( true === $has_membership_system ) {
				
				//Get their last level, last entry or second to last if they are changing levels
				global $wpdb;
				
				$current_user_levels = apply_filters( 'e20r-mailchimp-user-membership-levels', $current_user_levels, $user_id );
				
				foreach ( $current_user_levels as $user_level ) {
					$level_ids[] = $user_level->id;
				}
				
				$statuses = apply_filters( 'e20r-mailchimp-non-active-statuses', array() );
				
				// Fetch user's list of levels to drop the subscription from for other membership plugin options
				$levels_to_unsubscribe_from = apply_filters( 'e20r-mailchimp-user-old-membership-levels', $levels_to_unsubscribe_from, $user_id, $level_ids, $statuses );
			}
			
			return $levels_to_unsubscribe_from;
		}
		*/
		/**
		 * Unsubscribe a user from a specific list
		 *
		 * @param string   $list_id - the List ID
		 * @param \WP_User $user    - The WP_User object for the user
		 *
		 * @return bool
		 */
		public function unsubscribe( $list_id, $user, $merge_fields, $interests ) {
			
			//make sure user has an email address
			if ( empty( $user->user_email ) ) {
				return false;
			}
			
			$mc_api   = MailChimp_API::get_instance();
			$utils = Utilities::get_instance();
			
			$unsub_setting = $mc_api->get_option( 'unsubscribe' );
			
			if ( ! empty( $mc_api ) ) {
				
				switch ( $unsub_setting ) {
					case 1:
					case 'all':
						
						$utils->log( "Actually removing the user {$user->ID} from the {$list_id} list" );
						
						return $mc_api->unsubscribe( $list_id, $user, $merge_fields, $interests );
						break;
					
					case 2:
						
						// We're only updating the user/member's list settings
						$utils->log( "Updating the member list settings (merge fields and Interests) for {$user->ID} on {$list_id}" );
						
						return $mc_api->subscribe( $list_id, $user, $merge_fields, $interests );
						break;
					
					default:
						return false;
				}
				
				
			} else {
				wp_die( __( 'Error during unsubscribe operation. Please report this error to the administrator', Controller::plugin_slug ) );
			}
		}
		
		/**
		 * Subscribe a user to a specific list
		 *
		 * @param string   $list_id  - the List ID
		 * @param \WP_User $user     - The WP_User object for the user
		 * @param int      $level_id - The membership level (ID) the user is being added/subscribed to
		 *
		 * @return bool
		 */
		public function subscribe( $list_id, $user, $level_id ) {
			
			$utils         = Utilities::get_instance();
            $mc_api           = MailChimp_API::get_instance();
			$ig_controller = Interest_Groups::get_instance();
			$mf_controller = Merge_Fields::get_instance();
			$level_ids     = array();
			
			$gdpr_consent = (bool) $utils->get_variable( 'gdpr_consent_agreement', false );
			
			//make sure user has an email address
			if ( empty( $user->user_email ) ) {
				
				$utils->log( "No user info??? " . print_r( $user, true ) );
				
				return false;
			}
            
            $utils->log("Subscribe operation for {$user->user_email} for Level ID: {$level_id}");
			
			if ( ! is_null( $level_id ) ) {
				$level_ids = array( $level_id );
			}
			
			$merge_fields = $mf_controller->populate( $list_id, $user, $level_ids );
			$interests    = $ig_controller->populate( $list_id, $user, $level_ids );
			
			$opt_in = $mc_api->get_option( 'double_opt_in' );
			
			$email_type = apply_filters( 'e20r_mailchimp_default_mail_type', 'html' );
			
			if ( true === $gdpr_consent ) {
				$utils->log( "Trying to subscribe {$user->ID} to list {$list_id} with double opt-in ({$opt_in}), GDPR consent ({$gdpr_consent}) and type {$email_type}" );
				return $mc_api->subscribe( $list_id, $user, $merge_fields, $interests, $email_type, $opt_in );
			}
		}
		
		/**
		 * Subscribe a user to any additional opt-in lists selected
		 *
		 * @param int $user_id
		 */
		public function subscribe_to_additional_lists( $user_id, $level_id ) {
			
			$utils            = Utilities::get_instance();
			$additional_lists = $utils->get_variable( 'additional_lists', null );
			$gdpr_consent = (bool) $utils->get_variable( 'gdpr_consent_agreement', false );
			
			if ( ! empty( $additional_lists ) ) {
				update_user_meta( $user_id, 'e20r_mc_additional_lists', $additional_lists );
				
				$list_user = get_userdata( $user_id );
				
				foreach ( $additional_lists as $list ) {
					//subscribe them
					if ( true === $gdpr_consent ) {
						$this->subscribe( $list, $list_user, $level_id );
					}
				}
			}
			
			update_user_meta( $user_id, 'e20r_gdpr_consent_agreement', $gdpr_consent );
		}
		
		/**
		 * Class auto-loader for the Mailchimp for PMPro plugin
		 *
		 * @param string $class_name Name of the class to auto-load
		 *
		 * @since  1.0
		 * @access public static
		 */
		public static function auto_loader( $class_name ) {
			
			if ( false === stripos( $class_name, 'e20r' ) ) {
				return;
			}
			
			$parts     = explode( '\\', $class_name );
			$c_name    = strtolower( preg_replace( '/_/', '-', $parts[ ( count( $parts ) - 1 ) ] ) );
			$base_path = plugin_dir_path( __FILE__ ) . 'classes/';
			
			if ( file_exists( plugin_dir_path( __FILE__ ) . 'class/' ) ) {
				$base_path = plugin_dir_path( __FILE__ ) . 'class/';
			}
			
			$filename = "class.{$c_name}.php";
			$iterator = new \RecursiveDirectoryIterator( $base_path, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveIteratorIterator::SELF_FIRST | \RecursiveIteratorIterator::CATCH_GET_CHILD | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
			
			/**
			 * Loate class member files, recursively
			 */
			$filter = new \RecursiveCallbackFilterIterator( $iterator, function ( $current, $key, $iterator ) use ( $filename ) {
				
				$file_name = $current->getFilename();
				
				// Skip hidden files and directories.
				if ( $file_name[0] == '.' || $file_name == '..' ) {
					return false;
				}
				
				if ( $current->isDir() ) {
					// Only recurse into intended subdirectories.
					return $file_name() === $filename;
				} else {
					// Only consume files of interest.
					return strpos( $file_name, $filename ) === 0;
				}
			} );
			
			foreach ( new \ RecursiveIteratorIterator( $iterator ) as $f_filename => $f_file ) {
				
				$class_path = $f_file->getPath() . "/" . $f_file->getFilename();
				
				if ( $f_file->isFile() && false !== strpos( $class_path, $filename ) ) {
					require_once( $class_path );
				}
			}
		}
	}
}

/**
 * Filter to test the Groupings functionality.
 *
 * @param array    $fields
 * @param \WP_User $user
 * @param string   $list_id
 *
 * @return array
 */
function test_e20rmc_listsubscribe_fields( $fields, $user = null, $list_id = null ) {
	
	if ( defined( 'E20R_MC_TESTING' ) && true === E20R_MC_TESTING ) {
		if ( WP_DEBUG ) {
			error_log( "PMPROMC: Loading test filter for listsubscribe fields" );
		}
		
		if ( is_null( $user ) ) {
			$user = get_current_user();
		}
		
		$new_fields = array(
			"FNAME"     => 'Thomas',
			"LNAME"     => 'Sjolshagen',
			'GROUPINGS' => array(
				array(
					'name'   => "Category",
					'groups' => array( "Members" ),
				),
			),
			"JOINDATE"  => date( 'Y-m-d', current_time( 'timestamp' ) ),
		);
		
		$fields = array_merge( $fields, $new_fields );
	}
	
	return $fields;
}

global $e20r_mailchimp_plugins;

if ( empty( $e20r_mailchimp_plugins ) ) {
	$e20r_mailchimp_plugins = array();
}

$e20r_mailchimp_plugins[] = array(
	'plugin_slug' => 'pmpro',
	'class_name'  => 'PMPro',
);

$e20r_mailchimp_plugins[] = array(
	'plugin_slug' => 'woocommerce',
	'class_name'  => 'WooCommerce',
);

spl_autoload_register( 'E20R\MailChimp\Controller::auto_loader' );

register_activation_hook( __FILE__, array( Controller::get_instance(), "activation" ) );

// Load one-click update support for v3.x BETA from custom repository
if ( file_exists( plugin_dir_path( __FILE__ ) . "plugin-updates/plugin-update-checker.php" ) ) {
	
	require_once( plugin_dir_path( __FILE__ ) . "plugin-updates/plugin-update-checker.php" );
	
	$plugin_updates = \PucFactory::buildUpdateChecker(
		'https://eighty20results.com/protected-content/e20r-mailchimp-for-membership-plugins/metadata.json',
		__FILE__,
		Controller::get_instance()->get_plugin_name()
	);
}

add_action( 'plugins_loaded', array( Controller::get_instance(), 'plugins_loaded' ) );