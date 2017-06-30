<?php
/**
 * TorrentPier – Bull-powered BitTorrent tracker engine
 *
 * @copyright Copyright (c) 2005-2017 TorrentPier (https://torrentpier.com)
 * @link      https://github.com/torrentpier/torrentpier for the canonical source repository
 * @license   https://github.com/torrentpier/torrentpier/blob/master/LICENSE MIT License
 */

define('BB_ROOT', './../');
define('IN_ADMIN', true);

require dirname(__DIR__) . '/common.php';
require ATTACH_DIR . '/attachment_mod.php';
require ATTACH_DIR . '/includes/functions_admin.php';
require_once INC_DIR . '/functions_admin.php';

$user->session_start();

if (IS_GUEST) {
    redirectToUrl(LOGIN_URL . '?redirect=admin/index.php');
}

if (!IS_ADMIN) {
    bb_die(trans('messages.NOT_ADMIN'));
}

if (!$userdata['session_admin']) {
    $redirect = url_arg($_SERVER['REQUEST_URI'], 'admin', 1);
    redirectToUrl("login.php?redirect=$redirect");
}
