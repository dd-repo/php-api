-- phpMyAdmin SQL Dump
-- version 3.5.0
-- http://www.phpmyadmin.net
--
-- Client: sql
-- Gérée: Dim 11 Novembre 2012 à8:17
-- Version du serveur: 5.5.24-4-log
-- Version de PHP: 5.4.4-2

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de donné: `template`
--

-- --------------------------------------------------------

--
-- Structure de la table `bills`
--

CREATE TABLE IF NOT EXISTS `bills` (
  `bill_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `bill_user` int(11) unsigned NOT NULL,
  `bill_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `bill_description` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `bill_ref` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bill_date` int(11) unsigned NOT NULL,
  `bill_from` int(11) NOT NULL,
  `bill_to` int(11) NOT NULL,
  `bill_status` tinyint(1) unsigned NOT NULL,
  `bill_amount` float NOT NULL,
  `bill_vat` float NOT NULL,
  `bill_total` float NOT NULL,
  PRIMARY KEY (`bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `bill_service`
--

CREATE TABLE IF NOT EXISTS `bill_service` (
  `bill_id` int(11) unsigned NOT NULL,
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(11) unsigned DEFAULT NULL,
  `service_count` int(11) unsigned NOT NULL,
  `service_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `service_description` text COLLATE utf8_unicode_ci,
  `service_amount` float NOT NULL,
  `service_vat` float NOT NULL DEFAULT '19.6',
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`,`service_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=8 ;

-- --------------------------------------------------------

--
-- Structure de la table `databases`
--

CREATE TABLE IF NOT EXISTS `services` (
  `service_name` varchar(30) CHARACTER SET utf8 NOT NULL,
  `service_user` int(11) unsigned NOT NULL,
  `service_type` tinytext CHARACTER SET utf8 NOT NULL,
  `service_desc` text CHARACTER SET utf8 NOT NULL,
  UNIQUE KEY `service_name` (`service_name`),
  KEY `service_user` (`service_user`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `grants`
--

CREATE TABLE IF NOT EXISTS `grants` (
  `grant_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `grant_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`grant_id`),
  UNIQUE KEY `grant_name` (`grant_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=288 ;

--
-- Contenu de la table `grants`
--

INSERT INTO `grants` (`grant_id`, `grant_name`) VALUES
(71, 'ACCESS'),
(249, 'ACCOUNT_DELETE'),
(243, 'ACCOUNT_INSERT'),
(245, 'ACCOUNT_SELECT'),
(247, 'ACCOUNT_UPDATE'),
(265, 'APP_DELETE'),
(263, 'APP_INSERT'),
(267, 'APP_SELECT'),
(269, 'APP_UPDATE'),
(281, 'BILL_DELETE'),
(279, 'BILL_INSERT'),
(278, 'BILL_SELECT'),
(280, 'BILL_UPDATE'),
(209, 'DATABASE_DELETE'),
(207, 'DATABASE_INSERT'),
(211, 'DATABASE_SELECT'),
(213, 'DATABASE_UPDATE'),
(183, 'DOMAIN_DELETE'),
(181, 'DOMAIN_INSERT'),
(187, 'DOMAIN_SELECT'),
(185, 'DOMAIN_UPDATE'),
(109, 'GRANT_DELETE'),
(115, 'GRANT_GROUP_DELETE'),
(111, 'GRANT_GROUP_INSERT'),
(113, 'GRANT_GROUP_SELECT'),
(103, 'GRANT_INSERT'),
(105, 'GRANT_SELECT'),
(121, 'GRANT_TOKEN_DELETE'),
(117, 'GRANT_TOKEN_INSERT'),
(119, 'GRANT_TOKEN_SELECT'),
(107, 'GRANT_UPDATE'),
(127, 'GRANT_USER_DELETE'),
(123, 'GRANT_USER_INSERT'),
(125, 'GRANT_USER_SELECT'),
(95, 'GROUP_DELETE'),
(89, 'GROUP_INSERT'),
(91, 'GROUP_SELECT'),
(93, 'GROUP_UPDATE'),
(101, 'GROUP_USER_DELETE'),
(97, 'GROUP_USER_INSERT'),
(99, 'GROUP_USER_SELECT'),
(155, 'QUOTA_DELETE'),
(149, 'QUOTA_INSERT'),
(151, 'QUOTA_SELECT'),
(153, 'QUOTA_UPDATE'),
(163, 'QUOTA_USER_DELETE'),
(157, 'QUOTA_USER_INSERT'),
(159, 'QUOTA_USER_SELECT'),
(161, 'QUOTA_USER_UPDATE'),
(225, 'REGISTRATION_DELETE'),
(227, 'REGISTRATION_INSERT'),
(229, 'REGISTRATION_SELECT'),
(251, 'SELF_ACCOUNT_DELETE'),
(253, 'SELF_ACCOUNT_INSERT'),
(255, 'SELF_ACCOUNT_SELECT'),
(257, 'SELF_ACCOUNT_UPDATE'),
(273, 'SELF_APP_DELETE'),
(271, 'SELF_APP_INSERT'),
(275, 'SELF_APP_SELECT'),
(277, 'SELF_APP_UPDATE'),
(283, 'SELF_BILL_INSERT'),
(282, 'SELF_BILL_SELECT'),
(221, 'SELF_DATABASE_DELETE'),
(217, 'SELF_DATABASE_INSERT'),
(223, 'SELF_DATABASE_SELECT'),
(219, 'SELF_DATABASE_UPDATE'),
(133, 'SELF_DELETE'),
(201, 'SELF_DOMAIN_DELETE'),
(197, 'SELF_DOMAIN_INSERT'),
(199, 'SELF_DOMAIN_SELECT'),
(203, 'SELF_DOMAIN_UPDATE'),
(135, 'SELF_GRANT_SELECT'),
(139, 'SELF_GROUP_DELETE'),
(137, 'SELF_GROUP_SELECT'),
(165, 'SELF_QUOTA_SELECT'),
(129, 'SELF_SELECT'),
(191, 'SELF_SITE_DELETE'),
(193, 'SELF_SITE_INSERT'),
(189, 'SELF_SITE_SELECT'),
(195, 'SELF_SITE_UPDATE'),
(241, 'SELF_SUBDOMAIN_DELETE'),
(239, 'SELF_SUBDOMAIN_INSERT'),
(235, 'SELF_SUBDOMAIN_SELECT'),
(237, 'SELF_SUBDOMAIN_UPDATE'),
(147, 'SELF_TOKEN_DELETE'),
(167, 'SELF_TOKEN_GRANT_DELETE'),
(169, 'SELF_TOKEN_GRANT_INSERT'),
(141, 'SELF_TOKEN_INSERT'),
(143, 'SELF_TOKEN_SELECT'),
(145, 'SELF_TOKEN_UPDATE'),
(131, 'SELF_UPDATE'),
(287, 'SERVICE_DELETE'),
(285, 'SERVICE_INSERT'),
(284, 'SERVICE_SELECT'),
(286, 'SERVICE_UPDATE'),
(175, 'SITE_DELETE'),
(173, 'SITE_INSERT'),
(179, 'SITE_SELECT'),
(177, 'SITE_UPDATE'),
(215, 'SUBDOMAIN_DELETE'),
(205, 'SUBDOMAIN_INSERT'),
(231, 'SUBDOMAIN_SELECT'),
(233, 'SUBDOMAIN_UPDATE'),
(87, 'TOKEN_DELETE'),
(81, 'TOKEN_INSERT'),
(83, 'TOKEN_SELECT'),
(85, 'TOKEN_UPDATE'),
(79, 'USER_DELETE'),
(73, 'USER_INSERT'),
(75, 'USER_SELECT'),
(77, 'USER_UPDATE');

-- --------------------------------------------------------

--
-- Structure de la table `groups`
--

CREATE TABLE IF NOT EXISTS `groups` (
  `group_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `group_name` (`group_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=28 ;

--
-- Contenu de la table `groups`
--

INSERT INTO `groups` (`group_id`, `group_name`) VALUES
(17, 'ADMIN_GRANT'),
(15, 'ADMIN_GROUP'),
(21, 'ADMIN_QUOTA'),
(27, 'ADMIN_REGISTRATION'),
(13, 'ADMIN_TOKEN'),
(11, 'ADMIN_USER'),
(19, 'USERS');

-- --------------------------------------------------------

--
-- Structure de la table `group_grant`
--

CREATE TABLE IF NOT EXISTS `group_grant` (
  `group_id` int(11) unsigned NOT NULL,
  `grant_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`grant_id`),
  KEY `grant_id` (`grant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `group_grant`
--

INSERT INTO `group_grant` (`group_id`, `grant_id`) VALUES
(11, 71),
(19, 71),
(11, 73),
(11, 75),
(11, 77),
(11, 79),
(11, 81),
(13, 81),
(11, 83),
(13, 83),
(11, 85),
(13, 85),
(11, 87),
(13, 87),
(11, 89),
(15, 89),
(11, 91),
(15, 91),
(11, 93),
(15, 93),
(11, 95),
(15, 95),
(11, 97),
(15, 97),
(11, 99),
(15, 99),
(11, 101),
(15, 101),
(11, 103),
(17, 103),
(11, 105),
(17, 105),
(11, 107),
(17, 107),
(11, 109),
(17, 109),
(11, 111),
(17, 111),
(11, 113),
(17, 113),
(11, 115),
(17, 115),
(11, 117),
(17, 117),
(11, 119),
(17, 119),
(11, 121),
(17, 121),
(11, 123),
(17, 123),
(11, 125),
(17, 125),
(11, 127),
(17, 127),
(11, 129),
(19, 129),
(11, 131),
(19, 131),
(11, 133),
(19, 133),
(11, 135),
(19, 135),
(11, 137),
(19, 137),
(11, 139),
(19, 139),
(11, 141),
(19, 141),
(11, 143),
(19, 143),
(11, 145),
(19, 145),
(11, 147),
(19, 147),
(11, 149),
(21, 149),
(11, 151),
(21, 151),
(11, 153),
(21, 153),
(11, 155),
(21, 155),
(11, 157),
(21, 157),
(11, 159),
(21, 159),
(11, 161),
(21, 161),
(11, 163),
(21, 163),
(11, 165),
(19, 165),
(11, 167),
(19, 167),
(11, 169),
(19, 169),
(11, 173),
(11, 175),
(11, 177),
(11, 179),
(11, 181),
(11, 183),
(11, 185),
(11, 187),
(11, 189),
(19, 189),
(11, 191),
(19, 191),
(11, 193),
(19, 193),
(11, 195),
(19, 195),
(11, 197),
(19, 197),
(11, 199),
(19, 199),
(11, 201),
(19, 201),
(11, 203),
(19, 203),
(11, 205),
(11, 207),
(11, 209),
(11, 211),
(11, 213),
(11, 215),
(11, 217),
(19, 217),
(11, 219),
(19, 219),
(11, 221),
(19, 221),
(11, 223),
(19, 223),
(11, 225),
(27, 225),
(11, 227),
(27, 227),
(11, 229),
(27, 229),
(11, 231),
(11, 233),
(11, 235),
(19, 235),
(11, 237),
(19, 237),
(11, 239),
(19, 239),
(11, 241),
(19, 241),
(11, 243),
(11, 245),
(11, 247),
(11, 249),
(11, 251),
(19, 251),
(11, 253),
(19, 253),
(11, 255),
(19, 255),
(11, 257),
(19, 257),
(11, 263),
(11, 265),
(11, 267),
(11, 269),
(11, 271),
(19, 271),
(11, 273),
(19, 273),
(11, 275),
(19, 275),
(11, 277),
(19, 277),
(11, 278),
(11, 279),
(11, 280),
(11, 281),
(11, 282),
(19, 282),
(11, 283),
(19, 283),
(11, 284),
(11, 285),
(11, 286),
(11, 287);

-- --------------------------------------------------------

--
-- Structure de la table `quotas`
--

CREATE TABLE IF NOT EXISTS `quotas` (
  `quota_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `quota_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`quota_id`),
  UNIQUE KEY `quota_name` (`quota_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=10 ;

--
-- Contenu de la table `quotas`
--

INSERT INTO `quotas` (`quota_id`, `quota_name`) VALUES
(7, 'DATABASES'),
(9, 'DOMAINS');

-- --------------------------------------------------------

--
-- Structure de la table `register`
--

CREATE TABLE IF NOT EXISTS `register` (
  `register_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `register_user` varchar(100) CHARACTER SET utf8 NOT NULL,
  `register_email` varchar(150) CHARACTER SET utf8 NOT NULL,
  `register_code` varchar(32) CHARACTER SET utf8 NOT NULL,
  `register_date` int(11) unsigned NOT NULL,
  PRIMARY KEY (`register_id`),
  UNIQUE KEY `register_user` (`register_user`),
  UNIQUE KEY `register_email` (`register_email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

CREATE TABLE IF NOT EXISTS `services` (
  `service_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `service_amount` float NOT NULL,
  `service_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `service_description` text COLLATE utf8_unicode_ci NOT NULL,
  `service_credits` int(11) unsigned DEFAULT '0',
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=13 ;

-- --------------------------------------------------------

--
-- Structure de la table `tokens`
--

CREATE TABLE IF NOT EXISTS `tokens` (
  `token_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token_value` tinytext CHARACTER SET utf8 NOT NULL,
  `token_lease` int(11) unsigned NOT NULL,
  `token_name` tinytext CHARACTER SET utf8,
  `token_user` int(11) unsigned NOT NULL,
  PRIMARY KEY (`token_id`),
  KEY `token_user` (`token_user`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=90 ;

--
-- Contenu de la table `tokens`
--

INSERT INTO `tokens` (`token_id`, `token_value`, `token_lease`, `token_name`, `token_user`) VALUES
(1, '4b6a4906e4229463182c8c50d0022e97', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `token_grant`
--

CREATE TABLE IF NOT EXISTS `token_grant` (
  `token_id` int(11) unsigned NOT NULL,
  `grant_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`token_id`,`grant_id`),
  KEY `grant_id` (`grant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `token_grant`
--

INSERT INTO `token_grant` (`token_id`, `grant_id`) VALUES
(1, 71),
(1, 73),
(1, 75),
(1, 77),
(1, 79),
(1, 81),
(1, 83),
(1, 85),
(1, 87),
(1, 89),
(1, 91),
(1, 93),
(1, 95),
(1, 97),
(1, 99),
(1, 101),
(1, 103),
(1, 105),
(1, 107),
(1, 109),
(1, 111),
(1, 113),
(1, 115),
(1, 117),
(1, 119),
(1, 121),
(1, 123),
(1, 125),
(1, 127),
(1, 129),
(1, 131),
(1, 133),
(1, 135),
(1, 137),
(1, 139),
(1, 141),
(1, 143),
(1, 145),
(1, 147),
(1, 149),
(1, 151),
(1, 153),
(1, 155),
(1, 157),
(1, 159),
(1, 161),
(1, 163),
(1, 165),
(1, 167),
(1, 169),
(1, 187),
(1, 225),
(1, 227),
(1, 229);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `user_ldap` int(11) unsigned NOT NULL,
  `user_date` int(11) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=78 ;

--
-- Contenu de la table `users`
--

INSERT INTO `users` (`user_id`, `user_name`, `user_ldap`, `user_date`) VALUES
(1, 'admin', 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `user_grant`
--

CREATE TABLE IF NOT EXISTS `user_grant` (
  `user_id` int(11) unsigned NOT NULL,
  `grant_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`grant_id`),
  KEY `grant_id` (`grant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `user_grant`
--

INSERT INTO `user_grant` (`user_id`, `grant_id`) VALUES
(1, 71),
(1, 73),
(1, 75),
(1, 77),
(1, 79),
(1, 81),
(1, 83),
(1, 85),
(1, 87),
(1, 89),
(1, 91),
(1, 93),
(1, 95),
(1, 97),
(1, 99),
(1, 101),
(1, 103),
(1, 105),
(1, 107),
(1, 109),
(1, 111),
(1, 113),
(1, 115),
(1, 117),
(1, 119),
(1, 121),
(1, 123),
(1, 125),
(1, 127),
(1, 129),
(1, 131),
(1, 133),
(1, 135),
(1, 137),
(1, 139),
(1, 141),
(1, 143),
(1, 145),
(1, 147),
(1, 149),
(1, 151),
(1, 153),
(1, 155),
(1, 157),
(1, 159),
(1, 161),
(1, 163),
(1, 165);

-- --------------------------------------------------------

--
-- Structure de la table `user_group`
--

CREATE TABLE IF NOT EXISTS `user_group` (
  `user_id` int(11) unsigned NOT NULL,
  `group_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `user_group`
--

INSERT INTO `user_group` (`user_id`, `group_id`) VALUES
(1, 11),
(1, 13),
(1, 15),
(1, 17),
(1, 19),
(1, 21),
(1, 27);

-- --------------------------------------------------------

--
-- Structure de la table `user_quota`
--

CREATE TABLE IF NOT EXISTS `user_quota` (
  `user_id` int(11) unsigned NOT NULL,
  `quota_id` int(11) unsigned NOT NULL,
  `quota_used` int(11) unsigned NOT NULL DEFAULT '0',
  `quota_max` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`quota_id`),
  KEY `quota_id` (`quota_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contraintes pour les tables exporté
--

--
-- Contraintes pour la table `bill_service`
--
ALTER TABLE `bill_service`
  ADD CONSTRAINT `bill_service_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`bill_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `databases`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_3` FOREIGN KEY (`service_user`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `group_grant`
--
ALTER TABLE `group_grant`
  ADD CONSTRAINT `group_grant_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_grant_ibfk_2` FOREIGN KEY (`grant_id`) REFERENCES `grants` (`grant_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tokens`
--
ALTER TABLE `tokens`
  ADD CONSTRAINT `tokens_ibfk_1` FOREIGN KEY (`token_user`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `token_grant`
--
ALTER TABLE `token_grant`
  ADD CONSTRAINT `token_grant_ibfk_1` FOREIGN KEY (`token_id`) REFERENCES `tokens` (`token_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `token_grant_ibfk_2` FOREIGN KEY (`grant_id`) REFERENCES `grants` (`grant_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_grant`
--
ALTER TABLE `user_grant`
  ADD CONSTRAINT `user_grant_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_grant_ibfk_2` FOREIGN KEY (`grant_id`) REFERENCES `grants` (`grant_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_group`
--
ALTER TABLE `user_group`
  ADD CONSTRAINT `user_group_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_group_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_quota`
--
ALTER TABLE `user_quota`
  ADD CONSTRAINT `user_quota_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_quota_ibfk_2` FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`quota_id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

