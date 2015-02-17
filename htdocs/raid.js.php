<?php

/**
 * Javascript helper for Raid.
 *
 * @category   apps
 * @package    raid
 * @subpackage javascript
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
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

header('Content-Type: application/x-javascript');

echo "
  $(document).ready(function() {
    get_state();
  });
  function get_state() {
    $.ajax({
        type: 'GET',
        dataType: 'json',
        url: '/app/raid/software/get_state',
        data: '',
        success: function(data) {
            if (data.code == undefined) {
                $.each(data, function(id, state) { 
                    $('#state-' + id).html(state);
                })
            }
            window.setTimeout(get_state, 1000);
        }
    });
  }
";

// vim: syntax=php ts=4
