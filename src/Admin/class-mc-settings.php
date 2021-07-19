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

namespace E20R\MailChimp\Admin;

use E20R\MailChimp\Controller;
use E20R\MailChimp\Server\Interest_Groups;
use E20R\Utilities\Cache;
use E20R\Utilities\Utilities;
use E20R\MailChimp\Handlers\Member_Handler;
use E20R\MailChimp\Server\MailChimp_API;


class MC_Settings {

    const E20RMC_UPDATE_DONE = 1;
    const E20RMC_UPDATE_IN_PROGRESS = 2;
    const E20RMC_UPDATE_COMPLETE = 3;

    /**
     * @var null|MC_Settings
     */
    private static $instance = null;

    /**
     * Return or instantiate the MC_Settings class
     *
     * @return MC_Settings|null
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load actions and filters for the MailChimp plugin settings page(s)
     */
    public function load_actions() {

        if ( is_user_logged_in() ) {
            add_action( 'wp_ajax_e20rmc_refresh_list_id', array( $this, 'options_refresh' ) );
            add_action( 'wp_ajax_e20rmc_clear_cache', array( $this, 'clear_cache' ) );

            add_action( "admin_init", array( Member_Handler::get_instance(), "load_plugin" ), 5 );

            add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        }
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

        $utils->log( "Received settings: " . print_r( $input, true ) );

        $new_input = array();
        $current   = $mc_api->get_option(); // Load all existing options
        $defaults  = $mc_api->get_default_options();
        $to_save   = wp_parse_args( $input, $current );

        /**
         * Purpose: Get a list of all required option keys (from this and add-on plugins)
         *
         * @filter e20r-mailchimp-required-option-keys
         *
         * @param string[] $defaults - The option keys from all local and 3rd party options/settings
         */
        $option_keys = apply_filters( 'e20r-mailchimp-required-option-keys', array_keys( $defaults ) );

        /**
         * Purpose: Add any default values that have been removed by deselecting
         * the setting/options (for checkboxes, for instance)
         *
         * @filter e20r-mailchimp-populate-saved-defaults
         *
         * @param array $to_save - Merged current saved settings and new settings from page
         * @param string[] $option_keys - The expected/required option keys
         * @param array $input - Key/value pairs from the submitted Settings page
         */
        $to_save = apply_filters( 'e20r-mailchimp-populate-saved-defaults', $to_save, $option_keys, $input );

        /**
         * Purpose: Grab the plugin prefix for the membership plugin we've been configured to support
         *
         * @filter 'e20r-mailchimp-membership-plugin-prefix'
         *
         * @param string|null $prefix_key
         */
        $prefix = apply_filters( 'e20r-mailchimp-membership-plugin-prefix', null );

        $utils->log( "Merged settings: " . print_r( $to_save, true ) );

        foreach ( $to_save as $key => $value ) {

            switch ( $key ) {
                // Process string variables
                case 'api_key':
                case 'level_merge_field':
                case 'membership_plugin':
                    $value = isset( $to_save[ $key ] ) ? trim( preg_replace( "[^a-zA-Z0-9\-]", "", $value ) ) : $defaults[ $key ];
                    break;

                // Process integer values
                case 'double_opt_in':
                case 'mc_api_fetch_list_limit':
                case 'unsubscribe':
                case 'wcuser':
                case 'groupings_updated':
                    $value = $new_input[ $key ] = isset( $to_save[ $key ] ) ? intval( $to_save[ $key ] ) : intval( $defaults[ $key ] );
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

                    // Do we need to use the default value?
                    if ( 1 !== preg_match(
                            '/^level_(.*)_lists|^level_(.*)_interests|^level_(.*)_merge_fields/', $key ) &&
                         ! in_array( $key, array_keys( $to_save ) ) && isset( $defaults[ $key ] )
                    ) {
                        if ( $to_save[ $key ] != $value ) {
                            $value = $defaults[ $key ];
                            $utils->log( "Unset/changed variable from page/settings: {$key}. Resetting to default: {$value}" );
                        }
                    }

                    // Let a add-on(s) process the value
                    /**
                     * Purpose: Allow 3rd party add-ons/plugins use its own settings sanitation handler
                     * @filter 'e20r-mailchimp-sanitize-setting'
                     *
                     * @param mixed  $value
                     * @param string $key
                     * @param string $prefix
                     * @param array  $defaults
                     * @param array  $input
                     */
                    $value = apply_filters(
                        'e20r-mailchimp-sanitize-setting',
                        $value,
                        $key,
                        $prefix,
                        $defaults,
                        $input
                    );
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

        } // End of if not empty($levels)

        // $utils->log( "Saving settings: " . print_r( $new_input, true ) );

        return $new_input;
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
                $new_field->is_serialized = !($is_serialized === false);
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
     * Return the tag names from the e20r-mailchimp-merge-tag-settings filter
     *
     * @return array
     * @uses e20r-mailchimp-merge-tag-settings
     *
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
     * Clear all locally cached MailChimp data (force refresh from API server(s)
     */
    public function clear_cache() {

        $utils = Utilities::get_instance();

        wp_verify_nonce( 'e20rmc_update_nonce', 'e20rmc_update_members' );

        $mc_api = MailChimp_API::get_instance();

        $utils->log( "Nonce is verified" );
        $list_config = $mc_api->get_list_conf_by_id();

        // Clear all cached data for MailChimp.com
        foreach ( $list_config as $list_id => $list_settings ) {

            foreach ( $list_settings->interest_categories as $interest_category_id => $ic_settings ) {

                // Clear cached interests
                $mc_api->clear_cache( "{$list_id}-{$interest_category_id}", 'interests' );

                // Clear cached interest categories/groups
                $mc_api->clear_cache( $list_id, 'interest_groups' );
            }

            // Clear cached merge field info
            foreach ( $list_settings->merge_fields as $field_name => $field_settings ) {
                $mc_api->clear_cache( $list_id, 'merge_fields' );
            }

            // Clear any cached list info
            $mc_api->clear_cache( null, 'list_info' );
        }

        wp_send_json_success();
    }

    /**
     * Handler for the "Server refresh" buttons on the options page
     */
    public function options_refresh() {

        $utils = Utilities::get_instance();

        $list_id = $utils->get_variable( 'e20rmc_refresh_list_id', null );
        $utils->log( "Processing for list {$list_id}" );

        if ( empty( $list_id ) ) {

            $msg = __( "Unable to refresh unknown list", Controller::plugin_slug );
            $utils->add_message( $msg, 'error', 'backend' );
            $utils->log( $msg );
            wp_send_json_error( $msg );
        }

        wp_verify_nonce( 'e20rmc', "e20rmc_refresh_{$list_id}" );
        $utils->log( "Nonce is verified" );

        $mc_api   = MailChimp_API::get_instance();
        $ig_class = Interest_Groups::get_instance();
        $utils    = Utilities::get_instance();
        $level    = null;

        $level_id = $utils->get_variable( 'e20rmc_refresh_list_level', null );

        $utils->log( "Loading data for {$level_id}" );

        if ( empty( $mc_api ) ) {

            $error_msg = __( "Unable to load MailChimp API interface", Controller::plugin_slug );
            $utils->add_message( $error_msg, 'error', 'backend' );
            $utils->log( $error_msg );
            wp_send_json_error( $error_msg );
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

        $utils->log( "Attempting to load custom groups/tags/etc" );

        // Try updating the Merge Fields & Interest Groups on the MailChimp Server.
        if ( false === $mc_api->update_server_settings( $list_id ) ) {

            $msg = __( "Unable to update Groups and Merge Tags on the MailChimp.com server!", Controller::plugin_slug );
            $utils->add_message( $msg, 'error', 'backend' );
            $utils->log( "Error: {$msg}" );

            wp_send_json_error( $msg );
        }

        // Force update of upstream interest groups
        if ( ! is_null( $list_id ) && false === ( $ig_sync_status = $mc_api->get_cache( $list_id, 'interest_groups', false ) ) ) {

            $msg = sprintf( __( "Unable to refresh MailChimp Interest Group information for %s", Controller::plugin_slug ), $level->name );
            $utils->add_message( $msg, 'error', 'backend' );

            $utils->log( "Error: Unable to update interest group information" );
            wp_send_json_error( $msg );
        }

        // Force refresh of upstream merge fields
        if ( ! is_null( $list_id ) && false === ( $mg_sync_status = $mc_api->get_cache( $list_id, 'merge_fields', false ) ) ) {

            $msg = sprintf( __( "Unable to refresh MailChimp Merge Field information for %s", Controller::plugin_slug ), $level->name );
            $utils->add_message( $msg, 'error', 'backend' );

            $utils->log( "Error: Unable to update merge field information for list {$list_id} from API server" );
            wp_send_json_error( $msg );
        }

        // $mg_class->get_from_remote( $list_id, true );

        $utils->clear_buffers();
        wp_send_json_success();

    }
}
