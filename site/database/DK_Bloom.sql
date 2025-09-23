-- =====================================================
-- Création / Réinitialisation de la base hhva_t25_6
-- =====================================================
DROP DATABASE IF EXISTS `hhva_t25_6`;
CREATE DATABASE `hhva_t25_6`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `hhva_t25_6`;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
-- ---------- DROPS (enfants -> parents)
DROP TABLE IF EXISTS `CLIENT_RABAIS`;
DROP TABLE IF EXISTS `AVIS`;
DROP TABLE IF EXISTS `PAIEMENT`;
DROP TABLE IF EXISTS `COMMANDE_SUPP`;
DROP TABLE IF EXISTS `COMMANDE_PRODUIT`;
DROP TABLE IF EXISTS `COMMANDE_EMBALLAGE`;
DROP TABLE IF EXISTS `COMMANDE`;
DROP TABLE IF EXISTS `LIVRAISON`;
DROP TABLE IF EXISTS `RABAIS`;
DROP TABLE IF EXISTS `COFFRET_BOUQUET`;
DROP TABLE IF EXISTS `COFFRET`;
DROP TABLE IF EXISTS `BOUQUET_FLEUR`;
DROP TABLE IF EXISTS `BOUQUET`;
DROP TABLE IF EXISTS `FLEUR`;
DROP TABLE IF EXISTS `SUPP_PRODUIT`;
DROP TABLE IF EXISTS `SUPPLEMENT`;
DROP TABLE IF EXISTS `PRODUIT`;
DROP TABLE IF EXISTS `ADRESSE_CLIENT`;
DROP TABLE IF EXISTS `ADRESSE`;
DROP TABLE IF EXISTS `CLIENT`;
DROP TABLE IF EXISTS `ADMINISTRATEUR`;
DROP TABLE IF EXISTS `PERSONNE`;
DROP TABLE IF EXISTS `EMBALLAGE`;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- CREATES (parents -> enfants)
-- =====================================================

