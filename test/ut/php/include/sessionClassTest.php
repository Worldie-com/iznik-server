<?php
namespace Freegle\Iznik;

if (!defined('UT_DIR')) {
    define('UT_DIR', dirname(__FILE__) . '/../..');
}

require_once(UT_DIR . '/../../include/config.php');
require_once(UT_DIR . '/../../include/db.php');

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class sessionClassTest extends IznikTestCase {
    private $dbhr, $dbhm;

    protected function setUp() {
        parent::setUp ();

        global $dbhr, $dbhm;
        $this->dbhr = $dbhr;
        $this->dbhm = $dbhm;

        $dbhm->preExec("DELETE FROM users WHERE firstname = 'Test' AND lastname = 'User';");
        $_SESSION['id'] = NULL;
    }

    public function testBasic() {
        # Logged out
        $me = Session::whoAmI($this->dbhm, $this->dbhm);
        assertNull($me);

        $u = User::get($this->dbhm, $this->dbhm);
        $id = $u->create('Test', 'User', NULL);

        $s = new Session($this->dbhm, $this->dbhm);
        $ret = $s->create($id);

        # Verify it
        $ver = $s->verify($ret['id'], $ret['series'], $ret['token']);
        assertEquals($id, $ver);

        $_SESSION['id'] = NULL;

        assertNull($s->verify($id, $ret['series'] . 'z', $ret['token']));

        $me = Session::whoAmI($this->dbhm, $this->dbhm);
        assertNull($me);

        # Now fake the login
        $_SESSION['id'] = $id;
        $me = Session::whoAmI($this->dbhm, $this->dbhm);
        assertEquals($id, $me->getPrivate('id'));

        }

    public function testMisc() {
        # Can call this twice
        Session::prepareSession($this->dbhm, $this->dbhm);
        Session::prepareSession($this->dbhm, $this->dbhm);
        assertTrue(TRUE);

        }

    public function testCookie() {
        $u = User::get($this->dbhm, $this->dbhm);
        $id = $u->create('Test', 'User', NULL);

        $s = new Session($this->dbhm, $this->dbhm);
        $ret = $s->create($id);

        # Cookie should log us in
        $_SESSION['id'] = NULL;
        $_REQUEST['persistent'] = $ret;
        global $sessionPrepared;
        $sessionPrepared = FALSE;
        Session::prepareSession($this->dbhm, $this->dbhm);
        assertTrue($_SESSION['logged_in']);
        assertEquals($id, $_SESSION['id']);

        # ...repeatedly
        $_SESSION['id'] = NULL;
        $_REQUEST['persistent'] = $ret;
        global $sessionPrepared;
        $sessionPrepared = FALSE;
        Session::prepareSession($this->dbhm, $this->dbhm);
        assertTrue($_SESSION['logged_in']);
        assertEquals($id, $_SESSION['id']);

        # But not if the session has gone.
        $s->destroy($id, NULL);
        $_SESSION['logged_in'] = FALSE;
        Session::prepareSession($this->dbhm, $this->dbhm);
        assertFalse($_SESSION['logged_in']);
    }

    public function testRequestHeader() {
        $u = User::get($this->dbhm, $this->dbhm);
        $id = $u->create('Test', 'User', NULL);

        $s = new Session($this->dbhm, $this->dbhm);
        $ret = $s->create($id);

        $_SESSION['id'] = NULL;
        $_SESSION['persistent'] = NULL;
        $_REQUEST['persistent'] = NULL;
        $_SERVER['HTTP_Authorization'] = 'Iznik ' . json_encode($ret);
        error_log("Header " . $_SERVER['HTTP_Authorization']);
        global $sessionPrepared;
        $sessionPrepared = FALSE;
        Session::prepareSession($this->dbhm, $this->dbhm);
        assertTrue($_SESSION['logged_in']);
        assertEquals($id, $_SESSION['id']);
    }
}

