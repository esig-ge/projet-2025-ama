-- phpMyAdmin SQL Dump
-- version 4.9.6
-- https://www.phpmyadmin.net/
--
-- Hôte : hhva.myd.infomaniak.com
-- Généré le :  mar. 14 oct. 2025 à 16:02
-- Version du serveur :  10.4.21-MariaDB-1:10.4.21+maria~stretch-log
-- Version de PHP :  7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de données :  `hhva_t25_6`
--
CREATE DATABASE IF NOT EXISTS `hhva_t25_6` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hhva_t25_6`;

-- --------------------------------------------------------

--
-- Structure de la table `ADMINISTRATEUR`
--

DROP TABLE IF EXISTS `ADMINISTRATEUR`;
CREATE TABLE `ADMINISTRATEUR` (
                                  `PER_ID` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ADMINISTRATEUR`
--

INSERT INTO `ADMINISTRATEUR` (`PER_ID`) VALUES
    (1);

-- --------------------------------------------------------

--
-- Structure de la table `ADMIN_TODO`
--

DROP TABLE IF EXISTS `ADMIN_TODO`;
CREATE TABLE `ADMIN_TODO` (
                              `TODO_ID` int(11) NOT NULL,
                              `PER_ID` int(10) UNSIGNED NOT NULL,
                              `TEXTE` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
                              `DONE` tinyint(1) NOT NULL DEFAULT 0,
                              `ORDRE` int(11) NOT NULL DEFAULT 0,
                              `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
                              `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ADRESSE`
--

DROP TABLE IF EXISTS `ADRESSE`;
CREATE TABLE `ADRESSE` (
                           `ADR_ID` bigint(20) NOT NULL,
                           `ADR_RUE` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `ADR_NUMERO` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `ADR_NPA` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `ADR_VILLE` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `ADR_PAYS` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `ADR_TYPE` enum('LIVRAISON','FACTURATION') COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ADRESSE`
--

INSERT INTO `ADRESSE` (`ADR_ID`, `ADR_RUE`, `ADR_NUMERO`, `ADR_NPA`, `ADR_VILLE`, `ADR_PAYS`, `ADR_TYPE`) VALUES
                                                                                                              (1, 'Rue de Lausanne', '12A', '1202', 'Genève', 'Suisse', 'LIVRAISON'),
                                                                                                              (2, 'Rue de Lausanne', '12A', '1202', 'Genève', 'Suisse', 'FACTURATION');

-- --------------------------------------------------------

--
-- Structure de la table `ADRESSE_CLIENT`
--

DROP TABLE IF EXISTS `ADRESSE_CLIENT`;
CREATE TABLE `ADRESSE_CLIENT` (
                                  `PER_ID` bigint(20) NOT NULL,
                                  `ADR_ID` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ADRESSE_CLIENT`
--

INSERT INTO `ADRESSE_CLIENT` (`PER_ID`, `ADR_ID`) VALUES
                                                      (2, 1),
                                                      (2, 2);

-- --------------------------------------------------------

--
-- Structure de la table `BOUQUET`
--

DROP TABLE IF EXISTS `BOUQUET`;
CREATE TABLE `BOUQUET` (
                           `PRO_ID` bigint(20) NOT NULL,
                           `BOU_DESCRIPTION` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                           `BOU_COULEUR` enum('rouge','rose clair','rose','blanc','bleu','noir') COLLATE utf8mb4_unicode_ci NOT NULL,
                           `BOU_NB_ROSES` int(11) NOT NULL,
                           `BOU_QTE_STOCK` int(11) NOT NULL DEFAULT 0
) ;

--
-- Déchargement des données de la table `BOUQUET`
--

INSERT INTO `BOUQUET` (`PRO_ID`, `BOU_DESCRIPTION`, `BOU_COULEUR`, `BOU_NB_ROSES`, `BOU_QTE_STOCK`) VALUES
                                                                                                        (7, 'Bouquet de 12 roses', 'rouge', 12, 20),
                                                                                                        (8, 'Bouquet de 12 roses', 'rose clair', 12, 6),
                                                                                                        (9, 'Bouquet de 12 roses', 'rose', 12, 9),
                                                                                                        (10, 'Bouquet de 12 roses', 'blanc', 12, 6),
                                                                                                        (11, 'Bouquet de 12 roses', 'bleu', 12, 20),
                                                                                                        (12, 'Bouquet de 12 roses', 'noir', 12, 8),
                                                                                                        (13, 'Bouquet de 20 roses', 'rouge', 20, 9),
                                                                                                        (14, 'Bouquet de 20 roses', 'rose clair', 20, 1),
                                                                                                        (15, 'Bouquet de 20 roses', 'rose', 20, 10),
                                                                                                        (16, 'Bouquet de 20 roses', 'blanc', 20, 22),
                                                                                                        (17, 'Bouquet de 20 roses', 'bleu', 20, 9),
                                                                                                        (18, 'Bouquet de 20 roses', 'noir', 20, 12),
                                                                                                        (19, 'Bouquet de 24 roses', 'rouge', 24, 6),
                                                                                                        (20, 'Bouquet de 24 roses', 'rose clair', 24, 6),
                                                                                                        (21, 'Bouquet de 24 roses', 'rose', 24, 6),
                                                                                                        (22, 'Bouquet de 24 roses', 'blanc', 24, 14),
                                                                                                        (23, 'Bouquet de 24 roses', 'bleu', 24, 6),
                                                                                                        (24, 'Bouquet de 24 roses', 'noir', 24, 20),
                                                                                                        (25, 'Bouquet de 36 roses', 'rouge', 36, 20),
                                                                                                        (26, 'Bouquet de 36 roses', 'rose clair', 36, 20),
                                                                                                        (27, 'Bouquet de 36 roses', 'rose', 36, 21),
                                                                                                        (28, 'Bouquet de 36 roses', 'blanc', 36, 20),
                                                                                                        (29, 'Bouquet de 36 roses', 'bleu', 36, 7),
                                                                                                        (30, 'Bouquet de 36 roses', 'noir', 36, 8),
                                                                                                        (31, 'Bouquet de 50 roses', 'rouge', 50, 7),
                                                                                                        (32, 'Bouquet de 50 roses', 'rose clair', 50, 23),
                                                                                                        (33, 'Bouquet de 50 roses', 'rose', 50, 1),
                                                                                                        (34, 'Bouquet de 50 roses', 'blanc', 50, 14),
                                                                                                        (35, 'Bouquet de 50 roses', 'bleu', 50, 50),
                                                                                                        (36, 'Bouquet de 50 roses', 'noir', 50, 2),
                                                                                                        (37, 'Bouquet de 66 roses', 'rouge', 66, 11),
                                                                                                        (38, 'Bouquet de 66 roses', 'rose clair', 66, 11),
                                                                                                        (39, 'Bouquet de 66 roses', 'rose', 66, 10),
                                                                                                        (40, 'Bouquet de 66 roses', 'blanc', 66, 12),
                                                                                                        (41, 'Bouquet de 66 roses', 'bleu', 66, 6),
                                                                                                        (42, 'Bouquet de 66 roses', 'noir', 66, 7),
                                                                                                        (43, 'Bouquet de 99 roses', 'rouge', 99, 7),
                                                                                                        (44, 'Bouquet de 99 roses', 'rose clair', 99, 9),
                                                                                                        (45, 'Bouquet de 99 roses', 'rose', 99, 9),
                                                                                                        (46, 'Bouquet de 99 roses', 'blanc', 99, 9),
                                                                                                        (47, 'Bouquet de 99 roses', 'bleu', 99, 9),
                                                                                                        (48, 'Bouquet de 99 roses', 'noir', 99, 9),
                                                                                                        (49, 'Bouquet de 100 roses', 'rouge', 100, 6),
                                                                                                        (50, 'Bouquet de 100 roses', 'rose clair', 100, 7),
                                                                                                        (51, 'Bouquet de 100 roses', 'rose', 100, 7),
                                                                                                        (52, 'Bouquet de 100 roses', 'blanc', 100, 7),
                                                                                                        (53, 'Bouquet de 100 roses', 'bleu', 100, 7),
                                                                                                        (54, 'Bouquet de 100 roses', 'noir', 100, 7),
                                                                                                        (55, 'Bouquet de 101 roses', 'rouge', 101, 20),
                                                                                                        (56, 'Bouquet de 101 roses', 'rose clair', 101, 10),
                                                                                                        (57, 'Bouquet de 101 roses', 'rose', 101, 20),
                                                                                                        (58, 'Bouquet de 101 roses', 'blanc', 101, 10),
                                                                                                        (59, 'Bouquet de 101 roses', 'bleu', 101, 6),
                                                                                                        (60, 'Bouquet de 101 roses', 'noir', 101, 8),
                                                                                                        (78, NULL, '', 12, 8);

-- --------------------------------------------------------

--
-- Structure de la table `BOUQUET_FLEUR`
--

DROP TABLE IF EXISTS `BOUQUET_FLEUR`;
CREATE TABLE `BOUQUET_FLEUR` (
                                 `BOUQUET_ID` bigint(20) NOT NULL,
                                 `FLEUR_ID` bigint(20) NOT NULL,
                                 `BF_QTE` int(11) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Structure de la table `CLIENT`
--

DROP TABLE IF EXISTS `CLIENT`;
CREATE TABLE `CLIENT` (
                          `PER_ID` bigint(20) NOT NULL,
                          `CLI_DATENAISSANCE` date DEFAULT NULL,
                          `CLI_DATE_INSCRIPTION` datetime NOT NULL DEFAULT current_timestamp(),
                          `CLI_STRIPE_CUSTOMER_ID` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `CLIENT`
--

INSERT INTO `CLIENT` (`PER_ID`, `CLI_DATENAISSANCE`, `CLI_DATE_INSCRIPTION`, `CLI_STRIPE_CUSTOMER_ID`) VALUES
                                                                                                           (1, NULL, '2025-10-09 15:05:39', NULL),
                                                                                                           (2, '1999-05-31', '2025-10-09 15:05:39', NULL),
                                                                                                           (3, NULL, '2025-10-09 15:05:39', NULL),
                                                                                                           (17, NULL, '2025-10-09 15:05:39', NULL),
                                                                                                           (18, NULL, '2025-10-12 23:40:02', NULL),
                                                                                                           (19, NULL, '2025-10-13 14:52:39', NULL),
                                                                                                           (20, NULL, '2025-10-14 15:16:25', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `COFFRET`
--

DROP TABLE IF EXISTS `COFFRET`;
CREATE TABLE `COFFRET` (
                           `PRO_ID` bigint(20) NOT NULL,
                           `COF_EVENEMENT` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `COF_QTE_STOCK` int(11) NOT NULL DEFAULT 0
) ;

--
-- Déchargement des données de la table `COFFRET`
--

INSERT INTO `COFFRET` (`PRO_ID`, `COF_EVENEMENT`, `COF_QTE_STOCK`) VALUES
                                                                       (61, 'Anniversaire', 9),
                                                                       (62, 'Saint-Valentin', 10),
                                                                       (63, 'Fête des Mères', 14),
                                                                       (64, 'Baptême', 6),
                                                                       (65, 'Mariage', 20),
                                                                       (66, 'Pâques', 1),
                                                                       (67, 'Noël', 11),
                                                                       (68, 'Nouvel An', 5);

-- --------------------------------------------------------

--
-- Structure de la table `COFFRET_BOUQUET`
--

DROP TABLE IF EXISTS `COFFRET_BOUQUET`;
CREATE TABLE `COFFRET_BOUQUET` (
                                   `COFFRET_ID` bigint(20) NOT NULL,
                                   `BOUQUET_ID` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `COMMANDE`
--

DROP TABLE IF EXISTS `COMMANDE`;
CREATE TABLE `COMMANDE` (
                            `COM_ID` bigint(20) NOT NULL,
                            `PER_ID` bigint(20) NOT NULL,
                            `LIV_ID` bigint(20) DEFAULT NULL,
                            `PAI_ID` bigint(20) DEFAULT NULL,
                            `STRIPE_SESSION_ID` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `COM_STATUT` enum('en preparation','en attente d''expédition','expediee','en attente de ramassage','livree','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en preparation',
                            `COM_ARCHIVE` tinyint(1) NOT NULL DEFAULT 0,
                            `COM_ARCHIVED_AT` datetime DEFAULT NULL,
                            `COM_DATE` date NOT NULL,
                            `COM_DESCRIPTION` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `COM_TVA_TAUX` decimal(5,2) NOT NULL DEFAULT 7.70,
                            `COM_TVA_MONTANT` decimal(10,2) NOT NULL DEFAULT 0.00,
                            `COM_MONTANT_TOTAL` decimal(10,2) NOT NULL DEFAULT 0.00,
                            `TOTAL_PAYER_CHF` decimal(10,2) DEFAULT NULL,
                            `COM_RETRAIT_DEBUT` datetime DEFAULT NULL,
                            `COM_RETRAIT_FIN` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `COMMANDE`
--

INSERT INTO `COMMANDE` (`COM_ID`, `PER_ID`, `LIV_ID`, `PAI_ID`, `STRIPE_SESSION_ID`, `COM_STATUT`, `COM_ARCHIVE`, `COM_ARCHIVED_AT`, `COM_DATE`, `COM_DESCRIPTION`, `COM_TVA_TAUX`, `COM_TVA_MONTANT`, `COM_MONTANT_TOTAL`, `TOTAL_PAYER_CHF`, `COM_RETRAIT_DEBUT`, `COM_RETRAIT_FIN`) VALUES
                                                                                                                                                                                                                                                                                           (1, 2, 1, NULL, NULL, 'livree', 1, '2025-09-30 18:31:29', '2025-09-23', NULL, '7.70', '0.00', '0.00', NULL, NULL, NULL),
                                                                                                                                                                                                                                                                                           (13, 1, NULL, NULL, NULL, 'annulee', 0, NULL, '2025-09-24', NULL, '7.70', '0.00', '0.00', NULL, NULL, NULL),
                                                                                                                                                                                                                                                                                           (17, 2, 7, NULL, NULL, 'en attente de ramassage', 0, NULL, '2025-09-30', NULL, '7.70', '0.00', '0.00', NULL, '2025-10-10 11:02:36', '2025-10-15 11:02:36'),
                                                                                                                                                                                                                                                                                           (28, 2, 18, NULL, NULL, 'livree', 1, '2025-10-10 15:45:49', '2025-10-10', NULL, '8.10', '1.18', '0.00', NULL, NULL, NULL),
                                                                                                                                                                                                                                                                                           (29, 2, 9, NULL, NULL, 'en attente de ramassage', 0, NULL, '2025-10-10', NULL, '8.10', '1.18', '0.00', NULL, '2025-10-10 11:49:50', '2025-10-15 11:49:50'),
                                                                                                                                                                                                                                                                                           (30, 2, 10, NULL, NULL, 'en attente de ramassage', 0, NULL, '2025-10-10', NULL, '8.10', '1.18', '0.00', NULL, '2025-10-10 11:49:46', '2025-10-15 11:49:46'),
                                                                                                                                                                                                                                                                                           (31, 2, 11, NULL, NULL, 'en attente de ramassage', 0, NULL, '2025-10-10', NULL, '8.10', '1.18', '0.00', NULL, '2025-10-10 11:51:13', '2025-10-15 11:51:13'),
                                                                                                                                                                                                                                                                                           (32, 2, 12, NULL, NULL, 'en attente de ramassage', 0, NULL, '2025-10-10', NULL, '8.10', '1.18', '0.00', NULL, '2025-10-10 12:06:35', '2025-10-15 12:06:35'),
                                                                                                                                                                                                                                                                                           (33, 2, 17, NULL, NULL, 'en attente d\'expédition', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', NULL, NULL, NULL),
(34, 2, 2, NULL, NULL, 'livree', 0, NULL, '2025-10-10', NULL, '7.70', '0.00', '0.00', NULL, '2025-10-10 13:12:39', '2025-10-15 13:12:39'),
(35, 2, 14, NULL, NULL, 'en attente de ramassage', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', NULL, '2025-10-10 17:14:25', '2025-10-15 17:14:25'),
(36, 2, 15, 4, 'cs_test_b1vvaJR0shVDgnVffMgQEPsME1owGVPwo9F6vZSmhwJvVqLDehYI8sKuBR', 'expediee', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '100.01', NULL, NULL),
(37, 3, 16, 33, 'cs_test_b1Xo4BnGLXQpDgxNswzOphasQGCqaQINuyCeePKJdzmo7InyeKtsiK3h76', 'en attente d\'expédition', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '17.01', NULL, NULL),
                                                                                                                                                                                                                                                                                           (38, 2, 19, 13, 'cs_test_b1bzBfAk6NPXk61AMYKtci2VWsHUibPTyohG1VLeD6kUT5fVC4UYR84r64', '', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '110.01', NULL, NULL),
                                                                                                                                                                                                                                                                                           (39, 2, 20, 14, 'cs_test_b1S3wye9jLeDKXsw1peqczT45S7qZYc2EkeSMpR4ck8KB6G2CZF9Ienpe8', '', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '110.01', NULL, NULL),
                                                                                                                                                                                                                                                                                           (40, 2, 21, 15, 'cs_test_b1AZXDQ5mnYJahB5mixnCOZN5qrEmAwR2P1M9RcdE9odJWgkAtUitIjTu5', '', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '20.02', NULL, NULL),
                                                                                                                                                                                                                                                                                           (41, 2, 22, 16, 'cs_test_b13QSwGcbkZVDM1J9wg9O7VuqvuVxvcjtZ8ltWsxbLnz1f1FccgSVHF75o', '', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '55.02', NULL, NULL),
                                                                                                                                                                                                                                                                                           (42, 2, 23, 17, 'cs_test_b1ZDH941kVWttiszT9PacTY3MV6okC2qouYYlctnhiCcpycOdHDDBZHkaM', '', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '110.00', NULL, NULL),
                                                                                                                                                                                                                                                                                           (43, 2, 24, 21, 'cs_test_b1uYWVVnh7Cx5p3PQZJevsxslx5TPAtf7RuddFfpWblsSBtKFJp8VlDIkH', 'en attente d\'expédition', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '110.00', NULL, NULL),
(44, 2, 25, 22, 'cs_test_b1r6tf9yLCFylALdUEd2MxjLyTUaTGo1JpqeLxJlrDMxTfziYyhkU6OThR', 'en attente d\'expédition', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '110.00', NULL, NULL),
                                                                                                                                                                                                                                                                                           (45, 2, 26, 24, 'cs_test_b1BAgYhqTiwAexXiBCAjbVUTBIwrBB68HmJjfcykOczoCNpGAIbkFrdQ3Y', 'en attente d\'expédition', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '100.00', NULL, NULL),
(46, 2, 27, 25, 'cs_test_b1OF9uxQUBXP69XLdzlkL9NOYZAzJ3lV3fJfkrxR6jNh4Tg2eITGHWqKv2', 'en attente d\'expédition', 0, NULL, '2025-10-10', NULL, '0.00', '0.00', '0.00', '110.00', NULL, NULL),
                                                                                                                                                                                                                                                                                           (47, 2, 28, 35, 'cs_test_b1KJ0solfrmeweSEGM42dRCnrZ2N0wdrK1cJMdivU8RonkuwbtzqlVa6c5', 'en attente d\'expédition', 0, NULL, '2025-10-12', NULL, '0.00', '0.00', '0.00', '33.01', NULL, NULL),
(48, 18, 29, 29, 'cs_test_b1PPVfTxpWXqNugCpcIDy4X3tMX7yN3Gs7AZU5R7odailpYpCr275JmvDE', 'en attente d\'expédition', 0, NULL, '2025-10-13', NULL, '0.00', '0.00', '0.00', '14.01', NULL, NULL),
                                                                                                                                                                                                                                                                                           (49, 18, 30, 31, 'cs_test_b1eBLCZsYJn7VitW0a1gJPxCAuYveK3DZzipwFJP2pvaxyjKUqOb4VFVCB', 'en attente d\'expédition', 0, NULL, '2025-10-13', NULL, '0.00', '0.00', '0.00', '59.01', NULL, NULL),
(50, 19, 34, 32, 'cs_test_b1xhMWpezB1i3Uyyz9mD1WyMjBvDcXzH8iL2wW2bkMw1tlqVVwoXwPnNRy', 'expediee', 0, NULL, '2025-10-13', NULL, '0.00', '0.00', '0.00', '46.01', NULL, NULL),
(51, 3, 32, 34, 'cs_test_b1KJnMMP2wwvzLTCX5C9efY6czFbi5vLl98tSSPdvFq1xV70whjYwroVpo', 'en attente d\'expédition', 0, NULL, '2025-10-14', NULL, '0.00', '0.00', '0.00', '50.01', NULL, NULL),
                                                                                                                                                                                                                                                                                           (52, 2, 33, 36, 'cs_test_b1REVfgfQ8DZzBJAcVGpfGIIvnJQ3UfYkw4if9Q6k2cqq8RBPW1AcN1DYV', 'en attente de ramassage', 0, NULL, '2025-10-14', NULL, '0.00', '0.00', '0.00', '33.01', '2025-10-14 14:39:36', '2025-10-19 14:39:36'),
                                                                                                                                                                                                                                                                                           (53, 2, NULL, NULL, NULL, 'en preparation', 0, NULL, '2025-10-14', NULL, '7.70', '0.00', '0.00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `COMMANDE_EMBALLAGE`
--

DROP TABLE IF EXISTS `COMMANDE_EMBALLAGE`;
CREATE TABLE `COMMANDE_EMBALLAGE` (
                                      `COM_ID` bigint(20) NOT NULL,
                                      `EMB_ID` bigint(20) NOT NULL,
                                      `CE_QTE` int(11) NOT NULL DEFAULT 1
) ;

--
-- Déchargement des données de la table `COMMANDE_EMBALLAGE`
--

INSERT INTO `COMMANDE_EMBALLAGE` (`COM_ID`, `EMB_ID`, `CE_QTE`) VALUES
                                                                    (1, 3, 1),
                                                                    (13, 2, 1),
                                                                    (13, 4, 1),
                                                                    (17, 3, 1),
                                                                    (28, 4, 1),
                                                                    (29, 2, 1),
                                                                    (31, 1, 1),
                                                                    (32, 5, 1),
                                                                    (33, 1, 1),
                                                                    (34, 4, 1),
                                                                    (37, 5, 1),
                                                                    (40, 4, 1),
                                                                    (41, 2, 1),
                                                                    (47, 2, 1),
                                                                    (48, 1, 1),
                                                                    (49, 2, 1),
                                                                    (50, 1, 1),
                                                                    (51, 1, 1),
                                                                    (52, 2, 1);

-- --------------------------------------------------------

--
-- Structure de la table `COMMANDE_PRODUIT`
--

DROP TABLE IF EXISTS `COMMANDE_PRODUIT`;
CREATE TABLE `COMMANDE_PRODUIT` (
                                    `COM_ID` bigint(20) NOT NULL,
                                    `PRO_ID` bigint(20) NOT NULL,
                                    `CP_QTE_COMMANDEE` int(11) NOT NULL,
                                    `CP_TYPE_PRODUIT` enum('bouquet','fleur','coffret') COLLATE utf8mb4_unicode_ci NOT NULL
) ;

--
-- Déchargement des données de la table `COMMANDE_PRODUIT`
--

INSERT INTO `COMMANDE_PRODUIT` (`COM_ID`, `PRO_ID`, `CP_QTE_COMMANDEE`, `CP_TYPE_PRODUIT`) VALUES
                                                                                               (1, 4, 1, 'fleur'),
                                                                                               (13, 66, 1, 'coffret'),
                                                                                               (13, 78, 2, 'bouquet'),
                                                                                               (17, 49, 1, 'bouquet'),
                                                                                               (28, 1, 1, 'fleur'),
                                                                                               (29, 43, 1, 'bouquet'),
                                                                                               (30, 63, 1, 'coffret'),
                                                                                               (31, 5, 1, 'fleur'),
                                                                                               (32, 6, 5, 'fleur'),
                                                                                               (33, 2, 1, 'fleur'),
                                                                                               (34, 1, 1, 'fleur'),
                                                                                               (35, 62, 1, 'coffret'),
                                                                                               (36, 65, 1, 'coffret'),
                                                                                               (37, 1, 1, 'fleur'),
                                                                                               (37, 2, 1, 'fleur'),
                                                                                               (38, 64, 1, 'coffret'),
                                                                                               (39, 64, 1, 'coffret'),
                                                                                               (40, 1, 1, 'fleur'),
                                                                                               (41, 13, 1, 'bouquet'),
                                                                                               (42, 64, 1, 'coffret'),
                                                                                               (43, 64, 1, 'coffret'),
                                                                                               (44, 65, 1, 'coffret'),
                                                                                               (45, 62, 1, 'coffret'),
                                                                                               (46, 66, 1, 'coffret'),
                                                                                               (47, 1, 3, 'fleur'),
                                                                                               (47, 6, 1, 'fleur'),
                                                                                               (48, 5, 1, 'fleur'),
                                                                                               (49, 6, 1, 'fleur'),
                                                                                               (49, 17, 1, 'bouquet'),
                                                                                               (50, 11, 1, 'bouquet'),
                                                                                               (51, 15, 1, 'bouquet'),
                                                                                               (52, 1, 3, 'fleur'),
                                                                                               (52, 6, 1, 'fleur'),
                                                                                               (53, 1, 1, 'fleur');

-- --------------------------------------------------------

--
-- Structure de la table `COMMANDE_SUPP`
--

DROP TABLE IF EXISTS `COMMANDE_SUPP`;
CREATE TABLE `COMMANDE_SUPP` (
                                 `SUP_ID` bigint(20) NOT NULL,
                                 `COM_ID` bigint(20) NOT NULL,
                                 `CS_QTE_COMMANDEE` int(11) NOT NULL
) ;

--
-- Déchargement des données de la table `COMMANDE_SUPP`
--

INSERT INTO `COMMANDE_SUPP` (`SUP_ID`, `COM_ID`, `CS_QTE_COMMANDEE`) VALUES
                                                                         (1, 34, 1),
                                                                         (1, 37, 1),
                                                                         (1, 48, 1),
                                                                         (1, 50, 1),
                                                                         (4, 28, 1),
                                                                         (4, 33, 1),
                                                                         (4, 47, 1),
                                                                         (4, 52, 1),
                                                                         (5, 47, 2),
                                                                         (5, 52, 2),
                                                                         (6, 29, 1),
                                                                         (6, 40, 1),
                                                                         (6, 41, 1),
                                                                         (7, 1, 1),
                                                                         (7, 31, 1),
                                                                         (7, 49, 1),
                                                                         (7, 50, 1),
                                                                         (8, 32, 1);

-- --------------------------------------------------------

--
-- Structure de la table `EMBALLAGE`
--

DROP TABLE IF EXISTS `EMBALLAGE`;
CREATE TABLE `EMBALLAGE` (
                             `EMB_ID` bigint(20) NOT NULL,
                             `EMB_NOM` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
                             `EMB_COULEUR` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
                             `EMB_QTE_STOCK` int(11) NOT NULL DEFAULT 0,
                             `EMB_IMG` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                             `EMB_ACTIF` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=actif,0=inactif'
) ;

--
-- Déchargement des données de la table `EMBALLAGE`
--

INSERT INTO `EMBALLAGE` (`EMB_ID`, `EMB_NOM`, `EMB_COULEUR`, `EMB_QTE_STOCK`, `EMB_IMG`, `EMB_ACTIF`) VALUES
                                                                                                          (1, 'Papier blanc', 'blanc avec bords dorés', 21, NULL, 1),
                                                                                                          (2, 'Papier noir', 'noir avec bords blancs', 14, NULL, 1),
                                                                                                          (3, 'Papier rose pâle', 'rose avec bords dorés/roses ', 0, NULL, 1),
                                                                                                          (4, 'Papier gris', 'gris avec bords dorés', 20, NULL, 1),
                                                                                                          (5, 'Papier violet', 'violet avec bords couleur bronze', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `FLEUR`
--

DROP TABLE IF EXISTS `FLEUR`;
CREATE TABLE `FLEUR` (
                         `PRO_ID` bigint(20) NOT NULL,
                         `FLE_TYPE` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `FLE_COULEUR` enum('rouge','rose clair','rose','blanc','bleu','noir') COLLATE utf8mb4_unicode_ci NOT NULL,
                         `FLE_QTE_STOCK` int(11) NOT NULL DEFAULT 0
) ;

--
-- Déchargement des données de la table `FLEUR`
--

INSERT INTO `FLEUR` (`PRO_ID`, `FLE_TYPE`, `FLE_COULEUR`, `FLE_QTE_STOCK`) VALUES
                                                                               (1, 'Rose éternelle', 'rouge', 13),
                                                                               (2, 'Rose éternelle', 'rose clair', 25),
                                                                               (3, 'Rose éternelle', 'rose', 0),
                                                                               (4, 'Rose éternelle', 'blanc', 12),
                                                                               (5, 'Rose éternelle', 'bleu', 0),
                                                                               (6, 'Rose éternelle', 'noir', 10),
                                                                               (81, '', '', 50);

-- --------------------------------------------------------

--
-- Structure de la table `LIVRAISON`
--

DROP TABLE IF EXISTS `LIVRAISON`;
CREATE TABLE `LIVRAISON` (
                             `LIV_ID` bigint(20) NOT NULL,
                             `LIV_STATUT` enum('prévue','en cours','livrée','annulée') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prévue',
                             `LIV_MODE` enum('retrait','courrier','coursier') COLLATE utf8mb4_unicode_ci NOT NULL,
                             `LIV_MONTANT_FRAIS` decimal(12,2) NOT NULL DEFAULT 0.00,
                             `LIV_NOM_TRANSPORTEUR` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                             `LIV_NUM_SUIVI_COMMANDE` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                             `LIV_DATE` date NOT NULL
) ;

--
-- Déchargement des données de la table `LIVRAISON`
--

INSERT INTO `LIVRAISON` (`LIV_ID`, `LIV_STATUT`, `LIV_MODE`, `LIV_MONTANT_FRAIS`, `LIV_NOM_TRANSPORTEUR`, `LIV_NUM_SUIVI_COMMANDE`, `LIV_DATE`) VALUES
                                                                                                                                                    (1, '', '', '5.00', NULL, NULL, '2025-10-03'),
                                                                                                                                                    (2, '', '', '5.00', NULL, NULL, '2025-10-03'),
                                                                                                                                                    (3, '', '', '5.00', NULL, NULL, '2025-10-06'),
                                                                                                                                                    (4, '', '', '5.00', NULL, NULL, '2025-10-06'),
                                                                                                                                                    (5, '', '', '5.00', NULL, NULL, '2025-10-06'),
                                                                                                                                                    (6, '', '', '5.00', NULL, NULL, '2025-10-08'),
                                                                                                                                                    (7, '', 'retrait', '0.00', NULL, NULL, '2025-10-13'),
                                                                                                                                                    (8, '', '', '5.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (9, '', '', '5.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (10, '', '', '5.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (11, '', '', '5.00', 'DHL', 'DXH123456789', '2025-10-13'),
                                                                                                                                                    (12, '', '', '5.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (13, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (14, 'en cours', 'retrait', '0.00', 'Poste', 'DXH987654321', '2025-10-10'),
                                                                                                                                                    (15, '', 'retrait', '0.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (16, '', '', '5.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (17, 'prévue', '', '10.00', 'DPD', 'DHX134679852', '2025-10-10'),
                                                                                                                                                    (18, 'prévue', '', '5.00', 'DHL', 'DHX134672252', '2025-10-10'),
                                                                                                                                                    (19, 'prévue', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (20, 'prévue', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (21, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (22, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (23, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (24, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (25, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (26, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (27, '', '', '10.00', NULL, NULL, '2025-10-10'),
                                                                                                                                                    (28, '', 'retrait', '0.00', NULL, NULL, '2025-10-14'),
                                                                                                                                                    (29, '', '', '5.00', NULL, NULL, '2025-10-13'),
                                                                                                                                                    (30, '', '', '5.00', NULL, NULL, '2025-10-13'),
                                                                                                                                                    (31, '', '', '5.00', NULL, NULL, '2025-10-13'),
                                                                                                                                                    (32, 'prévue', '', '10.00', NULL, NULL, '2025-10-14'),
                                                                                                                                                    (33, '', 'retrait', '0.00', NULL, NULL, '2025-10-14'),
                                                                                                                                                    (34, 'prévue', '', '5.00', 'Poste', 'DHX1346722458', '2025-10-14');

-- --------------------------------------------------------

--
-- Structure de la table `NOTIFICATION`
--

DROP TABLE IF EXISTS `NOTIFICATION`;
CREATE TABLE `NOTIFICATION` (
                                `NOT_ID` bigint(20) NOT NULL,
                                `PER_ID` bigint(20) NOT NULL,
                                `COM_ID` bigint(20) DEFAULT NULL,
                                `NOT_TYPE` varchar(50) NOT NULL,
                                `NOT_TEXTE` varchar(255) NOT NULL,
                                `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
                                `READ_AT` datetime DEFAULT NULL,
                                `NOT_DATE` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `NOTIFICATION`
--

INSERT INTO `NOTIFICATION` (`NOT_ID`, `PER_ID`, `COM_ID`, `NOT_TYPE`, `NOT_TEXTE`, `CREATED_AT`, `READ_AT`, `NOT_DATE`) VALUES
                                                                                                                            (5, 2, 17, 'pickup_ready', 'Votre commande #17 est prête au retrait en boutique.', '2025-10-10 11:02:36', '2025-10-10 11:03:06', NULL),
                                                                                                                            (6, 2, 30, 'pickup_ready', 'Votre commande #30 est prête au retrait en boutique.', '2025-10-10 11:49:46', '2025-10-10 11:49:59', NULL),
                                                                                                                            (7, 2, 29, 'pickup_ready', 'Votre commande #29 est prête au retrait en boutique.', '2025-10-10 11:49:50', '2025-10-10 11:50:00', NULL),
                                                                                                                            (8, 2, 31, 'pickup_ready', 'Votre commande #31 est prête au retrait en boutique.', '2025-10-10 11:51:13', '2025-10-10 11:51:18', NULL),
                                                                                                                            (9, 2, 32, 'pickup_ready', 'Votre commande #32 est prête au retrait en boutique.', '2025-10-10 12:06:35', '2025-10-10 12:06:39', NULL),
                                                                                                                            (10, 2, 34, 'pickup_ready', 'Votre commande #34 est prête au retrait en boutique.', '2025-10-10 13:12:39', '2025-10-10 13:12:57', NULL),
                                                                                                                            (11, 2, 35, 'pickup_ready', 'Votre commande #35 est prête au retrait en boutique.', '2025-10-10 17:14:25', '2025-10-10 17:14:58', NULL),
                                                                                                                            (12, 2, 52, 'pickup_ready', 'Votre commande #52 est prête au retrait en boutique.', '2025-10-14 14:39:36', '2025-10-14 14:40:24', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `PAIEMENT`
--

DROP TABLE IF EXISTS `PAIEMENT`;
CREATE TABLE `PAIEMENT` (
                            `PAI_ID` bigint(20) NOT NULL,
                            `PER_ID` bigint(20) DEFAULT NULL,
                            `PAI_MODE` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `PAI_MONTANT` int(11) NOT NULL,
                            `PAI_MONNAIE` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CHF',
                            `PAI_STRIPE_PAYMENT_INTENT_ID` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `PAI_STRIPE_LATEST_CHARGE_ID` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `PAI_RECEIPT_URL` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `PAI_STATUT` enum('mode_paiement_requis','confirmation_requise','action_requise','en_traitement','reussi','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mode_paiement_requis',
                            `PAI_DATE` datetime DEFAULT NULL,
                            `PAI_DATE_CONFIRMATION` datetime DEFAULT NULL,
                            `PAI_DATE_ANNULATION` datetime DEFAULT NULL,
                            `PAI_LAST_EVENT_ID` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `PAI_LAST_EVENT_TYPE` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `PAI_LAST_EVENT_PAYLOAD` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `PAIEMENT`
--

INSERT INTO `PAIEMENT` (`PAI_ID`, `PER_ID`, `PAI_MODE`, `PAI_MONTANT`, `PAI_MONNAIE`, `PAI_STRIPE_PAYMENT_INTENT_ID`, `PAI_STRIPE_LATEST_CHARGE_ID`, `PAI_RECEIPT_URL`, `PAI_STATUT`, `PAI_DATE`, `PAI_DATE_CONFIRMATION`, `PAI_DATE_ANNULATION`, `PAI_LAST_EVENT_ID`, `PAI_LAST_EVENT_TYPE`, `PAI_LAST_EVENT_PAYLOAD`) VALUES
                                                                                                                                                                                                                                                                                                                            (4, 2, 'card', 100, 'CHF', '', NULL, NULL, '', '2025-10-10 13:56:07', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (13, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 17:25:38', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (14, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 17:30:15', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (15, 2, 'card', 20, 'CHF', NULL, NULL, NULL, '', '2025-10-10 17:30:44', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (16, 2, 'card', 55, 'CHF', NULL, NULL, NULL, '', '2025-10-10 17:31:52', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (17, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 17:51:41', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (18, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:05:02', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (19, 2, 'card', 105, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:09:40', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (20, 2, 'card', 105, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:10:15', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (21, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:10:37', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (22, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:18:40', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (23, 2, 'card', 100, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:24:53', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (24, 2, 'card', 100, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:25:06', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (25, 2, 'card', 110, 'CHF', NULL, NULL, NULL, '', '2025-10-10 18:30:36', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (26, 2, 'card', 27, 'CHF', NULL, NULL, NULL, '', '2025-10-12 22:33:07', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (27, 2, 'card', 32, 'CHF', NULL, NULL, NULL, '', '2025-10-12 22:34:07', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (28, 2, 'card', 22, 'CHF', NULL, NULL, NULL, '', '2025-10-12 22:34:20', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (29, 18, 'card', 14, 'CHF', NULL, NULL, NULL, '', '2025-10-13 13:44:57', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (30, 18, 'card', 59, 'CHF', NULL, NULL, NULL, '', '2025-10-13 14:29:38', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (31, 18, 'card', 59, 'CHF', NULL, NULL, NULL, '', '2025-10-13 14:29:39', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (32, 19, 'card', 46, 'CHF', NULL, NULL, NULL, '', '2025-10-13 14:54:21', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (33, 3, 'card', 17, 'CHF', NULL, NULL, NULL, '', '2025-10-14 11:41:02', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (34, 3, 'card', 50, 'CHF', NULL, NULL, NULL, '', '2025-10-14 14:04:54', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (35, 2, 'card', 33, 'CHF', NULL, NULL, NULL, '', '2025-10-14 14:32:42', NULL, NULL, NULL, 'checkout.session.created', NULL),
                                                                                                                                                                                                                                                                                                                            (36, 2, 'card', 33, 'CHF', NULL, NULL, NULL, '', '2025-10-14 14:38:09', NULL, NULL, NULL, 'checkout.session.created', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `PERSONNE`
--

DROP TABLE IF EXISTS `PERSONNE`;
CREATE TABLE `PERSONNE` (
                            `PER_ID` bigint(20) NOT NULL,
                            `PER_NOM` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `PER_PRENOM` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `PER_EMAIL` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `PER_MDP` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `PER_NUM_TEL` char(10) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `reset_token_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `reset_token_expires_at` datetime DEFAULT NULL,
                            `PER_COMPTE_ACTIF` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=actif,0=inactif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `PERSONNE`
--

INSERT INTO `PERSONNE` (`PER_ID`, `PER_NOM`, `PER_PRENOM`, `PER_EMAIL`, `PER_MDP`, `PER_NUM_TEL`, `reset_token_hash`, `reset_token_expires_at`, `PER_COMPTE_ACTIF`) VALUES
                                                                                                                                                                        (1, 'Admin', 'Admin', 'dk.bloom@gmail.com', 'Admin_123', '0790000000', '896738', '2025-10-06 15:36:12', 1),
                                                                                                                                                                        (2, 'Bustamante', 'Johany', 'johany.bstmn@eduge.ch', '123456Jb.', '0780000000', NULL, NULL, 1),
                                                                                                                                                                        (3, 'osseni', 'yasmine', 'yas@gmail.com', '12345Ys.', '0789763458', NULL, NULL, 1),
                                                                                                                                                                        (17, 'Bustamante', 'Astrid', 'astrid.jybm@gmail.com', '123456Ab.', '0798546321', NULL, NULL, 1),
                                                                                                                                                                        (18, 'NomTest', 'PrenomTest', 'test@gmail.com', 'Test123456.', '0798765432', NULL, NULL, 1),
                                                                                                                                                                        (19, 'bois', 'Lara', 'lea.b@gmail.com', 'Lara_123', '0761247569', NULL, NULL, 1),
                                                                                                                                                                        (20, 'Bizarre', 'Jean', 'jb@gmail.com', 'Abcdef_123456', '0793063962', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `PRODUIT`
--

DROP TABLE IF EXISTS `PRODUIT`;
CREATE TABLE `PRODUIT` (
                           `PRO_ID` bigint(20) NOT NULL,
                           `PRO_NOM` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
                           `PRO_PRIX` decimal(12,2) NOT NULL,
                           `PRO_IMG` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                           `PRO_TYPE` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                           `PRO_ACTIF` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=actif,0=inactif'
) ;

--
-- Déchargement des données de la table `PRODUIT`
--

INSERT INTO `PRODUIT` (`PRO_ID`, `PRO_NOM`, `PRO_PRIX`, `PRO_IMG`, `PRO_TYPE`, `PRO_ACTIF`) VALUES
                                                                                                (1, 'Rose Rouge', '5.00', 'rouge.png', 'fleur', 1),
                                                                                                (2, 'Rose Rose Clair', '5.00', 'rose_claire.png', 'fleur', 1),
                                                                                                (3, 'Rose Rose', '5.00', 'rose.png', 'fleur', 1),
                                                                                                (4, 'Rose Blanche', '6.00', 'rosesBlanche.png', 'fleur', 1),
                                                                                                (5, 'Rose Bleue', '7.00', 'bleu.png', NULL, 1),
                                                                                                (6, 'Rose Noire', '5.00', 'noir.png', NULL, 1),
                                                                                                (7, 'Bouquet 12 Rouge', '30.00', NULL, NULL, 1),
                                                                                                (8, 'Bouquet 12 Rose Clair', '30.00', NULL, NULL, 1),
                                                                                                (9, 'Bouquet 12 Rose', '30.00', NULL, NULL, 1),
                                                                                                (10, 'Bouquet 12 Blanc', '30.00', NULL, NULL, 1),
                                                                                                (11, 'Bouquet 12 Bleu', '30.00', NULL, NULL, 1),
                                                                                                (12, 'Bouquet 12 Noir', '30.00', NULL, NULL, 1),
                                                                                                (13, 'Bouquet 20 Rouge', '40.00', NULL, NULL, 1),
                                                                                                (14, 'Bouquet 20 Rose Clair', '40.00', NULL, NULL, 1),
                                                                                                (15, 'Bouquet 20 Rose', '40.00', NULL, NULL, 1),
                                                                                                (16, 'Bouquet 20 Blanc', '40.00', NULL, NULL, 1),
                                                                                                (17, 'Bouquet 20 Bleu', '40.00', NULL, NULL, 1),
                                                                                                (18, 'Bouquet 20 Noir', '40.00', NULL, NULL, 1),
                                                                                                (19, 'Bouquet 24 Rouge', '45.00', NULL, NULL, 1),
                                                                                                (20, 'Bouquet 24 Rose Clair', '45.00', NULL, NULL, 1),
                                                                                                (21, 'Bouquet 24 Rose', '45.00', NULL, NULL, 1),
                                                                                                (22, 'Bouquet 24 Blanc', '45.00', NULL, NULL, 1),
                                                                                                (23, 'Bouquet 24 Bleu', '45.00', NULL, NULL, 1),
                                                                                                (24, 'Bouquet 24 Noir', '45.00', NULL, NULL, 1),
                                                                                                (25, 'Bouquet 36 Rouge', '60.00', NULL, NULL, 1),
                                                                                                (26, 'Bouquet 36 Rose Clair', '60.00', NULL, NULL, 1),
                                                                                                (27, 'Bouquet 36 Rose', '60.00', NULL, NULL, 1),
                                                                                                (28, 'Bouquet 36 Blanc', '60.00', NULL, NULL, 1),
                                                                                                (29, 'Bouquet 36 Bleu', '60.00', NULL, NULL, 1),
                                                                                                (30, 'Bouquet 36 Noir', '60.00', NULL, NULL, 1),
                                                                                                (31, 'Bouquet 50 Rouge', '70.00', NULL, NULL, 1),
                                                                                                (32, 'Bouquet 50 Rose Clair', '70.00', NULL, NULL, 1),
                                                                                                (33, 'Bouquet 50 Rose', '70.00', NULL, NULL, 1),
                                                                                                (34, 'Bouquet 50 Blanc', '70.00', NULL, NULL, 1),
                                                                                                (35, 'Bouquet 50 Bleu', '70.00', NULL, NULL, 1),
                                                                                                (36, 'Bouquet 50 Noir', '70.00', NULL, NULL, 1),
                                                                                                (37, 'Bouquet 66 Rouge', '85.00', NULL, NULL, 1),
                                                                                                (38, 'Bouquet 66 Rose Clair', '85.00', NULL, NULL, 1),
                                                                                                (39, 'Bouquet 66 Rose', '85.00', NULL, NULL, 1),
                                                                                                (40, 'Bouquet 66 Blanc', '85.00', NULL, NULL, 1),
                                                                                                (41, 'Bouquet 66 Bleu', '85.00', NULL, NULL, 1),
                                                                                                (42, 'Bouquet 66 Noir', '85.00', NULL, NULL, 1),
                                                                                                (43, 'Bouquet 99 Rouge', '110.00', NULL, NULL, 1),
                                                                                                (44, 'Bouquet 99 Rose Clair', '110.00', NULL, NULL, 1),
                                                                                                (45, 'Bouquet 99 Rose', '110.00', NULL, NULL, 1),
                                                                                                (46, 'Bouquet 99 Blanc', '110.00', NULL, NULL, 1),
                                                                                                (47, 'Bouquet 99 Bleu', '110.00', NULL, NULL, 1),
                                                                                                (48, 'Bouquet 99 Noir', '110.00', NULL, NULL, 1),
                                                                                                (49, 'Bouquet 100 Rouge', '112.00', NULL, NULL, 1),
                                                                                                (50, 'Bouquet 100 Rose Clair', '112.00', NULL, NULL, 1),
                                                                                                (51, 'Bouquet 100 Rose', '112.00', NULL, NULL, 1),
                                                                                                (52, 'Bouquet 100 Blanc', '115.15', NULL, NULL, 1),
                                                                                                (53, 'Bouquet 100 Bleu', '112.00', NULL, NULL, 1),
                                                                                                (54, 'Bouquet 100 Noir', '112.00', NULL, NULL, 1),
                                                                                                (55, 'Bouquet 101 Rouge', '115.00', NULL, NULL, 1),
                                                                                                (56, 'Bouquet 101 Rose Clair', '115.00', NULL, NULL, 1),
                                                                                                (57, 'Bouquet 101 Rose', '115.00', NULL, NULL, 1),
                                                                                                (58, 'Bouquet 101 Blanc', '115.00', NULL, NULL, 1),
                                                                                                (59, 'Bouquet 101 Bleu', '115.00', NULL, NULL, 1),
                                                                                                (60, 'Bouquet 101 Noir', '115.00', NULL, NULL, 1),
                                                                                                (61, 'Coffret Anniversaire', '80.00', NULL, NULL, 1),
                                                                                                (62, 'Coffret Saint-Valentin', '90.00', NULL, NULL, 1),
                                                                                                (63, 'Coffret Fête des Mères', '100.00', NULL, NULL, 1),
                                                                                                (64, 'Coffret Baptême', '100.00', NULL, NULL, 1),
                                                                                                (65, 'Coffret Mariage', '100.00', NULL, NULL, 1),
                                                                                                (66, 'Coffret Pâques', '100.00', NULL, NULL, 1),
                                                                                                (67, 'Coffret Noël', '100.00', NULL, NULL, 1),
                                                                                                (68, 'Coffret Nouvel An', '150.00', NULL, NULL, 1),
                                                                                                (78, 'Bouquet 12 violet', '30.00', NULL, 'bouquet', 1),
                                                                                                (79, 'Rose Violette', '6.00', NULL, 'fleur', 1),
                                                                                                (81, 'Rose jaune', '5.00', 'rose_jaune-616930fd.png', 'fleur', 1);

-- --------------------------------------------------------

--
-- Structure de la table `SUPPLEMENT`
--

DROP TABLE IF EXISTS `SUPPLEMENT`;
CREATE TABLE `SUPPLEMENT` (
                              `SUP_ID` bigint(20) NOT NULL,
                              `SUP_NOM` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
                              `SUP_DESCRIPTION` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                              `SUP_PRIX_UNITAIRE` decimal(12,2) NOT NULL,
                              `SUP_QTE_STOCK` int(11) NOT NULL DEFAULT 0,
                              `SUP_IMG` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                              `SUP_ACTIF` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=actif,0=inactif'
) ;

--
-- Déchargement des données de la table `SUPPLEMENT`
--

INSERT INTO `SUPPLEMENT` (`SUP_ID`, `SUP_NOM`, `SUP_DESCRIPTION`, `SUP_PRIX_UNITAIRE`, `SUP_QTE_STOCK`, `SUP_IMG`, `SUP_ACTIF`) VALUES
                                                                                                                                    (1, 'Mini ourson', 'Petit ourson en peluche blanc', '2.00', 20, NULL, 1),
                                                                                                                                    (2, 'Décoration anniversaire', 'Décoration anniversaire sur le bouquet', '2.00', 15, NULL, 1),
                                                                                                                                    (3, 'Papillons', 'Papillons dorés en papier', '2.00', 8, NULL, 1),
                                                                                                                                    (4, 'Baton coeur', 'Petits batons avec un coeur au dessus', '3.00', 6, NULL, 1),
                                                                                                                                    (5, 'Diamants', 'Diamants en plastique', '5.00', 16, NULL, 1),
                                                                                                                                    (6, 'Couronne', 'Petite couronne argentée', '5.00', 16, NULL, 1),
                                                                                                                                    (7, 'Paillettes', 'Paillettes à mettre sur le bouquet', '9.00', 23, NULL, 1),
                                                                                                                                    (8, 'Initiale', 'Initiales du prénom dans le bouquet', '10.00', 9, NULL, 1),
                                                                                                                                    (9, 'Carte', 'Carte avec bords dorés pour un petit mot', '3.00', 30, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `SUPP_PRODUIT`
--

DROP TABLE IF EXISTS `SUPP_PRODUIT`;
CREATE TABLE `SUPP_PRODUIT` (
                                `SUP_ID` bigint(20) NOT NULL,
                                `PRO_ID` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `ADMINISTRATEUR`
--
ALTER TABLE `ADMINISTRATEUR`
    ADD PRIMARY KEY (`PER_ID`);

--
-- Index pour la table `ADMIN_TODO`
--
ALTER TABLE `ADMIN_TODO`
    ADD PRIMARY KEY (`TODO_ID`),
  ADD KEY `idx_todo_per` (`PER_ID`);

--
-- Index pour la table `ADRESSE`
--
ALTER TABLE `ADRESSE`
    ADD PRIMARY KEY (`ADR_ID`);

--
-- Index pour la table `ADRESSE_CLIENT`
--
ALTER TABLE `ADRESSE_CLIENT`
    ADD PRIMARY KEY (`PER_ID`,`ADR_ID`),
  ADD KEY `FK_CA_ADRESSE` (`ADR_ID`);

--
-- Index pour la table `BOUQUET`
--
ALTER TABLE `BOUQUET`
    ADD PRIMARY KEY (`PRO_ID`),
  ADD UNIQUE KEY `UK_BOU_TAILLE_COULEUR` (`BOU_NB_ROSES`,`BOU_COULEUR`);

--
-- Index pour la table `BOUQUET_FLEUR`
--
ALTER TABLE `BOUQUET_FLEUR`
    ADD PRIMARY KEY (`BOUQUET_ID`,`FLEUR_ID`),
  ADD KEY `FK_BF_FLEUR` (`FLEUR_ID`);

--
-- Index pour la table `CLIENT`
--
ALTER TABLE `CLIENT`
    ADD PRIMARY KEY (`PER_ID`),
  ADD UNIQUE KEY `UK_CLIENT_STRIPE_CUS` (`CLI_STRIPE_CUSTOMER_ID`);

--
-- Index pour la table `COFFRET`
--
ALTER TABLE `COFFRET`
    ADD PRIMARY KEY (`PRO_ID`);

--
-- Index pour la table `COFFRET_BOUQUET`
--
ALTER TABLE `COFFRET_BOUQUET`
    ADD PRIMARY KEY (`COFFRET_ID`,`BOUQUET_ID`),
  ADD KEY `FK_CB_BOUQUET` (`BOUQUET_ID`);

--
-- Index pour la table `COMMANDE`
--
ALTER TABLE `COMMANDE`
    ADD PRIMARY KEY (`COM_ID`),
  ADD KEY `FK_COM_CLIENT` (`PER_ID`),
  ADD KEY `FK_COM_PAIEMENT` (`PAI_ID`),
  ADD KEY `idx_commande_liv_id` (`LIV_ID`);

--
-- Index pour la table `COMMANDE_EMBALLAGE`
--
ALTER TABLE `COMMANDE_EMBALLAGE`
    ADD PRIMARY KEY (`COM_ID`,`EMB_ID`),
  ADD KEY `FK_CE_EMB` (`EMB_ID`);

--
-- Index pour la table `COMMANDE_PRODUIT`
--
ALTER TABLE `COMMANDE_PRODUIT`
    ADD PRIMARY KEY (`COM_ID`,`PRO_ID`),
  ADD KEY `FK_CP_PRODUIT` (`PRO_ID`);

--
-- Index pour la table `COMMANDE_SUPP`
--
ALTER TABLE `COMMANDE_SUPP`
    ADD PRIMARY KEY (`SUP_ID`,`COM_ID`),
  ADD KEY `FK_CS_COM` (`COM_ID`);

--
-- Index pour la table `EMBALLAGE`
--
ALTER TABLE `EMBALLAGE`
    ADD PRIMARY KEY (`EMB_ID`);

--
-- Index pour la table `FLEUR`
--
ALTER TABLE `FLEUR`
    ADD PRIMARY KEY (`PRO_ID`);

--
-- Index pour la table `LIVRAISON`
--
ALTER TABLE `LIVRAISON`
    ADD PRIMARY KEY (`LIV_ID`),
  ADD UNIQUE KEY `uq_liv_com` (`LIV_ID`),
  ADD UNIQUE KEY `UK_LIV_SUIVI` (`LIV_NUM_SUIVI_COMMANDE`),
  ADD KEY `idx_livraison_date` (`LIV_DATE`),
  ADD KEY `idx_livraison_statut` (`LIV_STATUT`),
  ADD KEY `idx_livraison_transporteur` (`LIV_NOM_TRANSPORTEUR`);

--
-- Index pour la table `NOTIFICATION`
--
ALTER TABLE `NOTIFICATION`
    ADD PRIMARY KEY (`NOT_ID`),
  ADD KEY `idx_notif_per` (`PER_ID`),
  ADD KEY `idx_notif_com` (`COM_ID`);

--
-- Index pour la table `PAIEMENT`
--
ALTER TABLE `PAIEMENT`
    ADD PRIMARY KEY (`PAI_ID`),
  ADD UNIQUE KEY `UK_PAI_PI` (`PAI_STRIPE_PAYMENT_INTENT_ID`),
  ADD UNIQUE KEY `UK_PAI_CH` (`PAI_STRIPE_LATEST_CHARGE_ID`),
  ADD KEY `FK_PAI_CLIENT` (`PER_ID`);

--
-- Index pour la table `PERSONNE`
--
ALTER TABLE `PERSONNE`
    ADD PRIMARY KEY (`PER_ID`),
  ADD UNIQUE KEY `UK_PERSONNE_EMAIL` (`PER_EMAIL`),
  ADD UNIQUE KEY `UK_PERSONNE_TEL` (`PER_NUM_TEL`),
  ADD UNIQUE KEY `reset_token_hash` (`reset_token_hash`),
  ADD KEY `idx_personne_per` (`PER_ID`);

--
-- Index pour la table `PRODUIT`
--
ALTER TABLE `PRODUIT`
    ADD PRIMARY KEY (`PRO_ID`),
  ADD UNIQUE KEY `UK_PRODUIT_NOM` (`PRO_NOM`);

--
-- Index pour la table `SUPPLEMENT`
--
ALTER TABLE `SUPPLEMENT`
    ADD PRIMARY KEY (`SUP_ID`);

--
-- Index pour la table `SUPP_PRODUIT`
--
ALTER TABLE `SUPP_PRODUIT`
    ADD PRIMARY KEY (`SUP_ID`,`PRO_ID`),
  ADD KEY `FK_SP_PRO` (`PRO_ID`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `ADMIN_TODO`
--
ALTER TABLE `ADMIN_TODO`
    MODIFY `TODO_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `ADRESSE`
--
ALTER TABLE `ADRESSE`
    MODIFY `ADR_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `COMMANDE`
--
ALTER TABLE `COMMANDE`
    MODIFY `COM_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT pour la table `EMBALLAGE`
--
ALTER TABLE `EMBALLAGE`
    MODIFY `EMB_ID` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `LIVRAISON`
--
ALTER TABLE `LIVRAISON`
    MODIFY `LIV_ID` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `NOTIFICATION`
--
ALTER TABLE `NOTIFICATION`
    MODIFY `NOT_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `PAIEMENT`
--
ALTER TABLE `PAIEMENT`
    MODIFY `PAI_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT pour la table `PERSONNE`
--
ALTER TABLE `PERSONNE`
    MODIFY `PER_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `PRODUIT`
--
ALTER TABLE `PRODUIT`
    MODIFY `PRO_ID` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `SUPPLEMENT`
--
ALTER TABLE `SUPPLEMENT`
    MODIFY `SUP_ID` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `ADMINISTRATEUR`
--
ALTER TABLE `ADMINISTRATEUR`
    ADD CONSTRAINT `FK_ADMIN_PERSONNE` FOREIGN KEY (`PER_ID`) REFERENCES `PERSONNE` (`PER_ID`);

--
-- Contraintes pour la table `ADRESSE_CLIENT`
--
ALTER TABLE `ADRESSE_CLIENT`
    ADD CONSTRAINT `FK_CA_ADRESSE` FOREIGN KEY (`ADR_ID`) REFERENCES `ADRESSE` (`ADR_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_CA_CLIENT` FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT` (`PER_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `BOUQUET`
--
ALTER TABLE `BOUQUET`
    ADD CONSTRAINT `FK_BOUQUET_PRODUIT` FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `BOUQUET_FLEUR`
--
ALTER TABLE `BOUQUET_FLEUR`
    ADD CONSTRAINT `FK_BF_BOUQUET` FOREIGN KEY (`BOUQUET_ID`) REFERENCES `BOUQUET` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_BF_FLEUR` FOREIGN KEY (`FLEUR_ID`) REFERENCES `FLEUR` (`PRO_ID`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `CLIENT`
--
ALTER TABLE `CLIENT`
    ADD CONSTRAINT `FK_CLIENT_PERSONNE` FOREIGN KEY (`PER_ID`) REFERENCES `PERSONNE` (`PER_ID`);

--
-- Contraintes pour la table `COFFRET`
--
ALTER TABLE `COFFRET`
    ADD CONSTRAINT `FK_COFFRET_PRODUIT` FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `COFFRET_BOUQUET`
--
ALTER TABLE `COFFRET_BOUQUET`
    ADD CONSTRAINT `FK_CB_BOUQUET` FOREIGN KEY (`BOUQUET_ID`) REFERENCES `BOUQUET` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_CB_COFFRET` FOREIGN KEY (`COFFRET_ID`) REFERENCES `COFFRET` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `COMMANDE`
--
ALTER TABLE `COMMANDE`
    ADD CONSTRAINT `FK_COM_CLIENT` FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT` (`PER_ID`),
  ADD CONSTRAINT `FK_COM_LIVRAISON` FOREIGN KEY (`LIV_ID`) REFERENCES `LIVRAISON` (`LIV_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_COM_PAIEMENT` FOREIGN KEY (`PAI_ID`) REFERENCES `PAIEMENT` (`PAI_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `COMMANDE_EMBALLAGE`
--
ALTER TABLE `COMMANDE_EMBALLAGE`
    ADD CONSTRAINT `FK_CE_COM` FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE` (`COM_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_CE_EMB` FOREIGN KEY (`EMB_ID`) REFERENCES `EMBALLAGE` (`EMB_ID`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `COMMANDE_PRODUIT`
--
ALTER TABLE `COMMANDE_PRODUIT`
    ADD CONSTRAINT `FK_CP_COMMANDE` FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE` (`COM_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_CP_PRODUIT` FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT` (`PRO_ID`);

--
-- Contraintes pour la table `COMMANDE_SUPP`
--
ALTER TABLE `COMMANDE_SUPP`
    ADD CONSTRAINT `FK_CS_COM` FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE` (`COM_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_CS_SUP` FOREIGN KEY (`SUP_ID`) REFERENCES `SUPPLEMENT` (`SUP_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `FLEUR`
--
ALTER TABLE `FLEUR`
    ADD CONSTRAINT `FK_FLEUR_PRODUIT` FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `NOTIFICATION`
--
ALTER TABLE `NOTIFICATION`
    ADD CONSTRAINT `fk_notif_commande` FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE` (`COM_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_personne` FOREIGN KEY (`PER_ID`) REFERENCES `PERSONNE` (`PER_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `PAIEMENT`
--
ALTER TABLE `PAIEMENT`
    ADD CONSTRAINT `FK_PAI_CLIENT` FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT` (`PER_ID`) ON DELETE SET NULL;

--
-- Contraintes pour la table `SUPP_PRODUIT`
--
ALTER TABLE `SUPP_PRODUIT`
    ADD CONSTRAINT `FK_SP_PRO` FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_SP_SUP` FOREIGN KEY (`SUP_ID`) REFERENCES `SUPPLEMENT` (`SUP_ID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;
