<?php
namespace Freegle\Iznik;


require_once(IZNIK_BASE . '/lib/geoPHP/geoPHP.inc');
require_once(IZNIK_BASE . '/lib/GreatCircle.php');

class Location extends Entity
{
    const NEARBY = 50; // In miles.
    const QUITENEARBY = 15; // In miles.
    const TOO_LARGE = 0.3;
    const TOO_SMALL = 0.001;

    /** @var  $dbhm LoggedPDO */
    var $publicatts = array('id', 'osm_id', 'name', 'type', 'popularity', 'gridid', 'postcodeid', 'areaid', 'lat', 'lng', 'maxdimension');

    /** @var  $log Log */
    private $log;
    var $loc;

    function __construct(LoggedPDO $dbhr, LoggedPDO $dbhm, $id = NULL, $fetched = NULL)
    {
        $this->fetch($dbhr, $dbhm, $id, 'locations', 'loc', $this->publicatts, $fetched);
        $this->log = new Log($dbhr, $dbhm);
    }

    /**
     * @param LoggedPDO $dbhm
     */
    public function setDbhm($dbhm)
    {
        $this->dbhm = $dbhm;
    }

    public function canon($str) {
        # There are some commom abbreviations which people might use, which we should expand.
        $str = preg_replace('/^St\b/', 'Saint', $str);
        $str = preg_replace('/\bSt\b/', 'Street', $str);
        $str = preg_replace('/\bRd\b/', 'Road', $str);
        $str = preg_replace('/\bAvenue\b/', 'Av', $str);
        $str = preg_replace('/\bDr\b/', 'Drive', $str);
        $str = preg_replace('/\bLn\b/', 'Lane', $str);
        $str = preg_replace('/\bPl\b/', 'Place', $str);
        $str = preg_replace('/\bSq\b/', 'Square', $str);
        $str = preg_replace('/\bCls\b/', 'Close', $str);
        $str = strtolower(preg_replace("/[^A-Za-z0-9]/", '', $str));

        return($str);
    }

