<?php

/**
 * Raid software manager view.
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
$this->lang->load('raid');

///////////////////////////////////////////////////////////////////////////////
// Headers
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    lang('raid_array'),
    lang('base_size'),
    lang('raid_mount'),
    lang('raid_level'),
    lang('base_status')
);

///////////////////////////////////////////////////////////////////////////////
// Row Data
///////////////////////////////////////////////////////////////////////////////

$degraded_dev = array();
foreach ($raid_array as $dev => $myarray) {
    $state = lang('raid_clean');
    $mount = $raid->get_mount($dev);
    $action = '&#160;';
    $detail_buttons = '';
    if ($myarray['state'] == Raid::STATUS_SYNCING && $myarray['sync_progress'] >= 0) {
        $state = lang('raid_syncing') . ' (' . $myarray['sync_progress'] . '%)';
    } else if ($myarray['state'] == Raid::STATUS_SYNCING) {
        $state = lang('raid_sync_scheduled');
    } else if ($myarray['state'] != Raid::STATUS_CLEAN) {
        $state = "<div class='theme-text-alert'>" . lang('raid_degraded') . "</div>";
        $detail_buttons = button_set(
            array(
                anchor_custom(
                    '/app/raid/software/add_device/' . strtr(base64_encode($dev),  '+/=', '-_.'),
                    lang('raid_add_device')
                )
            )
        );
    }
    foreach ($myarray['devices'] as $partition => $details) {
        if ($details['state'] == Raid::STATUS_DEGRADED) {
            // Provide a more detailed state message
            $state = "<div class='theme-text-alert'>" . lang('raid_degraded') . ': ' . $partition . "</div>";
            // Check what action applies
            $hash = strtr(base64_encode($dev . '|' . $partition),  '+/=', '-_.');
            $detail_buttons = button_set(
                array(
                    anchor_custom('/app/raid/software/remove_device/' . $hash, lang('raid_remove') . ' ' . $partition)
                )
            );
            $degraded_dev[$details['dev']] = TRUE;
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
        "<div id='state-" . preg_replace('/\/dev\//', '', $dev) . "'>$state</div>"
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
    lang('raid_software_raid'),
    NULL,
    $headers,
    $rows
);
