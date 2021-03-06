
<?php

require_once dirname(__FILE__) . '/../../include/config.php';
require_once(IZNIK_BASE . '/include/db.php');

require_once(IZNIK_BASE . '/include/user/User.php');

$users = $dbhr->preQuery("SELECT id, lastlocation, settings FROM users WHERE settings IS NOT NULL;");

error_log(count($users) . " users");

$total = count($users);
$count = 0;

foreach ($users as $user) {
    $s = json_decode($user['settings'], TRUE);

    if (Utils::pres('mylocation', $s) && $s['mylocation']['id'] != $user['lastlocation']) {
        #error_log("{$user['id']} => {$s['mylocation']['id']}");

        $dbhm->preExec("UPDATE users SET lastlocation = ? WHERE id = ?;", [
            $s['mylocation']['id'],
            $user['id']
        ], FALSE);
    }

    $count ++;

    if ($count % 1000 == 0) {
        error_log("...$count / $total");
    }
}

$msgs = $dbhr->preQuery("SELECT fromuser, locationid FROM messages INNER JOIN messages_groups ON messages_groups.msgid = messages.id INNER JOIN groups ON groups.id = messages_groups.groupid AND groups.type = 'Freegle';");
$total = count($msgs);
$count = 0;

foreach ($msgs as $msg) {
    $dbhm->preExec("UPDATE users SET lastlocation = ? WHERE id = ? AND lastlocation IS NULL;", [
        $msg['locationid'],
        $msg['fromuser']
    ]);

    $count ++;

    if ($count % 1000 == 0) {
        error_log("...$count / $total");
    }
}