    public function create($osm_id, $name, $type, $geometry, $osmparentsonly = 1, $place = 0)
    {
        try {
            # TODO osm_place is really just place.
            $rc = $this->dbhm->preExec("INSERT INTO locations (osm_id, name, type, geometry, canon, osm_place, maxdimension) VALUES (?, ?, ?, GeomFromText(?), ?, ?, GetMaxDimension(GeomFromText(?)))",
                [$osm_id, $name, $type, $geometry, $this->canon($name), $place, $geometry]);
            $id = $this->dbhm->lastInsertId();

            $this->dbhm->preExec("INSERT INTO locations_spatial (locationid, geometry) VALUES (?, GeomFromText(?));", [
                $id,
                $geometry
            ]);
            
            if ($rc) {
                # Although this is something we can derive from the geometry, it speeds things up a lot to have it cached.
                $rc = $this->dbhm->preExec("UPDATE locations SET lng = X(ST_Centroid(geometry)), lat = Y(ST_Centroid(geometry)) WHERE id = ?;",
                    [ $id ]);
            }

            $sql = "SELECT locations_grids.id AS gridid FROM `locations` INNER JOIN locations_grids ON locations.id = ? AND MBRIntersects(locations.geometry, locations_grids.box) LIMIT 1;";
            #error_log("SELECT locations_grids.id AS gridid FROM `locations` INNER JOIN locations_grids ON locations.id = $id AND MBRIntersects(locations.geometry, locations_grids.box) LIMIT 1;");
            $grids = $this->dbhr->preQuery($sql, [ $id ]);
            foreach ($grids as $grid) {
                $gridid = $grid['gridid'];
                $sql = "UPDATE locations SET gridid = ? WHERE id = ?;";
                $this->dbhm->preExec($sql, [ $grid['gridid'], $id ]);
            }

            # Set any area and postcode for this new location.
            $this->setParents($id, $osmparentsonly);

            if ($type == 'Polygon') {
                # We might have postcodes which should now map to this new area rather than wherever they mapped
                # previously.
                $g = new \geoPHP();
                $p = $g->load($geometry);
                $bbox = $p->getBBox();
                #error_log("Bounding box " . var_export($bbox, TRUE));

                # We need to decide which postcodes to scan.  Choose a slightly arbitrary larger box.
                $swlat = $bbox['miny'] - 0.01;
                $nelat = $bbox['maxy'] + 0.01;
                $swlng = $bbox['minx'] - 0.01;
                $nelng = $bbox['maxx'] + 0.01;

                $sql = "SELECT * FROM locations WHERE $swlat <= lat AND lat <= $nelat AND $swlng <= lng AND lng <= $nelng AND type = 'Postcode' AND LOCATE(' ', name) > 0;";
                #error_log("Find postcodes for new location $sql");
                $locs = $this->dbhr->preQuery($sql);
                foreach ($locs as $loc) {
                    if ($loc['id'] != $id) {
                        #error_log("Re-evaluate {$loc['id']} {$loc['name']}");
                        $this->setParents($loc['id'], 1, $id);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Location create exception " . $e->getMessage() . " " . $e->getTraceAsString());
            $id = NULL;
            $rc = 0;
        }

        if ($rc && $id) {
            $this->fetch($this->dbhm, $this->dbhm, $id, 'locations', 'loc', $this->publicatts);
            $this->log->log([
                'type' => Log::TYPE_LOCATION,
                'subtype' => Log::SUBTYPE_CREATED,
                'user' => $id,
                'text' => $name
            ]);

            return ($id);
        } else {
            return (NULL);
        }
    }

    public function setParents($id, $osmonly = 1, $areaid = NULL) {
        # We use the write DB handle because we don't want to waste time querying or cluttering our cache with this
        # info, which is unlikely to be cached effectively.
        #
        # For each location, we also want to store the area and first-part-postcode which this location is within.
        #
        # This allows us to standardise subjects on groups.
        $sql = "SELECT name, postcodeid, areaid, lat, lng, type, gridid, CASE WHEN ourgeometry IS NOT NULL THEN ourgeometry ELSE geometry END AS geometry FROM locations WHERE id = ?;";
        $locs = $this->dbhm->preQuery($sql, [ $id ]);
        #$this->dbhm->setErrorLog(TRUE);

        if (count($locs) > 0) {
            $loc = $locs[0];

            $p = strpos($loc['name'], ' ');

            if ($loc['type'] == 'Postcode' && $p !== FALSE) {
                # This is a full postcode - find the parent postcode.
                $sql = "SELECT id FROM locations WHERE name LIKE ? AND type = 'Postcode';";
                $pcs = $this->dbhm->preQuery($sql, [ substr($loc['name'], 0, $p) ]);
                foreach ($pcs as $pc) {
                    if ($loc['postcodeid'] != $pc['id']) {
                        $this->dbhm->preExec("UPDATE locations SET postcodeid = ? WHERE id = ?;",
                            [
                                $pc['id'],
                                $id
                            ]);
                    }
                }
            }

            if (!$areaid) {
                if ($loc['areaid']) {
                    # See if the existing area is correct.
                    $sql = "SELECT GetMaxDimension(CASE WHEN ourgeometry IS NOT NULL THEN ourgeometry ELSE geometry END) AS max, ST_Contains(CASE WHEN ourgeometry IS NOT NULL THEN ourgeometry ELSE geometry END, ?) AS within FROM locations LEFT OUTER JOIN locations_excluded ON locations.id = locations_excluded.locationid WHERE locations.id = ? AND locations_excluded.locationid IS NULL;";
                    $withins = $this->dbhr->preQuery($sql, [
                        $loc['geometry'],
                        $loc['areaid']
                    ]);

                    foreach ($withins as $within) {
                        if ($within['within'] && $within['max'] < Location::TOO_LARGE) {
                            $areaid = $loc['areaid'];
                        }
                    }
                }

                if (!$areaid) {
                    # Now that we're on 5.7 we have spatial indexing, which makes this a lot easier.  We create a
                    # small polygon round the location we're interested in, and then step it outwards until we
                    # overlap a location.
                    #
                    # TODO possibly we can completely remove grid stuff now.
                    $swlat = round($loc['lat'], 2) - 0.1;
                    $swlng = round($loc['lng'], 2) - 0.1;
                    $nelat = round($loc['lat'], 2) + 0.1;
                    $nelng = round($loc['lng'], 2) + 0.1;

                    $count = 0;

                    do {
                        $poly = "POLYGON(($swlng $swlat, $swlng $nelat, $nelng $nelat, $nelng $swlat, $swlng $swlat))";

                        # Exclude locations which are very large, e.g. Greater London or too small (probably just a
                        # single building.
                        $sql = "SELECT locations.name, locations.geometry, locations.ourgeometry, locations.id, AsText(locations_spatial.geometry) AS geom, ST_Contains(locations_spatial.geometry, POINT({$loc['lng']},{$loc['lat']})) AS within, ST_Distance(locations_spatial.geometry, POINT({$loc['lng']},{$loc['lat']})) AS dist FROM locations_spatial INNER JOIN  `locations` ON locations.id = locations_spatial.locationid LEFT OUTER JOIN locations_excluded ON locations_excluded.locationid = locations.id WHERE MBRWithin(locations_spatial.geometry, GeomFromText('$poly')) AND osm_place = $osmonly AND type != 'Postcode' AND ST_Dimension(locations_spatial.geometry) = 2 AND locations_excluded.locationid IS NULL HAVING id != $id AND GetMaxDimension(CASE WHEN ourgeometry IS NOT NULL THEN ourgeometry ELSE geometry END) < " . Location::TOO_LARGE . " AND GetMaxDimension(CASE WHEN ourgeometry IS NOT NULL THEN ourgeometry ELSE geometry END) > " . Location::TOO_SMALL . " ORDER BY within DESC, dist ASC LIMIT 1;";
                        $nearbyes = $this->dbhr->preQuery($sql);

                        #error_log($sql . " gives " . var_export($nearbyes));

                        if (count($nearbyes) === 0) {
                            $swlat -= 0.1;
                            $swlng -= 0.1;
                            $nelat += 0.1;
                            $nelng += 0.1;
                            $count++;
                        }
                    } while (count($nearbyes) == 0 && $count < 100);

                    if (count($nearbyes) > 0) {
                        $areaid = $nearbyes[0]['id'];
                        #error_log("{$loc['name']} choose areaid #$areaid {$nearbyes[0]['name']}");
                    }
                }
            }

            if ($areaid && $loc['areaid'] != $areaid) {
                #error_log("Set $id to have area $areaid");
                $sql = "UPDATE locations SET areaid = $areaid WHERE id = $id;";
                $this->dbhm->preExec($sql);
            }
        }
    }

    public function getGrid() {
        $ret = NULL;
        $sql = "SELECT * FROM locations_grids WHERE id = ?;";
        $locs = $this->dbhr->preQuery($sql, [ $this->loc['gridid'] ]);
        foreach ($locs as $loc) {
            $ret = $loc;
        }

        return($ret);
    }

    public function search($term, $groupid, $limit = 10) {
        $limit = intval($limit);

        # Remove any weird characters.
        $term = preg_replace("/[^[:alnum:][:space:]]/u", '', $term);
        
        # We have a large table of locations.  We want to search within the ones which are close to this group, so
        # we look in the same or adjacent grid squares.
        $termt = trim($term);
        $gridids = [];
        $ret = [];

        # We want to exclude some locations.
        $exclgroup = " LEFT JOIN locations_excluded ON locations.id = locations_excluded.locationid ";

        # Exclude all numeric locations (there are some in OSM).  Also exclude amenities and shops, otherwise we get
        # some silly mappings (e.g. London).
        $exclude = " AND NOT canon REGEXP '^-?[0-9]+$' AND osm_amenity = 0 AND osm_shop = 0 AND locations_excluded.locationid IS NULL ";

        # Find the gridid for the group.
        $sql = "SELECT locations_grids.* FROM locations_grids INNER JOIN groups ON groups.id = ? AND swlat <= groups.lat AND swlng <= groups.lng AND nelat > groups.lat AND nelng > groups.lng;";
        #error_log("$sql $groupid");
        $grids = $this->dbhr->preQuery($sql, [
            $groupid
        ]);
        
        foreach ($grids as $grid) {
            $gridids[] = $grid['id'];

            # Now find grids within approximately 30 miles of that.
            #$sql = "SELECT id FROM locations_grids WHERE haversine(" . ($grid['swlat'] + 0.05) . ", " . ($grid['swlng'] + 0.05) . ", swlat + 0.05, swlng + 0.05) < 30;";
            $sql = "SELECT id FROM locations_grids WHERE ABS(" . $grid['swlat'] . " - swlat) <= 0.4 AND ABS(" . $grid['swlng']. " - swlng) <= 0.4;";
            $neighbours = $this->dbhr->preQuery($sql);
            foreach ($neighbours as $neighbour) {
                $gridids[] = $neighbour['id'];
            }

            # Now we have a list of gridids within which we want to search.
            #error_log("Check grids " . implode(',', $gridids));
            if (count($gridids) > 0) {
                # First we do a simple match.  If the location is correct, that will find it quickly.
                $term2 = $this->dbhr->quote($this->canon($term));
                $sql = "SELECT locations.* FROM locations $exclgroup WHERE canon = $term2 AND gridid IN (" . implode(',', $gridids) . ") $exclude ORDER BY LENGTH(canon) ASC, popularity DESC LIMIT $limit;";
                $locs = $this->dbhr->preQuery($sql);

                foreach ($locs as $loc) {
                    $ret[] = $loc;
                    $limit--;
                }

                # Look for a known location which contains the location we've specified.  This will scan quite a lot of
                # locations, because that kind of search can't use the name index, but it is restricted by grids and therefore won't be
                # appalling.
                #
                # We want the matches that are closest in length to the term we're trying to match first
                # (you might have 'Stockbridge' and 'Stockbridge Church Of England Primary School'), then ordered
                # by most popular.
                if ($limit > 0) {
                    $sql = "SELECT locations.* FROM locations FORCE INDEX (gridid) $exclgroup WHERE LENGTH(name) >= " . strlen($termt) . " AND name REGEXP CONCAT('[[:<:]]', " . $this->dbhr->quote($termt) . ", '[[:>:]]') AND gridid IN (" . implode(',', $gridids) . ") $exclude ORDER BY ABS(LENGTH(name) - " . strlen($term) . ") ASC, popularity DESC LIMIT $limit;";
                    $locs = $this->dbhr->preQuery($sql);

                    foreach ($locs as $loc) {
                        $ret[] = $loc;
                        $limit--;
                    }
                }

                if ($limit > 0) {
                    # We didn't find as many as we wanted.  It's possible that the location text actually contains
                    # two locations, most commonly a place and a postcode.  So do an (even slower) search to find
                    # locations in our table which appear somewhere in the subject.  Ignore very short ones or
                    # ones which are less than half the length of what we're looking for (to speed up the search).
                    #
                    # We also order to find the one most similar in length.
                    $sql = "SELECT locations.* FROM locations $exclgroup WHERE gridid IN (" . implode(',', $gridids) . ") AND LENGTH(canon) > 2 AND LENGTH(name) >= " . strlen($termt)/2 . " AND " . $this->dbhr->quote($termt) . " REGEXP CONCAT('[[:<:]]', name, '[[:>:]]') $exclude ORDER BY ABS(LENGTH(name) - " . strlen($term) . "), GetMaxDimension(locations.geometry) ASC, popularity DESC LIMIT $limit;";
                    $locs = $this->dbhr->preQuery($sql);

                    foreach ($locs as $loc) {
                        $ret[] = $loc;
                        $limit--;
                    }
                }

                if ($limit > 0) {
                    # We still didn't find as many results as we wanted.  Do a (slow) search using a Damerau-Levenshtein
                    # distance function to spot typos, transpositions, spurious spaces etc.
                    $sql = "SELECT locations.* FROM locations $exclgroup WHERE gridid IN (" . implode(',', $gridids) . ") AND DAMLEVLIM(`canon`, " .
                        $this->dbhr->quote($this->canon($term)) . ", " . strlen($term) . ") < 2 $exclude ORDER BY ABS(LENGTH(canon) - " . strlen($term) . ") ASC, popularity DESC LIMIT $limit;";
                    #error_log("DamLeve $sql");
                    $locs = $this->dbhr->preQuery($sql);

                    foreach ($locs as $loc) {
                        $ret[] = $loc;
                        $limit--;
                    }
                }

            }
        }

        # Don't return duplicates.
        $ret = array_unique($ret, SORT_REGULAR);

        # We might have acquired a few too many.
        $ret = array_slice($ret, 0, 10);

        return($ret);
    }

    public function locsForGroup($groupid) {
        # We have a large table of locations.  We want to return the ones which are close to this group, so
        # we look in the same or adjacent grid squares.
        $gridids = [];
        $ret = [];

        # Find the gridid for the group.
        $sql = "SELECT locations_grids.* FROM locations_grids INNER JOIN groups ON groups.id = ? AND swlat <= groups.lat AND swlng <= groups.lng AND nelat > groups.lat AND nelng > groups.lng;";
        $grids = $this->dbhr->preQuery($sql, [
            $groupid
        ]);

        foreach ($grids as $grid) {
            $gridids[] = $grid['id'];

            # Now find grids which touch that.  That avoids issues where our group is near the boundary of a grid square.
            $sql = "SELECT touches FROM locations_grids_touches WHERE gridid = ?;";
            $neighbours = $this->dbhr->preQuery($sql, [ $grid['id'] ]);
            foreach ($neighbours as $neighbour) {
                $gridids[] = $neighbour['touches'];
            }

            # Now we have a list of gridids within which we want to find locations.
            #error_log("Got gridids " . var_export($gridids, TRUE));
            if (count($gridids) > 0) {
                $sql = "SELECT locations.* FROM locations WHERE gridid IN (" . implode(',', $gridids) . ") AND LENGTH(TRIM(name)) > 0 ORDER BY popularity ASC;";
                #error_log("Get locs in grids $sql");
                $ret = $this->dbhr->preQuery($sql);
            }
        }

        return($ret);
    }

    public function exclude($groupid, $userid, $byname = FALSE) {
        # We want to exclude a specific location.  Potentially exclude all locations with the same name as this one; our DB has
        # duplicate names.
        $sql = $byname ? "SELECT id FROM locations WHERE name = (SELECT name FROM locations WHERE id = ?);" : "SELECT id FROM locations WHERE id = ?;";
        $locs = $this->dbhr->preQuery($sql, [ $this->id ]);

        foreach ($locs as $loc) {
            # Mark location as blocked for this group, so it won't be suggested again.
            $sql = "REPLACE INTO locations_excluded (locationid, groupid, userid) VALUES (?,?,?);";
            $rc = $this->dbhm->preExec($sql, [
                $loc['id'],
                $groupid,
                $userid
            ]);
        }

        # We might have some postcodes which are mapped to this area.  Remap them.
        $sql = "SELECT id FROM locations WHERE areaid = ?;";
        $locs = $this->dbhr->preQuery($sql, [ $this->id ]);
        foreach ($locs as $loc) {
            $this->setParents($loc['id']);
        }

        # Not the end of the world if this doesn't work.
        return(TRUE);
    }

    public function delete()
    {
        $me = Session::whoAmI($this->dbhr, $this->dbhm);

        $rc = $this->dbhm->preExec("DELETE FROM locations WHERE id = ?;", [$this->id]);
        if ($rc) {
            $this->log->log([
                'type' => Log::TYPE_LOCATION,
                'subtype' => Log::SUBTYPE_DELETED,
                'user' => $this->id,
                'byuser' => $me ? $me->getId() : NULL,
                'text' => $this->loc['name']
            ]);
        }

        return ($rc);
    }

    public static function getDistance($latitude1, $longitude1, $latitude2, $longitude2)
    {
        $earth_radius = 3956;

        $dLat = deg2rad($latitude2 - $latitude1);
        $dLon = deg2rad($longitude2 - $longitude1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        $d = $earth_radius * $c;

        return $d;
    }

    public function closestPostcode($lat, $lng) {
        # Find the grids nearest to this lat/lng.  We use our spatial index to narrow down the locations to search
        # through; we start off very close to the point and work outwards. That way in densely postcoded areas we
        # have a fast query, and in less dense areas we have some queries which are quick but don't return anything.
        $scan = 0.00001953125;
        $ret = NULL;

        do {
            $sql = "SELECT id, name, areaid, lat, lng, ST_distance(locations_spatial.geometry, Point(?, ?)) AS dist FROM locations_spatial INNER JOIN locations ON locations.id = locations_spatial.locationid WHERE MBRContains(envelope(linestring(point(?, ?), point(?, ?))), locations_spatial.geometry) AND type = 'Postcode' ORDER BY dist ASC, ST_AREA(locations_spatial.geometry) ASC LIMIT 1;";
            #error_log("SELECT id, name, areaid, lat, lng, ST_distance(locations_spatial.geometry, Point($lng, $lng)) AS dist FROM locations_spatial INNER JOIN locations ON locations.id = locations_spatial.locationid WHERE MBRContains(envelope(linestring(point(" . ($lng - $scan) . ", " . ($lat - $scan) . "), point(" . ($lng + $scan) . ", "  . ($lat + $scan) . "))), locations_spatial.geometry) ORDER BY dist ASC LIMIT 1;");
            $locs = $this->dbhr->preQuery($sql, [
                $lng,
                $lat,
                $lng - $scan,
                $lat - $scan,
                $lng + $scan,
                $lat + $scan
            ]);

            if (count($locs) == 1) {
                $ret = $locs[0];

                if ($ret['areaid']) {
                    $l = new Location($this->dbhr, $this->dbhm, $ret['areaid']);
                    $ret['area'] = $l->getPublic();
                    unset($ret['areaid']);
                }

                $l = new Location($this->dbhr, $this->dbhm, $ret['id']);
                $ret['groupsnear'] = $l->groupsNear(Location::NEARBY, TRUE);
                break;
            }

            $scan *= 2;
        } while ($scan <= 0.2);

        return($ret);
    }

    public function groupsNear($radius = Location::NEARBY, $expand = FALSE, $limit = 10) {
        $limit = intval($limit);

        # To make this efficient we want to use the spatial index on polyindex.  But our groups are not evenly
        # distributed, so if we search immediately upto $radius, which is the maximum we need to cover, then we
        # will often have to scan many more groups than we need in order to determine the closest groups
        # (via the LIMIT clause), and this may be slow even with a spatial index.
        #
        # For example, searching in London will find ~120 groups within 50 miles, of which we are only interested
        # in 10, and the query will take ~0.03s.  If we search within 4 miles, that will typically find what we
        # need and the query takes ~0.00s.
        #
        # So we step up, using a bounding box that covers the point and radius and searching based on the lat/lng
        # centre of the group.  That's much faster.  But (infuriatingly) there are some groups which are so large that
        # the centre of the group is further away than the centre of lots of other groups, and that means that
        # we don't find the correct group.  So to deal with such groups we have an alt lat/lng which we can set to
        # be somewhere else, effectively giving the group two "centres".  This is a fudge which clearly wouldn't
        # cope with arbitrary geographies or hyperdimensional quintuple manifolds or whatever, but works ok for our
        # little old UK reuse network.
        $currradius = round($radius / 16 + 0.5, 0);
        
        do {
            $ne = \GreatCircle::getPositionByDistance(sqrt($currradius*$currradius*2)*1609.34, 45, $this->loc['lat'], $this->loc['lng']);
            $sw = \GreatCircle::getPositionByDistance(sqrt($currradius*$currradius*2)*1609.34, 225, $this->loc['lat'], $this->loc['lng']);

            $box = "GeomFromText('POLYGON(({$sw['lng']} {$sw['lat']}, {$sw['lng']} {$ne['lat']}, {$ne['lng']} {$ne['lat']}, {$ne['lng']} {$sw['lat']}, {$sw['lng']} {$sw['lat']}))')";

            # We order by the distance to the group polygon (dist), rather than to the centre (hav), because that
            # reflects which group you are genuinely closest to.
            #
            # Favour groups hosted by us if there's a tie.
            $sql = "SELECT id, nameshort, ST_distance(POINT({$this->loc['lng']}, {$this->loc['lat']}), polyindex) * 111195 * 0.000621371 AS dist, haversine(lat, lng, {$this->loc['lat']}, {$this->loc['lng']}) AS hav, CASE WHEN altlat IS NOT NULL THEN haversine(altlat, altlng, {$this->loc['lat']}, {$this->loc['lng']}) ELSE NULL END AS hav2 FROM groups WHERE MBRIntersects(polyindex, $box) AND publish = 1 AND listable = 1 HAVING (hav IS NOT NULL AND hav < $currradius OR hav2 IS NOT NULL AND hav2 < $currradius) ORDER BY dist ASC, hav ASC, external ASC LIMIT $limit;";
            #error_log("Find near $sql");
            $groups = $this->dbhr->preQuery($sql);

            $ret = [];

            foreach ($groups as $group) {
                if ($expand) {
                    $g = Group::get($this->dbhr, $this->dbhm, $group['id']);
                    $thisone = $g->getPublic();

                    $thisone['distance'] = $group['hav'];
                    $thisone['polydist'] = $group['dist'];

                    $ret[] = $thisone;
                } else {
                    $ret[] = $group['id'];
                }
            }
            
            $currradius *= 2;
        } while (count($ret) < $limit && $currradius <= $radius);

        return($ret);
    }

    public function typeahead($query, $limit = 10, $near = TRUE, $postcode = TRUE) {
        # We want to select full postcodes (with a space in them)
        $stripped = preg_replace('/\s/', '', $query);
        $postcodeq = $postcode ? " AND name LIKE '% %' AND type = 'Postcode'" : '';
        $sql = "SELECT * FROM locations WHERE canon LIKE ? $postcodeq LIMIT $limit;";
        $pcs = $this->dbhr->preQuery($sql, [
            "$stripped%"
        ]);
        $ret = [];
        foreach ($pcs as $pc) {
            $thisone = [];
            foreach ($this->publicatts as $att) {
                $thisone[$att] = $pc[$att];
            }

            if ($near && strpos($pc['name'], ' ') !== FALSE) {
                // Only return groups near for full postcode match.
                $l = new Location($this->dbhr, $this->dbhm, $pc['id']);
                $thisone['groupsnear'] = $l->groupsNear(Location::NEARBY, TRUE);
            }

            if ($thisone['areaid']) {
                $l = new Location($this->dbhr, $this->dbhm, $thisone['areaid']);
                $thisone['area'] = $l->getPublic();
                unset($thisone['areaid']);
            }

            $ret[] = $thisone;
        }

        if (count($ret) === 1) {
            # Just one; worth recording the popularity.
            $this->dbhm->background("UPDATE locations SET popularity = popularity + 1 WHERE id = {$ret[0]['id']}");
        }

        return($ret);
    }

    public function findByName($query)
    {
        $canon = $this->canon($query);
        $sql = "SELECT * FROM locations WHERE canon LIKE ? LIMIT 1;";
        $locs = $this->dbhr->preQuery($sql, [$canon]);
        return (count($locs) == 1 ? $locs[0]['id'] : NULL);
    }

    public function geomAsText() {
        $locs = $this->dbhr->preQuery("SELECT CASE WHEN ourgeometry IS NOT NULL THEN AsText(ourgeometry) ELSE AsText(geometry) END AS geomtext FROM locations WHERE id = ?;", [ $this->id ]);
        $ret = count($locs) == 1 ? $locs[0]['geomtext'] : NULL;
        return($ret);
    }

    public function remapPostcodes($val, $gridid) {
        # We might have postcodes which should now map to this new area rather than wherever they mapped
        # previously.
        $g = new \geoPHP();
        $p = $g->load($val);
        $bbox = $p->getBBox();
        #error_log("Bounding box " . var_export($bbox, TRUE));

        # We need to decide which postcodes to scan.  Choose a slightly arbitrary larger box.
        $swlat = $bbox['miny'] - 0.01;
        $nelat = $bbox['maxy'] + 0.01;
        $swlng = $bbox['minx'] - 0.01;
        $nelng = $bbox['maxx'] + 0.01;

        $sql = "SELECT * FROM locations WHERE $swlat <= lat AND lat <= $nelat AND $swlng <= lng AND lng <= $nelng AND type = 'Postcode' AND LOCATE(' ', name) > 0;";
        #error_log("Find postcodes for new location $sql");
        $locs = $this->dbhr->preQuery($sql);
        foreach ($locs as $loc) {
            if ($loc['id'] != $this->id) {
                #error_log("Re-evaluate {$loc['id']} {$loc['name']}");
                $this->setParents($loc['id'], 1, $this->id);
            }
        }
    }

    public function setGeometry($val) {
        $rc = $this->dbhm->preExec("UPDATE locations SET `type` = 'Polygon', `ourgeometry` = GeomFromText(?) WHERE id = {$this->id};", [$val]);
        if ($rc) {
            # Put in the index table.
            $this->dbhm->preExec("REPLACE INTO locations_spatial (locationid, geometry) VALUES (?, GeomFromText(?));", [
                $this->id,
                $val
            ]);

            # The centre point and max dimensions will also have changed.
            $rc = $this->dbhm->preExec("UPDATE locations SET maxdimension = GetMaxDimension(ourgeometry), lat = Y(ST_Centroid(ourgeometry)), lng = X(ST_Centroid(ourgeometry)) WHERE id = {$this->id};", [$val]);

            if ($rc) {
                $this->remapPostcodes($val, $this->loc['gridid']);

                $this->fetch($this->dbhm, $this->dbhm, $this->id, 'locations', 'loc', $this->publicatts);
            }
        }
        return($rc);
    }

    public function convexHull($points) {
        $mp = new \MultiPoint($points);
        $hull = $mp->convexHull();
        return $hull;
    }

    public function inventArea($areaid) {
        #  Invent our best guess based on the convex hull of the postcodes which we have
        # decided are in this area.
        $g = new \geoPHP();
        $pcs = $this->dbhr->preQuery("SELECT * FROM locations WHERE areaid = ?;", [$areaid]);
        $points = [];
        foreach ($pcs as $pc) {
            $pstr = "POINT({$pc['lng']} {$pc['lat']})";
            #error_log("...{$pc['name']} $pstr");
            $points[] = $g::load($pstr);
        }

        $hull = $this->convexHull($points);

        # We might not get a hull back, because it relies on a PHP extension.
        $geom = $hull ? $hull->asText() : NULL;
        #error_log("Set geom $geom");

        if ($geom) {
            $thisone['polygon'] = $geom;

            # Save it for next time.
            $this->dbhm->preExec("UPDATE locations SET ourgeometry = GeomFromText(?) WHERE id = ?;", [
                $geom,
                $areaid
            ]);

            $this->dbhm->preExec("REPLACE INTO locations_spatial (locationid, geometry) VALUES (?, GeomFromText(?));", [
                $areaid,
                $geom
            ]);
        }

        return($geom);
    }

    public function withinBox($swlat, $swlng, $nelat, $nelng) {
        # Return the areas within the box, along with a polygon which shows their shape.  This allows us to
        # display our areas on a map.  Put a limit on this so that the API can't kill us.
        $sql = "SELECT DISTINCT areaid FROM locations LEFT JOIN locations_excluded ON locations.areaid = locations_excluded.locationid WHERE lat >= ? AND lng >= ? AND lat <= ? AND lng <= ? AND locations_excluded.locationid IS NULL LIMIT 500;";
        $areas = $this->dbhr->preQuery($sql, [ $swlat, $swlng, $nelat, $nelng ]);
        #error_log("SELECT DISTINCT areaid FROM locations LEFT JOIN locations_excluded ON locations.areaid = locations_excluded.locationid WHERE lat >= $swlat AND lng >= $swlng AND lat <= $nelat AND lng <= $nelng AND locations_excluded.locationid IS NULL LIMIT 500;");
        $ret = [];

        foreach ($areas as $area) {
            $a = new Location($this->dbhr, $this->dbhm, $area['areaid']);
            if ($a->getId()) {
                $thisone = $a->getPublic();
                $thisone['polygon'] = NULL;

                $geom = $a->geomAsText();

                if (strpos($geom, 'POINT(') !== FALSE) {
                    # Point location.  Return a basic polygon to make it visible and editable.
                    $swlat = $thisone['lat'] - 0.0005;
                    $swlng = $thisone['lng'] - 0.0005;
                    $nelat = $thisone['lat'] + 0.0005;
                    $nelng = $thisone['lng'] + 0.0005;
                    $geom = "POLYGON(($swlng $swlat, $swlng $nelat, $nelng $nelat, $nelng $swlat, $swlng $swlat))";
                }

                #error_log("For {$area['areaid']} {$thisone['name']} geom $geom");

                if (strpos($geom, 'POLYGON') === FALSE) {
                    # We don't have a polygon for this area.  This is common for OSM data, where many towns etc are just
                    # recorded as points.
                    $geom = $this->inventArea($area['areaid']);
                }

                $thisone['polygon'] = $geom;

                # Get the top-level postcode.
                $tpcid = $a->getPrivate('postcodeid');
                #error_log("Postcode $tpcid for " . $a->getPrivate('name'));

                if ($tpcid) {
                    $tpc = new Location($this->dbhr, $this->dbhm, $tpcid);
                    $thisone['postcode'] = $tpc->getPublic();
                }

                $ret[] = $thisone;
            }
        }

        return($ret);
    }

    public function ensureVague()
    {
        $ret = $this->loc['name'];
        $p = strpos($ret, ' ');

        if ($this->loc['type'] == 'Postcode' && $p !== FALSE) {
            $ret = substr($ret, 0, $p);
        }

        return($ret);
    }

    public function getByIds($locids, &$locationlist) {
        # Efficiently get a bunch of locations.
        $locids = array_unique(array_diff($locids, array_filter(array_column($locationlist, 'id'))));

        if (count($locids)) {
            $sql = "SELECT " . implode(',', $this->publicatts) . " FROM locations WHERE id IN (" . implode(',', $locids) . ");";
            $fetches = $this->dbhr->preQuery($sql);
            $others = [];

            foreach ($fetches as $fetch) {
                $locationlist[$fetch['id']] = new Location($this->dbhr, $this->dbhm, $fetch['id'], $fetch);
                $others[] = Utils::pres('areaid', $fetch);
                $others[] = Utils::pres('postcodeid', $fetch);
            }

            # Now get the postcode and area locations.
            $others = array_unique(array_filter($others));

            if (count($others)) {
                $sql = "SELECT " . implode(',', $this->publicatts) . " FROM locations WHERE id IN (" . implode(',', $others) . ");";
                $fetches = $this->dbhr->preQuery($sql);

                foreach ($fetches as $fetch) {
                    $locationlist[$fetch['id']] = new Location($this->dbhr, $this->dbhm, $fetch['id'], $fetch);
                }
            }
        }
    }
}