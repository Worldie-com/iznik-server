<?php

require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/misc/Entity.php');
require_once(IZNIK_BASE . '/include/group/Group.php');
require_once(IZNIK_BASE . '/include/group/CommunityEvent.php');
require_once(IZNIK_BASE . '/include/user/User.php');
require_once(IZNIK_BASE . '/include/message/Message.php');
require_once(IZNIK_BASE . '/include/chat/ChatRoom.php');
require_once(IZNIK_BASE . '/include/chat/ChatMessage.php');

use Facebook\FacebookSession;
use Facebook\FacebookJavaScriptLoginHelper;
use Facebook\FacebookCanvasLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;

class GroupFacebook {
    static public $publicatts = ['name', 'token', 'type', 'authdate', 'valid', 'msgid', 'msgarrival', 'eventid', 'sharefrom', 'token', 'groupid', 'id', 'lastupdated', 'uid' ];

    const TYPE_PAGE = 'Page';

    const ACTION_DO = 'Do';
    const ACTION_HIDE = 'Hide';
    const ACTION_SHARE_MESSAGE_TO_GROUP = 'ShareMessageToGroup';

    function __construct(LoggedPDO $dbhr, LoggedPDO $dbhm, $id = NULL, $fetched = NULL)
    {
        $this->dbhr = $dbhr;
        $this->dbhm = $dbhm;

        foreach (GroupFacebook::$publicatts as $att) {
            $this->$att = NULL;
        }

        $this->uid = $id;

        if ($id) {
            $groups = $fetched ? [ $fetched ] : $this->dbhr->preQuery("SELECT * FROM groups_facebook WHERE uid = ?;", [ $id ]);
            foreach ($groups as $group) {
                foreach (GroupFacebook::$publicatts as $att) {
                    $this->$att = $group[$att];
                }
            }
        }
    }

    public function getPublic() {
        $ret = [];
        foreach (GroupFacebook::$publicatts as $att) {
            $ret[$att] = $this->$att;
        }

        $ret['authdate'] = ISODate($ret['authdate']);
        $ret['msgarrival'] = ISODate($ret['msgarrival']);

        return($ret);
    }

    public function getFB($graffiti, $apptoken = FALSE) {
        #error_log("Get FB $graffiti");
        $fb = new Facebook\Facebook([
            'app_id' => $graffiti ? FBGRAFFITIAPP_ID : FBAPP_ID,
            'app_secret' => $graffiti ? FBGRAFFITIAPP_SECRET : FBAPP_SECRET
        ]);

        if ($apptoken) {
            # Use an app access token
            $this->token = $fb->getApp()->getAccessToken();
        }

        return($fb);
    }

    public function add($groupid, $token, $name, $id, $type = GroupFacebook::TYPE_PAGE) {
        $this->dbhm->preExec("INSERT INTO groups_facebook (groupid, name, id, token, authdate, type) VALUES (?,?,?,?,NOW(), ?) ON DUPLICATE KEY UPDATE name = ?, id = ?, token = ?, authdate = NOW(), valid = 1, type = ?;",
            [
                $groupid,
                $name,
                $id,
                $token,
                $type,
                $name,
                $id,
                $token,
                $type
            ]);

        $created = $this->dbhm->lastInsertId();
        $this->token = $token;

        $n = new PushNotifications($this->dbhr, $this->dbhm);
        $n->notifyGroupMods($groupid);

        return($created);
    }

    public function remove($uid) {
        $this->dbhm->preExec("DELETE FROM groups_facebook WHERE uid = ?;", [ $uid ]);
    }

