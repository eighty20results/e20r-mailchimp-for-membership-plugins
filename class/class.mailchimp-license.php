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

namespace E20R\Utilities\Licensing;


use E20R\MailChimp\Controller;
use E20R\Utilities\Utilities;

class Mailchimp_License extends License_Client {
	
	/**
	 * @var null|Mailchimp_License
	 */
	private static $instance = null;
	
	/**
	 * Mailchimp_License constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return, or create, instance of Mailchimp_License class
	 *
	 * @return Mailchimp_License|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * Load a custom license warning on init
	 */
	public function check_licenses() {
		
		$utils = Utilities::get_instance();
		
		switch ( Licensing::is_license_expiring( 'e20r_mc' ) ) {
			
			case true:
				$utils->add_message( sprintf( __( 'The license for %s will renew soon. As this is an automatic payment, you will not have to do anything. To modify %syour license%s, you will need to go to %syour account page%s' ), 'Support and Updates Plus License', '<a href="https://eighty20results.com/shop/licenses/" target="_blank">', '</a>', '<a href="https://eighty20results.com/account/" target="_blank">', '</a>' ), 'info', 'backend' );
				break;
			case - 1:
				$utils->add_message( sprintf( __( 'Your %s license has expired. To continue to get updates and support for this plugin, you will need to %srenew and install your license%s.' ), 'Support and Updates Plus License', '<a href="https://eighty20results.com/shop/licenses/" target="_blank">', '</a>' ), 'error', 'backend' );
				break;
		}
	}
	
	/**
	 * Load action hooks & filters for Client License handler
	 */
	public function load_hooks() {
		
		if ( is_admin() ) {
			add_filter( 'e20r-license-add-new-licenses', array( $this, 'add_new_license_info', ), 10, 2 );
			add_action( 'admin_init', array( $this, 'check_licenses' ) );
		}
	}
	
	/**
	 * Configure settings for the E20R MailChimp license (must match upstream license info)
	 *
	 * @param array $license_settings
	 * @param array $plugin_settings
	 *
	 * @return array
	 */
	public function add_new_license_info( $license_settings, $plugin_settings = null ) {
		
		if ( !is_array( $plugin_settings ) ) {
			$plugin_settings = array();
		}
		
		$plugin_settings['e20r_mc'] = array(
			'label'      => __( 'E20R MailChimp', Controller::plugin_slug ),
			'key_prefix' => 'e20r_mc',
			'stub'       => 'e20r_mc'
		);
		
	
		$license_settings = parent::add_new_license_info( $license_settings, $plugin_settings['e20r_mc'] );
		
		return $license_settings;
	}
}