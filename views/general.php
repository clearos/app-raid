<?php

/**
 * Raid general settings view.
 *
 * @category   apps
 * @package    raid
 * @subpackage views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearcenter.com/support/documentation/clearos/raid/
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
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('base');
$this->lang->load('raid');

if ($level == FALSE)
    echo infobox_highlight(lang('base_warning'), lang('raid_no_support'));

///////////////////////////////////////////////////////////////////////////////
// Form open
///////////////////////////////////////////////////////////////////////////////

echo form_open('raid/general/edit');
echo form_header(lang('raid_summary'));

///////////////////////////////////////////////////////////////////////////////
// Form fields and buttons
///////////////////////////////////////////////////////////////////////////////

if ($mode === 'edit') {
    $read_only = FALSE;
    $buttons = array(
        form_submit_update('submit'),
        anchor_cancel('/app/raid')
    );
} else {
    $read_only = TRUE;
    if ($level != FALSE) {
        $buttons = array(anchor_edit('/app/raid/general/edit'));
        if (isset($email))
            $buttons[] = anchor_custom('/app/raid/general/send_test', lang('raid_test_email'), 'high');
    } else {
        $level = '---';
    }
}

echo field_input('level', $level, lang('raid_level'), TRUE);
echo field_toggle_enable_disable('monitor', $monitor, lang('raid_monitor'), $read_only);
echo field_dropdown('frequency', $frequency_options, $frequency, lang('raid_frequency'), $read_only);
echo field_input('email', $email, lang('raid_notify_email'), $read_only);
echo field_button_set($buttons);

///////////////////////////////////////////////////////////////////////////////
// Form close
///////////////////////////////////////////////////////////////////////////////

echo form_footer();
echo form_close();
