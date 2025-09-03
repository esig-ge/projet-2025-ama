Fleur :
INSERT INTO FLEUR VALUES(1, "RoseRou", 5, "Rouge", "Rose")
INSERT INTO FLEUR VALUES(2, "RoseRoC", 5, "Rose clair", "Rose")
INSERT INTO FLEUR VALUES(3, "RoseRo", 5, "Rose", "Rose")
INSERT INTO FLEUR VALUES(4, "RoseBla", 5, "Blanc", "Rose")
INSERT INTO FLEUR VALUES(5, "RoseBle", 5, "Bleu", "Rose")
INSERT INTO FLEUR VALUES(6, "RoseN", 5, "Noir", "Rose")

Bouquet :
                           id    nom    prix   desc type
INSERT INTO BOUQUET VALUES(1, "RoseRou", 5, "Rouge", "Rose")
Coffret :

Supplément :

Rabais :

INSERT INTO RABAIS (RAB_ID, RAB_POURCENTAGE, RAB_ACTIF) VALUES( 1, 10), (2, 15), (3, 25);

-- =========================
-- DONNÉES DE TEST
-- =========================

-- PERSONNE
INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL) VALUES
                                                                                ('Dupont','Alice','alice.dupont@example.com','Test@1234!','0791234567'),
                                                                                ('Martin','Jean','jean.martin@example.com','Secure#2024','0789876543'),
                                                                                ('Durand','Sophie','sophie.durand@example.com','Azerty@9','0774567890');

-- CLIENT
INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE) VALUES
                                                                           (1,'1990-05-12',100),
                                                                           (2,'1985-09-23',250),
                                                                           (3,'2000-01-10',50);

-- ADRESSE
INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE) VALUES
                                                                                      ('Rue de Lausanne','12A','1202','Genève','Suisse','LIVRAISON'),
                                                                                      ('Avenue du Rhône','27','1205','Genève','Suisse','FACTURATION'),
                                                                                      ('Chemin des Fleurs','45','1010','Lausanne','Suisse','LIVRAISON');

-- CLIENT_ADRESSE
INSERT INTO CLIENT_ADRESSE (PER_ID, ADR_ID) VALUES
                                                (1,1),(2,2),(3,3);

-- PRODUIT
-- 1..3 = fleurs (rouge/rose/blanc), 4..6 = bouquets (printemps/mariage/romantique), 7..9 = coffrets (SV/noel/fêtes mères)
INSERT INTO PRODUIT (PRO_NOM, PRO_PRIX) VALUES
                                            ('Rose rouge classique', 10.50),        -- id = 1
                                            ('Rose rose classique', 10.50),         -- id = 2
                                            ('Rose blanche classique', 10.50),      -- id = 3
                                            ('Bouquet printemps', 45.00),           -- id = 4
                                            ('Bouquet mariage élégant', 120.00),    -- id = 5
                                            ('Bouquet romantique 12 roses', 60.00), -- id = 6
                                            ('Coffret Saint-Valentin', 89.90),      -- id = 7
                                            ('Coffret Noël', 89.90),                -- id = 8
                                            ('Coffret Fêtes des mères', 89.90);     -- id = 9

-- SUPPLEMENT
INSERT INTO SUPPLEMENT (SUP_NOM, SUP_DESCRIPTION, SUP_PRIX_UNITAIRE, SUP_QTE_STOCK) VALUES
                                                                                        ('Ruban satin','Ruban décoratif rouge',2.50,100),
                                                                                        ('Carte personnalisée','Petit mot personnalisé',3.00,50),
                                                                                        ('Vase en verre','Vase cylindrique transparent',15.00,20);

-- SUPP_PRODUIT
INSERT INTO SUPP_PRODUIT (SUP_ID, PRO_ID) VALUES
                                              (1,1),(2,4),(3,7);

-- FLEUR (1–1 avec PRODUIT)
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR) VALUES
                                                      (1,'Rose','rouge'),
                                                      (2,'Rose','rose'),
                                                      (3,'Rose','blanc');

