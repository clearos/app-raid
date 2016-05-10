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
clearos_load_language('base');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\storage\Storage_Device as Storage_Device;
use \clearos\apps\mail_notification\Mail_Notification as Mail_Notification;
use \clearos\apps\network\Hostname as Hostname;
use \clearos\apps\raid\Raid as Raid;
use \clearos\apps\tasks\Cron as Cron;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('storage/Storage_Device');
clearos_load_library('mail_notification/Mail_Notification');
clearos_load_library('network/Hostname');
clearos_load_library('raid/Raid');
clearos_load_library('tasks/Cron');

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
    const CRON_DEFAULT = 1440;
    const CRON_MINUTE = "* * * * *";
    const CRON_HALF_HOUR = "0,30 * * * *";
    const CRON_HOUR = "0 * * * *";
    const CRON_4_HOUR = "0 */4 * * *";
    const CRON_DAILY = "0 5 * * *";
    const CRON_WEEKLY = "0 5 * * 1";
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
     * Get the send mail condition.
     *
     * @return String  occasion
     * @throws Engine_Exception
     */

    function get_send_mail()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        return $this->config['send_mail'] ? '1' : '0';
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
     * Get the monitor frequency.
     *
     * @return string frequency
     */

    function get_frequency()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        return $this->config['frequency'];
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
     * @param boolean $force force output of status even when not changed
     * @param boolean $send  force sending of email notification
     *
     * @return mixed array if RAID status has changed, NULL otherwise
     * @throws Engine_Exception
     */

    function check_status_change($force = FALSE, $send = FALSE)
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
                $args = CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS . ' ' .
                    CLEAROS_TEMP_DIR . '/' . self::FILE_STATUS . '.orig';
                $retval = $shell->execute(self::CMD_DIFF, $args, FALSE, array('validate_exit_code' => FALSE));
            }

            
            if ($this->get_send_mail() == 0) {
                // Check for failed only setting
                if (! $this->is_loaded)
                    $this->_load_config();

                if (isset($this->config['failure_occurred']) && $this->config['failure_occurred']) {
                    // Force send
                    $send = TRUE;
                    // Reset flag
                    $this->_set_parameter('failure_occurred', FALSE);
                }
            }

            if ($retval != 0 || $send)
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
            if ($this->get_email() == '')
                return;

            $mailer = new Mail_Notification();
            $hostname = new Hostname();
            $subject = lang('raid_raid_status') . ' - ' . $hostname->get();
            $body = "\n\n" . lang('raid_raid_status') . ":\n";
            $body .= str_pad('', strlen(lang('raid_raid_status') . ':'), '=') . "\n\n";

            $thedate = strftime("%b %e %Y");
            $thetime = strftime("%T %Z");
            $body .= str_pad(lang('base_date') . ':', 16) . "\t" . $thedate . ' ' . $thetime . "\n";
            $body .= str_pad(lang('base_status') . ':', 16) . "\t" . $this->state . "\n\n";
            foreach ($lines as $line)
                $body .= $line . "\n";
            $mailer->add_recipient($this->get_email());
            $mailer->set_message_subject($subject);
            $mailer->set_message_body($body);

            $mailer->send();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Set the RAID notification email.
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
     * Set the condition when send email.
     *
     * @param string occasion
     *
     * @return void
     * @throws Engine_Exception Validation_Exception
     */

    function set_send_mail($send_mail)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        $this->_set_parameter('send_mail', $send_mail);
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

        if (! $this->is_loaded)
            $this->_load_config();

        try {
            $cron = new Cron();
            if ($cron->exists_configlet(self::FILE_CROND))
                $cron->delete_configlet(self::FILE_CROND);

            if ($monitor) {
                $options = $this->get_frequency_options();
                $cron_time = self::CRON_DAILY;
                if (array_key_exists($this->get_frequency(), $options)) {
                    switch ($frequency) {
                        case 1:
                            $cron_time = self::CRON_MINUTE;
                            break;
                        case 30:
                            $cron_time = self::CRON_HALF_HOUR;
                            break;
                        case 60:
                            $cron_time = self::CRON_HOUR;
                            break;
                        case 240:
                            $cron_time = self::CRON_4_HOUR;
                            break;
                        case 1440:
                            $cron_time = self::CRON_DAILY;
                            break;
                        case 10080:
                            $cron_time = self::CRON_WEEKLY;
                            break;
                    }
                }
                $payload  = "# Created by API\n";
                $payload .= self::CRON_DAILY . " root " . self::CMD_RAID_SCRIPT . " >/dev/NULL 2>&1";
                $cron->add_configlet(self::FILE_CROND, $payload);
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Set RAID monitoring frequency.
     *
     * @param int $frequency frequency of monitoring
     *
     * @return void
     * @throws Engine_Exception Validation_Exception
     */

    function set_frequency($frequency)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_config();

        $this->_set_parameter('frequency', $frequency);
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
                $level = lang('base_unknown');
                try {
                    $level = $this->_get_md_field('/sys/block/' . $md_dev . '/md/level');
                    if (preg_match("/^RAID(\d+)$/", strtoupper($level), $match))
                        $level = 'RAID-' . $match[1];
                } catch (Exception $e) {
                }
                try {
                    // Not relavent to RAID0...will just throw a bunch of exceptions
                    if ($level != 'RAID-0') {
                        if ($this->_get_md_field('/sys/block/' . $md_dev . '/md/degraded') != 0)
                            $state = self::STATUS_DEGRADED;
                        if ($this->_get_md_field('/sys/block/' . $md_dev . '/md/sync_action') == 'recover') {
                            $state = self::STATUS_SYNCING;
                            // If sync in progress, fetch % complete
                            $sync_progress = $this->_get_sync_progress('/dev/' . $md_dev);
                        }
                    }
                } catch (Exception $e) {
                }
                $size = lang('base_unknown');
                try {
                    $size = $this->_get_md_field('/sys/block/' . $md_dev . '/size') * 512;
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
                        $bd = $match[1];
                    else
                        $bd = $storage_device;
                    $partitions = array_keys($info['partitioning']['partitions']);
                    if (empty($partitions))
                        continue;
                    foreach ($partitions as $index) {
                        try {
                            $folder = new Folder("/sys/block/$md_dev/md/dev-$bd$index", TRUE);
                            if (!$folder->exists())
                                continue;
                            $state = $this->_get_md_field("/sys/block/$md_dev/md/dev-$bd$index/state");
                            $size = $this->_get_md_field("/sys/block/$md_dev/md/dev-$bd$index/size");
                            $slot = $this->_get_md_field("/sys/block/$md_dev/md/dev-$bd$index/slot");
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
     * Adds a device to the specified array.
     *
     * @param string $array  the array
     * @param string $device the device
     *
     * @return void
     * @throws Engine_Exception
     */

    function add_device($array, $device)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Let's santify check data coming in
        $raid_array = $this->get_arrays();
        if ($raid_array[$array]['state'] != self::STATUS_DEGRADED)
            throw new Engine_Exception(lang('raid_not_degraded'), COMMON_WARNING);

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

    /**
     * Get a list of block devices not in RAID array.
     *
     * @return void
     * @throws Engine_Exception
     */

    function get_available_block_devices()
    {
        clearos_profile(__METHOD__, __LINE__);

        $avail = array();
        $storage = new Storage_Device();
        $physical_storage = $storage->get_devices();
        foreach ($physical_storage as $device => $info) {
            // Skip RAID devices
            if (preg_match('/^\/dev\/md\d+$/', $device))
                continue;
            foreach ($info['partitioning']['partitions'] as $index => $partition) {
                if (preg_match('/^linux-swap.*/', $partition['file_system']))
                    continue;
                if (!preg_match('/^.*raid.*/', $partition['flags']))
                    continue;
                if ($storage->is_software_raid_node($device . $index))
                    continue;

                $avail[$device . $index] = $partition;
            }
        }
        return $avail;
    }

    /**
     * Get frequency options.
     *
     * @return array
     * @throws Engine_Exception
     */

    function get_frequency_options()
    {
        clearos_profile(__METHOD__, __LINE__);
        $options = array (
            1 => lang('raid_every_minute'),
            30 => lang('raid_every_30_minutes'),
            60 => lang('base_hourly'),
            240 => lang('raid_every_4_hours'),
            1440 => lang('base_daily'),
            10080 => lang('base_weekly')
        );
        return $options;
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
     * Gets the sync progress of array.
     *
     * @param string $dev device array
     *
     * @access private
     *
     * @return int
     */
    private function _get_sync_progress($dev)
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $options['env'] = "LANG=en_US";
        $arg = '-D ' . $dev;
        $retval = $shell->execute(self::CMD_MDADM, $arg, TRUE, $options);
        if ($retval != 0) {
            return -1;
        } else {
            $lines = $shell->get_output();
            foreach ($lines as $line) {
                if (preg_match('/\s*Rebuild Status\s*:\s*(\d+)% complete/', $line, $match))
                    return $match[1];
            }
        }
        return -1;
    }

    /**
     * Gets the status of array field from /proc.
     *
     * @param string $arg arguement
     *
     * @access private
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

        if (! $this->is_loaded)
            $this->_load_config();

        $failed_list = array();
        if (isset($this->config['failed_list']))
            $failed_list = json_decode($this->config['failed_list']);
        if (!is_array($failed_list))
            $failed_list = array();

        try {
            $padding = array(10, 10, 10, 10);
            $lines = array();
            $lines[] = str_pad(lang('raid_array'), $padding[0]) . "\t" .
                str_pad(lang('base_size'), $padding[1]) . "\t" .
                str_pad(lang('raid_mount'), $padding[2]) . "\t" .
                str_pad(lang('raid_level'), $padding[3]) . "\t" .
                lang('raid_raid_status');
            $lines[] = str_pad('', strlen($lines[0]) + 4*4, '-');
            $myarrays = $this->get_arrays();
            foreach ($myarrays as $dev => $myarray) {
                $state = lang('raid_clean');
                $mount = $this->get_mount($dev);

                if ($myarray['state'] != Raid::STATUS_CLEAN) {
                    $state = lang('raid_degraded');
                    $this->state = $state;
                    // User may have settings to alert once on failure only...we need to store this
                    if (!in_array($dev, $failed_list))
                        $this->_set_parameter('failure_occurred', TRUE);
                    $failed_list[] = $dev;
                } else {
                    if (($key = array_search($dev, $failed_list)) !== false)
                        unset($failed_list[$key]);
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
                        $state = lang('raid_degraded') . ': ' . $partition;
                    }
                }

                $lines[] = str_pad($dev, $padding[0]) . "\t" .
                    str_pad(intval(intval($myarray['size'])/(1024 * 1024)) . lang('base_megabytes'), $padding[1]) . "\t" .
                    str_pad($mount, $padding[2]) . "\t" . str_pad($myarray['level'], $padding[3]) . "\t" . $state;
            }

            $this->_set_parameter('failed_list', json_encode($failed_list));

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
     * @return mixed string containing error if invalid
     */

    public function validate_email($email)
    {
        clearos_profile(__METHOD__, __LINE__);

        $notify = new Mail_Notification();

        try {
            Validation_Exception::is_valid($notify->validate_email($email));
        } catch (Validation_Exception $e) {
            return lang('base_email_address_invalid');
        }
    }

    /**
     * Validation routine for monitor setting
     *
     * @param boolean $monitor monitor flag
     *
     * @return mixed string containing error if invalid
     */

    public function validate_monitor($monitor)
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Validation routine for frequency setting
     *
     * @param int $frequency frequency
     *
     * @return mixed string containing error if invalid
     */

    public function validate_frequency($frequency)
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Validation routine for notify setting
     *
     * @param boolean $notify notify flag
     *
     * @return mixed string containing error if invalid
     */

    public function validate_notify($notify)
    {
        clearos_profile(__METHOD__, __LINE__);
    }
}
