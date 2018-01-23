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

namespace E20R\MailChimp\Shortcodes;

use E20R\MailChimp\MailChimp_API;

class Signup {
	
	/**
	 * @var null|Signup
	 */
	private static $instance = null;
	
	/**
	 * Signup constructor.
	 */
	private function __construct() {
	}
	
	/**
	 * Return or instantiate the Signup class
	 *
	 * @return Signup|null
	 */
	public static function get_instance() {
		
		if ( is_null( self::$instance ) ) {
			
			self::$instance = new self;
		}
		
		return self::$instance;
	}
}