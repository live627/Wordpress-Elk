<?php

/**
 * @package   Wordpress Bridge
 * @version   1.0
 * @author    Matt Zuba <matt@mattzuba.com>
 * @author    John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2017, John Rayes
 * @license   http://opensource.org/licenses/MIT MIT
 */

/**
 * Wordpress Bridge Class
 *
 * @package BlogBridger
 */
class WordpressBridge
{
    /**
     * @var WordpressBridge Instance of a class
     */
    protected static $instance;

    /**
     * @var array Comma separated list of hooks this class implements
     */
    protected $hooks = array(
            'integrate_load_theme',
            'integrate_admin_areas',
            'integrate_login',
            'integrate_logout',
            'integrate_register',
            'integrate_reset_pass',
            'integrate_change_member_data',);
    private $enabled = false;
    private $plugin_path;
    private $bypassRegisterHook = false;

    /**
     * The database object
     * @var database
     */
    protected $db = null;

    /**
     * Setup the object, gather all of the relevant settings
     */
    protected function __construct()
    {
        global $modSettings;

        $this->installHooks();
        $this->db = database();
        $this->enabled = !empty($modSettings['wordpress_enabled']);
    }

    /**
     * Let's try the singleton method
     *
     * @return void
     */
    public static function getInstance()
    {
        $class = __CLASS__;
        if (!isset(self::$instance) || !(self::$instance instanceof $class)) {
            self::$instance = new $class();
        }

        return self::$instance;
    }

    /**
     * Installs the hooks to be used by this module.
     */
    public function installHooks()
    {
        foreach ($this->hooks as $hook) {
            add_integration_function($hook, __CLASS__.'::handleHook', '', false);
        }
    }

    /**
     * Takes all call_integration_hook calls from ElkArte and figures out what
     * method to call within the class
     */
    public static function handleHook()
    {
        $backtrace = debug_backtrace();
        $method = null;
        $args = null;
        foreach ($backtrace as $item) {
            if ($item['function'] === 'call_integration_hook') {
                $method = $item['args'][0];
                $args = !empty($item['args'][1]) ? $item['args'][1] : array();
                break;
            }
        }

        if (!isset($method) || !is_callable(array(self::$instance, $method))) {
            trigger_error('Invalid call to handleHook', E_USER_ERROR);
        }

        return call_user_func_array(array(self::$instance, $method), $args);
    }

    /**
     * Load the language files for the bridge settings
     */
    public function integrate_load_theme()
    {
        loadLanguage('WordpressBridge');
        global $context, $modSettings, $sourcedir, $user_settings, $txt;

        if (!$this->enabled) {
            return '';
        }

        $context['disable_login_hashing'] = true;

        if ($context['current_action'] == 'login2' && isset($_POST['user'])) {
            $request = $this->db->query(
                '',
                '
            SELECT id_member
            FROM {db_prefix}members
            WHERE member_name = {string:user} OR email_address = {string:user}
            LIMIT 1',
                array(
                    'user' => $_POST['user'],
                )
            );
            if ($this->db->num_rows($request)) {
                return;
            }

            require_once($modSettings['wordpress_path'].'/wp-config.php');
            require_once(ABSPATH . WPINC . '/pluggable.php');
            require_once(ABSPATH . WPINC . '/user.php');
            $roleMaps = !empty($modSettings['wordpress_role_maps']) ? json_decode(
                $modSettings['wordpress_role_maps']
            ) : array('elk' => array(), 'wp' => array());
            $userdata = WP_User::get_data_by('login', $_POST['user']);
            if (!$userdata) {
                $userdata = WP_User::get_data_by('email', $_POST['user']);
            }
            if ($userdata) {
                $user = new WP_User;
                $user->init($userdata);
                if ($user && wp_check_password($_POST['passwrd'], $user->data->user_pass, $user->ID)) {
                    $role = current($user->roles);
                    $regOptions = array(
                        'interface' => 'wordpress_bridge',
                        'auth_method' => 'password',
                        'username' => $user->data->user_login,
                        'email' => $user->data->user_email,
                        'require' => 'nothing',
                        'password' => $_POST['passwrd'],
                        'password_check' => $_POST['passwrd'],
                        'check_password_strength' => false,
                        'check_email_ban' => false,
                        'extra_register_vars' => array(
                            'id_group' => !empty($roleMaps['wp'][$role]) ? $roleMaps['wp'][$role] : 0,
                            'real_name' => !empty($user->data->display_name) ? $user->data->display_name : $user->data->user_login,
                            'date_registered' => strtotime($user->data->user_registered),
                        ),
                    );

		require_once(SUBSDIR . '/Members.subs.php');
                    $this->bypassRegisterHook = true;
                    registerMember($regOptions, true);
                }
            }
        }
    }

