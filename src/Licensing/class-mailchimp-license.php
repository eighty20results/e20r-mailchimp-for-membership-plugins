<?php
/**
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
 */

namespace E20R\MailChimp\Licensing;


use E20R\MailChimp\Controller;
use E20R\Utilities\Utilities;
use E20R\Utilities\Licensing\Licensing;
use E20R\Utilities\Licensing\LicenseClient;

class Mailchimp_License extends LicenseClient {

	/**
	 * @var null|Mailchimp_License
	 */
	private static $instance = null;

	/**
	 * License server connections, etc
	 *
	 * @var Licensing|null $licensing
	 */
	private $licensing = null;

	private $is_expiring = false;

	private $utils = null;
	/**
	 * Mailchimp_License constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->licensing   = new Licensing( 'E20R_MC' );
		$this->is_expiring = $this->licensing->is_expiring( 'E20R_MC' );
		$this->utils 	   = Utilities::get_instance();
	}

	/**
	 * Load a custom license warning on init
	 */
	public function check_licenses() {

		switch ( $this->is_expiring ) {

			case true:
				$this->utils->add_message(
				    sprintf(
                    __(
				        'The license for %s will renew soon. As this is an automatic payment, you will not have to do anything. To change %syour license%s, please go to %syour account page%s',
                        Controller::plugin_slug
                    ),
                    __( 'E20R MailChimp Plus License (with Support &amp; Updates)', Controller::plugin_slug ),
                    '<a href="https://eighty20results.com/shop/licenses/" target="_blank">',
                    '</a>',
                    '<a href="https://eighty20results.com/account/" target="_blank">',
                    '</a>'
                ),
                    'info',
                    'backend' );
				break;
			case - 1:
				$this->utils->add_message(
				    sprintf(
				        __(
				            'Your %s license has expired. To continue to get updates and support for this plugin, you will need to %srenew and install your license%s.',
                           Controller::plugin_slug
                        ),
                __( 'Support and Updates Plus License', Controller::plugin_slug ),
                '<a href="https://eighty20results.com/shop/licenses/" target="_blank">',
                        '</a>'
                    ),
                    'error',
                    'backend'
                );
				break;
		}
	}

	/**
	 * Load action hooks & filters for Client License handler
	 */
	public function load_hooks() {
        $this->utils->log("Loading license hooks?");
		if ( $this->utils::is_admin() ) {
            $this->utils->log("Loading license being loaded...");
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

	    $this->utils->log("Adding new license settings for E20R_MC");

		if ( !is_array( $plugin_settings ) ) {
		    $this->utils->log("No plugin settings found...");
			$plugin_settings = array();
		}

		if ( empty( $license_settings ) ) {
		    $license_settings = parent::add_new_license_info( $license_settings, $plugin_settings );
        }

		$this->utils->log("Plugin settings are: " . print_r( $plugin_settings, true ) );

		$plugin_settings['E20R_MC'] = array(
			'label'      => __( 'E20R MailChimp Plus', Controller::plugin_slug ),
			'key_prefix' => 'E20R_MC',
			'product_sku' => 'E20R_MC',
			'stub'       => 'E20R_MC'
		);

		$license_settings = parent::add_new_license_info( $license_settings, $plugin_settings['E20R_MC'] );

		return $license_settings;
	}
}
