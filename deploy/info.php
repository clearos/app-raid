<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'raid';
$app['version'] = '1.1.7';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('raid_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('raid_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_storage');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_requires'] = array(
    'app-mail-notification-core',
    'app-storage-core',
    'app-tasks-core',
    'mdadm'
);

$app['core_file_manifest'] = array(
   'raid.conf' => array(
        'target' => '/etc/clearos/raid.conf',
        'mode' => '0644',
        'owner' => 'root',
        'group' => 'root',
        'config' => TRUE,
        'config_params' => 'noreplace',
    ),
    'raid-notification' => array(
        'target' => '/usr/sbin/raid-notification',
        'mode' => '0755',
        'owner' => 'root',
        'group' => 'root',
    )
);

/////////////////////////////////////////////////////////////////////////////
// Dashboard Widgets
/////////////////////////////////////////////////////////////////////////////

$app['dashboard_widgets'] = array(
    $app['category'] => array(
        'raid/raid_dashboard' => array(
            'title' => lang('raid_raid_summary'),
            'restricted' => FALSE,
        )
    )
);