-- PERSONNES & ROLES -----------------------------------
CREATE TABLE `PERSONNE` (
                            `PER_ID`      BIGINT PRIMARY KEY AUTO_INCREMENT,
                            `PER_NOM`     VARCHAR(50) NOT NULL,
                            `PER_PRENOM`  VARCHAR(30) NOT NULL,
                            `PER_EMAIL`   VARCHAR(50) NOT NULL,
                            `PER_MDP`     VARCHAR(100) NOT NULL,
                            `PER_NUM_TEL` CHAR(10) NOT NULL,
                            CONSTRAINT `UK_PERSONNE_EMAIL` UNIQUE (`PER_EMAIL`),
                            CONSTRAINT `UK_PERSONNE_TEL`   UNIQUE (`PER_NUM_TEL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ADMINISTRATEUR` (
                                  `PER_ID` BIGINT PRIMARY KEY,
                                  CONSTRAINT `FK_ADMIN_PERSONNE`
                                      FOREIGN KEY (`PER_ID`) REFERENCES `PERSONNE`(`PER_ID`)
                                          ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `CLIENT` (
                          `PER_ID`                 BIGINT PRIMARY KEY,
                          `CLI_DATENAISSANCE`      DATE NULL,
                          `CLI_NB_POINTS_FIDELITE` INT NOT NULL DEFAULT 0,
                          `CLI_STRIPE_CUSTOMER_ID` VARCHAR(64) NULL,
                          CONSTRAINT `UK_CLIENT_STRIPE_CUS` UNIQUE (`CLI_STRIPE_CUSTOMER_ID`),
                          CONSTRAINT `FK_CLIENT_PERSONNE`
                              FOREIGN KEY (`PER_ID`) REFERENCES `PERSONNE`(`PER_ID`)
                                  ON UPDATE RESTRICT ON DELETE RESTRICT,
                          CONSTRAINT `CK_CLIENT_POINTS_NON_NEGATIFS`
                              CHECK (`CLI_NB_POINTS_FIDELITE` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADRESSES --------------------------------------------
CREATE TABLE `ADRESSE` (
                           `ADR_ID`     BIGINT PRIMARY KEY AUTO_INCREMENT,
                           `ADR_RUE`    VARCHAR(150) NOT NULL,
                           `ADR_NUMERO` VARCHAR(10)  NOT NULL,
                           `ADR_NPA`    VARCHAR(6)   NOT NULL,
                           `ADR_VILLE`  VARCHAR(120) NOT NULL,
                           `ADR_PAYS`   VARCHAR(120) NOT NULL,
                           `ADR_TYPE`   ENUM('LIVRAISON','FACTURATION') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ADRESSE_CLIENT` (
                                  `PER_ID` BIGINT NOT NULL,
                                  `ADR_ID` BIGINT NOT NULL,
                                  PRIMARY KEY (`PER_ID`,`ADR_ID`),
                                  CONSTRAINT `FK_CA_CLIENT`
                                      FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT`(`PER_ID`)
                                          ON UPDATE CASCADE ON DELETE CASCADE,
                                  CONSTRAINT `FK_CA_ADRESSE`
                                      FOREIGN KEY (`ADR_ID`) REFERENCES `ADRESSE`(`ADR_ID`)
                                          ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRODUITS & SUPPLÉMENTS ------------------------------
CREATE TABLE `PRODUIT` (
                           `PRO_ID`  BIGINT PRIMARY KEY AUTO_INCREMENT,
                           `PRO_NOM` VARCHAR(190) NOT NULL,
                           `PRO_PRIX` DECIMAL(12,2) NOT NULL,
                           CONSTRAINT `UK_PRODUIT_NOM` UNIQUE (`PRO_NOM`),
                           CONSTRAINT `CK_PRODUIT_PRIX_POSITIF` CHECK (`PRO_PRIX` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SUPPLEMENT` (
                              `SUP_ID`            BIGINT PRIMARY KEY AUTO_INCREMENT,
                              `SUP_NOM`           VARCHAR(190) NOT NULL,
                              `SUP_DESCRIPTION`   TEXT NULL,
                              `SUP_PRIX_UNITAIRE` DECIMAL(12,2) NOT NULL,
                              `SUP_QTE_STOCK`     INT NOT NULL DEFAULT 0,
                              CONSTRAINT `CK_SUP_PRIX_NON_NEGATIFS` CHECK (`SUP_PRIX_UNITAIRE` >= 0),
                              CONSTRAINT `CK_SUP_STOCK_NON_NEGATIFS` CHECK (`SUP_QTE_STOCK` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SUPP_PRODUIT` (
                                `SUP_ID` BIGINT NOT NULL,
                                `PRO_ID` BIGINT NOT NULL,
                                PRIMARY KEY (`SUP_ID`,`PRO_ID`),
                                CONSTRAINT `FK_SP_SUP`
                                    FOREIGN KEY (`SUP_ID`) REFERENCES `SUPPLEMENT`(`SUP_ID`)
                                        ON UPDATE CASCADE ON DELETE CASCADE,
                                CONSTRAINT `FK_SP_PRO`
                                    FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT`(`PRO_ID`)
                                        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOUS-TYPES DE PRODUIT -------------------------------
CREATE TABLE `FLEUR` (
                         `PRO_ID`        BIGINT PRIMARY KEY,
                         `FLE_TYPE`      VARCHAR(50) NOT NULL,
                         `FLE_COULEUR`   ENUM('rouge','rose clair','rose','blanc','bleu','noir') NOT NULL,
                         `FLE_QTE_STOCK` INT NOT NULL DEFAULT 0,
                         CONSTRAINT `CK_FLE_STOCK_NON_NEG` CHECK (`FLE_QTE_STOCK` >= 0),
                         CONSTRAINT `FK_FLEUR_PRODUIT`
                             FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT`(`PRO_ID`)
                                 ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- >>> BOUQUET: BOU_TYPE supprimé + ajout BOU_NB_ROSES + unicité (NB_ROSES,COULEUR)
CREATE TABLE `BOUQUET` (
                           `PRO_ID`          BIGINT PRIMARY KEY,
                           `BOU_DESCRIPTION` VARCHAR(600) NULL,
                           `BOU_COULEUR`     ENUM('rouge','rose clair','rose','blanc','bleu','noir') NOT NULL,
                           `BOU_NB_ROSES`    INT NOT NULL,
                           `BOU_QTE_STOCK`   INT NOT NULL DEFAULT 0,
                           CONSTRAINT `CK_BOU_STOCK_NON_NEG` CHECK (`BOU_QTE_STOCK` >= 0),
                           CONSTRAINT `CK_BOU_NB_POS` CHECK (`BOU_NB_ROSES` > 0),
                           CONSTRAINT `UK_BOU_TAILLE_COULEUR` UNIQUE (`BOU_NB_ROSES`,`BOU_COULEUR`),
                           CONSTRAINT `FK_BOUQUET_PRODUIT`
                               FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT`(`PRO_ID`)
                                   ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `BOUQUET_FLEUR` (
                                 `BOUQUET_ID` BIGINT NOT NULL,
                                 `FLEUR_ID`   BIGINT NOT NULL,
                                 `BF_QTE`     INT NOT NULL,
                                 CONSTRAINT `CK_BF_QTE_POS` CHECK (`BF_QTE` >= 1),
                                 PRIMARY KEY (`BOUQUET_ID`,`FLEUR_ID`),
                                 CONSTRAINT `FK_BF_BOUQUET`
                                     FOREIGN KEY (`BOUQUET_ID`) REFERENCES `BOUQUET`(`PRO_ID`)
                                         ON UPDATE CASCADE ON DELETE CASCADE,
                                 CONSTRAINT `FK_BF_FLEUR`
                                     FOREIGN KEY (`FLEUR_ID`) REFERENCES `FLEUR`(`PRO_ID`)
                                         ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `COFFRET` (
                           `PRO_ID`         BIGINT PRIMARY KEY,
                           `COF_EVENEMENT`  ENUM('Anniversaire','Saint-Valentin','Fêtes des Mères','Baptême','Mariage','Pâques','Noël','Nouvel An') NOT NULL,
                           `COF_QTE_STOCK`  INT NOT NULL DEFAULT 0,
                           CONSTRAINT `CK_COF_STOCK_NON_NEG` CHECK (`COF_QTE_STOCK` >= 0),
                           CONSTRAINT `FK_COFFRET_PRODUIT`
                               FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT`(`PRO_ID`)
                                   ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `COFFRET_BOUQUET` (
                                   `COFFRET_ID` BIGINT NOT NULL,
                                   `BOUQUET_ID` BIGINT NOT NULL,
                                   PRIMARY KEY (`COFFRET_ID`,`BOUQUET_ID`),
                                   CONSTRAINT `FK_CB_COFFRET`
                                       FOREIGN KEY (`COFFRET_ID`) REFERENCES `COFFRET`(`PRO_ID`)
                                           ON UPDATE CASCADE ON DELETE CASCADE,
                                   CONSTRAINT `FK_CB_BOUQUET`
                                       FOREIGN KEY (`BOUQUET_ID`) REFERENCES `BOUQUET`(`PRO_ID`)
                                           ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EMBALLAGE -------------------------------------------
CREATE TABLE `EMBALLAGE` (
                             `EMB_ID`        BIGINT PRIMARY KEY AUTO_INCREMENT,
                             `EMB_NOM`       VARCHAR(120) NOT NULL,
                             `EMB_COULEUR`   VARCHAR(40)  NOT NULL,
                             `EMB_QTE_STOCK` INT NOT NULL DEFAULT 0,
                             CONSTRAINT `CK_EMB_STOCK_NON_NEG` CHECK (`EMB_QTE_STOCK` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RABAIS & LIVRAISON ---------------------------------
CREATE TABLE `RABAIS` (
                          `RAB_ID` BIGINT PRIMARY KEY AUTO_INCREMENT,
                          `RAB_POURCENTAGE` DECIMAL(5,2) NOT NULL,
                          `RAB_ACTIF` TINYINT(1) NOT NULL DEFAULT 1,
                          CONSTRAINT `CK_RABAIS_0_100` CHECK (`RAB_POURCENTAGE` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `LIVRAISON` (
                             `LIV_ID` BIGINT PRIMARY KEY AUTO_INCREMENT,
                             `LIV_STATUT` ENUM('prévue','en cours','livrée','annulée') NOT NULL DEFAULT 'prévue',
                             `LIV_MODE`   ENUM('retrait','courrier','coursier') NOT NULL,
                             `LIV_MONTANT_FRAIS` DECIMAL(12,2) NOT NULL DEFAULT 0,
                             `LIV_NOM_TRANSPORTEUR`   VARCHAR(120) NULL,
                             `LIV_NUM_SUIVI_COMMANDE` VARCHAR(120) NULL,
                             `LIV_DATE` DATE NOT NULL,
                             CONSTRAINT `CK_LIV_FRAIS_NON_NEG` CHECK (`LIV_MONTANT_FRAIS` >= 0),
                             CONSTRAINT `UK_LIV_SUIVI` UNIQUE (`LIV_NUM_SUIVI_COMMANDE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAIEMENT (Stripe simplifié) -------------------------
CREATE TABLE `PAIEMENT` (
                            `PAI_ID` BIGINT PRIMARY KEY AUTO_INCREMENT,
                            `PER_ID` BIGINT NULL,
                            `PAI_MODE` VARCHAR(64) NULL,
                            `PAI_MONTANT` INT NOT NULL,
                            `PAI_MONNAIE` CHAR(3) NOT NULL DEFAULT 'CHF',
                            `PAI_STRIPE_PAYMENT_INTENT_ID` VARCHAR(64) NOT NULL,
                            `PAI_STRIPE_LATEST_CHARGE_ID`  VARCHAR(64) NULL,
                            `PAI_RECEIPT_URL` VARCHAR(255) NULL,
                            `PAI_STATUT` ENUM('mode_paiement_requis','confirmation_requise','action_requise','en_traitement','reussi','annule')
    NOT NULL DEFAULT 'mode_paiement_requis',
                            `PAI_DATE_`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `PAI_DATE_CONFIRMATION` DATETIME NULL,
                            `PAI_DATE_ANNULATION`   DATETIME NULL,
                            `PAI_LAST_EVENT_ID`      VARCHAR(64)  NULL,
                            `PAI_LAST_EVENT_TYPE`    VARCHAR(80)  NULL,
                            `PAI_LAST_EVENT_PAYLOAD` LONGTEXT     NULL,
                            CONSTRAINT `UK_PAI_PI` UNIQUE (`PAI_STRIPE_PAYMENT_INTENT_ID`),
                            CONSTRAINT `UK_PAI_CH` UNIQUE (`PAI_STRIPE_LATEST_CHARGE_ID`),
                            CONSTRAINT `FK_PAI_CLIENT` FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT`(`PER_ID`)
                                ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COMMANDE & LIGNES -----------------------------------
CREATE TABLE `COMMANDE` (
                            `COM_ID` BIGINT PRIMARY KEY AUTO_INCREMENT,
                            `PER_ID` BIGINT NOT NULL,
                            `LIV_ID` BIGINT NULL,
                            `RAB_ID` BIGINT NULL,
                            `PAI_ID` BIGINT NULL,
                            `COM_STATUT` ENUM('en preparation','expediee','livree','en attente d''expédition','annulee') NOT NULL DEFAULT 'en preparation',
                            `COM_DATE` DATE NOT NULL,
                            `COM_DESCRIPTION` TEXT NULL,
                            `COM_PTS_CUMULE` INT NOT NULL DEFAULT 0,
                            CONSTRAINT `CK_COM_PTS_NON_NEG` CHECK (`COM_PTS_CUMULE` >= 0),
                            CONSTRAINT `FK_COM_CLIENT`
                                FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT`(`PER_ID`)
                                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                            CONSTRAINT `FK_COM_LIVRAISON`
                                FOREIGN KEY (`LIV_ID`) REFERENCES `LIVRAISON`(`LIV_ID`)
                                    ON UPDATE CASCADE ON DELETE SET NULL,
                            CONSTRAINT `FK_COM_RABAIS`
                                FOREIGN KEY (`RAB_ID`) REFERENCES `RABAIS`(`RAB_ID`)
                                    ON UPDATE CASCADE ON DELETE SET NULL,
                            CONSTRAINT `FK_COM_PAIEMENT`
                                FOREIGN KEY (`PAI_ID`) REFERENCES `PAIEMENT`(`PAI_ID`)
                                    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `COMMANDE_EMBALLAGE` (
                                      `COM_ID` BIGINT NOT NULL,
                                      `EMB_ID` BIGINT NOT NULL,
                                      `CE_QTE` INT NOT NULL DEFAULT 1,
                                      PRIMARY KEY (`COM_ID`,`EMB_ID`),
                                      CONSTRAINT `CK_CE_QTE_POS` CHECK (`CE_QTE` >= 1),
                                      CONSTRAINT `FK_CE_COM`
                                          FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE`(`COM_ID`)
                                              ON UPDATE CASCADE ON DELETE CASCADE,
                                      CONSTRAINT `FK_CE_EMB`
                                          FOREIGN KEY (`EMB_ID`) REFERENCES `EMBALLAGE`(`EMB_ID`)
                                              ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `COMMANDE_PRODUIT` (
                                    `COM_ID` BIGINT NOT NULL,
                                    `PRO_ID` BIGINT NOT NULL,
                                    `CP_QTE_COMMANDEE` INT NOT NULL,
                                    `CP_TYPE_PRODUIT` ENUM('bouquet','fleur','coffret') NOT NULL,
                                    CONSTRAINT `CK_CP_QTE_POS` CHECK (`CP_QTE_COMMANDEE` >= 1),
                                    PRIMARY KEY (`COM_ID`,`PRO_ID`),
                                    CONSTRAINT `FK_CP_COMMANDE`
                                        FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE`(`COM_ID`)
                                            ON UPDATE CASCADE ON DELETE CASCADE,
                                    CONSTRAINT `FK_CP_PRODUIT`
                                        FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT`(`PRO_ID`)
                                            ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `COMMANDE_SUPP` (
                                 `SUP_ID` BIGINT NOT NULL,
                                 `COM_ID` BIGINT NOT NULL,
                                 `CS_QTE_COMMANDEE` INT NOT NULL,
                                 CONSTRAINT `CK_CS_QTE_POS` CHECK (`CS_QTE_COMMANDEE` >= 1),
                                 PRIMARY KEY (`SUP_ID`,`COM_ID`),
                                 CONSTRAINT `FK_CS_SUP`
                                     FOREIGN KEY (`SUP_ID`) REFERENCES `SUPPLEMENT`(`SUP_ID`)
                                         ON UPDATE CASCADE ON DELETE CASCADE,
                                 CONSTRAINT `FK_CS_COM`
                                     FOREIGN KEY (`COM_ID`) REFERENCES `COMMANDE`(`COM_ID`)
                                         ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `AVIS` (
                        `AVI_ID` BIGINT PRIMARY KEY AUTO_INCREMENT,
                        `PRO_ID` BIGINT NOT NULL,
                        `PER_ID` BIGINT NOT NULL,
                        `AVI_NOTE` TINYINT NOT NULL,
                        `AVI_DESCRIPTION` VARCHAR(500) NULL,
                        `AVI_DATE` DATE NOT NULL,
                        CONSTRAINT `CK_AVI_NOTE_RANGE` CHECK (`AVI_NOTE` BETWEEN 0 AND 5),
                        CONSTRAINT `FK_AVIS_PRO`
                            FOREIGN KEY (`PRO_ID`) REFERENCES `PRODUIT`(`PRO_ID`)
                                ON UPDATE CASCADE ON DELETE CASCADE,
                        CONSTRAINT `FK_AVIS_PER`
                            FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT`(`PER_ID`)
                                ON UPDATE CASCADE ON DELETE CASCADE,
                        CONSTRAINT `UK_AVIS_UNIQUE` UNIQUE (`PRO_ID`,`PER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `CLIENT_RABAIS` (
                                 `RAB_ID` BIGINT NOT NULL,
                                 `PER_ID` BIGINT NOT NULL,
                                 PRIMARY KEY (`RAB_ID`,`PER_ID`),
                                 CONSTRAINT `FK_CR_RAB`
                                     FOREIGN KEY (`RAB_ID`) REFERENCES `RABAIS`(`RAB_ID`)
                                         ON UPDATE CASCADE ON DELETE CASCADE,
                                 CONSTRAINT `FK_CR_PER`
                                     FOREIGN KEY (`PER_ID`) REFERENCES `CLIENT`(`PER_ID`)
                                         ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
