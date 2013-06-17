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
use \clearos\apps\storage\Storage_Device as Storage_Device;

clearos_load_library('raid/Raid');
clearos_load_library('storage/Storage_Device');

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

$degraded_dev = array();
foreach ($raid_array as $dev => $myarray) {
    $status = lang('raid_clean');
    $mount = $raid->get_mount($dev);
    $action = '&#160;';
    $detail_buttons = '';
    if ($myarray['status'] != Raid::STATUS_CLEAN) {
        $iconclass = "icondisabled";
        $status = lang('raid_degraded');
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
                if (preg_match("/.*\/(md\d+)$/", $dev, $match)) {
                    $raid_dev = $match[1];
                    if (preg_match("/dev\/(.*)$/", $details['dev'], $match)) {
                        $phys_dev = $match[1];
                        $detail_buttons = button_set(
                            array(
                                anchor_custom('/app/raid/software/remove/' . $raid_dev . '/' . $phys_dev, lang('raid_remove') . ' ' . $details['dev'])
                            )
                        );
                    }
                }
            }
            $degraded_dev[preg_replace("/\d+$/", "", $details['dev'])] = TRUE;
            
        }
    }
    $row['title'] = $dev;
    $row['action'] = NULL;
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
if (!empty($degraded_dev)) {
    try {
        $storage = new Storage_Device();
        $help_info = '';
    foreach ($degraded_dev as $dev => $ignore) {
            $block_device = $storage->get_device_details($dev);
            $help_info .= '<div>' . $dev . ' = ' . $block_device['identifier'] . '</div>';
    }
        echo infobox_highlight(lang('base_information'), $help_info);
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
