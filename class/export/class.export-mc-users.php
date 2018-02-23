<?php
/**
 *
 *  Copyright (c) 2017. - Eighty / 20 Results by Wicked Strong Chicks.
 *  ALL RIGHTS RESERVED
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace E20R_Email_Memberships\Export;


use E20R\Utilities\E20R_Background_Process;

class Export_MC_Users  extends E20R_Background_Process {
	
	protected $action = null;
	
	/**
	 * Export_MC_Users constructor.
	 */
	public function __construct() {
		$this->action = 'e20r_mc_export';
		
		parent::__construct();
	}
	
	/**
	 * @param \WP_User[] $user_list
	 *
	 * @return bool
	 */
	public function task( $user_list ) {
		
		foreach( $user_list as $user ) {
			
			// Do something with the user record
		}
		
		return false;
	}
}