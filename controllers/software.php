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

        $this->load->library('raid/Raid');
        $this->lang->load('raid');

        try {
	    $level = $this->raid->get_level();
            if ($level !== FALSE) {
                $data['raid_array'] = $this->raid->get_arrays();
                $data['level'] = $level;
                $data['raid'] = $this->raid;
            }
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load view
        //----------

        $this->page->view_form('software', $data, lang('raid_app_name'));
    }

    /**
     * Raid remove degraded device controller
     *
     * @param $hash    base64 encoded reference to array and partition
     * @param $confirm confirm intent to remove device from array
     *
     * @return view
     */

    function remove($hash, $confirm = NULL)
    {
        // Load dependencies
        //------------------

        $this->load->library('raid/Raid');
        $this->lang->load('raid');

        try {
            list($md, $dev) = preg_split('/\|/', base64_decode($hash));
            if (isset($confirm)) {
                $this->raid->remove_device($md, $dev);
            } else {
                $buttons = button_set(
                    array(
                        anchor_custom('/app/raid/software/remove/' . $hash . '/1', lang('base_confirm')),
                        anchor_cancel('/app/raid')
                    )
                );
                $this->page->set_message(
                     sprintf(lang('raid_confirm_remove'), '<b>' . $dev . '</b>', '<b>' . $md . '</b>') .
                     "<div style='text-align: center; padding: 10px;'>" . $buttons . "</div>",
                     'info'
                );
            }
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        redirect('raid');
    }
}
