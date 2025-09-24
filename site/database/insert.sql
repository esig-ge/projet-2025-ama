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

                                            -- Bouquets 12 (6 couleurs)
                                            ('Bouquet 12 Rouge', 30.00),
                                            ('Bouquet 12 Rose Clair', 30.00),
                                            ('Bouquet 12 Rose', 30.00),
                                            ('Bouquet 12 Blanc', 30.00),
                                            ('Bouquet 12 Bleu', 30.00),
                                            ('Bouquet 12 Noir', 30.00),

                                            -- Bouquets 20
                                            ('Bouquet 20 Rouge', 40.00),
                                            ('Bouquet 20 Rose Clair', 40.00),
                                            ('Bouquet 20 Rose', 40.00),
                                            ('Bouquet 20 Blanc', 40.00),
                                            ('Bouquet 20 Bleu', 40.00),
                                            ('Bouquet 20 Noir', 40.00),

                                            -- Bouquets 24
                                            ('Bouquet 24 Rouge', 45.00),
                                            ('Bouquet 24 Rose Clair', 45.00),
                                            ('Bouquet 24 Rose', 45.00),
                                            ('Bouquet 24 Blanc', 45.00),
                                            ('Bouquet 24 Bleu', 45.00),
                                            ('Bouquet 24 Noir', 45.00),

                                            -- Bouquets 36
                                            ('Bouquet 36 Rouge', 60.00),
                                            ('Bouquet 36 Rose Clair', 60.00),
                                            ('Bouquet 36 Rose', 60.00),
                                            ('Bouquet 36 Blanc', 60.00),
                                            ('Bouquet 36 Bleu', 60.00),
                                            ('Bouquet 36 Noir', 60.00),

                                            -- Bouquets 50
                                            ('Bouquet 50 Rouge', 70.00),
                                            ('Bouquet 50 Rose Clair', 70.00),
                                            ('Bouquet 50 Rose', 70.00),
                                            ('Bouquet 50 Blanc', 70.00),
                                            ('Bouquet 50 Bleu', 70.00),
                                            ('Bouquet 50 Noir', 70.00),

                                            -- Bouquets 66
                                            ('Bouquet 66 Rouge', 85.00),
                                            ('Bouquet 66 Rose Clair', 85.00),
                                            ('Bouquet 66 Rose', 85.00),
                                            ('Bouquet 66 Blanc', 85.00),
                                            ('Bouquet 66 Bleu', 85.00),
                                            ('Bouquet 66 Noir', 85.00),

                                            -- Bouquets 99
                                            ('Bouquet 99 Rouge', 110.00),
                                            ('Bouquet 99 Rose Clair', 110.00),
                                            ('Bouquet 99 Rose', 110.00),
                                            ('Bouquet 99 Blanc', 110.00),
                                            ('Bouquet 99 Bleu', 110.00),
                                            ('Bouquet 99 Noir', 110.00),

                                            -- Bouquets 100
                                            ('Bouquet 100 Rouge', 112.00),
                                            ('Bouquet 100 Rose Clair', 112.00),
                                            ('Bouquet 100 Rose', 112.00),
                                            ('Bouquet 100 Blanc', 112.00),
                                            ('Bouquet 100 Bleu', 112.00),
                                            ('Bouquet 100 Noir', 112.00),

                                            -- Bouquets 101
                                            ('Bouquet 101 Rouge', 115.00),
                                            ('Bouquet 101 Rose Clair', 115.00),
                                            ('Bouquet 101 Rose', 115.00),
                                            ('Bouquet 101 Blanc', 115.00),
                                            ('Bouquet 101 Bleu', 115.00),
                                            ('Bouquet 101 Noir', 115.00),

                                            -- Coffrets
                                            ('Coffret Anniversaire', 90.00),
                                            ('Coffret Saint-Valentin', 90.00),
                                            ('Coffret Fête des Mères', 100.00),
                                            ('Coffret Baptême', 100.00),
                                            ('Coffret Mariage', 100.00),
                                            ('Coffret Pâques', 100.00),
                                            ('Coffret Noël', 100.00),
                                            ('Coffret Nouvel An', 150.00);

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
-- =====================
-- BOUQUETS DE 12 roses (stock 5)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 12 roses', 'rouge', 12, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 12 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 12 roses', 'rose clair', 12, 6 FROM PRODUIT WHERE PRO_NOM='Bouquet 12 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 12 roses', 'rose', 12, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 12 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 12 roses', 'blanc', 12, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 12 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 12 roses', 'bleu', 12, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 12 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 12 roses', 'noir', 12, 8 FROM PRODUIT WHERE PRO_NOM='Bouquet 12 Noir';

-- =====================
-- BOUQUETS DE 20 roses (stock 2)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 20 roses', 'rouge', 20, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 20 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 20 roses', 'rose clair', 20, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 20 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 20 roses', 'rose', 20, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 20 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 20 roses', 'blanc', 20, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 20 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 20 roses', 'bleu', 20, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 20 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 20 roses', 'noir', 20, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 20 Noir';

-- =====================
-- BOUQUETS DE 24 roses (stock 5)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 24 roses', 'rouge', 24, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 24 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 24 roses', 'rose clair', 24, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 24 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 24 roses', 'rose', 24, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 24 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 24 roses', 'blanc', 24, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 24 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 24 roses', 'bleu', 24, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 24 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 24 roses', 'noir', 24, 5 FROM PRODUIT WHERE PRO_NOM='Bouquet 24 Noir';

