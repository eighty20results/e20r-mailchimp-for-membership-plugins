<?php
/**
 * Copyright (c) 2020. - Eighty / 20 Results by Wicked Strong Chicks.
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

namespace E20R_Email_Memberships;


use E20R\MailChimp\Controller;
use E20R\MailChimp\MailChimp_API;
use E20R\MailChimp\MC_Settings;
use E20R\Utilities\Licensing\License_Settings;
use E20R\Utilities\Licensing\Licensing;
use E20R\Utilities\Utilities;

class Admin_Setup {

    /**
     * @var null|Admin_Setup $instance - The instance of the Admin_Setup class
     */
    private static $instance = null;

    /**
     * Get or instantiate and get the class
     *
     * @return Admin_Setup|null
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load WordPress action hook handlers for admin, etc
     */
    public function load_hooks() {

        add_action( 'admin_menu', array( $this, 'admin_add_page' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_bar_menu', array( $this, 'pmpro_adminbar' ), 1001 );

        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        add_filter(
            sprintf( "plugin_action_links_%s", Controller::$plugin ),
            array( $this, 'add_action_links' ),
            10,
            1
        );
    }

    /**
     * Register settings when running init for wp-admin
     */
    public function admin_init() {

        $utils            = Utilities::get_instance();
        $mc_api           = MailChimp_API::get_instance();
        $mc_settings      = MC_Settings::get_instance();
        $mc_settings_view = MC_Settings_View::get_instance();

        $prefix = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );

        $membership_option = $mc_api->get_option( 'membership_plugin' );

        if ( ! empty( $membership_option ) ) {

            $utils->log( "Check to see if current interest group(s) and merge fields exist for {$membership_option}" );
            do_action( 'e20r-mailchimp-init-default-groups' );
        }

        //setup settings
        register_setting(
            'e20r_mc_settings',
            'e20r_mc_settings',
            array( $mc_settings, 'settings_validate' )
        );

        add_settings_section(
            'e20r_mc_section_general',
            __( 'General Settings', Controller::plugin_slug ),
            array( $mc_settings_view, 'section_general', ),
            'e20r_mc_settings'
        );

        add_settings_field(
            'e20r_mc_option_api_key',
            __( 'MailChimp API Key', Controller::plugin_slug ),
            array( $mc_settings_view, 'option_api_key', ),
            'e20r_mc_settings',
            'e20r_mc_section_general'
        );

        add_settings_field(
            'e20r_mc_option_mc_api_fetch_list_limit',
            __( "Objects fetched per API call (number)", Controller::plugin_slug ),
            array( $mc_settings_view, 'option_retrieve_lists', ),
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
            array( $mc_settings_view, 'select' ),
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
                        'label' => __( "Not applicable", Controller::plugin_slug ),
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
                array( $mc_settings_view, 'select' ),
                'e20r_mc_settings',
                'e20r_mc_section_general',
                $user_selection
            );
        }

        add_settings_field(
            'e20r_mc_option_additional_lists',
            __( 'User selectable for checkout/profile (opt-in)', Controller::plugin_slug ),
            array( $mc_settings_view, 'additional_lists' ),
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
            array( $mc_settings_view, 'select' ),
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
            array( $mc_settings_view, 'select' ),
            'e20r_mc_settings',
            'e20r_mc_section_general',
            $unsub_options
        );

        if ( true === apply_filters( 'e20r-mailchimp-membership-plugin-present', false ) ) {

            // List for memberships (segmented by interest groups)
            add_settings_section(
                'e20r_mc_section_levels',
                __( 'Membership List', Controller::plugin_slug ),
                array( $mc_settings_view, 'section_levels' ),
                'e20r_mc_settings'
            );

            add_settings_field(
                "e20r_mc_option_memberships_lists",
                __( "Add new members to", Controller::plugin_slug ),
                array( $mc_settings_view, 'option_members_list' ),
                'e20r_mc_settings',
                'e20r_mc_section_levels'
            );

        } else {

            // List for new users (segmented by interest groups)
            add_settings_section(
                'e20r_mc_section_registration',
                __( 'User Registration', Controller::plugin_slug ),
                array( $mc_settings_view, 'section_user_registration' ),
                'e20r_mc_settings'
            );

            add_settings_field(
                "e20r_mc_option_memberships_lists",
                __( "Add new user to", Controller::plugin_slug ),
                array( $mc_settings_view, 'option_members_list' ),
                'e20r_mc_settings',
                'e20r_mc_section_registration'
            );
        }

        $is_licensed   = Licensing::is_licensed( 'E20R_MC', false );
        $plugin_loaded = $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) ||
                         $utils->plugin_is_active( 'e20r-mailchimp-plus-debug/class.e20r-mailchimp-plus.php' );

        $utils->log( "The product is licensed: " . ( $is_licensed ? 'Yes' : 'No' ) );
        $utils->log( "The Plus plugin is active: " . ( $plugin_loaded ? 'Yes' : 'No' ) );

        if ( true === $is_licensed && true === $plugin_loaded ) {

            $utils->log( "Loading licensed settings" );

            do_action( 'e20r-mailchimp-licensed-register-settings', 'e20r_mc_settings', 'e20r_mc_licensed' );

        } else if ( false === $is_licensed || false === $plugin_loaded ) {

            $utils->log( "Won't load GUI settings for interest groups and merge fields" );
            /*
            add_settings_section(
                'e20r_mc_unlicensed_section',
                __( 'E20R MailChimp PLUS License (with support and updates)', Controller::plugin_slug ),
                array( $this, 'section_license' ),
                'e20r_mc_settings'
            );
            */
            //section_unlicensed_igs
            add_settings_section(
                'e20r_mc_unlicensed_section_igs',
                __( 'Configure Interest Categories', Controller::plugin_slug ),
                array( $mc_settings_view, 'section_unlicensed_igs' ),
                'e20r_mc_settings'
            );
        }

        License_Settings::register_settings();
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
            array( MC_Settings_View::get_instance(), 'settings_page' )
        );

        if ( function_exists( 'pmpro_getPMProCaps' ) ) {
            add_submenu_page(
                'pmpro-dashboard',
                __( 'E20R MailChimp', Controller::plugin_slug ),
                __( 'E20R MailChimp', Controller::plugin_slug ),
                'manage_options',
                'e20r_mc_settings',
                array( MC_Settings_View::get_instance(), 'settings_page' )
            );
        }
        License_Settings::add_options_page();
    }

    /**
     * Add our settings the PMPro Memberships menu in the toolbar of /wp-admin/
     */
    public function pmpro_adminbar() {

        // Don't do this if there's no admin bar
        if ( ! is_admin_bar_showing() ) {
            return;
        }

        global $wp_admin_bar;

        // Add menu item to PMPro Memberships menu.
        if ( current_user_can( 'pmpro_dashboard' ) ) {

            $wp_admin_bar->add_menu(
                array(
                    'id'     => 'e20r-mailchimp',
                    'parent' => 'paid-memberships-pro',
                    'title'  => __( 'E20R MailChimp', Controller::plugin_slug ),
                    'href'   => get_admin_url( null, '/admin.php?page=e20r_mc_settings' ),
                )
            );
        }
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
}