    /**
     * Adds the Wordpress menu options to ElkArte's admin panel
     *
     * @param array &$admin_areas Admin areas from ElkArte
     */
    public function integrate_admin_areas(&$admin_areas)
    {
        global $txt, $modSettings;

        // We insert it after Features and Options
        $counter = 0;
        foreach ($admin_areas['config']['areas'] as $area => $dummy) {
            if (++$counter && $area == 'featuresettings') {
                break;
            }
        }

        $admin_areas['config']['areas'] = array_merge(
            array_slice($admin_areas['config']['areas'], 0, $counter, true),
            array(
                'wordpress' => array(
                    'label' => $txt['wordpress bridge'],
                    'function' => function () {
                        self::getInstance()->ModifyWordpressBridgeSettings();
                    },
                    'icon' => 'administration.gif',
                    'subsections' => array(
                        'bridge' => array($txt['wordpress bridge settings']),
                        'roles' => array($txt['wordpress roles'], 'enabled' => !empty($modSettings['wordpress_path'])),
                    ),
                ),
            ),
            array_slice($admin_areas['config']['areas'], $counter, null, true)
        );
    }

    /**
     * Logs a user into Wordpress by setting cookies
     *
     * @param string $user Username
     * @param string $hashPasswd ElkArte's version of the hashed password (unused)
     * @param int $cookieTime Time cookie should be live for
     * @return void
     */
    public function integrate_login($memberName, $hashPasswd, $cookieTime)
    {
        global $modSettings, $user_settings;

        if (!$this->enabled) {
            return;
        }

        require_once($modSettings['wordpress_path'].'/wp-config.php');
        require_once(ABSPATH . WPINC . '/pluggable.php');
        require_once(ABSPATH . WPINC . '/user.php');
        $roleMaps = !empty($modSettings['wordpress_role_maps']) ? json_decode(
            $modSettings['wordpress_role_maps']
        ) : array('elk' => array(), 'wp' => array());
        $user = new WP_User($memberName);
        if (!$user->ID) {
            $newUser = new WP_User;
            $newUser->data->user_login = $user_settings['member_name'];
            $newUser->data->user_email = $user_settings['email_address'];
            $newUser->data->user_pass = $_POST['passwrd'];
            $request = $this->db->query(
                '',
                '
            SELECT date_registered
            FROM {db_prefix}members
            WHERE member_name = {string:user}
            LIMIT 1',
                array(
                    'user' => $memberName,
                )
            );
            list($date_registered) = $this->db->fetch_row($request);
            $this->db->free_result($request);
            $newUser->data->user_registered = gmdate("Y-m-d H:i:s", $date_registered);
            $newUser->data->display_name = $user_settings['real_name'];

            if (isset($roleMaps['elk'][$user_settings['id_group']])) {
                $newUser->data->role = $roleMaps['elk'][$user_settings['id_group']];
            }

            $user_id = wp_insert_user($newUser->data);
        }
        $user = new WP_User($memberName);
        if ($user && wp_check_password($_POST['passwrd'], $user->data->user_pass, $user->ID)) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login);
        }
    }

    /**
     * Deletes the Wordpress cookies
     *
     * @param string $user Username, unused as WP doesn't track this in a database
     * @return void
     */
    public function integrate_logout($user)
    {
        global $modSettings, $user_settings;

        if (!$this->enabled) {
            return;
        }

        require_once($modSettings['wordpress_path'].'/wp-config.php');
        require_once(ABSPATH . WPINC . '/pluggable.php');
        require_once(ABSPATH . WPINC . '/user.php');

        wp_logout();
    }

    /**
     * Takes the registration data from ElkArte, creates a new user in WordPress
     * and populates it's data and saves.
     *
     * @param array &$regOptions Array of Registration data
     * @param array &$theme_vars Theme specific options (we don't use these)
     * @return void
     */
    public function integrate_register(&$regOptions, &$theme_vars)
    {
        global $context, $modSettings;

        if (!$this->enabled || $this->bypassRegisterHook) {
            return;
        }

        require_once($modSettings['wordpress_path'].'/wp-config.php');
        require_once(ABSPATH . WPINC . '/pluggable.php');
        require_once(ABSPATH . WPINC . '/user.php');
        $roleMaps = !empty($modSettings['wordpress_role_maps']) ? json_decode(
            $modSettings['wordpress_role_maps']
        ) : array('elk' => array(), 'wp' => array());
        $newUser = new WP_User;
        $newUser->data->user_login = $regOptions['register_vars']['member_name'];
        $newUser->data->user_email = $regOptions['register_vars']['email_address'];
        $newUser->data->user_pass = $regOptions['password'];
        if (isset($roleMaps['elk'][$regOptions['register_vars']['id_group']])) {
            $newUser->data->role = $roleMaps['elk'][$regOptions['register_vars']['id_group']];
        }
        $user_id = wp_insert_user($newUser->data);
    }

    /**
     * Called when a user resets their password in ElkArte.  It will properly hash
     * it into a WordPress compatible version and modify the user in WordPress.
     *
     * @param string $user Username to change
     * @param string $user2 Username to change (again?)
     * @param string $password Plaintext password to reset to
     * @return void
     */
    public function integrate_reset_pass($memberName, $memberName2, $password)
    {
        global $context, $modSettings;

        if (!$this->enabled) {
            return;
        }

        require_once($modSettings['wordpress_path'].'/wp-config.php');
        require_once(ABSPATH . WPINC . '/pluggable.php');
        require_once(ABSPATH . WPINC . '/user.php');

        $user = new WP_User($memberName);
        if ($user->ID) {
            wp_set_password($password, $user->ID);

            if ($context['user']['is_owner']) {
                wp_set_current_user($user->ID, $user->user_login);
                wp_set_auth_cookie($user->ID);
                do_action('wp_login', $user->user_login);
            }
        }
    }

    /**
     * Updates a users' WordPress information when they change in ElkArte
     *
     * @param array $member_names All of the members to change
     * @param string $var Variable that is being updated in ElkArte
     * @param mixed $data Data being updated in ElkArte
     * @return void
     */
    public function integrate_change_member_data($member_names, $var, $data)
    {
        if (!$this->enabled) {
            return;
        }

        // ElkArte var => Wordpress user var
        $integrateVars = array(
            'member_name' => 'user_login',
            'real_name' => 'display_name',
            'email_address' => 'user_email',
            'id_group' => 'role',
            'website_url' => 'user_url',
        );

        if (!isset($integrateVars[$var])) {
            return;
        }

        require_once($modSettings['wordpress_path'].'/wp-config.php');
        require_once(ABSPATH . WPINC . '/pluggable.php');
        require_once(ABSPATH . WPINC . '/user.php');

        $roleMaps = !empty($modSettings['wordpress_role_maps']) ? json_decode(
            $modSettings['wordpress_role_maps']
        ) : array('elk' => array(), 'wp' => array());

        foreach ($member_names as $memberName) {
            $user = new WP_User($memberName);
            if (!$user->ID) {
                continue;
            }

            // if this is a member_name, we have to update the nicename too
            if ($var === 'member_name') {
                $user->data->user_nicename = $data;
            }

            if ($var === 'id_group' && isset($roleMaps['elk'][$data])) {
                $user->data->role = $roleMaps['elk'][$data];
            } else {
                $user->data->{$integrateVars[$var]} = $data;
            }

            $user_id = wp_insert_user($user->data);
        }
    }

    /**
     * Base admin callback function
     */
    public function ModifyWordpressBridgeSettings()
    {
        global $txt, $context;

        $context['page_title'] = $txt['wordpress bridge'];

        loadTemplate('WordpressBridge');

        // Load up all the tabs...
        $context[$context['admin_menu_name']]['tab_data'] = array(
            'title' => $txt['wordpress bridge'],
            'description' => '',
            'tabs' => array(
                'bridge' => array(
                    'description' => $txt['wordpress settings desc'],
                ),
                'roles' => array(
                    'description' => $txt['wordpress roles desc'],
                ),
            ),
        );

		require_once(SUBSDIR . '/SettingsForm.class.php');
		$context['sub_template'] = 'show_settings';

        $action = new Action();
        $subActions = [
            'bridge' => [$this, 'actionModifyBridgeSettings', 'permission' => 'admin_forum'],
            'roles' => [$this, 'actionManageRoles', 'permission' => 'admin_forum'],
        ];
        $subAction = $action->initialize($subActions, 'bridge');
        $context['sub_action'] = $subAction;

        // Call the right function
        $action->dispatch($subAction);
    }

    /**
     * General Settings page for bridge in ElkArte
     */
    public function actionModifyBridgeSettings()
    {
        global $scripturl, $txt, $context, $modSettings;

        $config_vars = array(
            array('check', 'wordpress_enabled'),
            array('text', 'wordpress_path', 'size' => 50, 'subtext' => $txt['wordpress path desc'],),
            '',
            ['var_message','wordpress_version','var_message'=>$txt['wordpress cannot connect']]
        );

        // Saving?
        if (isset($_GET['save'])) {
            checkSession();

            if (isset($_POST['activate'])) {
            require_once($modSettings['wordpress_path'].'/wp-config.php');
            $this->plugin_path = 'elk-wp-auth.php';
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
                $result = activate_plugin($this->plugin_path);
                if ($result instanceof WP_Error) {
                    $context['settings_insert_above'] = '<div class="errorbox">'.$txt['wordpress problems'].'<ul><li>'.implode(
                            '</li><li>',
                            $result->get_error_messages()
                        ).'</li></ul></div>';
                } else {
                    $context['settings_insert_above'] = '<div class="errorbox">'.$txt['wordpress activated'].'</div>';
                }
            } else {
                if (!empty($_POST['wordpress_path']) && basename($_POST['wordpress_path']) === 'wp-config.php') {
                    $_POST['wordpress_path'] = dirname($_POST['wordpress_path']);
                }

                if (!empty($_POST['wordpress_path']) && is_dir($_POST['wordpress_path'])) {
                    $_POST['wordpress_path'] = realpath($_POST['wordpress_path']);
                }

                if (empty($_POST['wordpress_path']) || !file_exists($_POST['wordpress_path'].'/wp-config.php')) {
                    unset($_POST['wordpress_enabled']);
                }

                $save_vars = $config_vars;

                Settings_Form::save_db($save_vars);
            }
            redirectexit('action=admin;area=wordpress;sa=bridge');
        }

        if (!empty($modSettings['wordpress_path']) && !file_exists($modSettings['wordpress_path'].'/wp-config.php')) {
            $config_vars[1]['subtext'] .= ' '.$txt['wordpress path desc extra2'];
        } elseif (empty($modSettings['wordpress_path']) && ($modSettings['wordpress_path'] = $this->findWordpressPath(
                BOARDDIR.'/..'
            )) != ''
        ) {
            $config_vars[1]['subtext'] .= ' '.$txt['wordpress path desc extra'];
        } else {
            $config_vars = array_merge(
                $config_vars,
                array(
                    '',
                    array('callback', 'wordpress_edit_files'),
                )
            );
            require_once($modSettings['wordpress_path'].'/wp-config.php');
            $this->plugin_path = 'elk-wp-auth.php';
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
            $config_vars[3]['var_message'] = $wp_version;
            if (is_plugin_inactive($this->plugin_path)) {
                $context['settings_insert_above'] = '<div class="errorbox">'.$txt['wordpress inactive'].'</div>';
            $config_vars[1]['postinput'] = sprintf('<br><input name="activate" value="%s" type="submit">',$txt['wordpress activate_plugin']);
            } elseif (!get_option('elk_path')) {
                update_option('elk_path', BOARDDIR);
                $context['settings_insert_above'] = '<div class="successbox">'.$txt['wordpress activated'].'</div>';
            } else {
                $context['settings_insert_above'] = '<div class="infobox">'.$txt['wordpress active'].'</div>';
            }
        }
        $context['post_url'] = $scripturl.'?action=admin;area=wordpress;sa=bridge;save';

        Settings_Form::prepare_db($config_vars);
    }

    /**
     * Called in ElkArte admin panel for managing role maps
     */
    public function actionManageRoles()
    {
        global $txt, $scripturl, $context, $settings, $modSettings;

        // Get the basic group data.
        $request = $this->db->query(
            '',
            '
            SELECT id_group, group_name
            FROM {db_prefix}membergroups
            WHERE min_posts = -1 AND id_group != 3
            ORDER BY CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name');
        $context['elkGroups'] = array(
            '0' => array(
                'id_group' => 0,
                'group_name' => $txt['membergroups_members'],
            ),
        );
        while ($row = $this->db->fetch_assoc($request)) {
            $context['elkGroups'][$row['id_group']] = array(
                'id_group' => $row['id_group'],
                'group_name' => $row['group_name'],
            );
        }
        $this->db->free_result($request);

        // Get the WP roles
        require_once($modSettings['wordpress_path'].'/wp-config.php');
        $wp_roles = new WP_Roles();
        $context['wpRoles'] = $wp_roles->role_names;

        // Lastly, our mapping
        $context['wpMapping'] = !empty($modSettings['wordpress_role_maps']) ? unserialize(
            $modSettings['wordpress_role_maps']
        ) : array('elk' => array(), 'wp' => array());

        $config_vars = array(
            array('title', 'wordpress wp to elk mapping'),
            array('desc', 'wordpress wp to elk mapping desc'),
            array('callback', 'wordpress_edit_roles'),
            array('title', 'wordpress elk to wp mapping'),
            array('desc', 'wordpress elk to wp mapping desc'),
            array('callback', 'wordpress_edit_membergroups'),
        );

        $context['post_url'] = $scripturl.'?action=admin;area=wordpress;sa=roles;save';

        if (isset($_GET['save'])) {
            checkSession();

            foreach ($_POST['elkroles'] as $id_group => $role) {
                if (!isset($context['elkGroups'][$id_group]) || !isset($context['wpRoles'][$role])) {
                    unset($_POST['elkroles'][$id_group]);
                }
            }

            foreach ($_POST['wproles'] as $role => $id_group) {
                if (!isset($context['elkGroups'][$id_group]) || !isset($context['wpRoles'][$role])) {
                    unset($_POST['wproles'][$role]);
                }
            }

            $_POST['wordpress_role_maps'] = json_encode(array('elk' => $_POST['elkroles'], 'wp' => $_POST['wproles']));

            $save_vars = array(
                array('text', 'wordpress_role_maps'),
            );
            Settings_Form::save_db($save_vars);

            redirectexit('action=admin;area=wordpress;sa=roles');
        }

        Settings_Form::prepare_db($config_vars);
    }

    /**
     * Attempts to find wp-config.php based on a given path.  Recursive function.
     *
     * @param string $path Base path to start with (needs to be a directory)
     * @param int $level Current depth of search
     * @param int $depth Maximum depth to go
     * @return string Path if file found, empty string if not
     */
    private function findWordpressPath($path, $depth = 3, $level = 1)
    {
        if ($level > $depth) {
            return '';
        }

        // If we found the file return it
        $files = glob($path.'/wp-config.php', GLOB_NOSORT);
        if (!empty($files)) {
            return realpath($path);
        }

        // Didn't find it, do a directory search
        $dirs = glob($path.'/*', GLOB_ONLYDIR | GLOB_NOSORT);
        foreach ($dirs as $dir) {
            $value = $this->findWordpressPath($dir, $depth, $level + 1);
            if (!empty($value)) {
                return $value;
            }
        }
    }
}
