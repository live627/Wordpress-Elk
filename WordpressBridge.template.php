<?php

/**
 * @package   Wordpress Bridge
 * @version   1.0
 * @author    Matt Zuba <matt@mattzuba.com>
 * @author    John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2017, John Rayes
 * @license   http://opensource.org/licenses/MIT MIT
 */

function template_callback_wordpress_edit_roles() {
    global $context, $txt;

    echo '
            <dt><strong>', $txt['wordpress elk groups'], '</strong></dt>
            <dd><strong>', $txt['wordpress wp groups'], '</strong></dd>';
    foreach ($context['elkGroups'] as $group) {
        echo '
            <dt>', $group['group_name'], '</dt>
            <dd>
                <select name="elkroles[', $group['id_group'], ']">
                    <option value="">', $txt['wordpress select one'], '</option>';
        foreach ($context['wpRoles'] as $id => $name) {
            echo '
                    <option value="', $id, '"', (!empty($context['wpMapping']['elk'][$group['id_group']]) && $context['wpMapping']['elk'][$group['id_group']] === $id ? ' selected="selected"' : ''), '>', $name, '</option>';
        }
        echo '
                </select>
            </dd>';
    }
}

function template_callback_wordpress_edit_membergroups() {
    global $context, $txt;

    echo '
            <dt><strong>', $txt['wordpress wp groups'], '</strong></dt>
            <dd><strong>', $txt['wordpress elk groups'], '</strong></dd>';
    foreach ($context['wpRoles'] as $id => $name) {
        echo '
            <dt>', $name, '</dt>
            <dd>
                <select name="wproles[', $id, ']">
                    <option value="">', $txt['wordpress select one'], '</option>';
        foreach ($context['elkGroups'] as $group) {
            echo '
                    <option value="', $group['id_group'], '"', (isset($context['wpMapping']['wp'][$id]) && $context['wpMapping']['wp'][$id] === $group['id_group'] ? ' selected="selected"' : ''), '>', $group['group_name'], '</option>';
        }
        echo '
                </select>
            </dd>';
    }
}
