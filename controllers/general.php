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

use \clearos\apps\raid\Raid as Raid_Class;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Raid general settings controller.
 *
 * @category   apps
 * @package    raid
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/raid/
 */

class General extends ClearOS_Controller
{

    /**
     * Raid default controller
     *
     * @return view
     */

    function index()
    {
        $this->_view_edit();
    }

    /**
     * Raid edit controller
     *
     * @return view
     */

    function edit()
    {
        $this->_view_edit('edit');
    }

    /**
     * Raid view/edit controller
     *
     * @param string $mode mode
     *
     * @return view
     */
    function _view_edit($mode = 'view')
    {
        // Load dependencies
        //------------------

        $this->load->library('raid/Raid');
        $this->lang->load('raid');

        $data['mode'] = $mode;

        // Set validation rules
        //---------------------

        $this->form_validation->set_policy('monitor', 'raid/Raid', 'validate_monitor', TRUE);
        $this->form_validation->set_policy('frequency', 'raid/Raid', 'validate_frequency', TRUE);
        $this->form_validation->set_policy('email', 'raid/Raid', 'validate_email', TRUE);
        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if (($this->input->post('submit') && $form_ok)) {
            try {
                $this->raid->set_monitor($this->input->post('monitor'));
                $this->raid->set_frequency($this->input->post('frequency'));
                $this->raid->set_send_mail($this->input->post('send_mail'));
                $this->raid->set_email($this->input->post('email'));
                $this->page->set_status_updated();
                redirect('/raid');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load view data
        //---------------

        try {
            $level = $this->raid->get_level();
            $data['frequency_options'] = $this->raid->get_frequency_options();
            if ($level !== FALSE) {
                $data['level'] = $level;
                $data['monitor'] = $this->raid->get_monitor();
                $data['frequency'] = $this->raid->get_frequency();
                $data['send_mail'] = $this->raid->get_send_mail();
                $data['email'] = $this->raid->get_email();
            } else {
                $data['monitor'] = $this->raid->get_monitor();
            }
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load views
        //-----------

        $this->page->view_form('general', $data, lang('raid_app_name'));
    }

    /**
     * Send test email
     *
     * @return view
     */
    function send_test()
    {
        // Load dependencies
        //------------------

        $this->load->library('raid/Raid');
        $this->lang->load('raid');

        try {
            $this->raid->check_status_change(TRUE, TRUE);
            $this->page->set_message(lang('raid_test_sent') . ': ' . $this->raid->get_email() . '.', 'info');
            redirect('/raid');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }
}
