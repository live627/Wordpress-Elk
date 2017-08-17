<?php

/**
 * @package   Wordpress Bridge
 * @version   1.0
 * @author    Matt Zuba <matt@mattzuba.com>
 * @author    John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2017, John Rayes
 * @license   http://opensource.org/licenses/MIT MIT
 */

$txt['wordpress bridge'] = 'Wordpress Bridge';
$txt['wordpress bridge settings'] = 'Bridge Settings';
$txt['wordpress settings desc'] = 'Enter and modify settings that pertain to Wordpress and the bridge.';

// Basic Settings
$txt['wordpress_enabled'] = 'Enable Wordpress Bridge';
$txt['wordpress_path'] = 'Wordpress Path';
$txt['wordpress path desc'] = 'This should be the full file path to your wp-config.php file.';
$txt['wordpress path desc extra'] = 'This path is a guess and has NOT been saved yet.  Please click the "Save" button to save this path permamently.';
$txt['wordpress path desc extra2'] = 'Empty this field and hit save to attempt to find this automatically.';
$txt['wordpress_version'] = 'Wordpress Version';

// Role settings
$txt['wordpress roles'] = 'Role Settings';
$txt['wordpress roles desc'] = 'Select which roles in either software correspond to each other.';
$txt['wordpress elk groups'] = 'ElkArte Membergroup';
$txt['wordpress wp groups'] = 'Wordpress Role';
$txt['wordpress select one'] = 'Select one...';
$txt['wordpress elk to wp mapping'] = 'Map ElkArte Membergroups to Wordpress Roles';
$txt['wordpress elk to wp mapping desc'] = 'As users are imported from Wordpress, they will be created with the ElkArte Membergroup that you assign to their Wordpress role.  Any user with a Wordpress role that is not mapped will be created in ElkArte as a Regular Member.';
$txt['wordpress wp to elk mapping'] = 'Map Wordpress roles to ElkArte Membergroups';
$txt['wordpress wp to elk mapping desc'] = 'As users are created in Wordpress, they will be created with the Wordpress role that you assign to their primary membergroup.';

// Error strings
$txt['wordpress no config'] = 'No Wordpress configuration file was found';
$txt['wordpress cannot connect'] = 'Could not connect to the Wordpress database';
$txt['wordpress cannot sync'] = 'There was a problem logging %s into ElkArte using the Wordpress account.  Please ask the administrator to check the error log for more information.';
$txt['wordpress cannot read'] = 'Could not read the Wordpress file.  Please ask your host to allow one of the following functions: %s';
$txt['wordpress problems'] = 'We found the following problems:';

// plugin strings
$txt['wordpress inactive'] = 'The Wordpress redirection plugin is inactive.';
$txt['wordpress active'] = 'The Wordpress redirection plugin is active.';
$txt['wordpress activated'] = 'The Wordpress redirection plugin is now activated.';
$txt['wordpress activate_plugin'] = 'Activate the Wordpress redirection plugin';
