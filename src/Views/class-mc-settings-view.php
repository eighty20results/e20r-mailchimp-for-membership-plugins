<?php
/*
 * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
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

namespace E20R\MailChimp\Views;


use E20R\MailChimp\Controller;
use E20R\MailChimp\Server\MailChimp_API;
use E20R\MailChimp\Admin\MC_Settings;
use E20R\MailChimp\Handlers\Member_Handler;
use E20R\Utilities\Licensing\Licensing;
use E20R\Utilities\Utilities;

class MC_Settings_View {

    /**
     * @var null|MC_Settings_View $instance - The current class instance
     */
    private static $instance = null;

    /**
     * Get or instantiate and get the class
     *
     * @return MC_Settings_View|null
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load the settings page for the E20R MailChimp for PMPro Plugin
     */
    public function settings_page() {

        global $e20r_mc_lists;
        global $e20r_mailchimp_plugins;

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
            $e20r_mc_lists = $mc_api->get_all_lists();
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

        $utils->log( "Current value for update status is: {$update_status}" );

        if ( ! empty( $lists ) ) {
            $list_id = array_pop( $lists );
        }

        $is_licensed   = Licensing::is_licensed( 'E20R_MC', false );
        $plugin_loaded = $utils->plugin_is_active( 'e20r-mailchimp-plus/class.e20r-mailchimp-plus.php' ) ||
                         $utils->plugin_is_active( 'e20r-mailchimp-plus-debug/class.e20r-mailchimp-plus.php' );
        $active_plugin = $mc_api->get_option( 'membership_plugin' );

        $utils->log( "Loading Settings page HTML. We have an active license? " . ( $is_licensed ? 'Yes' : 'No' ) );
        $utils->log( "The plus plugin is active? " . ( $plugin_loaded ? 'Yes' : 'No' ) );
        ?>
        <div class="wrap">
            <?php

            if ( false === $is_licensed || false === $plugin_loaded ) { ?>
                <div class="e20r-mailchimp-recommendation">
                    <p><?php _e( "Get free upgrades, simplify your setup and ensure by purchasing the 'Plus' license from our website" ); ?></p>
                </div>
                <?php
                do_action( 'e20r-load-license-info' );
            } ?>
            <div id="icon-options-general" class="icon32"><br></div>
            <h2><?php _e( 'MailChimp Integration Options and Settings', Controller::plugin_slug ); ?></h2>

            <?php $utils->display_messages( 'backend' ); ?>

            <form action="options.php" method="post">
                <h3>
                    <span
                        class="e20r-mailchimp-settings"><?php _e( 'Automatically add users to your MailChimp.com list(s) when they sign up/register to access your site.', Controller::plugin_slug ); ?>
                        <span class="e20r-mailchimp-buttom"><input type="button" id="e20r-mc-reset-cache"
                                                                   value="<?php _e( 'Clear local Cache', Controller::plugin_slug ); ?>"
                                                                   class="button button-primary e20r-reset-button"></span>
                </h3>
                <p><?php
                    printf( __( 'If you have a %s membership plugin installed, you can subscribe your members to a mailchimp MailChimp list and configure interest groups and merge fields based on the membership level ', Controller::plugin_slug ),
                        sprintf(
                            '<a href="https://eighty20results.com/documentation/e20r-mailchimp-membership-plugins/supported-membership-plugins" target="_blank">%s</a>',
                            __( 'supported ', Controller::plugin_slug )
                        )
                    );
                    _e( 'or specify "Opt-in Lists" that members can select when they register with your web site.', Controller::plugin_slug );
                    printf( '<br/><br/><a href="http://eepurl.com/c1glUn" target="_blank">%s</a>', __( 'Get your Free MailChimp account.', Controller::plugin_slug ) );
                    ?>
                </p>
                <?php $plugin_active = apply_filters( 'e20r-mailchimp-membership-plugin-present', false );

                if ( true === $plugin_active && MC_Settings::E20RMC_UPDATE_DONE !== $update_status ) { ?>
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
                            <?php if ( true === $plugin_loaded && true === $is_licensed ) {
                                $utils->log( "Loading licensed settings form" );
                                do_action( 'e20r-mailchimp-licensed-add-to-settings-form', $list_id, $update_status );

                            } else if ( false === $is_licensed || false === $plugin_loaded ) {

                                $utils->log( "Plus plugin is inactive _or_ this plugin isn't licensed..." ); ?>
                                <?php printf( __( '%1$sBuy your %3$sE20R MailChimp Plus (Support and Updates)%4$s module and license to enable the automated background update for pre-existing active members%2$s', Controller::plugin_slug ), '<strong style="color: red;">', '</strong>', '<a href="https://eighty20results.com/shop/licenses/e20r-mailchimp-membership-plugins" target="_blank">', '</a>' ); ?>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else if ( true === $plugin_active && MC_Settings::E20RMC_UPDATE_DONE === $update_status ) {
                    do_action( 'e20r-mailchimp-licensed-add-to-settings-form', $list_id, $update_status );
                }

                $utils->log( "Loading Settings logic" );

                $default_settings       = new \stdClass();
                $default_settings->name = __( "General Settings", Controller::plugin_slug );
                $default_settings->id   = 0;
                $option_tabs            = array();
                $option_tabs[]          = $default_settings;

                $active_tab = $utils->get_variable( 'e20rmc_tab', 'tab_level_0' );
                $levels     = apply_filters( 'e20r-mailchimp-all-membership-levels', array() );

                if ( ! empty( $levels ) && true === $is_licensed && true === $plugin_loaded ) {

                    $utils->log( "Have " . count( $levels ) . " levels for {$active_plugin}" );

                    $option_tabs = array_merge( $option_tabs, $levels ); ?>

                    <h2 class="nav-tab-wrapper">
                        <?php
                        foreach ( $option_tabs as $level_id => $level_info ) {

                            if ( empty( $level_info->name ) ) {
                                $option_label = sprintf( __( '%s: Empty', Controller::plugin_slug ), $e20r_mailchimp_plugins[ $active_plugin ]['label'] );
                            } else {


                                $option_label = (
                                    __( 'General Settings', Controller::plugin_slug ) !== $level_info->name ) ?
                                    sprintf(
                                        __( '%1$s %2$s', Controller::plugin_slug ),
                                        $e20r_mailchimp_plugins[ $active_plugin ]['label'],
                                        wp_unslash( $level_info->name )
                                    ) : wp_unslash( $level_info->name );
                            }

                            $url = add_query_arg( array(
                                'page'       => 'e20r_mc_settings',
                                'e20rmc_tab' => "tab_level_{$level_info->id}",
                            ),
                                admin_url( 'options-general.php' )
                            );

                            printf( '<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
                                $url,
                                ( "tab_level_{$level_info->id}" === $active_tab ? 'nav-tab-active' : null ),
                                $option_label
                            );
                        } ?>
                    </h2> <?php

                    $utils->log( "Loading licensed settings tabs" );

                    foreach ( $option_tabs as $level_id => $level_info ) {

                        if ( "tab_level_{$level_info->id}" === $active_tab ) {

                            if ( 0 === $level_info->id ) {
                                do_settings_sections( 'e20r_mc_settings' );
                                settings_fields( 'e20r_mc_settings' );
                            } else {
                                do_settings_sections( "e20r_mc_settings_level_{$level_info->id}" );
                                settings_fields( 'e20r_mc_settings' );
                            }


                        }
                    }
                } else {
                    do_settings_sections( 'e20r_mc_settings' );
                    settings_fields( 'e20r_mc_settings' );
                } ?>
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
    public function section_general() { ?>
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
     * Message about benefits of E20R MailChimp Plus License
     */
    public function section_license() {

        $purchase_url = 'https://eighty20results.com/product/e20r-mailchimp-membership-plugins/'; ?>
        <div id="e20r-mailchimp-plus-license" class="postbox">
            <div class="inside">
                <h3><?php _e( "For non-programmers", Controller::plugin_slug ); ?></h3>
                <p><?php
                    _e( "Want to configure all of the plugin settings with a Graphical User Interface? ", Controller::plugin_slug );
                    ?><br/><br/>
                    <?php _e( 'Then, with a single click you can assign all interests and merge field info for your entire member database, in the background and without impacting your site\'s responsiveness.', Controller::plugin_slug ); ?>
                    <br/><br/><?php
                    _e( 'Our "Point and Click" configuration feature is available when you have an active E20R MailChimp Plus (Support and Updates) license installed.', Controller::plugin_slug );
                    _e( 'No need to spend money on a programmer or wait for support to help you. Simply...', Controller::plugin_slug ); ?>
                <ol>
                    <li><?php printf( __( '%1$sInstall the license%2$s', Controller::plugin_slug ),
                            sprintf(
                                '<a href="%1$s" target="_blank">',
                                add_query_arg( 'page', 'e20r-licensing', admin_url( 'general-options.php' ) )
                            ),
                            '</a>'
                        ); ?></li>
                    <li><?php _e( 'Click the "Sync with MailChimp.com" button (a couple of times)', Controller::plugin_slug ); ?></li>
                    <li><?php _e( 'Reload the page', Controller::plugin_slug ); ?></li>
                    <li><?php _e( "Select the Interests to assign when a user signs up for a level", Controller::plugin_slug ); ?></li>
                    <li><?php _e( "Select user data fields to assign to your merge tags (including Register Helper info)", Controller::plugin_slug ); ?></li>
                </ol>
                <?php _e( 'You can also any extra Interests and Groups via your account on mailchimp.com and click the "Clear local Cache" and reload', Controller::plugin_slug ); ?>
                </p>
                <?php printf( __( '%1$sBuy your %3$sE20R MailChimp Plus (Support and Updates) module and license%4$s. Then use "Point and click" configuration for Merge Tags and Interest Groups!%2$s', Controller::plugin_slug ), '<strong style="color: red;">', '</strong>', sprintf( '<a href="%1$s" target="_blank">', $purchase_url ), '</a>' ); ?>
            </div>
        </div>

        <?php
    }

    /**
     * Message for when the plugin doesn't have the plus license
     */
    public function section_unlicensed_igs() {

        $mc_url         = sprintf( "https://%s.admin.mailchimp.com/lists/", MailChimp_API::get_mc_dc() );
        $purchase_url   = 'https://eighty20results.com/product/e20r-mailchimp-membership-plugins/';
        $activation_url = add_query_arg( 'page', 'e20r-licensing', admin_url( 'general-options.php' ) ); ?>
        <div id="e20r_mc_update_members" class="postbox">
            <div class="inside">
                <h3><?php _e( "For non-programmers", Controller::plugin_slug ); ?></h3>
                <p><?php
                    _e( "Prefer a graphical user interface (GUI) to configure all of the plugin settings? ", Controller::plugin_slug );
                    ?><br/><br/>
                    <?php printf( __( 'How about, with a single click, %1$simport and assign all users with configured interests and merge field info for your entire member database%2$s to MailChimp.com, in the background and %1$swithout impacting your site\'s responsiveness%2$s?', Controller::plugin_slug ), '<strong>', '</strong>' ); ?>
                    <br/><br/>
                    <?php printf( __( 'The "E20R MailChimp Plus (Support and Updates)" %1$slicense includes 1 year of unlimited free support, bug fixes and feature updates!%2$s', Controller::plugin_slug ), '<strong>', '</strong>' ); ?>
                    <br/><br/><?php
                    _e( 'Our "Point and Click" configuration feature is added when you install and activate the E20R MailChimp Plus (Support and Updates) module, then activate its included license.', Controller::plugin_slug ); ?>
                    <br/>
                    <?php _e( 'No need to spend money on a programmer or wait for support to help you. Simply...', Controller::plugin_slug ); ?>
                <ol>
                    <li><?php printf( __( '%1$sBuy%3$s and %2$sInstall%3$s the E20R MailChimp Plus (Support and Updates) license', Controller::plugin_slug ),
                            sprintf(
                                '<a href="%1$s" target="_blank">',
                                $purchase_url
                            ),
                            sprintf(
                                '<a href="%1$s" target="_blank">',
                                $activation_url
                            ),
                            '</a>'
                        ); ?></li>
                    <li><?php _e( 'Click the "Clear local Cache" button', Controller::plugin_slug ); ?></li>
                    <li><?php _e( 'Click the "Sync with MailChimp.com" button (a couple of times)', Controller::plugin_slug ); ?></li>
                    <li><?php _e( 'Reload the page', Controller::plugin_slug ); ?></li>
                    <li><?php _e( "Select the Interests to assign when a user signs up for a level", Controller::plugin_slug ); ?></li>
                    <li><?php _e( "Select user data fields to assign to your Merge Fields/Merge Tags (including PMPro Register Helper field data)", Controller::plugin_slug ); ?></li>
                </ol>
                <p>
                    <?php _e( 'You can also create extra Interests and Groups via your account on mailchimp.com and click the "Clear local Cache" and reload to let you assign those custom interests', Controller::plugin_slug ); ?>
                </p>
                <?php printf(
                    __( '%1$sBuy your %3$sE20R MailChimp Plus (Support and Updates) license%4$s. Then %5$sactivate the license%6$s to enable "Point and click" configuration for Merge Tags and Interest Groups!%2$s',
                        Controller::plugin_slug
                    ),
                    '<strong style="color: red;">',
                    '</strong>',
                    sprintf( '<a href="%1$s" target="_blank">', $purchase_url ),
                    '</a>',
                    sprintf( '<a href="%1$s" target="_blank">', $activation_url ),
                    '</a>'
                ); ?>
                <br/><br/>
                <?php printf( __( '%1$sDid we remember to mention the full year of unlimited support, bug fixes and updates included with the E20R MailChimp Plus (Support and Updates) module..?%2$s', Controller::plugin_slug ), '<small>', '</small>' ); ?>
            </div>
        </div>

        <p><?php
            printf( __( 'Read the documentation about %show to assign interest groups to subscribers, using a Wordpress filter%s. (Requires advanced PHP programming experience)', Controller::plugin_slug ), '<a href="https://eighty20results.com/documentation/e20r-mailchimp-membership-plugins/interest-groups/" target="_blank">', '</a>' );
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
}
