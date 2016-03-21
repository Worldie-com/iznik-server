<?php

require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/misc/Entity.php');
require_once(IZNIK_BASE . '/include/user/MembershipCollection.php');

class Group extends Entity
{
    /** @var  $dbhm LoggedPDO */
    var $publicatts = array('id', 'nameshort', 'namefull', 'nameabbr', 'namedisplay', 'settings', 'type', 'logo',
        'onyahoo', 'trial', 'licenserequired', 'licensed', 'licenseduntil', 'membercount', 'lat', 'lng');

    const GROUP_REUSE = 'Reuse';
    const GROUP_FREEGLE = 'Freegle';
    const GROUP_OTHER = 'Other';

    /** @var  $log Log */
    private $log;

    function __construct(LoggedPDO $dbhr, LoggedPDO $dbhm, $id = NULL)
    {
        $this->fetch($dbhr, $dbhm, $id, 'groups', 'group', $this->publicatts);

        $this->defaultSettings = [
            'autoapprove' => [
                'members' => 0,
                'messages' => 0
            ], 'duplicates' => [
                'check' => 1,
                'offer' => 7,
                'taken' => 7,
                'wanted' => 14,
                'received' => 14
            ], 'spammers' => [
                'check' => $this->group['type'] == Group::GROUP_FREEGLE,
                'remove' => $this->group['type'] == Group::GROUP_FREEGLE
            ], 'joiners' => [
                'check' => 1,
                'threshold' => 5
            ], 'keywords' => [
                'OFFER' => 'OFFER',
                'TAKEN' => 'TAKEN',
                'WANTED' => 'WANTED',
                'RECEIVED' => 'RECEIVED'
            ]
        ];

        if (!$this->group['settings'] || strlen($this->group['settings']) == 0) {
            $this->group['settings'] = json_encode($this->defaultSettings);
        }

        $this->log = new Log($dbhr, $dbhm);
    }

    /**
     * @param LoggedPDO $dbhm
     */
    public function setDbhm($dbhm)
    {
        $this->dbhm = $dbhm;
    }

    public function create($shortname, $type) {
        try {
            # Check for duplicate.  Might still occur in a timing window but in that rare case we'll get an exception
            # and catch that, failing the call.
            $groups = $this->dbhm->preQuery("SELECT id FROM groups WHERE nameshort = ?;", [ $shortname ]);
            foreach ($groups as $group) {
                return(NULL);
            }

            $rc = $this->dbhm->preExec("INSERT INTO groups (nameshort, type) VALUES (?, ?)", [$shortname, $type]);
            $id = $this->dbhm->lastInsertId();
        } catch (Exception $e) {
            $id = NULL;
            $rc = 0;
        }

        if ($rc && $id) {
            $this->fetch($this->dbhr, $this->dbhm, $id, 'groups', 'group', $this->publicatts);
            $this->log->log([
                'type' => Log::TYPE_GROUP,
                'subtype' => Log::SUBTYPE_CREATED,
                'groupid' => $id,
                'text' => $shortname
            ]);

            return($id);
        } else {
            return(NULL);
        }
    }

    public function getModsEmail() {
        return($this->group['nameshort'] . "-owner@yahoogroups.com");
    }

    public function getGroupEmail() {
        return($this->group['nameshort'] . "@yahoogroups.com");
    }

    public function delete() {
        $rc = $this->dbhm->preExec("DELETE FROM groups WHERE id = ?;", [$this->id]);
        if ($rc) {
            $this->log->log([
                'type' => Log::TYPE_GROUP,
                'subtype' => Log::SUBTYPE_DELETED,
                'groupid' => $this->id
            ]);
        }

        return($rc);
    }

    public function findByShortName($name) {
        $groups = $this->dbhr->preQuery("SELECT id FROM groups WHERE nameshort = ?;",
            [$name]);
        foreach ($groups as $group) {
            return($group['id']);
        }

        return(NULL);
    }

