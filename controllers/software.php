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

use \clearos\apps\raid\Raid as Raid;

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
        clearos_profile(__METHOD__, __LINE__);

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
     * @param string $hash    base64 encoded reference to array and partition
     * @param string $confirm confirm intent to remove device from array
     *
     * @return view
     */

    function remove_device($hash, $confirm = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load dependencies
        //------------------

        $this->load->library('raid/Raid');
        $this->lang->load('raid');

        try {
            list($md, $dev) = preg_split('/\|/', base64_decode(strtr($hash, '-_.', '+/=')));
            if (isset($confirm)) {
                $this->raid->remove_device($md, $dev);
            } else {
                $buttons = button_set(
                    array(
                        anchor_custom('/app/raid/software/remove_device/' . $hash . '/1', lang('base_confirm')),
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

    /**
     * Raid add device controller
     *
     * @param string $hash    base64 encoded reference to array and device
     * @param string $confirm confirm intent to remove device from array
     *
     * @return view
     */

    function add_device($hash, $confirm = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load dependencies
        //------------------

        $this->load->library('raid/Raid');
        $this->lang->load('base');
        $this->lang->load('raid');

        $data = array();
        $avail_block_devs = $this->raid->get_available_block_devices();

        $data['block_devices'] = array(0 => lang('base_select'));
        foreach ($avail_block_devs as $id => $info)
            $data['block_devices'][$id] = $id . ' (' . $info['size'] . $info['size_units'] . ')';
        $data['raid_array'] = $this->raid->get_arrays();

        try {
            list($md, $block_device) = preg_split('/\|/', base64_decode(strtr($hash, '-_.', '+/=')));
            $data['md_device'] = $md;
            if ($md != '' && $block_device != '' && $confirm) {
                $this->raid->add_device($md, $block_device);
                redirect('raid');
                return;
            } else if ($this->input->post('add')) {
                $md = $this->input->post('md_device');
                $block_device = $this->input->post('block_device');
                $data['md_device'] = $md;
                if ($block_device !== 0) {
                    $hash = strtr(base64_encode($md . '|' . $block_device),  '+/=', '-_.');
                    $buttons = button_set(
                        array(
                            anchor_custom('/app/raid/software/add_device/' . $hash . '/1', lang('base_confirm')),
                            anchor_cancel('/app/raid')
                        )
                    );
                    $this->page->set_message(
                        sprintf(lang('raid_confirm_add'), '<b>' . $block_device . '</b>', '<b>' . $md . '</b>') .
                        "<div style='text-align: center; padding: 10px;'>" . $buttons . "</div>",
                        'info'
                    );
                    redirect('raid');
                    return;
                }
            }
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $this->page->view_form('add_device', $data, lang('raid_app_name'));
    }

    /**
     * Raid get state controller
     *
     * @return json
     */

    function get_state()
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        // Load dependencies
        //------------------

        $this->lang->load('raid');
        $this->load->library('raid/Raid');

        $output = array();
        try {
            $raid_array = $this->raid->get_arrays();
            foreach ($raid_array as $dev => $myarray) {
                $device = preg_replace('/\/dev\//', '', $dev);
                $output[$device] = lang('raid_clean');
                if ($myarray['state'] == Raid::STATUS_SYNCING && $myarray['sync_progress'] >= 0)
                    $output[$device] = lang('raid_syncing') . ' (' . $myarray['sync_progress'] . '%)';
                else if ($myarray['state'] == Raid::STATUS_SYNCING)
                    $output[$device] = lang('raid_sync_scheduled');
                else if ($myarray['state'] != Raid::STATUS_CLEAN)
                    $output[$device] = lang('raid_degraded');
                foreach ($myarray['devices'] as $partition => $details) {
                    if ($details['state'] == Raid::STATUS_DEGRADED) {
                        // Provide a more detailed state message
                        $output[$device] = lang('raid_degraded') . ': ' . $partition;
                    }
                }
            }
            echo json_encode($output);
        } catch (Exception $e) {
            echo json_encode(Array('code' => clearos_exception_code($e), 'errmsg' => clearos_exception_message($e)));
        }
    }
}
