<?php
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

namespace E20R\MailChimp\Views;

use E20R\MailChimp\Controller;
use E20R\MailChimp\MailChimp_API;
use E20R\Utilities\Utilities;

class Member_Handler_View {
	/**
	 * Static instance
	 *
	 * @var null|Member_Handler_View
	 * @access private
	 */
	private static $instance = null;
	
	/**
	 * Member_Handler_View constructor.
	 */
	private function __construct() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = $this;
		}
	}
	
	/**
	 * Return or instantiate the Member_Handler class
	 *
	 * @return Member_Handler_View|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	
	/**
	 * Display additional lists selection (table) or div.
	 *
	 * @param array $additional_lists_array
	 *
	 * @return string
	 */
	public static function addl_list_choice( $additional_lists_array ) {
		$utils = Utilities::get_instance();
		
		ob_start();
		?>
        <div id="e20rmc_mailing_lists" class="e20r-mc-divtable">
            <div class="e20r-mc-table-header">
                <div class="e20r-mc-table-row">
                    <div class="e20r-mc-table-cell e20r-mc-header">
						<?php
						if ( count( $additional_lists_array ) > 1 ) {
							_e( 'Join one or more of our other mailing lists.', Controller::plugin_slug );
						} else {
							_e( 'Join our other mailing list.', Controller::plugin_slug );
						}
						?>
                    </div>
                </div>
            </div>
            <div class="e20r-mc-table-body">
				<?php
				global $current_user;
				$additional_lists_selected = $utils->get_variable( 'additional_lists', array() );
				
				$saved_user_lists = get_user_meta( $current_user->ID, "e20r_mc_additional_lists", true );
				
				if ( empty( $additional_lists_selected ) && isset( $_SESSION['additional_lists'] ) ) {
					
					$additional_lists_selected = array_map( 'sanitize_text_field', $_SESSION['additional_lists'] );
					
				} else if ( empty( $additional_lists_selected ) && ! empty( $saved_user_lists ) ) {
					$additional_lists_selected = $saved_user_lists;
				}
				
				$count = 1;
				foreach ( $additional_lists_array as $key => $additional_list ) {
					$current_list = isset( $additional_lists_selected[ ( $count - 1 ) ] ) ? $additional_lists_selected[ ( $count - 1 ) ] : null;
					?>
                    <div class="e20r-mc-table-row">
                        <div class="e20r-mc-table-cell e20r-input-checkbox">
                            <input type="checkbox" id="additional_lists_<?php esc_attr_e( $count ); ?>"
                                   class="e20r-list-checkbox"
                                   name="additional_lists[]"
                                   style="width: 20px; position: relative; vertical-align: middle; float: left;"
                                   value="<?php esc_attr_e( $additional_list['id'] ); ?>" <?php checked( $current_list, $additional_list['id'] ); ?> />
                        </div>
                        <div class="e20r-mc-table-cell e20r-input-label">
                            <label for="additional_lists_<?php esc_attr_e( $count ); ?>"
                                   style="display: inline-block; position: relative; margin: 0; vertical-align: middle; float: left;"
                                   class="e20r-list-entry"><?php esc_attr_e( $additional_list['name'] ); ?></label>

                        </div>
                    </div>
					<?php
					$count ++;
				}
				?>
                <div class="e20r-mc-table-row">
                </div>
            </div>
        </div><?php
		return ob_get_clean();
	}
	
	/**
	 * Add to page: User opt-in field
	 *
	 * @return string
	 */
	public function add_opt_in() {
		
		$mc_api       = MailChimp_API::get_instance();
		$double_optin = $mc_api->get_option( 'double_opt_in' );
		
		$label = apply_filters( 'e20r-mailchimp-optin-label', __( "I'd rather not join the email list", Controller::plugin_slug ) );
		
		// Reversed from actual setting (user is choosing to actively opt out)
		$default = apply_filters( 'e20r-mailchimp-optin-default', ( $double_optin == 0 ) ? true : false );
		
		ob_start();
		?>
        <div id="e20rmc-user-optin" class="e20r-mc-divtable">
            <div class="e20r-mc-table-header">
                <div class="e20r-mc-table-header-row">
                    <div class="e20r-mc-table-cell e20r-mc-header">
                        <label for="e20r-mailchimp-double-optin"><?php esc_html_e( $label ); ?>
                    </div>
                </div>
            </div>
            <div class="e20r-mc-table-body">
                <div class="e20r-mc-table-row">
                    <div class="e20r-mc-table-cell">
                        <input type="checkbox" id="e20r-mailchimp-double-optin" name="e20r-double-optin"
                               value="0" <?php checked( false, $default ); ?>>
                    </div>
                </div>
            </div>
        </div>
		<?php
		return ob_get_clean();
	}
}