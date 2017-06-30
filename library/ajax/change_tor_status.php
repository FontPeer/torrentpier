<?php
/**
 * TorrentPier – Bull-powered BitTorrent tracker engine
 *
 * @copyright Copyright (c) 2005-2017 TorrentPier (https://torrentpier.com)
 * @link      https://github.com/torrentpier/torrentpier for the canonical source repository
 * @license   https://github.com/torrentpier/torrentpier/blob/master/LICENSE MIT License
 */

if (!defined('IN_AJAX')) {
    die(basename(__FILE__));
}

global $userdata;

if (!isset($this->request['attach_id'])) {
    $this->ajax_die(trans('messages.EMPTY_ATTACH_ID'));
}

$attach_id = (int)$this->request['attach_id'];
$mode = (string)$this->request['mode'];

if (config('tp.tor_comment')) {
    $comment = (string)$this->request['comment'];
}

$tor = OLD_DB()->fetch_row("
	SELECT
		tor.poster_id, tor.forum_id, tor.topic_id, tor.tor_status, tor.checked_time, tor.checked_user_id, f.cat_id, t.topic_title
	FROM       " . BB_BT_TORRENTS . " tor
	INNER JOIN " . BB_FORUMS . " f ON(f.forum_id = tor.forum_id)
	INNER JOIN " . BB_TOPICS . " t ON(t.topic_id = tor.topic_id)
	WHERE tor.attach_id = $attach_id
	LIMIT 1
");

if (!$tor) {
    $this->ajax_die(trans('messages.TORRENT_FAILED'));
}

switch ($mode) {
    case 'status':
        $new_status = (int)$this->request['status'];

        // Валидность статуса
        if (empty(trans('messages.TOR_STATUS_NAME.' . $new_status))) {
            $this->ajax_die(trans('messages.TOR_STATUS_FAILED'));
        }
        if (!isset($this->request['status'])) {
            $this->ajax_die(trans('messages.TOR_DONT_CHANGE'));
        }
        if (!IS_AM) {
            $this->ajax_die(trans('messages.NOT_MODERATOR'));
        }

        // Тот же статус
        if ($tor['tor_status'] == $new_status) {
            $this->ajax_die(trans('messages.TOR_STATUS_DUB'));
        }

        // Запрет на изменение/присвоение CH-статуса модератором
        if ($new_status == TOR_CLOSED_CPHOLD && !IS_ADMIN) {
            $this->ajax_die(trans('messages.TOR_DONT_CHANGE'));
        }

        // Права на изменение статуса
        if ($tor['tor_status'] == TOR_CLOSED_CPHOLD) {
            if (!IS_ADMIN) {
                $this->verify_mod_rights($tor['forum_id']);
            }
            OLD_DB()->query("UPDATE " . BB_TOPICS . " SET topic_status = " . TOPIC_UNLOCKED . " WHERE topic_id = {$tor['topic_id']}");
        } else {
            $this->verify_mod_rights($tor['forum_id']);
        }

        // Подтверждение изменения статуса, выставленного другим модератором
        if ($tor['tor_status'] != TOR_NOT_APPROVED && $tor['checked_user_id'] != $userdata['user_id'] && $tor['checked_time'] + 2 * 3600 > TIMENOW) {
            if (empty($this->request['confirmed'])) {
                $msg = trans('messages.TOR_STATUS_OF') . ' ' . trans('messages.TOR_STATUS_NAME.' . $tor['tor_status']) . "\n\n";
                $msg .= ($username = get_username($tor['checked_user_id'])) ? trans('messages.TOR_STATUS_CHANGED') . html_entity_decode($username) . ", " . delta_time($tor['checked_time']) . trans('messages.TOR_BACK') . "\n\n" : "";
                $msg .= trans('messages.PROCEED') . '?';
                $this->prompt_for_confirm($msg);
            }
        }

        change_tor_status($attach_id, $new_status);

        $this->response['status'] = config('tp.tor_icons.' . $new_status) . ' <b> ' . trans('messages.TOR_STATUS_NAME.' . $new_status) . '</b> &middot; ' . profile_url($userdata) . ' &middot; <i>' . delta_time(TIMENOW) . trans('messages.TOR_BACK') . '</i>';

        if (config('tp.tor_comment') && (($comment && $comment != trans('messages.COMMENT')) || in_array($new_status, config('tp.tor_reply')))) {
            if ($tor['poster_id'] > 0) {
                $subject = sprintf(trans('messages.TOR_MOD_TITLE'), $tor['topic_title']);
                $message = sprintf(trans('messages.TOR_MOD_MSG'), get_username($tor['poster_id']), make_url(TOPIC_URL . $tor['topic_id']), config('tp.tor_icons.' . $new_status) . ' ' . trans('messages.TOR_STATUS_NAME.' . $new_status));

                if ($comment && $comment != trans('messages.COMMENT')) {
                    $message .= "\n\n[b]" . trans('messages.COMMENT') . '[/b]: ' . $comment;
                }

                send_pm($tor['poster_id'], $subject, $message, $userdata['user_id']);
                cache_rm_user_sessions($tor['poster_id']);
            }
        }
        break;

    case 'status_reply':
        if (!config('tp.tor_comment')) {
            $this->ajax_die(trans('messages.MODULE_OFF'));
        }

        $subject = sprintf(trans('messages.TOR_AUTH_TITLE'), $tor['topic_title']);
        $message = sprintf(trans('messages.TOR_AUTH_MSG'), get_username($tor['checked_user_id']), make_url(TOPIC_URL . $tor['topic_id']), $tor['topic_title']);

        if ($comment && $comment != trans('messages.COMMENT')) {
            $message .= "\n\n[b]" . trans('messages.COMMENT') . '[/b]: ' . $comment;
        }

        send_pm($tor['checked_user_id'], $subject, $message, $userdata['user_id']);
        cache_rm_user_sessions($tor['checked_user_id']);
        break;
}

$this->response['attach_id'] = $attach_id;
