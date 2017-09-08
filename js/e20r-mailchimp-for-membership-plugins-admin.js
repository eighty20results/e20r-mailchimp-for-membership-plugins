/*
 * License:

 Copyright 2016-2017 - Eighty / 20 Results by Wicked Strong Chicks, LLC (thomas@eighty20results.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License, version 2, as
 published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
jQuery(document).ready(function( $ ) {
    "use strict";

    var pmpromc_admin = {
        init: function() {

            window.console.log("Loading the E20R Admin class");
            this.refresh_btn = $('input.e20rmc_server_refresh');
            this.bg_update_btn = $('#e20rmc_background');
            this.ack = $('#e20rmc-warning-ack');

            this.list_is_defined = $('#e20rmc-list-is-defined').val();
            var self = this;

            self.refresh_btn.each( function() {

                var btn = $(this);

                btn.unbind('click').on('click', function() {

                    window.console.log("Processing click action for: ", this );
                    event.preventDefault();

                    var element = $(this).closest( 'div.e20rmc-server-refresh-form' );
                    var list_id = element.find('.e20rmc_refresh_list_id').val();
                    var level = element.find('.e20rmc_refresh_list_level_id').val();
                    var $nonce = element.find( '#e20rmc_refresh_' + list_id ).val();
                    window.console.log("List ID: " + list_id + ", Level: " + level );
                    self.trigger_server_refresh( level, list_id, $nonce );
                   //
                });
            });

            self.bg_update_btn.unbind('click').on('click', function() {

                event.preventDefault();
                window.console.log("Processing click action for: ", this );
                self.trigger_background_operation();
            });

            self.ack.unbind('click').on('click', function() {

                if ( $(this).is(':checked') ) {
                    window.console.log("Requesting that the background processing buttin is shown");
                    self.bg_update_btn.show();
                } else {
                    self.bg_update_btn.hide();
                }
            });
        },
        trigger_background_operation: function() {

            var self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 10000,
                dataType: 'JSON',
                data: {
                    action: 'e20rmc_update_members',
                    list_defined: self.list_is_defined,
                    e20rmc_update_nonce: $('#e20rmc_update_nonce' ).val()
                },
                success: function( $response ) {

                    if ( $response.success === false && $response.data.msg.length > 0 ) {
                        window.alert( $response.data.msg );
                        return;
                    }

                    location.reload( true );
                },
                error: function( hdr, $error, errorThrown ) {
                    window.alert("Error ( " + $error + " ) while triggering background update");
                    window.console.log("Error:", errorThrown, $error, hdr  );
                }
            });
        },
        trigger_server_refresh: function( $level_id, $list_id, $nonce ) {

            var $class = this;
            var $list_nonce = 'e20r_mc_refresh_' + $list_id;

            var data = {
                action: 'e20rmc_refresh_list_id',
                'e20rmc_refresh_list_id' : $list_id,
                'e20rmc_refresh_list_level': $level_id
            };

            // Add custom nonce ID
            data[$list_nonce] = $nonce;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 10000,
                dataType: 'JSON',
                data: data,
                success: function( $response ) {
                    window.console.log("Completed AJAX operation: ", $response);
                    location.reload( true );
                },
                error: function( hdr, $error, errorThrown ) {
                    window.alert("Error ( " + $error + " ) while refreshing MailChimp server info");
                    window.console.log("Error:", errorThrown, $error, hdr  );
                }
            });
        }
    };

    pmpromc_admin.init();
});