-- BOUQUET (1–1 avec PRODUIT)
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE) VALUES
                                                            (4,'Bouquet de printemps avec fleurs variées','saisonnier'),
                                                            (5,'Bouquet mariage élégant avec roses blanches','mariage'),
                                                            (6,'Bouquet romantique avec 12 roses rouges','romantique');

-- BOUQUET_FLEUR (composition des bouquets avec les fleurs 1..3)
INSERT INTO BOUQUET_FLEUR (BOUQUET_ID, FLEUR_ID, BF_QTE) VALUES
                                                             (4,1,12),(4,2,8),(4,3,6),     -- printemps
                                                             (5,3,24),                     -- mariage
                                                             (6,1,12);                     -- romantique

-- COFFRET (1–1 avec PRODUIT)
INSERT INTO COFFRET (PRO_ID, CO_EVENEMENT) VALUES
                                               (7,'saint-valentin'),
                                               (8,'noel'),
                                               (9,'fetes des meres');

-- COFFRET_BOUQUET (pas de doublon)
INSERT INTO COFFRET_BOUQUET (COFFRET_ID, BOUQUET_ID) VALUES
                                                         (7,6),   -- SV avec bouquet romantique
                                                         (8,4),   -- Noël avec printemps
                                                         (9,5);   -- Fêtes mères avec mariage

-- RABAIS
INSERT INTO RABAIS (RAB_POURCENTAGE, RAB_ACTIF) VALUES
                                                    (10.00,1),(20.00,1),(15.00,0);

-- LIVRAISON
INSERT INTO LIVRAISON (LIV_STATUT, LIV_MODE, LIV_MONTANT_FRAIS, LIV_NOM_TRANSPORTEUR, LIV_NUM_SUIVI_COMMANDE, LIV_DATE) VALUES
                                                                                                                            ('prévue','courrier',5.00,'Poste Suisse','CH12345','2025-09-10'),
                                                                                                                            ('en cours','coursier',10.00,'DHL','CH54321','2025-09-12'),
                                                                                                                            ('livrée','retrait',0.00,NULL,NULL,'2025-08-30');

-- COMMANDE
INSERT INTO COMMANDE (PER_ID, LIV_ID, RAB_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE) VALUES
                                                                                                         (1,1,1,'en préparation','2025-09-01','Commande bouquet printemps',45),
                                                                                                         (2,2,2,'expédiée','2025-08-28','Commande coffret Saint-Valentin',90),
                                                                                                         (3,3,NULL,'livrée','2025-08-20','Commande roses rouges',30);

-- COMMANDE_PRODUIT (adapter aux nouveaux IDs)
INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT) VALUES
                                                                                     (1,4,1,'bouquet'),  -- bouquet printemps
                                                                                     (2,7,1,'coffret'),  -- coffret SV
                                                                                     (3,1,12,'fleur');   -- 12 roses rouges (produit fleur rouge)

-- COMMANDE_SUPP
INSERT INTO COMMANDE_SUPP (SUP_ID, COM_ID, CS_QTE_COMMANDEE) VALUES
                                                                 (1,1,1),(2,2,2),(3,3,1);

-- PAIEMENT
INSERT INTO PAIEMENT (PAI_MODE, PAI_MONTANT, PAI_DATE) VALUES
                                                           ('Twint',45.00,'2025-09-01'),
                                                           ('Carte',89.90,'2025-08-28'),
                                                           ('Revolut',120.00,'2025-08-20');

-- COMMANDE_PAIEMENT
INSERT INTO COMMANDE_PAIEMENT (COM_ID, PAI_ID) VALUES
                                                   (1,1),(2,2),(3,3);

-- AVIS (désormais PER_ID doit être un CLIENT)
INSERT INTO AVIS (PRO_ID, PER_ID, AVI_NOTE, AVI_DESCRIPTION, AVI_DATE) VALUES
                                                                           (1,1,9,'Très belles roses, fraîcheur impeccable.','2025-09-01'),
                                                                           (4,2,8,'Bouquet bien arrangé, livraison rapide.','2025-08-29'),
                                                                           (7,3,7,'Coffret sympa mais un peu cher.','2025-08-21');

-- CLIENT_RABAIS
INSERT INTO CLIENT_RABAIS (RAB_ID, PER_ID) VALUES
                                               (1,1),(2,2),(3,3);
