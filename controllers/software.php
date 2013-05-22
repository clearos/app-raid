<?php

/**
 * Raid controller.
 *
 * @category   apps
 * @package    raid
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/raid/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Raid controller.
 *
 * @category   apps
 * @package    raid
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/raid/
 */

class Software extends ClearOS_Controller
{

    /**
     * Raid default controller
     *
     * @return view
     */

    function index()
    {
        // Load dependencies
        //------------------

        $this->load->library('raid/Raid_Software');
        $this->lang->load('raid');
        $this->raid = $this->raid->create();

        try {
	    $type = $this->raid->get_type_details();
            $data['raid_array'] = $this->raid->get_arrays();
            $data['raid_software'] = $this->raid;
            $data['type'] = $type;
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load view
        //----------

        $this->page->view_form('software', $data, lang('raid_software'));
    }
}
