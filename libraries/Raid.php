<?php

/**
 * Raid class.
 *
 * @category   apps
 * @package    raid
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/raid/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\raid;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('raid');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\mail_notification\Mail_Notification as Mail_Notification;
use \clearos\apps\network\Hostname as Hostname;
use \clearos\apps\tasks\Cron as Cron;
use \clearos\apps\storage\Storage_Device as Storage_Device;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Shell');
clearos_load_library('mail_notification/Mail_Notification');
clearos_load_library('network/Hostname');
clearos_load_library('tasks/Cron');
clearos_load_library('storage/Storage_Device');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Raid class.
 *
 * @category   apps
 * @package    raid
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/raid/
 */

class Raid extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = '/etc/clearos/raid.conf';
    const FILE_MDSTAT = '/proc/mdstat';
    const FILE_STATUS = 'raid.state';
    const FILE_CROND = "app-raid";
    const DEFAULT_CRONTAB_TIME = "0 * * * *";
    const CMD_MDADM = '/sbin/mdadm';
    const CMD_CAT = '/bin/cat';
    const CMD_DF = '/bin/df';
    const CMD_DIFF = '/usr/bin/diff';
    const CMD_SFDISK = '/sbin/sfdisk';
    const CMD_GREP = '/bin/grep';
    const CMD_RAID_SCRIPT = '/usr/sbin/raid-notification';
    const STATUS_CLEAN = 'in_sync';
    const STATUS_DEGRADED = 'faulty';
    const STATUS_SYNCING = 'recover';
    const STATUS_SYNC_SCHEDULED = 'sync_scheduled';
    const STATUS_REMOVED = 'removed';
    const STATUS_SPARE = 'spare';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $mdstat = array();
    protected $config = NULL;
    protected $type = NULL;
    protected $state = NULL;
    protected $is_loaded = FALSE;

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Raid constructor.
     */

    function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Returns type of RAID.
     *
     * @return mixed type of software RAID (false if none)
     * @throws Engine_Exception
     */

    function get_level()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Test for software RAID
        $shell = new Shell();
        $args = self::FILE_MDSTAT;
        $retval = $shell->execute(self::CMD_CAT, $args);

        if ($retval == 0) {
            $lines = $shell->get_output();
            foreach ($lines as $line) {
                if (preg_match("/^Personalities : (.*)$/", $line, $match)) {
                    $unformatted = preg_replace('/\[|\]/', '', strtoupper($match[1]));
                    if (preg_match("/^(RAID)(\d+)$/", $unformatted, $match))
                        return $match[1] . '-' . $match[2];
                    else
                        return $unformatted;
                }
            }
        }
        return FALSE;
    }

    /**
     * Returns the mount point.
     *
     * @param String $dev a device
     *
     * @return string the mount point
     * @throws Engine_Exception
     */

    function get_mount($dev)
    {
        clearos_profile(__METHOD__, __LINE__);

        $mount = '';
        $shell = new Shell();
        $args = $dev;
        $retval = $shell->execute(self::CMD_DF, $args);

        if ($retval != 0) {
            $errstr = $shell->get_last_output_line();
            throw new Engine_Exception($errstr, CLEAROS_WARNING);
        } else {
            $lines = $shell->get_output();
            foreach ($lines as $line) {
                if (preg_match("/^" . str_replace('/', "\\/", $dev) . ".*$/", $line)) {
                    $parts = preg_split("/\s+/", $line);
                    $mount = trim($parts[5]);
                    break;
                }
            }
        }

        return $mount;
    }

    /**
     * Get the notification email.
     *
     * @return String  notification email
     * @throws Engine_Exception
     */

    function get_email()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        return $this->config['email'];
    }

    /**
     * Get the monitor status.
     *
     * @return boolean TRUE if monitoring is enabled
     */

    function get_monitor()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $cron = new Cron();
            if ($cron->exists_configlet(self::FILE_CROND))
                return TRUE;
            return FALSE;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    /**
     * Get partition table.
     *
     * @param string $device RAID device
     *
     * @return String  $device  device
     * @throws Engine_Exception
     */

    function get_partition_table($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $table = array();

        try {
            $shell = new Shell();
            $args = '-d ' . $device;
            $options['env'] = "LANG=en_US";
            $retval = $shell->execute(self::CMD_SFDISK, $args, TRUE, $options);

            if ($retval != 0) {
                $errstr = $shell->get_last_output_line();
                throw new Engine_Exception($errstr, CLEAROS_WARNING);
            } else {
                $lines = $shell->get_output();
                $regex = "/^\/dev\/(\S+) : start=\s*(\d+), size=\s*(\d+), Id=(\S+)(,\s*.*$|$)/";
                foreach ($lines as $line) {
                    if (preg_match($regex, $line, $match)) {
                        $table[] = array(
                        'size' => $match[3],
                        'id' => $match[4],
                        'bootable' => ($match[5]) ? 1 : 0, 'raw' => $line
                        );
                    }
                }
            }

            return $table;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e . " ($device)"), CLEAROS_ERROR);
        }
    }

    /**
     * Copy a partition table from one device to another.
     *
     * @param string $from from partition device
     * @param string $to   to partition device
     *
     * @return void
     * @throws Engine_Exception
     */

    function copy_partition_table($from, $to)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $shell = new Shell();
            $args = '-d ' . $from . ' > ' . CLEAROS_TEMP_DIR . '/pt.txt';
            $options['env'] = "LANG=en_US";
            $retval = $shell->execute(self::CMD_SFDISK, $args, TRUE, $options);

            if ($retval != 0) {
                $errstr = $shell->get_last_output_line();
                throw new Engine_Exception($errstr, CLEAROS_WARNING);
            }

            $args = '-f ' . $to . ' < ' . CLEAROS_TEMP_DIR . '/pt.txt';
            $options['env'] = "LANG=en_US";
            $retval = $shell->execute(self::CMD_SFDISK, $args, TRUE, $options);

            if ($retval != 0) {
                $errstr = $shell->get_last_output_line();
                throw new Engine_Exception($errstr, CLEAROS_WARNING);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Performs a sanity check on partition table to see it matches.
     *
     * @param string $array the array to find a device that is clean
     * @param string $check the device to check partition against
     *
     * @return array
     * @throws Engine_Exception
     */

    function sanity_check_partition($array, $check)
    {
        clearos_profile(__METHOD__, __LINE__);

        $partition_match = array('ok' => FALSE);

        try {
            $myarrays = $this->get_arrays();
            foreach ($myarrays as $dev => $myarray) {
                if ($dev != $array)
                    continue;

                if (isset($myarray['devices']) && is_array($myarray['devices'])) {
                    foreach ($myarray['devices'] as $device) {
                        // Make sure it is clean

                        if ($device['state'] != self::STATUS_CLEAN)
                            continue;

                        $partition_match['dev'] = preg_replace("/\d/", "", $device['dev']);
                        $good = $this->get_partition_table($partition_match['dev']);
                        $check = $this->get_partition_table(preg_replace("/\d/", "", $check));
                        $ok = TRUE;

                        // Check that the same number of partitions exist

                        if (count($good) != count($check))
                            $ok = FALSE;

                        $raw = array();

                        for ($index = 0; $index < count($good); $index++) {
                            if ($check[$index]['size'] < $good[$index]['size'])
                                $ok = FALSE;

                            if ($check[$index]['id'] != $good[$index]['id'])
                                $ok = FALSE;

                            if ($check[$index]['bootable'] != $good[$index]['bootable'])
                                $ok = FALSE;

                            $raw[] = $good[$index]['raw'];
                        }

                        $partition_match['table'] = $raw;

                        if ($ok) {
                            $partition_match['ok'] = TRUE;
                            break;
                        }
                    }
                }
            }

            return $partition_match;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Checks the change of status of the RAID array.
     *
     * @return mixed array if RAID status has changed, NULL otherwise
     * @throws Engine_Exception
     */

    function check_status_change($force = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $lines = $this->_create_report();

            $file = new File(CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS);

            $first_check = FALSE;
            if ($file->exists()) {
                $file->move_to(CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS . '.orig');
                $file = new File(CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS);
            } else {
                $first_check = TRUE;
            }

            $file->create("webconfig", "webconfig", 0644);
            $file->dump_contents_from_array($lines);

            // Diff files to see if notification should be sent
            $retval = -1;
            if (!$first_check) {
                $shell = new Shell();
                $args = CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS . ' ' . CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS . '.orig';
                $retval = $shell->execute(self::CMD_DIFF, $args, FALSE, array('validate_exit_code' => FALSE));
            }

            if ($retval != 0)
                $this->send_status_change_notification($lines);
            else if (!$force)
                return NULL;

            return $lines;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Sends a status change notification to admin.
     *
     * @param string $lines the message content
     *
     * @return void
     * @throws Engine_Exception
     */

    function send_status_change_notification($lines)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            if (!$this->get_monitor() || $this->get_email() == '')
                return;

            $mailer = new Mail_Notification();
            $hostname = new Hostname();
            $subject = lang('raid_state') . ' - ' . $hostname->get();
            $body = "\n\n" . lang('raid_state') . ":\n";
            $body .= str_pad('', strlen(lang('raid_state') . ':'), '=') . "\n\n";

            $thedate = strftime("%b %e %Y");
            $thetime = strftime("%T %Z");
            $body .= str_pad(lang('base_date') . ':', 16) . "\t" . $thedate . ' ' . $thetime . "\n";
            $body .= str_pad(lang('base_status') . ':', 16) . "\t" . $this->state . "\n\n";
            foreach ($lines as $line)
                $body .= $line . "\n";
            $mailer->add_recipient($this->get_email());
            $mailer->set_message_subject($subject);
            $mailer->set_message_body($body);

            $mailer->set_sender($this->get_email());
            $mailer->send();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Set the RAID notificatoin email.
     *
     * @param string $email a valid email
     *
     * @return void
     * @throws Engine_Exception Validation_Exception
     */

    function set_email($email)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        // Validation
        // ----------

        Validation_Exception::is_valid($this->validate_email($email));

        $this->_set_parameter('email', $email);
    }

    /**
     * Set RAID monitoring status.
     *
     * @param boolean $monitor toggles monitoring
     *
     * @return void
     * @throws Engine_Exception Validation_Exception
     */

    function set_monitor($monitor)
    {
        clearos_profile(__METHOD__, __LINE__);
        try {
            $cron = new Cron();
            if ($cron->exists_configlet(self::FILE_CROND) && $monitor) {
                return;
            } else if ($cron->exists_configlet(self::FILE_CROND) && !$monitor) {
                $cron->delete_configlet(self::FILE_CROND);
            } else if (!$cron->exists_configlet(self::FILE_CROND) && $monitor) {
                $payload  = "# Created by API\n";
                $payload .= self::DEFAULT_CRONTAB_TIME . " root " . self::CMD_RAID_SCRIPT . " >/dev/NULL 2>&1";
                $cron->add_configlet(self::FILE_CROND, $payload);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Set RAID notification.
     *
     * @param boolean $status toggles notification
     *
     * @return void
     * @throws Engine_Exception
     */

    function set_notify($status)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        $this->_set_parameter('notify', (isset($status) && $status ? 1 : 0));
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Gets the RAID arrays.
     *
     * @access private
     *
     * @return void
     * @throws Engine_Exception
     */
    public function get_arrays()
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $args = "'^md.*: active' " . self::FILE_MDSTAT . " | cut -f 1 -d ' '";
        $options['env'] = "LANG=en_US";
        $retval = $shell->execute(self::CMD_GREP, $args, FALSE, $options);
        $storage = new Storage_Device();
        $physical_storage = $storage->get_devices();
        if ($retval != 0) {
            $errstr = $shell->get_last_output_line();
            throw new Engine_Exception($errstr, COMMON_WARNING);
        } else {
            $arrays = $shell->get_output();
            foreach ($arrays as $md_dev) {
                $state = self::STATUS_CLEAN;
                $sync_progress = NULL;
                try {
                    if ($this->_get_md_field('/sys/block/' . $md_dev . '/md/degraded') != 0)
                        $state = self::STATUS_DEGRADED;
                    if ($this->_get_md_field('/sys/block/' . $md_dev . '/md/sync_action') == 'recover') {
                        $state = self::STATUS_SYNCING;
                        // If sync in progress, fetch % complete
                        $progress = preg_split('|\s+/\s+|',  $this->_get_md_field('/sys/block/' . $md_dev . '/md/sync_completed'));
                        if ($progress[0] == 0 || $progress[1] == 0)
                            $sync_progress = -1;
                        else
                            $sync_progress = intval($progress[0] / $progress[1] * 100);
                    }
                } catch (Exception $e) {
                }
                $size = lang('base_unknown');
                try {
                    $size = $this->_get_md_field('/sys/block/' . $md_dev . '/md/component_size');
                } catch (Exception $e) {
                }
                $level = lang('base_unknown');
                try {
                    $level = $this->_get_md_field('/sys/block/' . $md_dev . '/md/level');
                    if (preg_match("/^RAID(\d+)$/", strtoupper($level), $match))
                        $level = 'RAID-' . $match[1];
                } catch (Exception $e) {
                }
                $number = 0;
                try {
                    $number = $this->_get_md_field('/sys/block/' . $md_dev . '/md/raid_disks');
                } catch (Exception $e) {
                }
        
                $this->mdstat['/dev/' . $md_dev] = array(
                     'state' => $state,
                     'size' => $size,
                     'level' => $level,
                     'number' => $number,
                     'sync_progress' => $sync_progress,
                     'devices' => array()
                );
                foreach ($physical_storage as $storage_device => $info) {
                    // Skip removable media
                    if (isset($info['removable']) && $info['removable'])
                        continue;
                    // Skip RAID devices
                    if (preg_match('/^\/dev\/md\d+$/', $storage_device, $match))
                        continue;
                    if (preg_match('/^\/dev\/(.*)$/', $storage_device, $match))
                        $block_dev = $match[1];
                    else
                        $block_dev = $storage_device;
                    $partitions = array_keys($info['partitioning']['partitions']);
                    if (empty($partitions))
                        continue;
                    foreach ($partitions as $index) {
                        try {
                            $state = $this->_get_md_field('/sys/block/' . $md_dev . '/md/dev-' . $block_dev . $index . '/state');
                            $size = $this->_get_md_field('/sys/block/' . $md_dev . '/md/dev-' . $block_dev . $index . '/size');
                            $slot = $this->_get_md_field('/sys/block/' . $md_dev . '/md/dev-' . $block_dev . $index . '/slot');
                            $this->mdstat['/dev/' . $md_dev]['devices'][$storage_device . $index] = array(
                                'dev' => $storage_device,
                                'state' => $state,
                                'size' => $size,
                                'slot' => $slot
                            );
                        } catch (Exception $e) {
                            // Ignore - not part of array
                        }
                    }
                }
            }
        }
        return $this->mdstat;
    }

    /**
     * Repair an array with the specified device.
     *
     * @param string $array  the array
     * @param string $device the device
     *
     * @return void
     * @throws Engine_Exception
     */

    function repair_array($array, $device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $args = '-a ' . $array . ' ' . $device;
        $options['env'] = "LANG=en_US";
        $retval = $shell->execute(self::CMD_MDADM, $args, TRUE, $options);
        if ($retval != 0) {
            $errstr = $shell->get_last_output_line();
            throw new Engine_Exception($errstr, COMMON_WARNING);
        }
    }

    /**
     * Removes a device from the specified array.
     *
     * @param string $array  the array
     * @param string $device the device
     *
     * @return void
     * @throws Engine_Exception
     */

    function remove_device($array, $device)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Let's santify check data coming in
        $raid_array = $this->get_arrays();
        if ($raid_array[$array]['devices'][$device]['state'] != self::STATUS_DEGRADED)
            throw new Engine_Exception(lang('raid_not_degraded'), COMMON_WARNING);

        $shell = new Shell();
        $args = '-r ' . $array . ' ' . $device;
        $options['env'] = "LANG=en_US";
        $retval = $shell->execute(self::CMD_MDADM, $args, TRUE, $options);
        if ($retval != 0) {
            $errstr = $shell->get_last_output_line();
            throw new Engine_Exception($errstr, COMMON_WARNING);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E    R O U T I N E S
    ///////////////////////////////////////////////////////////////////////////////

    /**
    * Loads configuration files.
    *
    * @return void
    * @throws Engine_Exception
    */

    protected function _load_config()
    {
        clearos_profile(__METHOD__, __LINE__);

        $configfile = new Configuration_File(self::FILE_CONFIG);
            
        $this->config = $configfile->Load();

        $this->is_loaded = TRUE;
    }

    /**
     * Gets the status of array field from /proc.
     *
     * @access private
     *
     * @param string $arg arguement
     *
     * @return string
     */
    private function _get_md_field($arg)
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $options['env'] = "LANG=en_US";
        $retval = $shell->execute(self::CMD_CAT, $arg, FALSE, $options);
        if ($retval != 0) {
            $errstr = $shell->get_last_output_line();
            throw new Engine_Exception($errstr, COMMON_WARNING);
        } else {
            return $shell->get_last_output_line();
        }
    }

    /**
     * Gets the status according to mdstat.
     *
     * @access private
     *
     * @return void
     * @throws Engine_Exception
     */
    private function _get_md_stat()
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $args = self::FILE_MDSTAT;
        $options['env'] = "LANG=en_US";
        $retval = $shell->execute(self::CMD_CAT, $args, FALSE, $options);
        if ($retval != 0) {
            $errstr = $shell->get_last_output_line();
            throw new Engine_Exception($errstr, COMMON_WARNING);
        } else {
            $this->mdstat = $shell->get_output();
        }
    }

    /**
     * Generic set routine.
     *
     * @param string $key   key name
     * @param string $value value for the key
     *
     * @return  void
     * @throws Engine_Exception
     */

    private function _set_parameter($key, $value)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(self::FILE_CONFIG, TRUE);
            $match = $file->replace_lines("/^$key\s*=\s*/", "$key = $value\n");

            if (!$match)
                $file->add_lines("$key = $value\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $this->is_loaded = FALSE;
    }

    /**
     * Report for software RAID.
     *
     * @return array
     * @throws Engine_Exception
     */

    private function _create_report()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->state = lang('raid_clean');

        try {
            $padding = array(10, 10, 10, 10);
            $lines = array();
            $lines[] = str_pad(lang('raid_array'), $padding[0]) . "\t" .
                str_pad(lang('raid_size'), $padding[1]) . "\t" .
                str_pad(lang('raid_mount'), $padding[2]) . "\t" .
                str_pad(lang('raid_level'), $padding[3]) . "\t" .
                lang('base_status');
            $lines[] = str_pad('', strlen($lines[0]) + 4*4, '-');
            $myarrays = $this->get_arrays();
            foreach ($myarrays as $dev => $myarray) {
                $state = lang('raid_clean');
                $mount = $this->get_mount($dev);

                if ($myarray['state'] != Raid::STATUS_CLEAN) {
                    $state = lang('raid_degraded');
		    $this->state = $state;
                }
                if ($myarray['state'] == Raid::STATUS_SYNCING && $myarray['sync_progress'] >= 0)
                    $state = lang('raid_syncing') . ' (' . $myarray['sync_progress'] . '%)';
                else if ($myarray['state'] == Raid::STATUS_SYNCING)
                    $state = lang('raid_sync_scheduled');

                foreach ($myarray['devices'] as $partition => $details) {
                    if ($details['state'] == self::STATUS_SYNCING) {
                        $state = lang('raid_syncing') . ' (' . $details['dev'] . ') - ' . $details['recovery'] . '%';
                    } else if ($details['state'] == self::STATUS_SYNC_SCHEDULED) {
                        $state = lang('raid_sync_pending') . ' (' . $details['dev'] . ')';
                    } else if ($details['state'] == self::STATUS_DEGRADED) {
                        $state = lang('raid_degraded') . ' (' . $partition . ' ' . lang('raid_failed') . ')';
                    }
                }
        
                $lines[] = str_pad($dev, $padding[0]) . "\t" .
                    str_pad(intval(intval($myarray['size'])/1024) . lang('base_megabytes'), $padding[1]) . "\t" .
                    str_pad($mount, $padding[2]) . "\t" . str_pad($myarray['level'], $padding[3]) . "\t" . $state;
            }

            return $lines;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   R O U T I N E S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for email
     *
     * @param string $email email
     *
     * @return boolean TRUE if email is valid
     */

    public function validate_email($email)
    {
        clearos_profile(__METHOD__, __LINE__);

        $notify = new Mail_Notification();

        try {
            Validation_Exception::is_valid($notify->validate_email($email));
        } catch (Validation_Exception $e) {
            return lang('raid_email_is_invalid');
        }
    }

    /**
     * Validation routine for monitor setting
     *
     * @param boolean $monitor monitor flag
     *
     * @return boolean TRUE if monitor is valid
     */

    public function validate_monitor($monitor)
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Validation routine for notify setting
     *
     * @param boolean $notify notify flag
     *
     * @return boolean TRUE if notify is valid
     */

    public function validate_notify($notify)
    {
        clearos_profile(__METHOD__, __LINE__);
    }
}