    public function getPostsToShare($sharefrom, $since = "last week") {
        $fb = $this->getFB(TRUE, TRUE);
        $count = 0;

        # Get posts we might want to share.  This returns only posts by the page itself.
        try {
            $url = $sharefrom . "/feed?limit=100&&fields=id,link,message,type,caption,icon,name,full_picture";
            #error_log("Get from feed $url, {$this->token}");
            $ret = $fb->get($url, $this->token);
            #error_log("Got ok");

            $posts = $ret->getDecodedBody();

            foreach ($posts['data'] as $wallpost) {
                #error_log("Got " . json_encode($wallpost));
                $rc = $this->dbhm->preExec("INSERT IGNORE INTO groups_facebook_toshare (sharefrom, postid, data) VALUES (?,?,?);", [
                    $sharefrom,
                    $wallpost['id'],
                    json_encode($wallpost)
                ]);

                if ($rc) {
                    $id = $this->dbhm->lastInsertId();
                    $count++;

                    if ($id) {
                        # We only want one copy of this in our newsfeed because it's shown to everyone.
                        $n = new Newsfeed($this->dbhr, $this->dbhm);
                        $fid = $n->create(Newsfeed::TYPE_CENTRAL_PUBLICITY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, $id);
                    }
                }
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            error_log("Failed code $code message " . $e->getMessage() . " token " . $this->token);
        }

        return($count);
    }

    public function listSocialActions(&$ctx, $mindate = '7 days ago') {
        # We want posts which have been collected from the sharefrom page which have not already been shared, for
        # groups where we are a moderator.
        $mindate = date("Y-m-d H:i:s", strtotime($mindate));
        $me = whoAmI($this->dbhr, $this->dbhm);
        $ret = [];
        $dateq = $mindate ? " groups_facebook_toshare.date >= '$mindate' AND " : '';


        if ($me) {
            $minid = $ctx ? intval($ctx['id']) : 0;

            $modships = [];

            # Remove groups which aren't linked.
            $groups = $this->dbhr->preQuery("SELECT memberships.groupid FROM memberships INNER JOIN groups_facebook ON groups_facebook.groupid = memberships.groupid WHERE userid = ? AND role IN ('Owner', 'Moderator') AND valid = 1;",
                [
                    $me->getId()
                ]);

            foreach ($groups as $group) {
                # Only show social actions where we're an active mod.
                if ($me->activeModForGroup($group['groupid'])) {
                    $modships[] = $group['groupid'];
                }
            }

            if (count($modships) > 0) {
                # We want to find all Facebook pages where we haven't shared this post.  To make this scale better with
                # many groups we do a more complex query and then munge the data.
                #
                # We don't want groups = that's for posts, not social actions / publicity.
                $groupids = implode(',', $modships);
                $sql = "SELECT DISTINCT groups_facebook_toshare.*, groups_facebook.uid, 'Facebook' AS actiontype FROM groups_facebook_toshare 
INNER JOIN groups_facebook ON groups_facebook.sharefrom = groups_facebook_toshare.sharefrom AND valid = 1 
LEFT JOIN groups_facebook_shares ON groups_facebook_shares.postid = groups_facebook_toshare.postid AND groups_facebook_shares.uid = groups_facebook.uid 
WHERE 
$dateq 
groups_facebook_toshare.id > ?
AND groups_facebook.groupid IN ($groupids) 
AND groups_facebook_shares.postid IS NULL 
AND groups_facebook.type = 'Page' 
ORDER BY groups_facebook_toshare.id DESC;";
                $posts = $this->dbhr->preQuery($sql, [ $minid ]);

                $remaining = [];

                foreach ($posts as &$post) {
                    $ctx['id'] = $post['id'];

                    if (!array_key_exists($post['id'], $remaining)) {
                        # This is a new post which we've not considered so far.
                        $remaining[$post['id']] = $post;
                        unset($remaining[$post['id']]['uid']);
                        $remaining[$post['id']]['uids'] = [];
                        $data = json_decode($post['data'], TRUE);
                        $remaining[$post['id']]['full_picture'] = presdef('full_picture', $data, NULL);
                        $remaining[$post['id']]['message'] = presdef('message', $data, NULL);
                        $remaining[$post['id']]['type'] = presdef('type', $data, NULL);

                        if (preg_match('/(.*)_(.*)/', $post['postid'], $matches)) {
                            # Create the iframe version of the Facebook plugin.
                            $pageid = $matches[1];
                            $postid = $matches[2];
                            $remaining[$post['id']]['iframe'] = '<iframe src="https://www.facebook.com/plugins/post.php?href=https%3A%2F%2Fwww.facebook.com%2F' . $pageid . '%2Fposts%2F' . $postid . '%2F&width=auto&show_text=true&appId=' . FBGRAFFITIAPP_ID . '&height=500" width="500" height="500" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true"></iframe>';
                        }
                    }

                    # Add this Facebook page/group in for this post.
                    $remaining[$post['id']]['uids'][] = $post['uid'];
                }

                foreach ($remaining as $groupid => $post) {
                    $ret[] = $post;
                }
            }
        }

        return($ret);
    }

    public function performSocialAction($id) {
        $me = whoAmI($this->dbhr, $this->dbhm);
        $ret = FALSE;

        if ($me) {
            error_log("me");
            # We need to be a mod on the relevant group.
            $modships = $me->getModeratorships();

            if (count($modships) > 0) {
                error_log("Get");
                $groupids = implode(',', $modships);
                $sql = "SELECT DISTINCT groups_facebook_toshare.*, groups_facebook.type AS facebooktype, groups_facebook.uid, groups_facebook.groupid FROM groups_facebook_toshare INNER JOIN groups_facebook ON groups_facebook.sharefrom = groups_facebook_toshare.sharefrom AND groupid IN ($groupids) AND uid = ? AND groups_facebook_toshare.id = ?;";
                error_log("$sql, {$this->uid}, $id");
                $actions = $this->dbhr->preQuery($sql, [ $this->uid, $id ]);

                foreach ($actions as $action) {
                    error_log("mod");
                    # Whether or not this worked, remember that we've tried, so that we don't try again.
                    #error_log("Record INSERT IGNORE INTO groups_facebook_shares (uid, groupid, postid) VALUES ({$action['uid']},{$action['groupid']},{$action['postid']});");
                    $this->dbhm->preExec("INSERT IGNORE INTO groups_facebook_shares (uid, groupid, postid) VALUES (?,?,?);", [
                        $action['uid'],
                        $action['groupid'],
                        $action['postid']
                    ]);

                    $page = $action['facebooktype'] == GroupFacebook::TYPE_PAGE;
                    $fb = $this->getFB($page);

                    if ($page) {
                        # Like the original post.
                        try {
                            $res = $fb->post($action['postid'] . '/likes', [], $this->token);
                        } catch (Exception $e) {
                            # Some likes can fail when using the user access token because some posts are
                            # strangely not visible.  Unclear why.  But don't mark the token as invalid just for
                            # these.
                            error_log("Like failed with " . $e->getMessage());
                        }
                        #error_log("Like returned " . var_export($res, true));
                    }

                    try {
                        $a = explode('_', $action['postid']);
                        $params['link'] = 'https://www.facebook.com/' . $a[0] . '/posts/' . $a[1];
                        $result = $fb->post($this->id . '/feed', $params, $this->token);
                        error_log("Share via " . json_encode($params) . " returned " . var_export($result, TRUE));
                        $ret = TRUE;
                    } catch (Exception $e) {
                        error_log("Share failed with " . $e->getMessage());
                        $msg = $e->getMessage();

                        $this->dbhm->preExec("UPDATE groups_facebook SET valid = 0, lasterror = ?, lasterrortime = NOW() WHERE uid = ?", [
                            $msg,
                            $action['uid']
                        ]);
                    }
                }
            }
        }

        return $ret;
    }

    public function hideSocialAction($id) {
        $me = whoAmI($this->dbhr, $this->dbhm);
        if ($me) {
            # We need to be a mod on the relevant group.
            $modships = $me->getModeratorships();

            if (count($modships) > 0) {
                $groupids = implode(',', $modships);

                $sql = "SELECT DISTINCT groups_facebook_toshare.*, groups_facebook.type AS facebooktype, groups_facebook.uid, groups_facebook.groupid FROM groups_facebook_toshare INNER JOIN groups_facebook ON groups_facebook.sharefrom = groups_facebook_toshare.sharefrom AND groupid IN ($groupids) AND uid = ? AND groups_facebook_toshare.id = ?;";
                $actions = $this->dbhr->preQuery($sql, [ $this->uid, $id ]);

                foreach ($actions as $action) {
                    $this->dbhm->preExec("INSERT IGNORE INTO groups_facebook_shares (uid, groupid, postid, status) VALUES (?,?,?, 'Hidden');", [
                        $this->uid,
                        $action['groupid'],
                        $action['postid']
                    ]);
                }
            }
        }
    }

    public static function listForGroup($dbhr, $dbhm, $groupid) {
        $ids = [];
        $groups = $dbhr->preQuery("SELECT uid FROM groups_facebook WHERE groupid = ?;", [ $groupid ]);
        foreach ($groups as $group) {
            $ids[] = $group['uid'];
        }

        return($ids);
    }

    public static function listForGroups($dbhr, $dbhm, $gids, $token = FALSE) {
        $ret = [];

        if (count($gids)) {
            $groups = $dbhr->preQuery("SELECT " . implode(',', GroupFacebook::$publicatts) . " FROM groups_facebook WHERE groupid IN (" . implode(',', $gids) . ");");
            foreach ($groups as &$group) {
                if (!$token) {
                    unset($group['token']);
                }

                $ret[$group['groupid']][] = $group;
            }
        }

        return($ret);
    }
}
