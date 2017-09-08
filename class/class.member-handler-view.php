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

namespace E20R\MailChimp\Views;


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
	 * Add to page: User opt-in field
	 */
	public function add_opt_in_option() {
		$membership_plugin_classes = apply_filters( 'e20r-mailhimp-checout-page' )
		?>
		<table id="e20rmc-user-optin" class="top1em <?php ?>" width="100%" cellpadding="0" cellspacing="0"
		       border="0" <?php if ( ! empty( $pmpro_review ) ) { ?>style="display: none;"<?php } ?>>
			<thead>
			<tr>
				<th>
					<?php _e( '', Controller::plugin_slug ); ?>
				</th>
			</tr>
			</thead>
			<tbody>
			<tr class="odd">
				<td>
				
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}
}