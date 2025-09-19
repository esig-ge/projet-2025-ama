-- =========================
-- DONNÉES DE TEST (light)
-- =========================

-- PERSONNES
INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL) VALUES
                                                                                ('Admin','Admin','dk.bloom@gmail.com','Admin_123','0790000000'),
                                                                                ('Bustamante','Johany','johany@gmail.com','123456Jb.','0780000000');

-- ADMIN (associer la 1re personne)
INSERT INTO ADMINISTRATEUR (PER_ID)
SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = 'dk.bloom@gmail.com';

-- CLIENT (associer la 2e personne)
INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
SELECT PER_ID, '1999-05-31', 0 FROM PERSONNE WHERE PER_EMAIL='johany@gmail.com';

-- ADRESSES (une pour chacun – l’association ne lie que le client)
INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE) VALUES
                                                                                      ('Rue de Lausanne','12A','1202','Genève','Suisse','LIVRAISON');
-- Lier l’adresse du CLIENT
INSERT INTO ADRESSE_CLIENT (PER_ID, ADR_ID)
SELECT c.PER_ID, a.ADR_ID
FROM CLIENT c
         JOIN ADRESSE a ON a.ADR_RUE='Chemin des Fleurs' AND a.ADR_NUMERO='12A'
WHERE c.PER_ID = (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='johany@gmail.com');

-- PRODUITS (fleurs, bouquets, coffrets)
INSERT INTO PRODUIT (PRO_NOM, PRO_PRIX) VALUES
                                            ('Rose Rouge', 5.00),
                                            ('Rose Rose Clair', 5.00),
                                            ('Rose Rose', 5.00),
                                            ('Rose Blanche', 5.00),
                                            ('Rose Bleue', 5.00),
                                            ('Rose Noire', 5.00),
                                            ('Bouquet 12', 30.00),
                                            ('Bouquet 20', 40.00),
                                            ('Bouquet 24', 45.00),
                                            ('Bouquet 36', 60.00),
                                            ('Bouquet 50', 70.00),
                                            ('Bouquet 66', 85.00),
                                            ('Bouquet 99', 110.00),
                                            ('Bouquet 100', 112.00),
                                            ('Bouquet 101', 115.00),
                                            ('Coffret ANV', 90.00),
                                            ('Coffret SV', 90.00),
                                            ('Coffret FdM', 100.00),
                                            ('Coffret BPT', 100.00),
                                            ('Coffret MRG', 100.00),
                                            ('Coffret PAQ', 100.00),
                                            ('Coffret NOE', 100.00),
                                            ('Coffret NVA', 150.00);

-- SUPPLÉMENTS
INSERT INTO SUPPLEMENT (SUP_NOM, SUP_DESCRIPTION, SUP_PRIX_UNITAIRE, SUP_QTE_STOCK) VALUES
                                                                                        ('Mini ourson','Petit ourson en peluche blanc',2.00,10),
                                                                                        ('Décoration anniversaire','Décoration anniversaire sur le bouquet',2.00,15),
                                                                                        ('Papillons','Papillons dorés en papier',2.00,10),
                                                                                        ('Baton coeur','Petits batons avec un coeur au dessus',2.00,15),
                                                                                        ('Diamants','Diamants en plastique',5.00,20),
                                                                                        ('Couronne','Petite couronne argentée',5.00,20),
                                                                                        ('Paillettes','Paillettes à mettre sur le bouquet',9.00,30),
                                                                                        ('Initiale','Initiales du prénom dans le bouquet',10.00,10),
                                                                                        ('Carte','Carte avec bords dorés pour un petit mot',3.00,30);

-- FLEURS (couleurs conformes à l’ENUM de FLEUR)
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR)
SELECT PRO_ID, 'Rose éternelle', 'rouge'      FROM PRODUIT WHERE PRO_NOM='Rose Rouge';
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR)
SELECT PRO_ID, 'Rose éternelle', 'rose clair' FROM PRODUIT WHERE PRO_NOM='Rose Rose Clair';
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR)
SELECT PRO_ID, 'Rose éternelle', 'rose'       FROM PRODUIT WHERE PRO_NOM='Rose Rose';
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR)
SELECT PRO_ID, 'Rose éternelle', 'blanc'      FROM PRODUIT WHERE PRO_NOM='Rose Blanche';
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR)
SELECT PRO_ID, 'Rose éternelle', 'bleu'       FROM PRODUIT WHERE PRO_NOM='Rose Bleue';
INSERT INTO FLEUR (PRO_ID, FLE_TYPE, FLE_COULEUR)
SELECT PRO_ID, 'Rose éternelle', 'noir'       FROM PRODUIT WHERE PRO_NOM='Rose Noire';

-- BOUQUETS (type conforme à l’ENUM, couleur par défaut dans la table)
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 12 roses', 'standard'     FROM PRODUIT WHERE PRO_NOM='Bouquet 12';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 20 roses', 'anniversaire' FROM PRODUIT WHERE PRO_NOM='Bouquet 20';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 24 roses', 'mariage'      FROM PRODUIT WHERE PRO_NOM='Bouquet 24';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 36 roses', 'deuil'        FROM PRODUIT WHERE PRO_NOM='Bouquet 36';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 50 roses', 'luxe'         FROM PRODUIT WHERE PRO_NOM='Bouquet 50';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 66 roses', 'saisonnier'   FROM PRODUIT WHERE PRO_NOM='Bouquet 66';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 99 roses', 'naissance'    FROM PRODUIT WHERE PRO_NOM='Bouquet 99';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 100 roses', 'romantique'  FROM PRODUIT WHERE PRO_NOM='Bouquet 100';
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 101 roses', 'personnalise' FROM PRODUIT WHERE PRO_NOM='Bouquet 101';
                                                                                    ('Carte','Carte avec bords dorés pour un petit mot',3.00,30);

-- COFFRETS (ENUM avec accents selon ta table)
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Anniversaire'   FROM PRODUIT WHERE PRO_NOM='Coffret ANV';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Saint-Valentin' FROM PRODUIT WHERE PRO_NOM='Coffret SV';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Fêtes des Mères' FROM PRODUIT WHERE PRO_NOM='Coffret FdM';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Baptême'        FROM PRODUIT WHERE PRO_NOM='Coffret BPT';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Mariage'        FROM PRODUIT WHERE PRO_NOM='Coffret MRG';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Pâques'         FROM PRODUIT WHERE PRO_NOM='Coffret PAQ';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Noël'           FROM PRODUIT WHERE PRO_NOM='Coffret NOE';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Nouvel An'      FROM PRODUIT WHERE PRO_NOM='Coffret NVA';

-- EMBALLAGES
INSERT INTO EMBALLAGE (EMB_NOM, EMB_COULEUR, EMB_QTE_STOCK) VALUES
                                                                ('Papier blanc', 'blanc avec bords dorés', 10),
                                                                ('Papier noir',  'noir avec bords blancs', 5),
                                                                ('Papier rose pâle', 'rose avec bords dorés/roses ', 3),
                                                                ('Papier gris',   'gris avec bords dorés', 7),
                                                                ('Papier violet', 'violet avec bords couleur bronze', 4);