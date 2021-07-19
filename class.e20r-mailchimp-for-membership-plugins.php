<?php
/*
Plugin Name: E20R MailChimp Interest Groups for Paid Memberships Pro (and WooCommerce)
Plugin URI: https://eighty20results.com/wordpress-plugins/e20r-mailchimp-for-membership-plugins/
Description: Use MailChimp Interest Groups and Merge Fields when adding members to your MailChimp.com list(s) when they purchase, sign up, or register to get access your site/products. Segment users with Merge Tags and/or MailChimp Interest Groups. Include custom user meta data in the merge tags/merge fields. Supports <a href="https://wordpress.org/plugins/paid-memberships-pro/">Paid Memberships Pro</a> and <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a>
Version: 6.0.1
WC requires at least: 3.3
WC tested up to: 5.6
Requires at least: 4.5
Tested up to: 5.6
Author: Eighty/20 Results <thomas@eighty20results.com>
Author URI: https://eighty20results.com/thomas-sjolshagen/
Developer: Thomas Sjolshagen <thomas@eighty20results.com>
Developer URI: https://eighty20results.com/thomas-sjolshagen/
Text Domain: e20r-mailchimp-for-membership-plugins
Domain Path: /languages
License: GPLv2

* Copyright (c) 2017-2021 - Eighty / 20 Results by Wicked Strong Chicks.
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

namespace E20R\MC_Loader;

use E20R\Utilities\Utilities;
use E20R\MailChimp\Controller;

/**
 * Load the required E20R Utilities Module functionality
 */
require_once plugin_dir_path( __FILE__ ) . 'class-activateutilitiesplugin.php';

if ( ! apply_filters( 'e20r_utilities_module_installed', false ) ) {

	$required_plugin = __( 'E20R MailChimp Interest Groups for Paid Memberships Pro (and WooCommerce)', 'e20r-mailchimp-for-membership-plugins' );


	if ( false === \E20R\Utilities\ActivateUtilitiesPlugin::attempt_activation() ) {
		add_action(
			'admin_notices',
			function () use ( $required_plugin ) {
				\E20R\Utilities\ActivateUtilitiesPlugin::plugin_not_installed( $required_plugin );
			}
		);

		return false;
	}
}

/**
 * Deny TESTING the "GROUPINGS" entry in the `e20r_mailchimp_merge_fields` supplied array of merge fields
 */

if ( ! defined( 'E20R_MC_TESTING' ) ) {
	define( 'E20R_MC_TESTING', false );
}

if ( ! defined( 'E20R_MAILCHIMP_VERSION' ) ) {
	define( 'E20R_MAILCHIMP_VERSION', '6.0.1' );
}

if ( ! defined( 'E20R_MAILCHIMP_DIR' ) ) {
	define( 'E20R_MAILCHIMP_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'E20R_MAILCHIMP_URL' ) ) {
	define( 'E20R_MAILCHIMP_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'E20R_MAILCHIMP_NA' ) ) {
	define( 'E20R_MAILCHIMP_NA', - 1 );
}

if ( ! defined( 'E20R_MAILCHIMP_CURRENT_USER' ) ) {
	define( 'E20R_MAILCHIMP_CURRENT_USER', 0 );
}

if ( ! defined( 'E20R_MAILCHIMP_BILLING_USER' ) ) {
	define( 'E20R_MAILCHIMP_BILLING_USER', 1 );
}

if ( ! class_exists( 'E20R\MC_Loader\Loader' ) ) {

	class Loader {

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

			$parts      = explode( '\\', $class_name );
			$c_name     = strtolower( preg_replace( '/_/', '-', $parts[ ( count( $parts ) - 1 ) ] ) );
			$base_paths = apply_filters( 'e20r_mailchimp_autoloader_paths', array( plugin_dir_path( __FILE__ ) . 'classes/' ) );

			if ( file_exists( plugin_dir_path( __FILE__ ) . 'src/' ) ) {
				$base_paths = apply_filters( 'e20r_mailchimp_autoloader_paths', array( plugin_dir_path( __FILE__ ) . 'src/' ) );
			}

			$filename = "class-{$c_name}.php";

			foreach ( $base_paths as $base_path ) {
				$iterator = new \RecursiveDirectoryIterator(
					$base_path,
					\RecursiveDirectoryIterator::SKIP_DOTS |
					\RecursiveIteratorIterator::SELF_FIRST |
					\RecursiveIteratorIterator::CATCH_GET_CHILD |
					\RecursiveDirectoryIterator::FOLLOW_SYMLINKS
				);

				/**
				 * Load class member files, recursively
				 */
				$filter = new \RecursiveCallbackFilterIterator(
					$iterator,
					function ( $current, $key, $iterator ) use ( $filename ) {

						$file_name = $current->getFilename();

						// Skip hidden files and directories.
						if ( '.' === $file_name[0] || '..' === $file_name ) {
							return false;
						}

						if ( $current->isDir() ) {
							// Only recurse into intended subdirectories.
							return $file_name() === $filename;
						} else {
							// Only consume files of interest.
							return strpos( $file_name, $filename ) === 0;
						}
					}
				);

				foreach ( new \ RecursiveIteratorIterator( $iterator ) as $f_filename => $f_file ) {

					$class_path = $f_file->getPath() . '/' . $f_file->getFilename();

					if ( $f_file->isFile() && false !== strpos( $class_path, $filename ) ) {
						require_once $class_path;
					}
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

	if ( defined( 'E20R_MC_TESTING' ) && false !== E20R_MC_TESTING ) {
		if ( WP_DEBUG ) {
			error_log( 'PMPROMC: Loading test filter for listsubscribe fields' ); // phpcs:ignore
		}

		if ( is_null( $user ) ) {
			$user = get_current_user();
		}

		$new_fields = array(
			'FNAME'     => 'Thomas',
			'LNAME'     => 'PMPro',
			'GROUPINGS' => array(
				array(
					'name'   => 'Category',
					'groups' => array( 'Members' ),
				),
			),
			'JOINDATE'  => date_i18n( 'Y-m-d', time() ),
		);

		$fields = array_merge( $fields, $new_fields );
	}

	return $fields;
}

global $e20r_mailchimp_plugins;

/**
 * @var string[] $e20r_mailchimp_plugins
 *
 * Format:
 *      array( 'plugin_slug' => '', 'class_name' => '', 'label' => '' )
 */
if ( empty( $e20r_mailchimp_plugins ) ) {
	$e20r_mailchimp_plugins = array();
}

$e20r_mailchimp_plugins['pmpro'] = array(
	'plugin_slug' => 'pmpro',
	'class_name'  => 'PMPro',
	'label'       => __( 'Level:', Controller::plugin_slug ),
);

$e20r_mailchimp_plugins['woocommerce'] = array(
	'plugin_slug' => 'woocommerce',
	'class_name'  => 'WooCommerce',
	'label'       => __( 'Cat:', Controller::plugin_slug ),
);

try {
	spl_autoload_register( 'E20R\\MailChimp\\Loader::auto_loader' );
} catch ( \Exception $e ) {
	wp_die( 'E20R-MC-Loader-Error: ' . $e->getMessage() );
}
register_activation_hook( __FILE__, array( Controller::get_instance(), 'activation' ) );

$GLOBALS['e20r_mc_error_msg'] = null;

add_action( 'plugins_loaded', array( Controller::get_instance(), 'plugins_loaded' ), - 1 );

/** One-Click update support **/
if ( class_exists( 'E20R\Utilities\Utilities' ) ) {
	Utilities::configure_update( 'e20r-mailchimp-for-membership-plugins', __FILE__ );
}
/** End of One-Click update support **/
