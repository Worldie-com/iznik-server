-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2015 at 07:39 AM
-- Server version: 5.6.25-73.1
-- PHP Version: 5.6.99-hhvm

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `iznik`
--

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE IF NOT EXISTS `groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of group',
  `nameshort` varchar(80) DEFAULT NULL COMMENT 'A short name for the group',
  `namefull` varchar(255) DEFAULT NULL COMMENT 'A longer name for the group',
  `nameabbr` varchar(5) DEFAULT NULL COMMENT 'An abbreviated name for the group',
  `settings` text NOT NULL COMMENT 'JSON-encoded settings for group',
  `type` set('Reuse','Freegle','Other') DEFAULT NULL COMMENT 'High-level characteristics of the group',
  `onyahoo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this group is also on Yahoo Groups',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `nameshort` (`nameshort`),
  UNIQUE KEY `namefull` (`namefull`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='The different groups that we host' AUTO_INCREMENT=31808 ;

-- --------------------------------------------------------

--
-- Table structure for table `locations_approved`
--

CREATE TABLE IF NOT EXISTS `locations_approved` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `location` varchar(255) NOT NULL,
  `groupid` bigint(20) unsigned NOT NULL,
  `popularity` bigint(20) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `location` (`location`,`groupid`),
  KEY `groupid` (`groupid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=12049 ;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE IF NOT EXISTS `logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Machine assumed set to GMT',
  `byuser` bigint(20) unsigned DEFAULT NULL COMMENT 'User responsible for action, if any',
  `type` enum('Group','Message') DEFAULT NULL,
  `subtype` enum('Created','Deleted','Received','Sent','Failure','ClassifiedSpam','Joined','Left') DEFAULT NULL,
  `group` bigint(20) unsigned DEFAULT NULL COMMENT 'Any group this log is for',
  `user` bigint(20) unsigned DEFAULT NULL COMMENT 'Any user that this log is for',
  `message_approved` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_incoming` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_outgoing` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_pending` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_spam` bigint(20) unsigned NOT NULL COMMENT 'Any relevant spam message',
  `text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `user` (`user`),
  KEY `group` (`group`),
  KEY `message_approved` (`message_approved`),
  KEY `message_incoming` (`message_incoming`),
  KEY `message_outgoing` (`message_outgoing`),
  KEY `message_pending` (`message_pending`),
  KEY `byuser` (`byuser`),
  KEY `type` (`type`,`subtype`),
  KEY `subtype` (`subtype`),
  KEY `timestamp` (`timestamp`,`type`,`subtype`),
  KEY `timestamp_2` (`timestamp`,`group`),
  KEY `message_spam` (`message_spam`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Logs.  Not guaranteed against loss' AUTO_INCREMENT=385685 ;

-- --------------------------------------------------------

--
-- Table structure for table `logs_api`
--

CREATE TABLE IF NOT EXISTS `logs_api` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session` varchar(255) NOT NULL,
  `request` longtext NOT NULL,
  `response` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `session` (`session`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Log of all API requests and responses' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `logs_sql`
--

CREATE TABLE IF NOT EXISTS `logs_sql` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `result` varchar(255) DEFAULT NULL COMMENT 'The result of the op',
  `duration` bigint(20) unsigned NOT NULL COMMENT 'How long in ms it took',
  `statement` longtext NOT NULL COMMENT 'The actual SQL statement',
  `user` bigint(20) unsigned DEFAULT NULL COMMENT 'Any user that this log is for',
  `message_approved` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_incoming` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_pending` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  `message_outgoing` bigint(20) unsigned DEFAULT NULL COMMENT 'Any relevant message',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `user` (`user`),
  KEY `message_approved` (`message_approved`),
  KEY `message_incoming` (`message_incoming`),
  KEY `message_pending` (`message_pending`),
  KEY `message_outgoing` (`message_outgoing`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=477605 ;

-- --------------------------------------------------------

--
-- Table structure for table `memberships`
--

CREATE TABLE IF NOT EXISTS `memberships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userid` bigint(20) unsigned NOT NULL,
  `groupid` bigint(20) unsigned NOT NULL,
  `role` enum('Member','Moderator','Owner') NOT NULL DEFAULT 'Member',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `userid_groupid` (`userid`,`groupid`),
  KEY `groupid` (`groupid`),
  KEY `groupid_2` (`groupid`,`role`),
  KEY `userid` (`userid`,`role`),
  KEY `role` (`role`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Which groups users are members of' AUTO_INCREMENT=36955 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_approved`
--

CREATE TABLE IF NOT EXISTS `messages_approved` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique iD',
  `arrival` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this message arrived at our server',
  `date` timestamp NULL DEFAULT NULL COMMENT 'When this message was created, e.g. Date header',
  `groupid` bigint(20) unsigned DEFAULT NULL COMMENT 'Destination group, if identified',
  `incomingid` bigint(20) unsigned DEFAULT NULL,
  `source` enum('Email','Yahoo Approved','Yahoo Pending') NOT NULL DEFAULT 'Email' COMMENT 'Source of incoming message',
  `sourceheader` varchar(80) DEFAULT NULL COMMENT 'Any source header, e.g. X-Freegle-Source',
  `fromip` varchar(40) DEFAULT NULL COMMENT 'IP we think this message came from',
  `message` longtext NOT NULL COMMENT 'The unparsed message',
  `envelopefrom` varchar(255) DEFAULT NULL,
  `fromname` varchar(255) DEFAULT NULL,
  `fromaddr` varchar(255) DEFAULT NULL,
  `envelopeto` varchar(255) DEFAULT NULL,
  `subject` varchar(1024) DEFAULT NULL,
  `type` enum('Offer','Taken','Wanted','Received','Admin','Other') DEFAULT NULL COMMENT 'For reuse groups, the message categorisation',
  `messageid` varchar(255) DEFAULT NULL,
  `tnpostid` varchar(80) DEFAULT NULL COMMENT 'If this message came from Trash Nothing, the unique post ID',
  `textbody` longtext,
  `htmlbody` longtext,
  `retrycount` int(11) NOT NULL DEFAULT '0' COMMENT 'We might fail to route, and later retry',
  `retrylastfailure` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `envelopefrom` (`envelopefrom`),
  KEY `envelopeto` (`envelopeto`),
  KEY `retrylastfailure` (`retrylastfailure`),
  KEY `message-id` (`messageid`),
  KEY `groupid` (`groupid`),
  KEY `fromup` (`fromip`),
  KEY `tnpostid` (`tnpostid`),
  KEY `incomingid` (`incomingid`),
  KEY `type` (`type`),
  KEY `sourceheader` (`sourceheader`),
  KEY `arrival` (`arrival`,`sourceheader`),
  KEY `arrival_2` (`arrival`,`fromaddr`),
  KEY `arrival_3` (`arrival`,`groupid`),
  KEY `fromaddr` (`fromaddr`,`groupid`,`subject`(767)),
  KEY `date` (`date`),
  KEY `subject` (`subject`(767))
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Messages which have been approved for members' AUTO_INCREMENT=152502 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_attachments`
--

CREATE TABLE IF NOT EXISTS `messages_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incomingid` bigint(20) unsigned DEFAULT NULL,
  `approvedid` bigint(20) unsigned DEFAULT NULL,
  `pendingid` bigint(20) unsigned DEFAULT NULL,
  `spamid` bigint(20) unsigned DEFAULT NULL,
  `contenttype` varchar(80) NOT NULL,
  `data` blob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `incomingid` (`incomingid`),
  KEY `approvedid` (`approvedid`),
  KEY `pendingid` (`pendingid`),
  KEY `spamid` (`spamid`),
  KEY `incomingid_2` (`incomingid`,`approvedid`,`pendingid`,`spamid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Attachments parsed out from messages' AUTO_INCREMENT=18678 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_history`
--

CREATE TABLE IF NOT EXISTS `messages_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique iD',
  `incomingid` bigint(20) unsigned DEFAULT NULL,
  `arrival` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this message arrived at our server',
  `source` enum('Email','Yahoo Approved','Yahoo Pending') NOT NULL DEFAULT 'Email' COMMENT 'Source of incoming message',
  `fromip` varchar(40) DEFAULT NULL COMMENT 'IP we think this message came from',
  `fromhost` varchar(80) DEFAULT NULL COMMENT 'Hostname for fromip if resolvable, or NULL',
  `message` longtext NOT NULL COMMENT 'The unparsed message',
  `envelopefrom` varchar(255) DEFAULT NULL,
  `fromname` varchar(255) DEFAULT NULL,
  `fromaddr` varchar(255) DEFAULT NULL,
  `envelopeto` varchar(255) DEFAULT NULL,
  `groupid` bigint(20) unsigned DEFAULT NULL COMMENT 'Destination group, if identified',
  `subject` varchar(1024) DEFAULT NULL,
  `prunedsubject` varchar(1024) DEFAULT NULL COMMENT 'For spam detection',
  `messageid` varchar(255) DEFAULT NULL,
  `textbody` longtext,
  `htmlbody` longtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `fromaddr` (`fromaddr`),
  KEY `envelopefrom` (`envelopefrom`),
  KEY `envelopeto` (`envelopeto`),
  KEY `message-id` (`messageid`),
  KEY `groupid` (`groupid`),
  KEY `fromup` (`fromip`),
  KEY `incomingid` (`incomingid`),
  KEY `fromhost` (`fromhost`),
  KEY `arrival` (`arrival`),
  KEY `subject` (`subject`(767)),
  KEY `prunedsubject` (`prunedsubject`(767)),
  KEY `fromname` (`fromname`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Message arrivals, used for spam checking' AUTO_INCREMENT=231926 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_incoming`
--

CREATE TABLE IF NOT EXISTS `messages_incoming` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique iD',
  `arrival` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this message arrived at our server',
  `date` timestamp NULL DEFAULT NULL COMMENT 'When this message was created, e.g. Date header',
  `source` enum('Email','Yahoo Approved','Yahoo Pending') NOT NULL DEFAULT 'Email' COMMENT 'Source of incoming message',
  `sourceheader` varchar(80) DEFAULT NULL COMMENT 'Any source header, e.g. X-Freegle-Source',
  `fromip` varchar(40) DEFAULT NULL COMMENT 'IP we think this message came from',
  `message` longtext NOT NULL COMMENT 'The unparsed message',
  `envelopefrom` varchar(255) DEFAULT NULL,
  `fromname` varchar(255) DEFAULT NULL,
  `fromaddr` varchar(255) DEFAULT NULL,
  `envelopeto` varchar(255) DEFAULT NULL,
  `groupid` bigint(20) unsigned DEFAULT NULL COMMENT 'Destination group, if identified',
  `subject` varchar(1024) DEFAULT NULL,
  `type` enum('Offer','Taken','Wanted','Received','Admin','Other') DEFAULT NULL COMMENT 'For reuse groups, the message categorisation',
  `messageid` varchar(255) DEFAULT NULL,
  `tnpostid` varchar(80) DEFAULT NULL COMMENT 'If this message came from Trash Nothing, the unique post ID',
  `textbody` longtext,
  `htmlbody` longtext,
  `retrycount` int(11) NOT NULL DEFAULT '0' COMMENT 'We might fail to route, and later retry',
  `retrylastfailure` timestamp NULL DEFAULT NULL,
  `yahoopendingid` varchar(20) DEFAULT NULL COMMENT 'For Yahoo messages, pending id if known',
  `yahooreject` varchar(255) DEFAULT NULL COMMENT 'For Yahoo messages, email to trigger reject if known',
  `yahooapprove` varchar(255) DEFAULT NULL COMMENT 'For Yahoo messages, email to trigger approve if known',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `fromaddr` (`fromaddr`),
  KEY `envelopefrom` (`envelopefrom`),
  KEY `envelopeto` (`envelopeto`),
  KEY `retrylastfailure` (`retrylastfailure`),
  KEY `message-id` (`messageid`),
  KEY `groupid` (`groupid`),
  KEY `fromup` (`fromip`),
  KEY `tnpostid` (`tnpostid`),
  KEY `type` (`type`),
  KEY `sourceheader` (`sourceheader`),
  KEY `date` (`date`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Messages which have arrived, but not yet been processed' AUTO_INCREMENT=52617 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_outgoing`
--

CREATE TABLE IF NOT EXISTS `messages_outgoing` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique iD',
  `source` enum('Email') NOT NULL COMMENT 'Source of incoming message',
  `message` longtext NOT NULL COMMENT 'The unparsed message',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Messages which have arrived, but not yet been processed' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_pending`
--

CREATE TABLE IF NOT EXISTS `messages_pending` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique iD',
  `arrival` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this message arrived at our server',
  `date` timestamp NULL DEFAULT NULL COMMENT 'When this message was created, e.g. Date header',
  `groupid` bigint(20) unsigned DEFAULT NULL COMMENT 'Destination group, if identified',
  `incomingid` bigint(20) unsigned DEFAULT NULL,
  `source` enum('Email','Yahoo Approved','Yahoo Pending') NOT NULL DEFAULT 'Email' COMMENT 'Source of incoming message',
  `sourceheader` varchar(80) DEFAULT NULL COMMENT 'Any X-Freegle-Source header from message',
  `fromip` varchar(40) DEFAULT NULL COMMENT 'IP we think this message came from',
  `message` longtext NOT NULL COMMENT 'The unparsed message',
  `envelopefrom` varchar(255) DEFAULT NULL,
  `fromname` varchar(255) DEFAULT NULL,
  `fromaddr` varchar(255) DEFAULT NULL,
  `envelopeto` varchar(255) DEFAULT NULL,
  `subject` varchar(1024) DEFAULT NULL,
  `type` enum('Offer','Taken','Wanted','Received','Admin','Other') DEFAULT NULL COMMENT 'For reuse groups, the message categorisation',
  `messageid` varchar(255) DEFAULT NULL,
  `tnpostid` varchar(80) DEFAULT NULL COMMENT 'If this message came from Trash Nothing, the unique post ID',
  `textbody` longtext,
  `htmlbody` longtext,
  `retrycount` int(11) NOT NULL DEFAULT '0' COMMENT 'We might fail to route, and later retry',
  `retrylastfailure` timestamp NULL DEFAULT NULL,
  `yahoopendingid` varchar(20) DEFAULT NULL COMMENT 'For Yahoo messages, pending id if known',
  `yahooreject` varchar(255) DEFAULT NULL COMMENT 'For Yahoo messages, email to trigger reject if known',
  `yahooapprove` varchar(255) DEFAULT NULL COMMENT 'For Yahoo messages, email to trigger approve if known',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `envelopefrom` (`envelopefrom`),
  KEY `envelopeto` (`envelopeto`),
  KEY `retrylastfailure` (`retrylastfailure`),
  KEY `message-id` (`messageid`),
  KEY `groupid` (`groupid`),
  KEY `fromup` (`fromip`),
  KEY `tnpostid` (`tnpostid`),
  KEY `incomingid` (`incomingid`),
  KEY `type` (`type`),
  KEY `sourceheader` (`sourceheader`),
  KEY `fromaddr` (`fromaddr`,`groupid`,`subject`(767)),
  KEY `date` (`date`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Messages which have been approved for members' AUTO_INCREMENT=22284 ;

-- --------------------------------------------------------

--
-- Table structure for table `messages_spam`
--

CREATE TABLE IF NOT EXISTS `messages_spam` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique iD',
  `arrival` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this message arrived at our server',
  `date` timestamp NULL DEFAULT NULL COMMENT 'When this message was created, e.g. Date header',
  `groupid` bigint(20) unsigned DEFAULT NULL COMMENT 'Destination group, if identified',
  `incomingid` bigint(20) unsigned DEFAULT NULL,
  `source` enum('Email','Yahoo Approved','Yahoo Pending') NOT NULL DEFAULT 'Email' COMMENT 'Source of incoming message',
  `sourceheader` varchar(80) DEFAULT NULL COMMENT 'Any source header, e.g. X-Freegle-Source',
  `fromip` varchar(40) DEFAULT NULL COMMENT 'IP we think this message came from',
  `message` longtext NOT NULL COMMENT 'The unparsed message',
  `envelopefrom` varchar(255) DEFAULT NULL,
  `fromname` varchar(255) DEFAULT NULL,
  `fromaddr` varchar(255) DEFAULT NULL,
  `envelopeto` varchar(255) DEFAULT NULL,
  `subject` varchar(1024) DEFAULT NULL,
  `type` enum('Offer','Taken','Wanted','Received','Admin','Other') DEFAULT NULL COMMENT 'For reuse groups, the message categorisation',
  `messageid` varchar(255) DEFAULT NULL,
  `tnpostid` varchar(80) DEFAULT NULL COMMENT 'If this message came from Trash Nothing, the unique post ID',
  `textbody` longtext,
  `htmlbody` longtext,
  `reason` varchar(255) NOT NULL COMMENT 'Reason we flagged this as spam',
  `yahoopendingid` varchar(20) DEFAULT NULL COMMENT 'For Yahoo messages, pending id if known',
  `yahooreject` varchar(255) DEFAULT NULL COMMENT 'For Yahoo messages, email to trigger reject if known',
  `yahooapprove` varchar(255) DEFAULT NULL COMMENT 'For Yahoo messages, email to trigger approve if known',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `fromaddr` (`fromaddr`),
  KEY `envelopefrom` (`envelopefrom`),
  KEY `envelopeto` (`envelopeto`),
  KEY `message-id` (`messageid`),
  KEY `groupid` (`groupid`),
  KEY `fromup` (`fromip`),
  KEY `incomingid` (`incomingid`),
  KEY `tnpostid` (`tnpostid`),
  KEY `type` (`type`),
  KEY `sourceheader` (`sourceheader`),
  KEY `date` (`date`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Messages suspected as spam, for review' AUTO_INCREMENT=742 ;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` bigint(20) unsigned NOT NULL,
  `series` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `id` (`id`),
  KEY `date` (`date`),
  KEY `id_3` (`id`,`series`,`token`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `spam_countries`
--

CREATE TABLE IF NOT EXISTS `spam_countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country` varchar(80) NOT NULL COMMENT 'A country we want to block',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `country` (`country`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `spam_whitelist_ips`
--

CREATE TABLE IF NOT EXISTS `spam_whitelist_ips` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ip` varchar(80) NOT NULL,
  `comment` text NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Whitelisted IP addresses' AUTO_INCREMENT=450 ;

-- --------------------------------------------------------

--
-- Table structure for table `spam_whitelist_subjects`
--

CREATE TABLE IF NOT EXISTS `spam_whitelist_subjects` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `ip` (`subject`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Whitelisted subjects' AUTO_INCREMENT=19 ;

-- --------------------------------------------------------

--
-- Table structure for table `supporters`
--

CREATE TABLE IF NOT EXISTS `supporters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` enum('Wowzer','Front Page','Supporter','Buyer') NOT NULL,
  `email` varchar(255) NOT NULL,
  `display` varchar(255) DEFAULT NULL,
  `voucher` varchar(255) NOT NULL COMMENT 'Voucher code',
  `vouchercount` int(11) NOT NULL DEFAULT '1' COMMENT 'Number of licenses in this voucher',
  `voucheryears` int(11) NOT NULL DEFAULT '1' COMMENT 'Number of years voucher licenses are valid for',
  `anonymous` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `id` (`id`),
  KEY `name` (`name`,`type`,`email`),
  KEY `display` (`display`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='People who have supported this site' AUTO_INCREMENT=2696 ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `systemrole` set('User','Moderator','Support','Admin') NOT NULL DEFAULT 'User' COMMENT 'System-wide roles',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastaccess` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `settings` text COMMENT 'JSON-encoded settings',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `systemrole` (`systemrole`),
  KEY `added` (`added`,`lastaccess`),
  KEY `fullname` (`fullname`),
  KEY `firstname` (`firstname`),
  KEY `lastname` (`lastname`),
  KEY `firstname_2` (`firstname`,`lastname`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=37348 ;

-- --------------------------------------------------------

--
-- Table structure for table `users_emails`
--

CREATE TABLE IF NOT EXISTS `users_emails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userid` bigint(20) unsigned NOT NULL COMMENT 'Unique ID in users table',
  `email` varchar(255) NOT NULL COMMENT 'The email',
  `primary` tinyint(4) NOT NULL DEFAULT '1' COMMENT 'Preferred email for this user',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `validated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `userid` (`userid`),
  KEY `validated` (`validated`),
  KEY `userid_2` (`userid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=38051 ;

-- --------------------------------------------------------

--
-- Table structure for table `users_logins`
--

CREATE TABLE IF NOT EXISTS `users_logins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userid` bigint(20) unsigned NOT NULL COMMENT 'Unique ID in users table',
  `type` enum('Yahoo','Facebook','Google','Native') DEFAULT NULL,
  `uid` varchar(255) DEFAULT NULL COMMENT 'Unique identifier for login',
  `credentials` varchar(255) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastaccess` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `email` (`uid`,`type`),
  UNIQUE KEY `userid_3` (`userid`,`type`,`uid`),
  KEY `userid` (`userid`),
  KEY `validated` (`lastaccess`),
  KEY `userid_2` (`userid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2105 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `locations_approved`
--
ALTER TABLE `locations_approved`
ADD CONSTRAINT `locations_approved_ibfk_1` FOREIGN KEY (`groupid`) REFERENCES `groups` (`id`);

--
-- Constraints for table `logs_sql`
--
ALTER TABLE `logs_sql`
ADD CONSTRAINT `logs_sql_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memberships`
--
ALTER TABLE `memberships`
ADD CONSTRAINT `memberships_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `memberships_ibfk_2` FOREIGN KEY (`groupid`) REFERENCES `groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages_approved`
--
ALTER TABLE `messages_approved`
ADD CONSTRAINT `messages_approved_ibfk_1` FOREIGN KEY (`groupid`) REFERENCES `groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages_incoming`
--
ALTER TABLE `messages_incoming`
ADD CONSTRAINT `messages_incoming_ibfk_1` FOREIGN KEY (`groupid`) REFERENCES `groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages_spam`
--
ALTER TABLE `messages_spam`
ADD CONSTRAINT `messages_spam_ibfk_1` FOREIGN KEY (`groupid`) REFERENCES `groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users_emails`
--
ALTER TABLE `users_emails`
ADD CONSTRAINT `users_emails_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users_logins`
--
ALTER TABLE `users_logins`
ADD CONSTRAINT `users_logins_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `pruneSessions` ON SCHEDULE EVERY 1 DAY STARTS '2015-09-04 05:00:00' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM sessions WHERE DATEDIFF(NOW(), `date`) > 7$$

CREATE DEFINER=`root`@`localhost` EVENT `pruneSpam` ON SCHEDULE EVERY 1 DAY STARTS '2015-08-28 11:13:05' ON COMPLETION NOT PRESERVE ENABLE COMMENT 'Delete old messages from spam' DO DELETE FROM messages_spam WHERE DATEDIFF(NOW(), arrival) > 7$$

CREATE DEFINER=`root`@`localhost` EVENT `pruneHistory` ON SCHEDULE EVERY 1 DAY STARTS '2015-08-27 07:46:30' ON COMPLETION NOT PRESERVE ENABLE COMMENT 'Delete old records from the history table which we use for spam' DO DELETE FROM messages_history WHERE DATEDIFF(NOW(), arrival) > 7$$

CREATE DEFINER=`root`@`localhost` EVENT `pruneAttachments` ON SCHEDULE EVERY 1 DAY STARTS '2015-09-11 03:00:00' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM messages_attachments WHERE incomingid IS NULL AND
                                                                                                                                                                                            approvedid IS NULL AND
                                                                                                                                                                                            pendingid IS NULL AND
                                                                                                                                                                                            spamid IS NULL$$

DELIMITER ;