-- =====================
-- BOUQUETS DE 36 roses (stock 3)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 36 roses', 'rouge', 36, 3 FROM PRODUIT WHERE PRO_NOM='Bouquet 36 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 36 roses', 'rose clair', 36, 3 FROM PRODUIT WHERE PRO_NOM='Bouquet 36 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 36 roses', 'rose', 36, 3 FROM PRODUIT WHERE PRO_NOM='Bouquet 36 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 36 roses', 'blanc', 36, 3 FROM PRODUIT WHERE PRO_NOM='Bouquet 36 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 36 roses', 'bleu', 36, 3 FROM PRODUIT WHERE PRO_NOM='Bouquet 36 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 36 roses', 'noir', 36, 3 FROM PRODUIT WHERE PRO_NOM='Bouquet 36 Noir';

-- =====================
-- BOUQUETS DE 50 roses (stock 2)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 50 roses', 'rouge', 50, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 50 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 50 roses', 'rose clair', 50, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 50 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 50 roses', 'rose', 50, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 50 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 50 roses', 'blanc', 50, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 50 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 50 roses', 'bleu', 50, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 50 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 50 roses', 'noir', 50, 2 FROM PRODUIT WHERE PRO_NOM='Bouquet 50 Noir';

-- =====================
-- BOUQUETS DE 66 roses (stock 1)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 66 roses', 'rouge', 66, 1 FROM PRODUIT WHERE PRO_NOM='Bouquet 66 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 66 roses', 'rose clair', 66, 1 FROM PRODUIT WHERE PRO_NOM='Bouquet 66 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 66 roses', 'rose', 66, 1 FROM PRODUIT WHERE PRO_NOM='Bouquet 66 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 66 roses', 'blanc', 66, 1 FROM PRODUIT WHERE PRO_NOM='Bouquet 66 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 66 roses', 'bleu', 66, 1 FROM PRODUIT WHERE PRO_NOM='Bouquet 66 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 66 roses', 'noir', 66, 1 FROM PRODUIT WHERE PRO_NOM='Bouquet 66 Noir';

-- =====================
-- BOUQUETS DE 99 roses (stock 9)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 99 roses', 'rouge', 99, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 99 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 99 roses', 'rose clair', 99, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 99 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 99 roses', 'rose', 99, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 99 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 99 roses', 'blanc', 99, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 99 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 99 roses', 'bleu', 99, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 99 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 99 roses', 'noir', 99, 9 FROM PRODUIT WHERE PRO_NOM='Bouquet 99 Noir';

-- =====================
-- BOUQUETS DE 100 roses (stock 7)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 100 roses', 'rouge', 100, 7 FROM PRODUIT WHERE PRO_NOM='Bouquet 100 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 100 roses', 'rose clair', 100, 7 FROM PRODUIT WHERE PRO_NOM='Bouquet 100 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 100 roses', 'rose', 100, 7 FROM PRODUIT WHERE PRO_NOM='Bouquet 100 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 100 roses', 'blanc', 100, 7 FROM PRODUIT WHERE PRO_NOM='Bouquet 100 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 100 roses', 'bleu', 100, 7 FROM PRODUIT WHERE PRO_NOM='Bouquet 100 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 100 roses', 'noir', 100, 7 FROM PRODUIT WHERE PRO_NOM='Bouquet 100 Noir';

-- =====================
-- BOUQUETS DE 101 roses (stock 0)
-- =====================
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 101 roses', 'rouge', 101, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 101 Rouge';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 101 roses', 'rose clair', 101, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 101 Rose Clair';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 101 roses', 'rose', 101, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 101 Rose';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 101 roses', 'blanc', 101, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 101 Blanc';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 101 roses', 'bleu', 101, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 101 Bleu';

INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_COULEUR, BOU_NB_ROSES, BOU_QTE_STOCK)
SELECT PRO_ID, 'Bouquet de 101 roses', 'noir', 101, 0 FROM PRODUIT WHERE PRO_NOM='Bouquet 101 Noir';


-- COFFRETS (ENUM avec accents selon ta table)
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Anniversaire', 10
FROM PRODUIT WHERE PRO_NOM = 'Coffret Anniversaire';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Saint-Valentin', 12
FROM PRODUIT WHERE PRO_NOM = 'Coffret Saint-Valentin';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Fête des Mères', 15
FROM PRODUIT WHERE PRO_NOM = 'Coffret Fête des Mères';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Baptême', 0
FROM PRODUIT WHERE PRO_NOM = 'Coffret Baptême';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Mariage', 1
FROM PRODUIT WHERE PRO_NOM = 'Coffret Mariage';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Pâques', 3
FROM PRODUIT WHERE PRO_NOM = 'Coffret Pâques';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Noël', 11
FROM PRODUIT WHERE PRO_NOM = 'Coffret Noël';

INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
SELECT PRO_ID, 'Nouvel An', 5
FROM PRODUIT WHERE PRO_NOM = 'Coffret Nouvel An';


-- EMBALLAGES
INSERT INTO EMBALLAGE (EMB_NOM, EMB_COULEUR, EMB_QTE_STOCK) VALUES
                                                                ('Papier blanc', 'blanc avec bords dorés', 10),
                                                                ('Papier noir',  'noir avec bords blancs', 5),
                                                                ('Papier rose pâle', 'rose avec bords dorés/roses ', 3),
                                                                ('Papier gris',   'gris avec bords dorés', 7),
                                                                ('Papier violet', 'violet avec bords couleur bronze', 4);