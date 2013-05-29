<?php

/**
 * Raid manager view.
 *
 * @category   apps
 * @package    raid
 * @subpackage views
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
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\raid\Raid as Raid;
use \clearos\apps\base\Storage_Device as Storage_Device;

clearos_load_library('raid/Raid');
clearos_load_library('base/Storage_Device');

$this->load->helper('number');
$this->lang->load('base');
$this->lang->load('marketplace');

$this->lang->load('base');
$this->lang->load('raid');

///////////////////////////////////////////////////////////////////////////////
// Headers
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    lang('raid_array'),
    lang('raid_size'),
    lang('raid_mount'),
    lang('raid_level'),
    lang('raid_status')
);

///////////////////////////////////////////////////////////////////////////////
// Row Data
///////////////////////////////////////////////////////////////////////////////

$help = NULL;
foreach ($raid_array as $dev => $myarray) {
    $status = lang('raid_clean');
    $mount = $raid->get_mount($dev);
    $action = '&#160;';
    $detail_buttons = '';
    if ($myarray['status'] != Raid::STATUS_CLEAN) {
        $iconclass = "icondisabled";
        $status = lang('raid_degraded');
        if ($this->raid->get_interactive())
            $detail_buttons = button_set(
                array(
                    anchor_custom(lang('raid_repair'), '/app/raid/software/repair/' . $dev)
                )
            );
    }
    foreach ($myarray['devices'] as $id => $details) {
        if ($details['status'] == Raid::STATUS_SYNCING) {
            // Provide a more detailed status message
            $status = lang('raid_syncing') . ' (' . $details['dev'] . ') - ' . $details['recovery'] . '%';
        } else if ($details['status'] == Raid::STATUS_SYNC_PENDING) {
            // Provide a more detailed status message
            $status = lang('raid_sync_pending') . ' (' . $details['dev'] . ')';
        } else if ($details['status'] == Raid::STATUS_DEGRADED) {
            // Provide a more detailed status message
            $status = lang('raid_degraded') . ' (' . $details['dev'] . ' ' . lang('raid_failed') . ')';
            // Check what action applies
            if ($myarray['number'] >= count($myarray['devices'])) {
                if ($this->raid->get_interactive())
                    $detail_buttons = button_set(
                        array(
                            anchor_delete('/app/raid/software/remove/' . $dev)
                        )
                    );
            }
            $help = $details['dev'];
            
        }
    }
    $row['title'] = $dev;
    $row['action'] = '/app/raid/FIXME/';
    $row['anchors'] = $detail_buttons;
    $row['details'] = array (
        $dev,
	byte_format($myarray['size']),
        $mount,
        $myarray['level'],
        $status
    );
    $rows[] = $row;
}

// Help box to identify physical device that is degraded
if ($help != NULL) {
    try {
        // TODO - Put in controller and pass in as argument?
        $storage = new Storage_Device();
        $block_devices = $storage->get_devices();
        $info = $block_devices[$help];
        echo infobox_highlight(lang('base_information'), $help . ' = ' . $info['vendor'] . ' ' . $info['model']);
    } catch (Exception $e) {
        // Ignore
    }
}
///////////////////////////////////////////////////////////////////////////////
// Sumary table
///////////////////////////////////////////////////////////////////////////////

echo summary_table(
    lang('raid_software'),
    NULL,
    $headers,
    $rows
);
