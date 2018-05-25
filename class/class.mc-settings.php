<?php
/**
 * Copyright (c) 2017-2018 - Eighty / 20 Results by Wicked Strong Chicks.
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

use E20R\MailChimp\Membership_Support\Membership_Plugin;
use E20R\Utilities\Cache;
use E20R\Utilities\Utilities;
use E20R\Utilities\Licensing\Licensing;

class MC_Settings {
	
	/**
	 * @var null|MC_Settings
	 */
	private static $instance = null;
	
	/**
	 * Load actions and filters for the MailChimp plugin settings page(s)
	 */
	public function load_actions() {
		
	    if ( is_user_logged_in() ) {
		    add_action( 'wp_ajax_e20rmc_refresh_list_id', array( self::get_instance(), 'options_refresh' ) );
		
		    add_action( "admin_init", array( Member_Handler::get_instance(), "load_plugin" ), 5 );
		    add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		    add_action( 'admin_menu', array( $this, 'admin_add_page' ) );
		    add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
	    }
	}
	
	/**
	 * Return or instantiate the MC_Settings class
	 *
	 * @return MC_Settings|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * Load JS for wp-admin on the PMPro MailChimp settings page
	 *
	 * @param string $hook Name of the page being loaded (stub)
	 *
	 */
	public function load_scripts( $hook ) {
		
		if ( 'settings_page_e20r_mc_settings' != $hook ) {
			return;
		}
		
		wp_register_script( 'e20r-mc-admin', E20R_MAILCHIMP_URL . 'js/e20r-mailchimp-for-membership-plugins-admin.js', array( 'jquery' ), E20R_MAILCHIMP_VERSION );
		
		wp_localize_script(
			'e20r-mc-admin',
			'e20rmc',
			array(
				'admin_url' => esc_url( add_query_arg( 'action', 'e20r_mailchimp_export_csv', admin_url( 'admin-ajax.php' ) ) ),
			)
		);
		
		wp_enqueue_script( 'e20r-mc-admin' );
	}
	
	/**
	 * Add MailChimp Settings page to menu
	 */
	public function admin_add_page() {
		add_options_page(
			__( 'E20R MailChimp', Controller::plugin_slug ),
			__( 'E20R MailChimp', Controller::plugin_slug ),
			'manage_options',
			'e20r_mc_settings',
			array( $this, 'settings_page' )
		);
		
		Licensing::add_options_page();
	}
	
	/**
	 * Register settings when running init for wp-admin
	 */
	public function admin_init() {
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		$prefix = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		
		$membership_option = $mc_api->get_option( 'membership_plugin' );
		
		if ( ! empty( $membership_option ) ) {
			
			$utils->log( "Check to see if current interest group(s) and merge fields exist for {$membership_option}" );
			do_action( 'e20r-mailchimp-init-default-groups' );
		}
		
		//setup settings
		register_setting( 'e20r_mc_settings', 'e20r_mc_settings', array( $this, 'settings_validate' ) );
		
		add_settings_section(
			'e20r_mc_section_general',
			__( 'General Settings', Controller::plugin_slug ),
			array( $this, 'section_general', ),
			'e20r_mc_settings'
		);
		
		add_settings_field(
			'e20r_mc_option_api_key',
			__( 'MailChimp API Key', Controller::plugin_slug ),
			array( $this, 'option_api_key', ),
			'e20r_mc_settings',
			'e20r_mc_section_general'
		);
		
		add_settings_field(
			'e20r_mc_option_mc_api_fetch_list_limit',
			__( "Objects fetched per API call (number)", Controller::plugin_slug ),
			array( $this, 'option_retrieve_lists', ),
			'e20r_mc_settings',
			'e20r_mc_section_general'
		);
		
		$membership_options = array(
			'option_name'        => 'membership_plugin',
			'option_default'     => - 1,
			'option_description' => __( "Select the membership/shopping cart service you're using together with this plugin.", Controller::plugin_slug ),
			'options'            => array(
				array(
					'value' => - 1,
					'label' => __( 'Not selected', Controller::plugin_slug ),
				),
				array(
					'value' => 'pmpro',
					'label' => __( 'Paid Memberships Pro', Controller::plugin_slug ),
				),
				array(
					'value' => 'woocommerce',
					'label' => __( 'WooCommerce', Controller::plugin_slug ),
				),
			),
		);
		
		
		add_settings_field(
			'e20r_mc_option_membership_plugin',
			__( 'Membership Plugin to use', Controller::plugin_slug ),
			array( $this, 'select' ),
			'e20r_mc_settings',
			'e20r_mc_section_general',
			$membership_options
		);
		
		// Only needed/used if the WooCommerce plugin is the configured membership plugin
		if ( 'wc' == apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null ) ) {
		
            $user_selection = array(
                'option_name'        => 'wcuser',
                'option_default'     => E20R_MAILCHIMP_BILLING_USER,
                'option_description' => __( 'Select the user email to add to the list on completion of the checkout.', Controller::plugin_slug ),
                'options'            => array(
                    array(
                        'value' => E20R_MAILCHIMP_NA,
                        'label' => __("Not applicable", Controller::plugin_slug )
                    ),
                    array(
                        'value' => E20R_MAILCHIMP_CURRENT_USER,
                        'label' => __( "Email of currently logged in user", Controller::plugin_slug ),
                    ),
                    array(
                        'value' => E20R_MAILCHIMP_BILLING_USER,
                        'label' => __( "Email of person in billing information", Controller::plugin_slug ),
                    ),
                ),
            );
            
            add_settings_field(
                'e20r_mc_selected_wc_user',
                __( "Email address to use for subscription", Controller::plugin_slug ),
                array( $this, 'select' ),
                'e20r_mc_settings',
                'e20r_mc_section_general',
                $user_selection
            );
		}
  
		add_settings_field(
			'e20r_mc_option_additional_lists',
			__( 'User selectable for checkout/profile (opt-in)', Controller::plugin_slug ),
			array( $this, 'additional_lists' ),
			'e20r_mc_settings',
			'e20r_mc_section_general'
		);
		
		$optin_settings = array(
			'option_name'        => 'double_opt_in',
			'option_default'     => true,
			'option_description' => null,
			'options'            => array(
				array(
					'value' => 0,
					'label' => __( "No", Controller::plugin_slug ),
				),
				array(
					'value' => 1,
					'label' => __( "Yes", Controller::plugin_slug ),
				),
			),
		);
		
		add_settings_field(
			'e20r_mc_option_double_opt_in',
			__( 'Require Double Opt-in?', Controller::plugin_slug ),
			array( $this, 'select' ),
			'e20r_mc_settings',
			'e20r_mc_section_general',
			$optin_settings
		);
		
		$unsub_options = array(
			'option_name'        => 'unsubscribe',
			'option_default'     => 2,
			'option_description' => __( "Recommended: 'Change Membership Level Interest (\"Cancelled\")'. If people subscribe from other sources than this website, you may want to choose one of the other options.", Controller::plugin_slug ),
			'options'            => array(
				array(
					'value' => 0,
					'label' => __( 'Do nothing', Controller::plugin_slug ),
				),
				array(
					'value' => 2,
					'label' => __( 'Change Membership Level Interest ("Cancelled")', Controller::plugin_slug ),
				),
				array(
					'value' => 1,
					'label' => __( 'Clear Interest group settings (old membership levels)', Controller::plugin_slug ),
				),
				array(
					'value' => 'all',
					'label' => __( 'Clear all Interest group settings (all lists)', Controller::plugin_slug ),
				),
			),
		);
		
		add_settings_field(
			'e20r_mc_option_unsubscribe',
			__( 'Unsubscribe on Level Change?', Controller::plugin_slug ),
			array( $this, 'select' ),
			'e20r_mc_settings',
			'e20r_mc_section_general',
			$unsub_options
		);
		
		if ( true === apply_filters( 'e20r-mailchimp-membership-plugin-present', false ) ) {
			
			// List for memberships (segmented by interest groups)
			add_settings_section(
				'e20r_mc_section_levels',
				__( 'Membership List', Controller::plugin_slug ),
				array( $this, 'section_levels' ),
				'e20r_mc_settings'
			);
			
			add_settings_field(
				"e20r_mc_option_memberships_lists",
				__( "Add new members to", Controller::plugin_slug ),
				array( $this, 'option_members_list' ),
				'e20r_mc_settings',
				'e20r_mc_section_levels'
			);
			
		} else {
			
			// List for new users (segmented by interest groups)
			add_settings_section(
				'e20r_mc_section_registration',
				__( 'User Registration', Controller::plugin_slug ),
				array( $this, 'section_user_registration' ),
				'e20r_mc_settings'
			);
			
			add_settings_field(
				"e20r_mc_option_memberships_lists",
				__( "Add new user to", Controller::plugin_slug ),
				array( $this, 'option_members_list' ),
				'e20r_mc_settings',
				'e20r_mc_section_registration'
			);
		}
		
		$is_licensed = Licensing::is_licensed( 'e20r_mc', true );
		
		if ( true === $is_licensed && true ===  $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) ) {
			
			$utils->log("Loading licensed settings");
			
		    do_action( 'e20r-mailchimp-licensed-register-settings', 'e20r_mc_settings', 'e20r_mc_licensed' );
		    
		} else if ( false === $is_licensed || false === $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) ) {
			
			$utils->log( "Won't load GUI settings for interest groups and merge fields" );
			// TODO: Create notice about added value of buying license (added features and support).
			//section_unlicensed_igs
			add_settings_section(
				'e20r_mc_unlicensed_section_igs',
				__( 'Configure Interest Categories', Controller::plugin_slug ),
				array( $this, 'section_unlicensed_igs' ),
				'e20r_mc_settings'
			);
		}
		
		Licensing::register_settings();
	}
	
	/**
	 * Load the settings page for the E20R MailChimp for PMPro Plugin
	 */
	public function settings_page() {
		
		global $e20r_mc_lists;
		
		$utils  = Utilities::get_instance();
		$mc_api = MailChimp_API::get_instance();
		
		$mc_api->set_key();
		
		$unsubscribe = $mc_api->get_option( 'unsubscribe' );
		
		//defaults
		if ( empty( $unsubscribe ) ) {
			$unsubscribe = 2;
			$mc_api->save_option( $unsubscribe, 'unsubscribe' );
		}
		
		if ( empty( $mc_api ) ) {
			
			$msg = __( "Unable to load MailChimp API interface", Controller::plugin_slug );
			$utils->add_message( $msg, 'error', 'backend' );
			$utils->log( $msg );
			
			return;
		}
		
		if ( ! empty( $mc_api ) ) {
			
			$utils->log( "Loading lists from cache or upstream" );
			$e20r_mc_lists = $mc_api->get_all_lists( true );
			$all_lists     = array();
			
			if ( ! empty( $e20r_mc_lists ) ) {
				
				//save all lists in an option
				foreach ( $e20r_mc_lists as $key => $list ) {
					
					$all_lists[ $key ]           = array();
					$all_lists[ $key ]['id']     = $list['id'];
					$all_lists[ $key ]['web_id'] = $list['id'];
					$all_lists[ $key ]['name']   = $list['name'];
				}
				
				/** Save all of our new data */
				update_option( "e20r_mc_lists", $all_lists );
			}
		}
		
		$update_status = intval( get_option( 'e20r_mailchimp_old_updated', false ) );
		$lists         = $mc_api->get_option( 'members_list' );
		$list_id       = null;
		
		if ( ! empty( $lists ) ) {
			$list_id = array_pop( $lists );
		}
		
		$is_licensed = Licensing::is_licensed( 'e20r_mc', true );
		
		$utils->log( "Loading Settings page HTML" ); ?>
        <div class="wrap">
            <?php
		
		if ( false === $is_licensed || false === $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) ) { ?>
            <div class="e20r-mailchimp-recommendation">
                <p><?php _e( "Get free upgrades, simplify your setup and ensure by purchasing the 'Plus' license from our website"); ?></p>
            </div>
            <?php
		} ?>
            <div id="icon-options-general" class="icon32"><br></div>
            <h2><?php _e( 'MailChimp Integration Options and Settings', Controller::plugin_slug ); ?></h2>
			
			<?php $utils->display_messages( 'backend' ); ?>

            <form action="options.php" method="post">
                <h3><?php _e( 'Automatically add users to your MailChimp.com list(s) when they sign up/register to access your site.', Controller::plugin_slug ); ?></h3>
                <p><?php
					printf( __( 'If you have a %s membership plugin installed, you can subscribe your members to a mailchimp MailChimp list and configure interest groups and merge fields based on the membership level', Controller::plugin_slug ),
						sprintf(
							'<a href="https://eighty20results.com/documentation/e20r-mailchimp-membership-plugins/supported-membership-plugins" target="_blank">%s</a>',
							__( 'supported ', Controller::plugin_slug )
						)
					);
					_e( 'or specify "Opt-in Lists" that members can select when they register with your web site.', Controller::plugin_slug );
					printf( '<br/><br/><a href="http://eepurl.com/c1glUn" target="_blank">%s</a>', __( 'Get your Free MailChimp account.', Controller::plugin_slug ) );
					?>
                </p>
				<?php if ( apply_filters( 'e20r-mailchimp-membership-plugin-present', false ) &&  1 !== $update_status ) { ?>
                    <hr/>
                    <div id="e20r_mc_update_members" class="postbox">
                        <div class="inside">
                            <h3><?php _e( "Update Interest Groups and Merge Tags for existing members", Controller::plugin_slug ); ?></h3>
                            <p>
								<?php
								_e( 'This plugin can create and synchronize membership level specific MailChimp List Group and Merge Tag information when a user joins or cancels their membership to your site.', Controller::plugin_slug );
								?>&nbsp;<?php
								_e( 'It will happen automatically when a new member joins, or an existing member changes their membership level.', Controller::plugin_slug );
								?><br/><br/><?php
								printf( __( '%sTo update existing members, you need to run an update of their accounts%s. The background update will be applied to active members who are still subscribed to mailchimp.com mailing list you have configured for the membership level. This means that we will not add active members who do not have "subscribed" as their status on mailchimp.com for the Membership Level specific mailichimp list you configured.', Controller::plugin_slug ), '<strong>', '</strong>' );
								?>&nbsp;<?php
								printf(
									__( '<a href="%s" target="_blank">%s</a>.', Controller::plugin_slug ),
									'https://eighty20results.com/documentation/e20r-mailchimp-membership-plugins/mailchimp-interest-groups-for-existing-members/',
									__( 'Read the documentation: How to update your member\'s MailChimp.com settings automatically', Controller::plugin_slug )
								);
								?><br/><br/>
                            </p>
							<?php wp_nonce_field( 'e20rmc_update_members', 'e20rmc_update_nonce' ); ?>
							<?php if ( true === $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) && true === $is_licensed ) {
							    $utils->log("Loading licensed settings form");
							    do_action('e20r-mailchimp-licensed-add-to-settings-form', $list_id, $update_status );
							    
							} else if ( false === $is_licensed || false === $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) ) {
							    
							    $utils->log("Plus plugin is inactive _or_ this plugin isn't licensed..." ); ?>
								<?php printf( __( '%1$sPurchase or renew your %3$svalid support and update license (buy and install now)%4$s to enable the automated background update for pre-existing active members%2$s', Controller::plugin_slug ), '<strong style="color: red;">', '</strong>', '<a href="https://eighty20results.com/shop/licenses/e20r-mailchimp-membership-plugins" target="_blank">', '</a>' ); ?>
							<?php } ?>
                        </div>
                    </div>
				<?php } ?>
				
				<?php
				$utils->log( "Loading Settings logic" );
				settings_fields( 'e20r_mc_settings' ); ?>
				<?php do_settings_sections( 'e20r_mc_settings' ); ?>

                <p><br/></p>

                <div class="bottom-buttons">
                    <input type="hidden" name="e20r_mc_settings[set]" value="1"/>
                    <input type="submit" name="submit" class="button-primary"
                           value="<?php _e( 'Save Settings', Controller::plugin_slug ); ?>">
                </div>

            </form>
        </div>
		<?php
	}
	
	/**
	 * General section description for options/settings
	 */
	public function section_general() {
		?>
        <p></p>
		<?php
	}

	/**
	 * API Key setting field on options page
	 */
	public function option_api_key() {
		
		$options = get_option( 'e20r_mc_settings' );
		
		if ( isset( $options['api_key'] ) ) {
			$api_key = $options['api_key'];
		} else {
			$api_key = "";
		}
		
		printf( '<input id="e20r_mc_api_key" name="e20r_mc_settings[api_key]" class="e20r-settings-fields" type="text" value="%s" />', esc_attr( $api_key ) );
	}
	
	/**
	 * Number of lists to fetch per operation from Mailchimp API server
	 */
	public function option_retrieve_lists() {
		
		$options    = get_option( 'e20r_mc_settings', false );
		$list_count = isset( $options['mc_api_fetch_list_limit'] ) ? $options['mc_api_fetch_list_limit'] : apply_filters( 'e20r_mailchimp_list_fetch_limit', 15 );
		
		printf( '<input type="number" name="e20r_mc_settings[mc_api_fetch_list_limit]" class="e20r-settings-fields" value="%s">', esc_attr( $list_count ) );
		printf( '<br/><small>%s</small>', sprintf( __( "%sNOTE%s: Try increasing this number if you expect to see lists or groups from the server, but they're missing on this page", Controller::plugin_slug ), '<strong>', '</strong>' ) );
		
		
	}
	
	/**
	 * List of optional/additional lists a user can opt in to
	 */
	public function additional_lists() {
		
		$utils = Utilities::get_instance();
		
		global $e20r_mc_lists;
		
		$options = get_option( 'e20r_mc_settings' );
		
		if ( isset( $options['additional_lists'] ) && is_array( $options['additional_lists'] ) ) {
			$selected_lists = $options['additional_lists'];
		} else {
			$selected_lists = array();
		}
		
		if ( ! empty( $e20r_mc_lists ) ) {
			
			printf( '<select multiple="multiple" name="e20r_mc_settings[additional_lists][]" class="e20r-settings-fields">' );
			
			foreach ( $e20r_mc_lists as $list ) {
				printf( '\t<option value="%1$s" %2$s>%3$s</option>', $list['id'], $utils->selected( $list['id'], $selected_lists ), $list['name'] );
			}
			printf( "</select>" );
		} else {
			_e( "No lists found.", Controller::plugin_slug );
		}
		
	}
	
	/**
	 * Handler for the Membership Levels section on Options page
     *
     * @since v1.2.1 - BUG FIX: Incorrect path name to the WP Plugins directory
	 */
	public function section_levels() {
		
		global $e20r_mailchimp_plugins;
		$mc_api = MailChimp_API::get_instance();
		
		$e20r_mc_levels = Member_Handler::get_instance()->get_levels();
		$plugin_list    = apply_filters( 'e20r-mailchimp-supported-membership-plugin-list', array() );
		$plugin_active  = $mc_api->get_option( 'membership_plugin' );
		
		//do we have Membership plugin installed?
		if ( apply_filters( 'e20r-mailchimp-membership-plugin-present', false ) ) {
			?>
            <p><?php printf( __( "The %s plugin is installed, active and selected.", Controller::plugin_slug ), $plugin_list[ $plugin_active ]['label'] ); ?></p>
			<?php
			//do we have levels?
			if ( empty( $e20r_mc_levels ) ) {
				?>
                <p><?php
					printf( __( 'Once you have %s, you will be able to assign MailChimp lists to them here.', Controller::plugin_slug ),
						sprintf(
							'<a href="%s">%s</a>',
							add_query_arg( 'page', 'pmpro-membershiplevels', admin_url( 'admin.php' ) ),
							__( 'created some levels in Paid Memberships Pro', Controller::plugin_slug )
						)
					); ?>
                </p>
				<?php
			} else {
				?>
                <p><?php _e( "For each level, choose the interest group(s) that a new user should be added to when they register as a member.", Controller::plugin_slug ) ?></p>
				<?php
			}
		} else {
			//just deactivated or needs to be installed?
			if ( file_exists( WP_PLUGIN_DIR . "/paid-memberships-pro/paid-memberships-pro.php" ) ) {
				//just deactivated
				?>
                <p><?php
					printf( '<a href="%s">%s</a>%s',
						add_query_arg( 'plugin_status', 'inactive', admin_url( 'plugins.php' ) ),
						__( 'Activate Paid Memberships Pro', Controller::plugin_slug ),
						__( 'to add membership functionality to your site and finer control over your MailChimp lists.', Controller::plugin_slug )
					);
					?>
                </p>
				
				<?php
			} else {
				//needs to be installed
				?>
                <p><?php
				printf( '<a href="%s">%s</a> %s',
					add_query_arg(
						array(
							'tab'                 => 'search',
							'type'                => 'term',
							's'                   => 'paid+memberships+pro',
							'plugin-search-input' => 'Search+Plugins',
						),
						admin_url( 'plugin-install.php' )
					),
					__( 'Install Paid Memberships Pro', Controller::plugin_slug ),
					__( 'to add membership functionality to your site and finer control over your MailChimp lists.', Controller::plugin_slug )
				); ?>
                </p><?php
			}
		}
	}
	
	/**
	 * Header section in settings for when there's no membership plugin configured for MailChimp integration
	 */
	public function section_user_registration() {
		?>
        <p><?php _e( "No membership plugin has been identified for integration. We'll simply use the standard WordPress user registration as the trigger for adding new users to a MailChimp mailing list.", Controller::plugin_slug ); ?></p>
		<?php
	}
	
	/**
	 * Selects the list to use as the Member's list (on MailChimp.com)
	 *
	 * @param array|null $settings
	 */
	public function option_members_list( $settings = null ) {
		
		$utils = Utilities::get_instance();
		
		global $e20r_mc_lists;
		$options = get_option( 'e20r_mc_settings' );
		
		if ( isset( $options['members_list'] ) && is_array( $options['members_list'] ) ) {
			$selected_lists = $options['members_list'];
		} else {
			$selected_lists = array();
		}
		
		if ( ! empty( $e20r_mc_lists ) ) {
			printf( '<select name="e20r_mc_settings[members_list][%d]" class="e20r-settings-fields">', ( isset( $settings['level_id'] ) ? $settings['level_id'] : null ) );
			
			foreach ( $e20r_mc_lists as $list ) {
				
				printf( '<option value="%s" %s>%s</option>',
					$list['id'],
					$utils->selected( $list['id'], $selected_lists, false ),
					$list['name']
				);
			}
			printf( "</select>" );
		} else {
			_e( "No lists found on MailChimp server", Controller::plugin_slug );
		}
	}
	
	/**
	 * Message for when the plugin doesn't have the plus license
	 */
	public function section_unlicensed_igs() {
		
		$mc_url = sprintf( "https://%s.admin.mailchimp.com/lists/", MailChimp_API::get_mc_dc() ); ?>
        <div id="e20r_mc_update_members" class="postbox">
            <div class="inside">
                <h3><?php _e( "Easy configuration of MailChimp Groups, Interests and Merge Tags", Controller::plugin_slug ); ?></h3>
                <p><?php
					_e( 'To simplify assigning interest groups and merge tag data, we have created a graphical (point and click) section for this options/settings page.', Controller::plugin_slug );
					?><br/><br/><?php
					_e( 'The "Point and Click" configuration feature is available when you have an active Support and Updates license installed.', Controller::plugin_slug );
					?><br/><br/>
                </p>
				<?php printf( __( '%1$sPurchase or renew your %3$sSupport and Updates license%4$s to enable "Point and click" configuration for Merge Tags and Interest Groups!%2$s', Controller::plugin_slug ), '<strong style="color: red;">', '</strong>', '<a href="https://eighty20results.com/shop/licenses/e20r-mailchimp-membership-plugins" target="_blank">', '</a>' ); ?>
            </div>
        </div>

        <p><?php
			printf( __( 'Read the documentation about %show to assign interest groups to subscribers, using a Wordpress filter%s. (Requires programming experience)', Controller::plugin_slug ), '<a href="https://eighty20results.com/documentation/e20r-mailchimp-membership-plugins/interest-groups/" target="_blank">', '</a>' );
			?><br/><br/>
			<?php
			printf( __( 'You manage (add/remove/change) your Interest Group definitions in your account on the %s', Controller::plugin_slug ),
				sprintf( '<a href="%s" target="_blank">%s</a>.<br/>', esc_url_raw( $mc_url ), __( "MailChimp Server", Controller::plugin_slug ) )
			);
			?></p>
        <p><?php
			
			_e( 'To update your groups and interests, select the MailChimp list, then the "Manage Subscribers" menu, and select the "Groups" sub-menu', Controller::plugin_slug ); ?>
        </p>
        <h3><?php _e( 'Configure Merge Tags', Controller::plugin_slug ); ?></h3>
        <p>
			<?php printf( __( 'You can define mailchimp mailing list specific merge tags, and include the member\'s data for those tags, using the %1$se20r-mailchimp-merge-tag-settings%2$s and %1$se20r-mailchimp-user-defined-merge-tag-fields%2$s filter combination.', Controller::plugin_slug ), '<code>', '</code>' ); ?>
            <br/><br/>
			<?php printf( __( 'If any of your merge fields should should include membership specific data for the user, it is likely the corresponding data is stored as something other than User Meta Data (i.e. not stored in the %1$swp_usermeta%2$s database table). In these cases, you will %3$shave%4$s to use the %1$se20r-mailchimp-user-defined-merge-tag-fields%2$s filter to populate the merge fields for the new member.', Controller::plugin_slug ), '<code>', '</code>', '<em>', '</em>' );
			printf( __( "With your Support and Updates license, you may %srequest custom code%s to populate up to 3 custom merge tags for your users.", Controller::plugin_slug ), '<a href="options-general.php?page=e20r_mc_settings#support">', '</a>' );
			?>
        </p>
		<?php
	}
	
	/**
	 * Create a list of user meta fields and return them w/data to indicate if the value(s) are serialized or not
	 *
	 * @return array
	 * @access public
	 */
	public function get_user_meta_keys() {
		
		$utils = Utilities::get_instance();
		
		if ( null === ( $meta_fields = Cache::get( 'e20rmc_meta_fields', 'e20r_mailchimp' ) ) ) {
			
			$utils->log( "Invalid cache for the user meta fields" );
			
			global $wpdb;
			$sql              = "SELECT DISTINCT( meta_key ) AS meta_key FROM {$wpdb->usermeta} ORDER BY meta_key";
			$meta_field_names = $wpdb->get_col( $sql );
			$meta_fields      = array();
			$is_serialized    = false;
			
			foreach ( $meta_field_names as $field_name ) {
				
				$meta_value_sql = $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $field_name );
				$meta_values    = $wpdb->get_col( $meta_value_sql );
				
				foreach ( $meta_values as $meta_value ) {
					$is_serialized = $is_serialized || @unserialize( $meta_value );
				}
				
				$new_field                = new \stdClass();
				$new_field->is_serialized = ( $is_serialized === false ? false : true );
				$new_field->field_name    = $field_name;
				
				$meta_fields[ $field_name ] = $new_field;
			}
			
			if ( ! empty( $meta_fields ) ) {
				Cache::set( 'e20rmc_meta_fields', $meta_fields, 60 * MINUTE_IN_SECONDS, 'e20r_mailchimp' );
			}
		}
		
		return $meta_fields;
	}
	
	/**
	 * Create Select (drop-down) input for options page
	 *
	 * @param array $settings
	 */
	public function select( $settings ) {
		
		$options = get_option( 'e20r_mc_settings' );
		
		printf( '<select name="e20r_mc_settings[%s]" class="e20r-settings-fields">', esc_attr( $settings['option_name'] ) );
		
		foreach ( $settings['options'] as $o_settings ) {
			
			printf( '\t<option value="%s" %s>%s</option>',
				$o_settings['value'],
				selected(
					( ! isset( $options[ $settings['option_name'] ] ) ? $settings['option_default'] : $options[ $settings['option_name'] ] ),
					$o_settings['value'],
					false
				),
				$o_settings['label'] );
		}
		
		printf( '</select>' );
		
		if ( ! empty( $settings['option_description'] ) ) {
			printf( '<br/><small>%s</small>', $settings['option_description'] );
		}
	}
	
	/**
	 * Validate the E20R MailChimp settings
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	public function settings_validate( $input ) {
		
		$mc_api                = MailChimp_API::get_instance();
		$membership_controller = Member_Handler::get_instance();
		$utils                 = Utilities::get_instance();
		
		$new_input = array();
		$defaults  = $mc_api->get_default_options();
		$prefix    = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );
		
		// $newinput = array();
		
		$utils->log( "Received settings: " . print_r( $input, true ) );
		
		foreach ( $input as $key => $value ) {
			
			switch ( $key ) {
				// Process string variables
				case 'api_key':
				case 'level_merge_field':
					$value = isset( $input[ $key ] ) ? trim( preg_replace( "[^a-zA-Z0-9\-]", "", $value ) ) : $defaults[ $key ];
					break;
				
				// Process integer values
				case 'double_opt_in':
				case 'mc_api_fetch_list_limit':
				case 'unsubscribe':
				case 'wcuser':
					$value = $new_input[ $key ] = isset( $input[ $key ] ) ? intval( $input[ $key ] ) : intval( $defaults[ $key ] );
					break;
				
				// Process list entries (additional and the per-level/user specific lists
				case 'members_list':
				case 'additional_lists':
					foreach ( $value as $ml_key => $list_id ) {
						$value[ $ml_key ] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $list_id ) );
					};
					
					$value = array_unique( $value );
					break;
					
                default:
                    $value = apply_filters( 'e20r-mailchimp-membership-plugin-save-default-settings-handler', $value, $key, $prefix, $defaults  );
			}
			
			// Assign processed value to $new_input array.
			$new_input[ $key ] = $value;
		}
		
		// Grab any (available) membership levels
		$levels = $membership_controller->get_levels();
		
		// Load user meta keys from DB/Cache
		$meta_fields = $this->get_user_meta_keys();
		
		if ( ! empty( $levels ) ) {
			
			$utils->log( "Iterate through levels list" );
			
			// Process all membership levels (configure membership level specific lists, interests and merge fields)
			foreach ( $levels as $level ) {
				
				if ( ! isset( $new_input["level_{$prefix}_{$level->id}_lists"] ) || ! is_array( $new_input["level_{$prefix}_{$level->id}_lists"] ) ) {
					$new_input["level_{$prefix}_{$level->id}_lists"] = $new_input['members_list'];
				}
				
				// Instantiate for new level interest settings
				if ( ! isset( $new_input["level_{$prefix}_{$level->id}_interests"] ) ) {
					$new_input["level_{$prefix}_{$level->id}_interests"] = array();
				}
				
				if ( ! isset( $new_input["level_{$prefix}_{$level->id}_merge_fields"] ) ) {
					$new_input["level_{$prefix}_{$level->id}_merge_fields"] = array();
				}
				
				// Process all lists defined for the membership level
				foreach ( $new_input["level_{$prefix}_{$level->id}_lists"] as $list_id ) {
					
					// The the list specific options for the current level being processes
					$list_options = $mc_api->get_list_conf_by_id( $list_id );
					$utils->log( "Loaded list options for level {$level->id}/{$list_id}" );
					
					// Instantiate for new lists
					if ( ! isset( $new_input["level_{$prefix}_{$level->id}_interests"][ $list_id ] ) ) {
						$new_input["level_{$prefix}_{$level->id}_interests"][ $list_id ] = array();
					}
					
					if ( ! isset( $new_input["level_{$prefix}_{$level->id}_merge_fields"][ $list_id ] ) ) {
						$new_input["level_{$prefix}_{$level->id}_merge_fields"][ $list_id ] = array();
					}
					
					// Skip if not configured
					if ( ! isset( $input["level_{$prefix}_{$level->id}_interests"][ $list_id ] ) ) {
						continue;
					}
					
					// Process configured interest categories
					foreach ( $input["level_{$prefix}_{$level->id}_interests"][ $list_id ] as $category_id => $category ) {
						
						// Instantiate for new categories
						if ( ! isset( $new_input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ] ) ) {
							$new_input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ] = array();
						}
						
						// Configure interests
						if ( ! isset( $category->interests ) ) {
							continue;
						}
						
						foreach ( $category->interests as $interest_id => $interest_label ) {
							
							$new_input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ][ $interest_id ] = $input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ][ $interest_id ];
						}
						/*
						if ( isset( $input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ] ) && ! empty( $input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ] ) ) {
							
							foreach (  as $configured_interest_id => $value ) {
								$new_input["level_{$prefix}_{$level->id}_interests"][ $list_id ][ $category_id ][ $configured_interest_id ] = $value;
							}
					    }
						*/
					}
					
					$upstream_mf      = array_keys( $list_options->merge_fields );
					$filter_mf        = $this->get_filter_mf_tags();
					$merge_field_tags = array_unique( array_merge( $upstream_mf, $filter_mf ) );
					$new_merge_fields = array();
					
					// Process upstream and filtered merge fields
					foreach ( $merge_field_tags as $tag ) {
						
						// Default value
						$input_value = - 1;
						
						if ( isset ( $input["level_{$prefix}_{$level->id}_merge_fields"][ $list_id ][ $tag ] ) ) {
							
							$utils->log( "Processing merge field: {$tag}" );
							$input_value = $input["level_{$prefix}_{$level->id}_merge_fields"][ $list_id ][ $tag ];
							
							if ( - 1 !== $input_value && isset( $meta_fields[ $tag ] ) ) {
								$input_value = $meta_fields[ $tag ];
							}
							
							$new_merge_fields[ $tag ] = $input_value;
						}
					} // End of merge_field processing
					
					// Set the merge field configuration for the list/level
					$new_input["level_{$prefix}_{$level->id}_merge_fields"][ $list_id ] = $new_merge_fields;
					
				} // End of foreach for level list info loop
			} // End of foreach for membership level loop
		} // End of if empty
		
		$utils->log( "Saving settings: " . print_r( $new_input, true ) );
		
		return $new_input;
	}
	
	/**
	 * Return the tag names from the e20r-mailchimp-merge-tag-settings filter
	 *
	 * @uses e20r-mailchimp-merge-tag-settings
	 *
	 * @return array
	 */
	private function get_filter_mf_tags() {
		
		$filtered = apply_filters( 'e20r-mailchimp-merge-tag-settings', array(), null );
		$ret_val  = array();
		
		foreach ( $filtered as $field_defs ) {
			$ret_val[] = $field_defs['tag'];
		}
		
		return $ret_val;
	}
	
	/**
	 * Handler for the "Server refresh" buttons on the options page
	 */
	public function options_refresh() {
		
		$utils = Utilities::get_instance();
		
		global $pmpro_msg;
		global $pmpro_msgt;
		
		$list_id = $utils->get_variable( 'e20rmc_refresh_list_id', null );
		$utils->log( "Processing for list {$list_id}" );
		
		if ( empty( $list_id ) ) {
			
			$pmpro_msg  = __( "Unable to refresh unknown list", Controller::plugin_slug );
			$pmpro_msgt = "error";
			$utils->log( $pmpro_msg );
			wp_send_json_error( $pmpro_msg );
			wp_die();
		}
		
		wp_verify_nonce( 'e20rmc', "e20rmc_refresh_{$list_id}" );
		$utils->log( "Nonce is verified" );
		
		$mc_api   = MailChimp_API::get_instance();
		$mg_class = Merge_Fields::get_instance();
		$ig_class = Interest_Groups::get_instance();
		$utils    = Utilities::get_instance();
		$level    = null;
		
		$level_id = $utils->get_variable( 'e20rmc_refresh_list_level', null );
		
		$utils->log( "Loading data for {$level_id}" );
		
		if ( empty( $mc_api ) ) {
			
			$error_msg  = __( "Unable to load MailChimp API interface", Controller::plugin_slug );
			$error_msgt = "error";
			$utils->log( $error_msg );
			wp_send_json_error( $error_msg );
			wp_die();
		}
		
		/*
		if ( function_exists( 'pmpro_getLevel' ) ) {
			$level = pmpro_getLevel( $level_id );
		}
		*/
		
		$level = apply_filters( 'e20r-mailchimp-get-membership-level-definition', $level, $level_id );
		
		// $ig_class->get_from_remote( $list_id, true );
		$utils->log( "Creating interest group the specified membership level (ID: {$level_id} )?" );
		$ig_class->create_categories_for_membership( $level_id );
		
        $utils->log("Attempting to load custom groups/tags/etc");
		
		// Try updating the Merge Fields & Interest Groups on the MailChimp Server.
		if ( false === $mc_api->update_server_settings( $list_id ) ) {
		    
		    $msg = __("Unable to update Groups and Merge Tags on the MailChimp.com server!", Controller::plugin_slug );
		    $utils->add_message( $msg, 'error', 'backend' );
		    $utils->log("Error: {$msg}");
		    
		    wp_send_json_error( $msg );
		    wp_die();
		}
		
		// Force update of upstream interest groups
		if ( ! is_null( $list_id ) && false === ( $ig_sync_status = $mc_api->get_cache( $list_id, 'interest_groups', true ) ) ) {
			
			$msg = sprintf( __( "Unable to refresh MailChimp Interest Group information for %s", Controller::plugin_slug ), $level->name );
			$utils->add_message( $msg, 'error', 'backend' );
			
			$utils->log( "Error: Unable to update interest group information" );
			wp_send_json_error( $msg );
			wp_die();
		}
		
		// Force refresh of upstream merge fields
		if ( ! is_null( $list_id ) && false === ( $mg_sync_status = $mc_api->get_cache( $list_id, 'merge_fields', true ) ) ) {
			
			$msg = sprintf( __( "Unable to refresh MailChimp Merge Field information for %s", Controller::plugin_slug ), $level->name );
			$utils->add_message( $msg, 'error', 'backend' );
			
			$utils->log( "Error: Unable to update merge field information for list {$list_id} from API server" );
			wp_send_json_error( $msg );
			wp_die();
		}
		
		// $mg_class->get_from_remote( $list_id, true );
		
		$utils->clear_buffers();
		wp_send_json_success();
		wp_die();
		
	}
}