    public function getWorkCounts($mysettings) {
        # Depending on our group settings we might not want to show this work as primary; "other" work is displayed
        # less prominently in the client.
        #error_log("Getworkcounts " . error_log(var_export($mysettings, true)));
        $showmessages = !array_key_exists('showmessages', $mysettings) || $mysettings['showmessages'];
        $showmembers = !array_key_exists('showmembers', $mysettings) || $mysettings['showmembers'];
        $spam = $showmessages ? 'spam' : 'spamother';

        # We only want to show spam messages upto 7 days old to avoid seeing too many, especially on first use.
        $mysqltime = date ("Y-m-d", strtotime("Midnight 7 days ago"));

        $ret = [
            'pending' => $showmessages ? $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM messages INNER JOIN messages_groups ON messages.id = messages_groups.msgid AND messages_groups.groupid = ? AND messages_groups.collection = ? AND messages_groups.deleted = 0 AND messages.heldby IS NULL AND messages.deleted IS NULL;", [
                $this->id,
                MessageCollection::PENDING
            ])[0]['count'] : 0,
            'pendingother' => $showmessages ? $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM messages INNER JOIN messages_groups ON messages.id = messages_groups.msgid AND messages_groups.groupid = ? AND messages_groups.collection = ? AND messages_groups.deleted = 0 AND messages.heldby IS NOT NULL;", [
                $this->id,
                MessageCollection::PENDING
            ])[0]['count'] : 0,
            $spam => $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM messages INNER JOIN messages_groups ON messages.id = messages_groups.msgid AND messages_groups.groupid = ? AND messages_groups.collection = ? AND messages.date >= '$mysqltime' AND messages_groups.deleted = 0 " . ($showmessages ? "AND messages.heldby IS NULL" : "") . ";", [
                $this->id,
                MessageCollection::SPAM
            ])[0]['count'],
            'pendingmembers' => $showmembers ? $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM memberships WHERE groupid = ? AND collection = ? AND memberships.heldby IS NULL;", [
                $this->id,
                MembershipCollection::PENDING
            ])[0]['count'] : 0,
            'pendingmembersother' => $showmembers ? $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM memberships WHERE groupid = ? AND collection = ? AND memberships.heldby IS NOT NULL;", [
                $this->id,
                MembershipCollection::PENDING
            ])[0]['count'] : 0,
            'plugin' => $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM plugin WHERE groupid = ?;", [
                $this->id
            ])[0]['count']
        ];

        return($ret);
    }

    public function getPublic() {
        $atts = parent::getPublic();

        # Add in derived properties.
        $atts['namedisplay'] = $atts['namefull'] ? $atts['namefull'] : $atts['nameshort'];
        $atts['lastyahoomembersync'] = ISODate($this->group['lastyahoomembersync']);
        $atts['lastyahoomessagesync'] = ISODate($this->group['lastyahoomessagesync']);
        $atts['settings'] = array_merge($this->defaultSettings, json_decode($atts['settings'], true));

        $sql = "SELECT COUNT(*) AS count FROM memberships WHERE groupid = {$this->id} AND role IN ('Owner', 'Moderator');";
        $counts = $this->dbhr->preQuery($sql);
        $atts['nummods'] = $counts[0]['count'];

        foreach (['trial', 'licensed', 'licenseduntil'] as $datefield) {
            $atts[$datefield] = $atts[$datefield] ? ISODate($atts[$datefield]) : NULL;
        }

        return($atts);
    }

    public function exportYahoo($groupid) {
        $members = $this->dbhr->preQuery("SELECT members FROM memberships_yahoo WHERE groupid = ?;", [ $groupid ]);
        foreach ($members as $member) {
            return(json_decode($member['members'], TRUE));
        }

        return(NULL);
    }

    public function getMembers($limit = 10, $search = NULL, &$ctx = NULL, $searchid = NULL, $collection = MembershipCollection::APPROVED, $groupids = NULL, $yps = NULL, $ydt = NULL) {
        $ret = [];
        $groupids = $groupids ? $groupids : ($this->id ? [ $this-> id ] : NULL);

        $date = $ctx == NULL ? NULL : $this->dbhr->quote(date("Y-m-d H:i:s", $ctx['Added']));
        $addq = $ctx == NULL ? '' : (" AND (memberships.added < $date OR memberships.added = $date AND memberships.id < " . $this->dbhr->quote($ctx['id']) . ") ");
        # TODO We ought to search on firstname/lastname too, and handle word splits.  But this is sufficient for ModTools.
        $searchq = $search == NULL ? '' : (" AND (users_emails.email LIKE " . $this->dbhr->quote("%$search%") . " OR users.fullname LIKE " . $this->dbhr->quote("%$search%") . " OR users.yahooid LIKE " . $this->dbhr->quote("%$search%") . ") ");
        $searchq = $searchid ? (" AND users.id = " . $this->dbhr->quote($searchid) . " ") : $searchq;
        $groupq = $groupids ? " memberships.groupid IN (" . implode(',', $groupids) . ") " : " 1=1 ";
        $ypsq = $yps ? (" AND memberships.yahooPostingStatus = " . $this->dbhr->quote($yps)) : '';
        $ydtq = $ydt ? (" AND memberships.yahooDeliveryType = " . $this->dbhr->quote($ydt)) : '';

        # Collection filter.  If we're searching on a specific id then don't put it in.
        $collectionq = '';
        if (!$searchid) {
            if ($collection == MembershipCollection::SPAM) {
                # This collection is handled separately; we use the suspectcount field.
                #
                # This is to avoid moving members into a spam collection and then having to remember whether they
                # came from Pending or Approved.
                $collectionq = ' AND suspectcount > 0 ';
            } else if ($collection) {
                $collectionq = ' AND collection = ' . $this->dbhr->quote($collection) . ' ';
            }
        }

        $sql = "SELECT DISTINCT memberships.* FROM memberships LEFT JOIN users_emails ON memberships.userid = users_emails.userid INNER JOIN users ON users.id = memberships.userid WHERE $groupq $collectionq $addq $searchq $ypsq $ydtq ORDER BY memberships.added DESC, memberships.id DESC LIMIT $limit;";
        $members = $this->dbhr->preQuery($sql);

        $ctx = [ 'Added' => NULL ];

        foreach ($members as $member) {
            $u = new User($this->dbhr, $this->dbhm, $member['userid']);
            $thisone = $u->getPublic($groupids, TRUE);

            # We want to return an id of the membership, because the same user might be pending on two groups, and
            # a userid of the user's id.
            $thisone['userid'] = $thisone['id'];
            $thisone['id'] = $member['id'];

            $thisepoch = strtotime($member['added']);

            if ($ctx['Added'] == NULL || $thisepoch < $ctx['Added']) {
                $ctx['Added'] = $thisepoch;
            }

            $ctx['id'] = $member['id'];

            # We want to return both the email used on this group and any others we have.
            $emails = $u->getEmails();
            $emailid = $u->getEmailForGroup($member['groupid']);
            $email = NULL;
            $others = [];
            foreach ($emails as $anemail) {
                if ($anemail['id'] == $emailid) {
                    $email = $anemail['email'];
                }

                $others[] = $anemail;
            }

            $thisone['joined'] = ISODate($member['added']);

            # Defaults match ones in User.php
            $thisone['settings'] = $member['settings'] ? json_decode($member['settings'], TRUE) : [
                'showmessages' => 1,
                'showmembers' => 1,
                'pushnotify' => 1
            ];
            $thisone['settings']['configid'] = $member['configid'];
            $thisone['email'] = $email;
            $thisone['groupid'] = $member['groupid'];
            $thisone['otheremails'] = $others;
            $thisone['yahooDeliveryType'] = $member['yahooDeliveryType'];
            $thisone['yahooPostingStatus'] = $member['yahooPostingStatus'];
            $thisone['role'] = $u->getRole($member['groupid']);
            $thisone['joincomment'] = $member['joincomment'];

            $thisone['heldby'] = $member['heldby'];

            if (pres('heldby', $thisone)) {
                $u = new User($this->dbhr, $this->dbhm, $thisone['heldby']);
                $thisone['heldby'] = $u->getPublic();
            }

            $ret[] = $thisone;
        }

        return($ret);
    }

    public function setMembers($members, $collection, $synctime = NULL) {
        # This is used to set the whole of the membership list for a group.  It's only used when the group is
        # mastered on Yahoo, rather than by us.
        #
        # Slightly surprisingly, we don't need to do this in a transaction.  This is because:
        # - adding memberships on here which exist on Yahoo is fine to fail halfway through, we've just got some
        #   of them which is better than we were before we started, and the remainder will get added on the next
        #   sync.
        # - updating membership details from Yahoo to here is similarly fine
        # - deleting memberships on here which are no longer on Yahoo is a single statement, but even if that
        #   failed partway through it would still be fine; we'd have removed some of them which is better than
        #   nothing, and the remainder would get removed on the next sync.
        #
        # So as long as we only return a success when it's worked, we don't need to be in a transaction.  This is
        # good as it would be a large transaction and would hit lock timeouts.
        $ret = [
            'ret' => 0,
            'status' => 'Success'
        ];

        $synctime = $synctime ? $synctime : ISODate("@" . time());

        # Really don't want to remove all members by mistake, so don't allow it.
        if (!$members && $collection == MembershipCollection::APPROVED) { return($ret); }

        try {
            #$this->dbhm->setErrorLog(TRUE);

            # First make sure we have users set up for all the new members.  The input might have duplicate members;
            # save off the uid, and work out the role.
            $u = new User($this->dbhm, $this->dbhm);
            $roles = [];

            error_log("Scan members {$this->group['nameshort']}");
            foreach ($members as &$memb) {
                # Long
                set_time_limit(60);

                if (pres('email', $memb)) {
                    # First check if we already know about this user.  This is a good time to pick up duplicates -
                    # the same yahooid or yahooUserId means this is the same user, so we should merge.
                    #
                    # If the merge fails for some reason we'd still want to continue the sync.
                    $yuid = presdef('yahooid', $memb, NULL) ? $u->findByYahooId($memb['yahooid']) : NULL;
                    $yiduid = presdef('yahooUserId', $memb, NULL) ? $u->findByYahooUserId($memb['yahooUserId']) : NULL;
                    $emailinfo = $u->getIdForEmail($memb['email']);
                    $emailid = $emailinfo ? $emailinfo['userid'] : NULL;

                    $reason = "SetMembers {$this->group['nameshort']} - YahooId " . presdef('yahooid', $memb, '') . " = $yuid, YahooUserId " . presdef('yahooUserId', $memb, '') . " = $yiduid, Email {$memb['email']} = $emailid";

                    # Now merge any different ones.
                    if ($emailid && $yuid && $emailid != $yuid) {
                        $mergerc = $u->merge($emailid, $yuid, $reason);
                    }

                    if ($emailid && $yiduid && $emailid != $yiduid && $yiduid != $yuid) {
                        $mergerc = $u->merge($emailid, $yiduid, $reason);
                    }

                    # Pick a non-null one.
                    $uid = $emailid ? $emailid : ($yuid ? $yuid : $yiduid);
                    #error_log("uid $uid yuid $yuid yiduid $yiduid");

                    if (!$uid) {
                        # We don't - create them.
                        preg_match('/(.*)@/', $memb['email'], $matches);
                        $name = presdef('name', $memb, $matches[1]);
                        $uid = $u->create(NULL, NULL, $name, "During SetMembers for {$this->group['nameshort']}");

                        if (pres('yahooUserId', $memb)) {
                            $u->setPrivate('yahooUserId', $memb['yahooUserId']);
                        }
                    } else {
                        $u = new User($this->dbhr, $this->dbhm, $uid);
                    }

                    # Make sure that the email is associated with this user.  Note that this may be required even
                    # if we succeeded in our findByEmail above, as that may have found a different email with the
                    # same canon value.
                    #
                    # Don't flag it as a primary email otherwise we might override the one we have.
                    $memb['emailid'] = $u->addEmail($memb['email'], 0, FALSE);

                    $u = new User($this->dbhr, $this->dbhm, $uid);

                    if (pres('yahooUserId', $memb)) {
                        $u->setPrivate('yahooUserId', $memb['yahooUserId']);
                    }

                    # If we don't have a yahooid for this user, update it.  If we already have one, then stick with it
                    # to avoid updating a user with an old Yahoo id
                    if (pres('yahooid', $memb) && !$u->getPrivate('yahooid')) {
                        $u->setPrivate('yahooid', $memb['yahooid']);
                    }

                    # Remember the uid for later below.
                    $memb['uid'] = $uid;
                    $distinctmembers[$uid] = TRUE;

                    # Get the role.  We might have the same underlying user who is a member using multiple email addresses
                    # so we need to take the max role that they have.
                    $thisrole = User::ROLE_MEMBER;
                    if (pres('yahooModeratorStatus', $memb)) {
                        if ($memb['yahooModeratorStatus'] == 'MODERATOR') {
                            $thisrole = User::ROLE_MODERATOR;
                        } else if ($memb['yahooModeratorStatus'] == 'OWNER') {
                            $thisrole = User::ROLE_OWNER;
                        }
                    }

                    $role = pres($uid, $roles) ? $u->roleMax($roles[$uid], $thisrole) : $thisrole;

                    $roles[$uid] = $role;
                }
            }

            error_log("Scanned members {$this->group['nameshort']}");

            $me = whoAmI($this->dbhr, $this->dbhm);
            $myrole = $me ? $me->getRole($this->id) : User::ROLE_NONMEMBER;

            # Save off the list of members which currently exist, so that after we've processed the ones which currently
            # exist, we can remove any which should no longer be present.
            #
            # We only want the members upto the point where the sync started, otherwise we might remove a member who has
            # just joined.
            $mysqltime = date("Y-m-d H:i:s", strtotime($synctime));
            $this->dbhm->preExec("DROP TEMPORARY TABLE IF EXISTS syncdelete; CREATE TEMPORARY TABLE syncdelete (id INT UNSIGNED, PRIMARY KEY idkey(id));");
            $this->dbhm->preExec("INSERT INTO syncdelete (SELECT DISTINCT userid FROM memberships WHERE groupid = ? AND collection = '$collection' AND added < '$mysqltime');", [
                $this->id
            ]);

            $bulksql = '';
            $tried = 0;

            error_log("Update members {$this->group['nameshort']}");

            for ($count = 0; $count < count($members); $count++) {
                # Long
                set_time_limit(60);

                $member = $members[$count];
                if (pres('uid', $member)) {
                    $tried++;
                    $role = $roles[$member['uid']];

                    # Use a single SQL statement rather than the usual methods for performance reasons.  And then
                    # batch them up into groups because that performs better in a cluster.
                    $yps = presdef('yahooPostingStatus', $member, NULL);
                    $ydt = presdef('yahooDeliveryType', $member, NULL);
                    $joincomment = pres('joincomment', $member) ? $this->dbhm->quote($member['joincomment']) : 'NULL';

                    # Get any existing membership.
                    $sql = "SELECT * FROM memberships WHERE userid = ? AND groupid = ?;";
                    $membs = $this->dbhm->preQuery($sql, [$member['uid'], $this->id]);
                    $new = count($membs) == 0;

                    $added = pres('date', $member) ? ("'" . date("Y-m-d H:i:s", strtotime($member['date'])) . "'") : 'NULL';

                    if ($member['emailid']) {
                        if (count($membs) == 0) {
                            # Make sure the membership is present.  We don't want to REPLACE as that might lose settings.
                            # We also don't want to just do INSERT IGNORE as that doesn't perform well in clusters.
                            $sql = "INSERT IGNORE INTO memberships (userid, groupid, emailid, collection) VALUES ({$member['uid']}, {$this->id}, {$member['emailid']}, '$collection');";
                            $bulksql .= $sql;
                            $membs = [
                                [
                                    'role' => User::ROLE_MEMBER
                                ]
                            ];

                            # Make sure we have a history entry.
                            $sql = "SELECT * FROM memberships_history WHERE userid = ? AND groupid = ?;";
                            $hists = $this->dbhr->preQuery($sql, [$member['uid'], $this->id]);

                            if (count($hists) == 0) {
                                $sql = "INSERT INTO memberships_history (userid, groupid, collection, added) VALUES ({$member['uid']},{$this->id},'$collection',$added);";
                                $bulksql .= $sql;
                            }
                        }

                        # If we are promoting a member, then we can only promote as high as we are.  This prevents
                        # moderators setting owner status.
                        if ($role == User::ROLE_OWNER &&
                            $myrole != User::ROLE_OWNER &&
                            $membs[0]['role'] != User::ROLE_OWNER
                        ) {
                            $role = User::ROLE_MODERATOR;
                        }

                        # Now update with any new settings.  Having this if test looks a bit clunky but it means that
                        # when resyncing a group where most members have not changed settings, we can avoid many UPDATEs.
                        #
                        # This will have the effect of moving members between collections if required.
                        if ($new ||
                            $membs[0]['role'] != $role || $membs[0]['collection'] != $collection || $membs[0]['yahooPostingStatus'] != $yps || $membs[0]['yahooDeliveryType'] != $ydt || $membs[0]['joincomment'] != $joincomment || $membs[0]['emailid'] != $member['emailid'] || $membs[0]['added'] != $added) {
                            $sql = "UPDATE memberships SET role = '$role', collection = '$collection', yahooPostingStatus = " . $this->dbhm->quote($yps) .
                                ", yahooDeliveryType = " . $this->dbhm->quote($ydt) . ", joincomment = $joincomment, emailid = {$member['emailid']}, added = $added WHERE userid = " .
                                "{$member['uid']} AND groupid = {$this->id};";
                            $bulksql .= $sql;
                        }

                        # If this is a mod/owner, make sure the systemrole reflects that.
                        if ($role == User::ROLE_MODERATOR || $role == User::ROLE_OWNER) {
                            $sql = "UPDATE users SET systemrole = 'Moderator' WHERE id = {$member['uid']} AND systemrole = 'User';";
                            $bulksql .= $sql;
                        }

                        # Record that this member still exists by deleting their id from the temp table
                        $bulksql .= "DELETE FROM syncdelete WHERE id = {$member['uid']};";

                        if ($count > 0 && $count % 1000 == 0) {
                            # Do a chunk of work.  If this doesn't work correctly we'll end up with fewer members
                            # and fail the count below.  Or we'll have incorrect settings until the next sync, but
                            # that's ok - better than failing it.
                            error_log("Execute batch $count {$this->group['nameshort']}");
                            $this->dbhm->exec($bulksql);
                            error_log("Executed batch $count {$this->group['nameshort']}");
                            $bulksql = '';
                        }
                    }
                }
            }

            if ($bulksql != '') {
                # Do remaining SQL.  If this fails then we'll fail the count check below.
                $this->dbhm->exec($bulksql);
            }

            error_log("Updated members {$this->group['nameshort']}");

            # Delete any residual members.
            #
            # We need to log these deletes so that we can see why memberships disappear.
            $todeletes = $this->dbhm->preQuery("SELECT id FROM syncdelete;", [$this->id]);
            $meid = $me ? $me->getId() : NULL;
            foreach ($todeletes as $todelete) {
                # Long
                set_time_limit(60);

                $this->log->log([
                    'type' => Log::TYPE_GROUP,
                    'subtype' => Log::SUBTYPE_LEFT,
                    'user' => $todelete['id'],
                    'byuser' => $meid,
                    'groupid' => $this->id,
                    'text' => "Sync of whole $collection membership list"
                ]);
            }

            $this->dbhm->preExec("DELETE FROM memberships WHERE groupid = ? AND collection = '$collection' AND userid IN (SELECT id FROM syncdelete);", [$this->id]);
            $this->dbhm->preExec("DROP TEMPORARY TABLE syncdelete;");

            error_log("Tidied members {$this->group['nameshort']}");

            if ($collection == MessageCollection::APPROVED) {
                # Record the sync.
                $this->dbhm->preExec("UPDATE groups SET lastyahoomembersync = NOW() WHERE id = ?;", [$this->id]);
                $this->dbhm->preExec("REPLACE INTO memberships_yahoo (groupid, members) VALUES (?,?);", [$this->id, json_encode($members)]);
            }
        } catch (Exception $e) {
            $ret = [ 'ret' => 2, 'status' => "Sync failed with " . $e->getMessage() ];
            error_log(var_export($ret, TRUE));
        }

        return($ret);
    }

    public function setSettings($settings)
    {
        $str = json_encode($settings);
        $me = whoAmI($this->dbhr, $this->dbhm);
        $this->dbhm->preExec("UPDATE groups SET settings = ? WHERE id = ?;", [ $str, $this->id ]);
        $this->group['settings'] = $str;
        $this->log->log([
            'type' => Log::TYPE_GROUP,
            'subtype' => Log::SUBTYPE_EDIT,
            'groupid' => $this->id,
            'byuser' => $me ? $me->getId() : NULL,
            'text' => $this->getEditLog([
                'settings' => $settings
            ])
        ]);

        return(true);
    }

    public function getSetting($key, $def) {
        $settings = json_decode($this->group['settings'], true);
        return(presdef($key, $settings, $def));
    }

    private function getKey($message) {
        # Both pending and approved messages have unique IDs, though they are only unique within pending and approved,
        # not between them.
        #
        # It would be nice to believe in a world where Message-ID was unique.
        $key = NULL;
        if (pres('yahoopendingid', $message)) {
            $key = "P-{$message['yahoopendingid']}";
        } else if (pres('yahooapprovedid', $message)) {
            $key = "A-{$message['yahooapprovedid']}";
        }

        return($key);
    }

    public function correlate($collections, $messages) {
        $missingonserver = [];
        $missingonclient = [];

        # Check whether any of the messages in $messages are not present on the server or vice-versa.
        $supplied = [];
        $cs = [];

        # First find messages which are missing on the server, i.e. present in $messages but not
        # present in any of $collections.
        foreach ($collections as $collection) {
            $c = new MessageCollection($this->dbhr, $this->dbhm, $collection);
            $cs[] = $c;

            if ($collection = MessageCollection::APPROVED) {
                $this->dbhm->preExec("UPDATE groups SET lastyahoomessagesync = NOW() WHERE id = ?;", [
                    $this->id
                ]);
            }
        }

        if ($messages) {
            foreach ($messages as $message) {
                $key = $this->getKey($message);
                $supplied[$key] = true;

                $missing = true;

                foreach ($cs as $c) {
                    /** @var MessageCollection $c */
                    $id = NULL;

                    switch (($c->getCollection())) {
                        case MessageCollection::APPROVED:
                            $id = $c->findByYahooApprovedId($this->id, $message['yahooapprovedid']);
                            break;
                        case MessageCollection::PENDING:
                            $id = $c->findByYahooPendingId($this->id, $message['yahoopendingid']);
                            break;
                    }

                    if ($id) {
                        $missing = false;
                    }
                }

                if ($missing) {
                    $missingonserver[] = $message;
                }
            }
        }

        # Now find messages which are missing on the client, i.e. present in $collections but not present in
        # $messages.
        /** @var Collection $c */
        foreach ($cs as $c) {
            $sql = "SELECT id, fromaddr, yahoopendingid, yahooapprovedid, subject, date, messageid FROM messages INNER JOIN messages_groups ON messages.id = messages_groups.msgid AND messages_groups.groupid = ? AND messages_groups.collection = ? AND messages_groups.deleted = 0;";
            $ourmsgs = $this->dbhr->preQuery(
                $sql,
                [
                    $this->id,
                    $c->getCollection()
                ]
            );

            foreach ($ourmsgs as $msg) {
                $key = $this->getKey($msg);
                if (!array_key_exists($key, $supplied)) {
                    $missingonclient[] = [
                        'id' => $msg['id'],
                        'email' => $msg['fromaddr'],
                        'subject' => $msg['subject'],
                        'collection' => $c->getCollection(),
                        'date' => ISODate($msg['date']),
                        'messageid' => $msg['messageid'],
                        'yahoopendingid' => $msg['yahoopendingid'],
                        'yahooapprovedid' => $msg['yahooapprovedid']
                    ];
                }
            }
        }

        return ([$missingonserver, $missingonclient]);
    }

    public function getConfirmKey() {
        $key = randstr(32);
        $sql = "UPDATE groups SET confirmkey = ? WHERE id = ?;";
        $rc = $this->dbhm->preExec($sql, [ $key, $this->id ]);
        return($key);
    }

    public function createVoucher() {

        do {
            $voucher = randstr(20);
            $sql = "INSERT INTO vouchers (voucher) VALUES (?);";
            $rc = $this->dbhm->preExec($sql, [ $voucher ]);
        } while (!$rc);

        return($voucher);
    }

    public function redeemVoucher($voucher) {
        $ret = FALSE;
        $me = whoAmI($this->dbhr, $this->dbhm);
        $myid = $me ? $me->getId() : NULL;

        $sql = "SELECT * FROM vouchers WHERE voucher = ? AND used IS NULL;";
        $vs = $this->dbhr->preQuery($sql , [ $voucher ]);

        foreach ($vs as $v) {
            $this->dbhm->beginTransaction();

            $sql = "UPDATE groups SET licensed = CURDATE(), licenseduntil = CASE WHEN licenseduntil > CURDATE() THEN licenseduntil + INTERVAL 1 YEAR ELSE CURDATE() + INTERVAL 1 YEAR END WHERE id = ?;";
            $rc = $this->dbhm->preExec($sql, [ $this->id ]);

            if ($rc) {
                $sql = "UPDATE vouchers SET used = NOW(), userid = ?, groupid = ? WHERE id = ?;";
                $rc = $this->dbhm->preExec($sql, [
                    $myid,
                    $this->id,
                    $v['id']
                ]);

                if ($rc) {
                    $rc = $this->dbhm->commit();

                    if ($rc) {
                        $ret = TRUE;
                        $this->log->log([
                            'type' => Log::TYPE_GROUP,
                            'subtype' => Log::SUBTYPE_LICENSED,
                            'groupid' => $this->id,
                            'text' => "Using voucher $voucher"
                        ]);
                    }
                }
            }
        }

        return($ret);
    }

    # Default mailer is to use the standard PHP one, but this can be overridden in UT.
    private function mailer() {
        call_user_func_array('mail', func_get_args());
    }

    public function contact($from, $subject, $body) {
        $headers = "From: $from <$from>\r\n";

        $sql = "SELECT userid FROM memberships WHERE groupid = ? AND role IN ('Owner', 'Moderator');";
        $mods = $this->dbhr->preQuery($sql, [ $this->id ]);

        foreach ($mods as $mod) {
            $u = new User($this->dbhr, $this->dbhm, $mod['userid']);
            $email = $u->getEmails()[0];

            $this->mailer(
                $email['email'],
                $subject,
                $body,
                $headers,
                "-f$from"
            );
        }

        if ($this->group['onyahoo']) {
            # This group is on Yahoo - so mail the owner address too.
            $this->mailer(
                $this->getModsEmail(),
                $subject,
                $body,
                $headers,
                "-f$from"
            );
        }

        $this->mailer(
            $from,
            "Copy of message to {$this->group['nameshort']}:$subject",
            $body,
            $headers,
            "-f$from"
        );
    }

    public function listByType($type) {
        $typeq = $type ? "type = ?" : '1=1';
        $sql = "SELECT id, nameshort, namefull FROM groups WHERE $typeq ORDER BY nameshort;";
        $groups = $this->dbhr->preQuery($sql, [ $type ]);
        foreach ($groups as &$group) {
            $group['namedisplay'] = $group['namefull'] ? $group['namefull'] : $group['nameshort'];
        }

        return($groups);
   }
}