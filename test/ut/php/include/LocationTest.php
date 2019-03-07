<?php

if (!defined('UT_DIR')) {
    define('UT_DIR', dirname(__FILE__) . '/../..');
}
require_once UT_DIR . '/IznikTestCase.php';
require_once IZNIK_BASE . '/include/group/Group.php';
require_once IZNIK_BASE . '/include/misc/Location.php';
require_once IZNIK_BASE . '/include/message/Message.php';


/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class locationTest extends IznikTestCase {
    private $dbhr, $dbhm;

    protected function setUp() {
        parent::setUp ();

        global $dbhr, $dbhm;
        $this->dbhr = $dbhr;
        $this->dbhm = $dbhm;

        $dbhm->preExec("DELETE FROM groups WHERE nameshort = 'testgroup';");

        # We test around Tuvalu.  If you're setting up Tuvalu Freegle you may need to change that.
        $dbhm->preExec("DELETE FROM locations_grids WHERE swlat >= 8.3 AND swlat <= 8.7;");
        $dbhm->preExec("DELETE FROM locations_grids WHERE swlat >= 179.1 AND swlat <= 179.3;");
        $dbhm->preExec("DELETE FROM locations WHERE name LIKE 'Tuvalu%';");
        $dbhm->preExec("DELETE FROM locations WHERE name LIKE 'TV13%';");
        $dbhm->preExec("DELETE FROM locations WHERE name LIKE '??%';");
        for ($swlat = 8.3; $swlat <= 8.6; $swlat += 0.1) {
            for ($swlng = 179.1; $swlng <= 179.3; $swlng += 0.1) {
                $nelat = $swlat + 0.1;
                $nelng = $swlng + 0.1;

                # Use lng, lat order for geometry because the OSM data uses that.
                $dbhm->preExec("INSERT IGNORE INTO locations_grids (swlat, swlng, nelat, nelng, box) VALUES (?, ?, ?, ?, GeomFromText('POLYGON(($swlng $swlat, $nelng $swlat, $nelng $nelat, $swlng $nelat, $swlng $swlat))'));",
                    [
                        $swlat,
                        $swlng,
                        $nelat,
                        $nelng
                    ]);
            }
        }

        $grids = $dbhr->preQuery("SELECT * FROM locations_grids WHERE swlng >= 179.1 AND swlng <= 179.3;");
        foreach ($grids as $grid) {
            $sql = "SELECT id FROM locations_grids WHERE MBRTouches (GeomFromText('POLYGON(({$grid['swlng']} {$grid['swlat']}, {$grid['swlng']} {$grid['nelat']}, {$grid['nelng']} {$grid['nelat']}, {$grid['nelng']} {$grid['swlat']}, {$grid['swlng']} {$grid['swlat']}))'), box);";
            $touches = $dbhr->preQuery($sql);
            foreach ($touches as $touch) {
                $sql = "INSERT IGNORE INTO locations_grids_touches (gridid, touches) VALUES (?, ?);";
                $rc = $dbhm->preExec($sql, [ $grid['id'], $touch['id'] ]);
            }
        }
    }

    public function testBasic() {
        $l = new Location($this->dbhr, $this->dbhm);
        $id = $l->create(NULL, 'Tuvalu High Street', 'Road', 'POINT(179.2167 8.53333)');
        assertNotNull($id);
        assertEquals($id, $l->findByName('Tuvalu High Street'));
        $l = new Location($this->dbhr, $this->dbhm, $id);
        $atts = $l->getPublic();
        $this->log("Created loc " . var_export($atts, true));
        $gridid = $atts['gridid'];
        $grid = $l->getGrid();
        $this->log("Grid " . var_export($grid, true));
        assertEquals($gridid, $grid['id']);
        assertEquals(8.5, $grid['swlat']);
        assertEquals(179.2, $grid['swlng']);

        assertEquals(1, $l->delete());

        }

    public function testParents() {
        $l = new Location($this->dbhr, $this->dbhm);
        $pcid = $l->create(NULL, 'TV13', 'Postcode', 'POLYGON((179.2 8.5, 179.3 8.5, 179.3 8.6, 179.2 8.6, 179.2 8.5))');
        $this->log("Postcode id $pcid");
        assertNotNull($pcid);

        $areaid = $l->create(NULL, 'Tuvalu Central', 'Polygon', 'POLYGON((179.21 8.53, 179.21 8.54, 179.22 8.54, 179.22 8.53, 179.21 8.53, 179.21 8.53))', 0);
        $this->log("Area id $areaid");
        assertNotNull($areaid);

        $id = $l->create(NULL, 'Tuvalu High Street', 'Road', 'POINT(179.2167 8.53333)', 0);
        $this->log("Loc id $id");
        $l = new Location($this->dbhr, $this->dbhm, $id);
        $atts = $l->getPublic();
        assertEquals($areaid, $atts['areaid']);

        $id2 = $l->create(NULL, 'TV13 1HH', 'Postcode', 'POINT(179.2167 8.53333)', 0);
        $this->log("Full postcode id $id");
        $l = new Location($this->dbhr, $this->dbhm, $id2);
        $atts = $l->getPublic();
        assertEquals($areaid, $atts['areaid']);
        assertEquals($pcid, $atts['postcodeid']);

        }

    public function testError() {
        $dbconfig = array (
            'host' => SQLHOST,
            'port_read' => SQLPORT_READ,
            'port_mod' => SQLPORT_MOD,
            'user' => SQLUSER,
            'pass' => SQLPASSWORD,
            'database' => SQLDB
        );

        $l = new Location($this->dbhr, $this->dbhm);
        $mock = $this->getMockBuilder('LoggedPDO')
            ->setConstructorArgs([
                "mysql:host={$dbconfig['host']};dbname={$dbconfig['database']};charset=utf8",
                $dbconfig['user'], $dbconfig['pass'], array(), TRUE
            ])
            ->setMethods(array('preExec'))
            ->getMock();
        $mock->method('preExec')->willThrowException(new Exception());
        $l->setDbhm($mock);

        $id = $l->create(NULL, 'Tuvalu High Street', 'Road', 'POINT(179.2167 8.53333)');
        assertNull($id);

        }

    public function testSearch() {
        $g = Group::get($this->dbhr, $this->dbhm);
        $gid = $g->create('testgroup', Group::GROUP_REUSE);
        $this->log("Created group $gid");
        $g = Group::get($this->dbhr, $this->dbhm, $gid);

        $g->setPrivate('lng', 179.15);
        $g->setPrivate('lat', 8.4);

        $l = new Location($this->dbhr, $this->dbhm);
        $id = $l->create(NULL, 'Tuvalu High Street', 'Road', 'POINT(179.2167 8.53333)');

        $l = new Location($this->dbhr, $this->dbhm, $id);

        $res = $l->search("Tuvalu", $gid);
        $this->log(var_export($res, true));
        assertEquals(1, count($res));
        assertEquals($id, $res[0]['id']);

        # Find something which matches a word.
        $res = $l->search("high", $gid);
        assertEquals(1, count($res));
        assertEquals($id, $res[0]['id']);

        # Fail to find something which doesn't match a word.
        $res = $l->search("stre", $gid);
        assertEquals(0, count($res));

        $res = $l->search("high street", $gid);
        assertEquals(1, count($res));
        assertEquals($id, $res[0]['id']);

        # Make sure that exact matches trump prefix matches
        $id2 = $l->create(NULL, 'Tuvalu High', 'Road', 'POINT(179.2167 8.53333)');

        $res = $l->search("Tuvalu high", $gid, 1);
        assertEquals(1, count($res));
        assertEquals($id2, $res[0]['id']);

        # Find one where the valid location is contained within our search term
        $res = $l->search("in Tuvalu high street area", $gid, 1);
        assertEquals(1, count($res));
        assertEquals($id, $res[0]['id']);

        assertEquals(1, $l->delete());

        }

    public function testClosestPostcode() {
        $l = new Location($this->dbhr, $this->dbhm);

        if (!$l->findByName('PR3 2NE')) {
            $pcid = $l->create(NULL, 'PR3 2NE', 'Postcode', 'POINT(-2.64225600682264 53.8521694004918)');
        }

        $loc = $l->closestPostcode(53.856556299999994, -2.6401651999999998);
        assertEquals("PR3 2NE", $loc['name']);

        if (!$l->findByName('RM9 6SR')) {
            $pcid = $l->create(NULL, 'RM9 6SR', 'Postcode', 'POINT(0.14700179589836 51.531097253523)');
        }

        $loc = $l->closestPostcode(51.530687199999996, 0.146932);
        assertEquals("RM9 6SR", $loc['name']);

        }

    public function testGroupsNear() {
        $g = Group::get($this->dbhr, $this->dbhm);
        $gid = $g->create('testgroup', Group::GROUP_REUSE);
        $this->log("Created group $gid");
        $g = Group::get($this->dbhr, $this->dbhm, $gid);

        $g->setPrivate('lng', 179.15);
        $g->setPrivate('lat', 8.4);
        $g->setPrivate('poly', 'POLYGON((179.1 8.3, 179.2 8.3, 179.2 8.4, 179.1 8.4, 179.1 8.3))');

        $l = new Location($this->dbhr, $this->dbhm);
        $id = $l->create(NULL, 'Tuvalu High Street', 'Road', 'POINT(179.2167 8.53333)');

        $groups = $l->groupsNear(50);
        $this->log("Found groups near " . var_export($groups, TRUE));
        assertTrue(in_array($gid, $groups));

        # Shouldn't find unlisted groups
        $g->setPrivate('listable', 0);
        $groups = $l->groupsNear(50);
        $this->log("Shouldn't find groups near " . var_export($groups, TRUE));
        assertFalse(in_array($gid, $groups));

        }
}


