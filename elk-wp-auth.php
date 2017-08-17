<?php
/*
Plugin Name: elk-wp-auth
Description: Login redirect to elk
Version: 1.0.0
Author: live627
*/

add_action(
    'init',
    function () {
        if (stripos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false && get_option('elk_path') !== false) {
            require get_option('elk_path').'/SSI.php';
            $orgin = parse_url($_SERVER['REQUEST_URI']);
            // Coming from wp-login.php?
            if (strpos($orgin['path'], 'wp-login.php') !== false) {
                if (empty($orgin['query'])) {
                    $orgin['query'] = 'action=login';
                }
                $query = array();
                parse_str($orgin['query'], $query);
                if (empty($query['action'])) {
                    $query['action'] = 'login';
                }
                switch ($query['action']) {
                    case 'register':
                        redirectexit('action=register');
                        break;

                    case 'logout':
                        // Need to load the session real quick so we can properly logout and redirect
                        //loadSession();
                        $_SESSION['logout_url'] = home_url();
                        redirectexit(
                            'action=logout&'.$_SESSION['session_var'].'='.$_SESSION['session_value']
                        );
                        break;

                    case 'lostpassword':
                    case 'retrievepassword':
                        redirectexit('action=reminder');
                        break;

                    default:
                        $_SESSION['login_url'] = home_url();
                        redirectexit('action=login');
                        break;
                }
            }
        }
    }
);