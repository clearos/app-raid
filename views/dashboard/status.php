<?php

/**
 * Raid dashboar status view.
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

clearos_load_library('raid/Raid');

$this->lang->load('base');
$this->lang->load('raid');

///////////////////////////////////////////////////////////////////////////////
// Headers
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    lang('raid_array'),
    lang('raid_level'),
    lang('base_status')
);

///////////////////////////////////////////////////////////////////////////////
// Row Data
///////////////////////////////////////////////////////////////////////////////

foreach ($devices as $dev => $myarray) {
    $state = "<div class='theme-text-ok'>" . lang('raid_clean') . "</div>";
    if ($myarray['state'] == Raid::STATUS_SYNCING && $myarray['sync_progress'] >= 0) {
        $state = "<div class='theme-text-warning'>" . lang('raid_syncing') . ' (' . $myarray['sync_progress'] . '%)</div>';
    } else if ($myarray['state'] == Raid::STATUS_SYNCING) {
        $state = "<div class='theme-text-warning'>" . lang('raid_sync_scheduled') . "</div>";
    } else if ($myarray['state'] != Raid::STATUS_CLEAN) {
        $state = "<div class='theme-text-alert'>" . lang('raid_degraded') . "</div>";
    }
    $row['title'] = $dev;
    $row['details'] = array (
        $dev,
        $myarray['level'],
        $state
    );
    $rows[] = $row;
}

///////////////////////////////////////////////////////////////////////////////
// Sumary table
///////////////////////////////////////////////////////////////////////////////

echo summary_table(
    lang('raid_software'),
    NULL,
    $headers,
    $rows,
    array('no_action' => TRUE)
);
