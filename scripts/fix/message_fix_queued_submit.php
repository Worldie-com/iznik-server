<?php

require_once dirname(__FILE__) . '/../../include/config.php';
require_once(IZNIK_BASE . '/include/db.php');
require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/message/Message.php');

$bad = [
    ['Baker@merefolly.com', 'Canon Pixma printer inks'],
    ['INKSPOTH@BTINTERNET.COM', 'Guttering'],
    ['JillLamede@AOL.com', 'Old laptop'],
    ['Joyh1949@hotmail.co.uk', 'Vax Vacuum cleaner'],
    ['Paulherring2@gmail.com', 'Car booster seat'],
    ['adriennecurzon@gmail.com', 'Garden table and chairs'],
    ['aewatret@manx.net', 'Fridge freezer'],
    ['ankit.prabhu1@gmail.com', 'Leather sofa - black - moving sale - good condition'],
    ['annettemack@live.co.uk', 'Ladies clothes'],
    ['anthea.slater@yahoo.com', 'Photo paper'],
    ['anthea.slater@yahoo.com', 'Photograph storage'],
    ['arian779@gmail.com', 'Crazy paving,hardcore'],
    ['arthur007@arthur007.plus.com', 'Guttering'],
    ['arwenlaucht@hotmail.com', 'Wanted household items as moving to new home, draws&doubles'],
    ['barbara.addrison@ntlworld.com', 'hosepipes'],
    ['barbara.addrison@ntlworld.com', 'shed door'],
    ['basia_taylor2003@hotmail.com', 'Chair'],
    ['basia_taylor2003@hotmail.com', 'High chair feet extenders'],
    ['bbcgav@aol.com', 'Car seat'],
    ['bbcgav@aol.com', 'Car seat'],
    ['bemca@hotmail.co.uk', 'Suitcase'],
    ['bisouskitty@gmail.com', '2x LIVE hair dye'],
    ['braysoojasnoo@gmail.com', 'Pebbles'],
    ['brilight@btinternet.com', 'Clear waste bags'],
    ['brilight@btinternet.com', 'Tarpaulin'],
    ['brynmarlt@hotmail.co.uk', 'Bathroom Set'],
    ['buckleymartinj@hotmail.com', 'pallets'],
    ['cgtrevisan@yahoo.com', 'Ikea Flower Night Wall Lamp'],
    ['charlottekeegan1@msn.com', 'Single ottoman bed'],
    ['charolholmes@msn.com', 'Mattress'],
    ['chjfb@yahoo.co.uk', 'Bathroom Cabinet'],
    ['clive.toll@ntlworld.com', 'Diana, Princess of Wales Tribute Concert'],
    ['clive@booth.it', 'Refrigerator'],
    ['colinphoenix@gmail.com', 'Turf rolls'],
    ['dangerheyward@yahoo.co.uk', 'Microwave'],
    ['davidgazzard@blueyonder.co.uk', 'Small vanity mirror'],
    ['deanfergi@yahoo.co.uk', '4 x SHOE BOXES, for storage, postage ETC.'],
    ['deanfergi@yahoo.co.uk', '4x Shoe Boxes... Empty, obv\'...'],
    ['dennis.scott11@gmail.com', 'Old decking wood'],
    ['droplet48@yahoo.co.uk', 'Wired Mice'],
    ['eileen10550@gmail.com', 'Front External Door'],
    ['emilyhelen_85@outlook.com', '3-4 girl bike'],
    ['emmaturk286@yahoo.co.uk', 'Satellite Dish'],
    ['estellemea@yahoo.co.uk', 'Stationery items'],
    ['familyclegg@yahoo.co.uk', '2 x sport/overnight bags'],
    ['fordbev@hotmail.co.uk', 'BABY TOYS'],
    ['frances.moir@btinternet.com', 'Duvets and sheets'],
    ['hafsa_621995@yahoo.com', 'Baby boy clothes newborn and 0-3'],
    ['hafsa_621995@yahoo.com', 'Mosed Basket'],
    ['heathergrogerson@gmail.com', 'Almost new Antler suitcase'],
    ['heathergrogerson@gmail.com', 'Tea cups'],
    ['hnayebzadeh@gmail.com', 'Door'],
    ['ian45@ian45.karoo.co.uk', 'Chair'],
    ['ianandpam@blueyonder.co.uk', 'Garden border edging panels'],
    ['imsocyndy@hotmail.com', 'Artificial Roses'],
    ['isobel.pennington@gmail.com', 'Plastic Tubing 8mm'],
    ['j.ronay@btinternet.com', '2 cardboard packing boxes plus packing noodles'],
    ['janeoriel@blackspace.freeserve.co.uk', '4 grey paving slabs'],
    ['jennyporch@btinternet.com', 'Magazines'],
    ['jennyporch@btinternet.com', 'Pair of BT phones'],
    ['jhc1805@btinternet.com', 'HP Deskjet 1050 Printer/Copier/Scanner'],
    ['joannebackshall@btinternet.com', 'Rabbit hutch and run'],
    ['joannebackshall@btinternet.com', 'Sky HD box'],
    ['john@homewurks.co.uk', '8mm video tape'],
    ['john@homewurks.co.uk', 'Cassette tapes'],
    ['john@homewurks.co.uk', 'Computer monitor'],
    ['john@homewurks.co.uk', 'Computer monitor'],
    ['john@homewurks.co.uk', 'Screw Joint Guide'],
    ['jthompson55@btinternet.com', 'Wardrobe'],
    ['julia.gabl@icloud.com', 'Microwave'],
    ['juliajenkin@yahoo.co.uk', 'Household items'],
    ['karabay23@yahoo.com', 'wardrobe'],
    ['kathy.k@live.co.uk', 'Dog coats for small dogs'],
    ['kathy.k@live.co.uk', 'Fir cones'],
    ['kathy.k@live.co.uk', 'Hopoint Washing Machine'],
    ['katzcouzin@yahoo.com', 'Paint roller handle, shelf'],
    ['kelmarsh1952@gmail.com', 'Garden chairs'],
    ['keramsden@yahoo.co.uk', '5ltr Grey garage floor paint .'],
    ['keramsden@yahoo.co.uk', 'Rollerblades, suit shoe size 9.5 approx.'],
    ['kirstyallnutt@gmail.com', 'Metal, two-tiered stand'],
    ['kylie_greeny@hotmail.com', 'Book shelf'],
    ['kylie_greeny@hotmail.com', 'Double Bed frame'],
    ['laughlen@gmail.com', 'Blank DVDs'],
    ['leesamaudsley@gmail.com', '4 black bags of womens shoes and boots in very good condirio'],
    ['leonard1942@live.com', '2 ceiling roses, 1 large, 1 smaller'],
    ['lidia_png@hotmail.com', 'Dumbells and weights'],
    ['m.davis178@btinternet.com', 'Old shed clearout of scrap metal. Old childs desk, tea chest'],
    ['mack57uk@yahoo.co.uk', 'Computer Case'],
    ['mack57uk@yahoo.co.uk', 'HP Printer, Scanner, Coper'],
    ['margaretyoungs@yahoo.com', 'Cds and DVDs'],
    ['markwood@freeola.com', 'GCSE Edexcel science revision workbooks x5'],
    ['martin.hunt@gmx.com', '4 x 2 timber 1.5 8 foot lengths'],
    ['martinbuck1987@gmail.com', '2 bike frames and pair of wheels'],
    ['mcarr472@hotmail.com', 'Chaise longue storage seat restoration project'],
    ['mckaysadie@ymail.com', 'Glass table and 2 benches'],
    ['megan.grant.332345@outlook.com', 'Household furniture'],
    ['melaniejstevens@btinternet.com', '2 x Large rolls of Willow Screening/'],
    ['michelleandstanleytuck@ntlworld.com', 'Various DVDs'],
    ['mickwaltersuk@gmail.com', 'Retracting flat ethernet cable'],
    ['miles.litvinoff@phonecoop.coop', 'Bosch ART 26 LI grass strimmer accessories'],
    ['natb17@hotmail.co.uk', 'Candle Glasses/Containers'],
    ['nittynuttynatz@yahoo.co.uk', 'Various kitchen paraphernalia'],
    ['norman.cumming@talktalk.net', '"This England" back issues'],
    ['onegable@gmail.com', 'Chessboard'],
    ['patriciaharbot@btinternet.com', 'Mirror fronted, white framed sliding wardrobe doors'],
    ['patriciakidd123@btinternet.com', 'Sewing Machine'],
    ['paul.crooke@hotmail.co.uk', 'Decade resistance box'],
    ['paul.crooke@hotmail.co.uk', 'Sound level meter'],
    ['pdavies@leaph.co.uk', '16 Mint Condition Empty DVD Cases'],
    ['pdavies@leaph.co.uk', '> 1/2 Kg New Mixed Sorted Screws'],
    ['pdavies@leaph.co.uk', 'Hozelock 24V 3A 60VA Transformer Unused'],
    ['pdavies@leaph.co.uk', 'New MDF Internal Window Sill'],
    ['pdell@btinternet.com', 'Tins of paint'],
    ['posh734@hotmail.co.uk', 'Ceramic toilet'],
    ['potter77@hotmail.co.uk', '2 seater ikea sofa'],
    ['presteignedogman@icloud.com', 'Mirror'],
    ['rob.bray@ludomusic.co.uk', 'Canon PIXMA compatible ink cartridges'],
    ['rob.bray@ludomusic.co.uk', 'Child car seat'],
    ['rob.bray@ludomusic.co.uk', 'Halfords universal child car seat'],
    ['roseoyenuga@yahoo.com', 'QUAD BIKE'],
    ['ruthearl@rocketmail.com', 'Soil and gravel'],
    ['ruthearl@rocketmail.com', 'Wooden lawn edging'],
    ['ruthjfinch@googlemail.com', 'Queen Size (4\') Mattress'],
    ['s.a.casey@hotmail.co.uk', 'Ikea Laiva birch bookcase 62x165 cm'],
    ['sam.d.bates@gmail.com', 'Gas barbecue'],
    ['sampullen@yahoo.co.uk', 'Apollo Starfighter kids bike'],
    ['sampullen@yahoo.co.uk', 'Mini Micro scooter'],
    ['sanddunesandsaltyair@yahoo.com', 'Dyson DC19 vacuum cleaner'],
    ['saona.lilyuk@gmail.com', 'victorian conservatory'],
    ['sarahjwalsh@hotmail.com', 'Insulating lime plaster'],
    ['sheilahird@sky.com', 'Exercise Bike'],
    ['spocmul@yahoo.co.uk', 'Desk'],
    ['stuartpollock5@gmail.com', 'Postbox'],
    ['sue.pearce65@gmail.com', 'Double bed with foam mattress'],
    ['suevera46@yahoo.co.uk', 'Bag'],
    ['suevera46@yahoo.co.uk', 'Footstool'],
    ['suzan.martyn@hotmail.co.uk', 'dog crate'],
    ['thinkingcowgirl@gmail.com', 'Insulating lime render'],
    ['tinabristowpt@gmail.com', 'Squat rack, bar  and weights'],
    ['wendybennewith@yahoo.co.uk', 'OFFER: Girls’ clothes for age 10- 12 approximately  (Connah\'s Quay CH5)'],
    ['westie.page@gmail.com', 'Plastic plant pots'],
    ['wsjc@aol.com', 'Electricals'],
    ['x22anderson@outlook.com', 'Chopping Board'],
    ['x22anderson@outlook.com', 'Hotter Sandals'],
    ['x22anderson@outlook.com', 'Hotter Slip on shoes'],
    ['x22anderson@outlook.com', 'Kitchen bin'],
    ['x22anderson@outlook.com', 'Ribbons and Bows'],
    ['x22anderson@outlook.com', 'Summer Shoes']
];

foreach ($bad as $a => $m) {
    $msgs = $dbhr->preQuery("SELECT messages.id, groupid, subject FROM messages INNER JOIN messages_groups ON messages_groups.msgid = messages.id WHERE fromaddr LIKE ? AND subject LIKE ?;", [
        $m[0],
        $m[1]
    ]);

    foreach ($msgs as $m) {
        error_log("Found {$m['subject']}");
        $msg = new Message($dbhr, $dbhm, $m['id']);
        $msg->constructSubject($m['groupid']);
    }
}