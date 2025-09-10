-- =========================
-- DONNÉES DE TEST (corrigées)
-- =========================

-- PERSONNE
INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL) VALUES
                                                                                ('Dupont','Alice','alice.dupont@example.com','Test@1234!','0791234567'),
                                                                                ('Martin','Jean','jean.martin@example.com','Secure#2024','0789876543'),
                                                                                ('Durand','Sophie','sophie.durand@example.com','Azerty@9','0774567890');

-- CLIENT
INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
SELECT PER_ID, '1990-05-12', 100 FROM PERSONNE WHERE PER_EMAIL='alice.dupont@example.com';
INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
SELECT PER_ID, '1985-09-23', 250 FROM PERSONNE WHERE PER_EMAIL='jean.martin@example.com';
INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
SELECT PER_ID, '2000-01-10',  50 FROM PERSONNE WHERE PER_EMAIL='sophie.durand@example.com';

-- ADRESSE
INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE) VALUES
                                                                                      ('Rue de Lausanne','12A','1202','Genève','Suisse','LIVRAISON'),
                                                                                      ('Avenue du Rhône','27','1205','Genève','Suisse','FACTURATION'),
                                                                                      ('Chemin des Fleurs','45','1010','Lausanne','Suisse','LIVRAISON');

-- CLIENT_ADRESSE
INSERT INTO CLIENT_ADRESSE (PER_ID, ADR_ID)
SELECT c.PER_ID, a.ADR_ID
FROM CLIENT c JOIN ADRESSE a ON a.ADR_RUE='Rue de Lausanne' AND a.ADR_NUMERO='12A'
WHERE c.PER_ID = (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='alice.dupont@example.com');

INSERT INTO CLIENT_ADRESSE (PER_ID, ADR_ID)
SELECT c.PER_ID, a.ADR_ID
FROM CLIENT c JOIN ADRESSE a ON a.ADR_RUE='Avenue du Rhône' AND a.ADR_NUMERO='27'
WHERE c.PER_ID = (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='jean.martin@example.com');

INSERT INTO CLIENT_ADRESSE (PER_ID, ADR_ID)
SELECT c.PER_ID, a.ADR_ID
FROM CLIENT c JOIN ADRESSE a ON a.ADR_RUE='Chemin des Fleurs' AND a.ADR_NUMERO='45'
WHERE c.PER_ID = (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='sophie.durand@example.com');

-- PRODUIT
INSERT INTO PRODUIT (PRO_NOM, PRO_PRIX) VALUES
                                            ('Rose Rouge', 5.00),
                                            ('Rose Rose Clair', 5.00),
                                            ('Rose Rose',  5.00),
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
                                            ('Coffret SV',  90.00),
                                            ('Coffret FdM', 100.00),
                                            ('Coffret BPT', 100.00),
                                            ('Coffret MRG', 100.00),
                                            ('Coffret PAQ', 100.00),
                                            ('Coffret NOE', 100.00),
                                            ('Coffret NVA', 150.00),

-- SUPPLEMENT
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

-- SUPP_PRODUIT
INSERT INTO SUPP_PRODUIT (SUP_ID, PRO_ID)
SELECT s.SUP_ID, p.PRO_ID FROM SUPPLEMENT s, PRODUIT p
WHERE s.SUP_NOM IN ('Mini ourson','Décoration anniversaire','Papillons') AND p.PRO_NOM='Bouquet 12';
INSERT INTO SUPP_PRODUIT (SUP_ID, PRO_ID)
SELECT s.SUP_ID, p.PRO_ID FROM SUPPLEMENT s, PRODUIT p
WHERE s.SUP_NOM='Carte' AND p.PRO_NOM='Coffret ANV';

-- FLEUR
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

-- BOUQUET
INSERT INTO BOUQUET (PRO_ID, BOU_DESCRIPTION, BOU_TYPE)
SELECT PRO_ID, 'Bouquet de 12 roses', 'standard'    FROM PRODUIT WHERE PRO_NOM='Bouquet 12';
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

-- BOUQUET_FLEUR (corrigé : noms des roses)
INSERT INTO BOUQUET_FLEUR (BOUQUET_ID, FLEUR_ID, BF_QTE)
SELECT b.PRO_ID, f.PRO_ID, 12
FROM BOUQUET b
         JOIN PRODUIT pb ON pb.PRO_ID=b.PRO_ID AND pb.PRO_NOM='Bouquet 12'
         JOIN FLEUR f ON f.PRO_ID = (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Rose Rouge');

INSERT INTO BOUQUET_FLEUR (BOUQUET_ID, FLEUR_ID, BF_QTE)
SELECT b.PRO_ID, f.PRO_ID, 24
FROM BOUQUET b
         JOIN PRODUIT pb ON pb.PRO_ID=b.PRO_ID AND pb.PRO_NOM='Bouquet 24'
         JOIN FLEUR f ON f.PRO_ID = (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Rose Blanche');

-- COFFRET
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Anniversaire' FROM PRODUIT WHERE PRO_NOM='Coffret ANV';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Saint-Valentin' FROM PRODUIT WHERE PRO_NOM='Coffret SV';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Fetes des Mères' FROM PRODUIT WHERE PRO_NOM='Coffret FdM';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Baptême' FROM PRODUIT WHERE PRO_NOM='Coffret BPT';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Mariage' FROM PRODUIT WHERE PRO_NOM='Coffret MRG';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Paques' FROM PRODUIT WHERE PRO_NOM='Coffret PAQ';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Noel' FROM PRODUIT WHERE PRO_NOM='Coffret NOE';
INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT)
SELECT PRO_ID, 'Nouvel An' FROM PRODUIT WHERE PRO_NOM='Coffret NVA';

-- COFFRET_BOUQUET
INSERT INTO COFFRET_BOUQUET (COFFRET_ID, BOUQUET_ID)
SELECT (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Coffret SV'),
       (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Bouquet 100');

INSERT INTO COFFRET_BOUQUET (COFFRET_ID, BOUQUET_ID)
SELECT (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Coffret ANV'),
       (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Bouquet 20');

INSERT INTO COFFRET_BOUQUET (COFFRET_ID, BOUQUET_ID)
SELECT (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Coffret FdM'),
       (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Bouquet 24');

INSERT INTO EMBALLAGE (EMB_NOM, EMB_COULEUR, EMB_QTE_STOCK) VALUES
                                                                ('Papier blanc', 'blanc avec bords dorés', 10),
                                                                ('Papier noir',  'noir avec bords blancs', 5),
                                                                ('Papier rose pâle',   'rose avec bords dorés/roses ',  3),
                                                                ('Papier gris',   'gris avec bords dorés',  7),
                                                                ('Papier violet', 'violet avec bords couleur bronze', 4);

-- RABAIS
INSERT INTO RABAIS (RAB_POURCENTAGE, RAB_ACTIF) VALUES
                                                    (10.00,1),(15.00,1),(25.00,0);

-- LIVRAISON
INSERT INTO LIVRAISON (LIV_STATUT, LIV_MODE, LIV_MONTANT_FRAIS, LIV_NOM_TRANSPORTEUR, LIV_NUM_SUIVI_COMMANDE, LIV_DATE) VALUES
                                                                                                                            ('prévue','courrier',5.00,'Poste Suisse','CH12345','2025-09-10'),
                                                                                                                            ('en cours','coursier',10.00,'DHL','CH54321','2025-09-12'),
                                                                                                                            ('livrée','retrait',0.00,NULL,NULL,'2025-08-30');

-- COMMANDE
INSERT INTO COMMANDE (PER_ID, LIV_ID, RAB_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE)
SELECT
    (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='alice.dupont@example.com'),
    (SELECT LIV_ID FROM LIVRAISON WHERE LIV_DATE='2025-09-10' AND LIV_MODE='courrier' AND LIV_STATUT='prévue'),
    (SELECT RAB_ID FROM RABAIS WHERE RAB_POURCENTAGE=10.00 AND RAB_ACTIF=1),
    'en préparation','2025-09-01','Commande bouquet 12',30;

INSERT INTO COMMANDE (PER_ID, LIV_ID, RAB_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE)
SELECT
    (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='jean.martin@example.com'),
    (SELECT LIV_ID FROM LIVRAISON WHERE LIV_DATE='2025-09-12' AND LIV_MODE='coursier' AND LIV_STATUT='en cours'),
    (SELECT RAB_ID FROM RABAIS WHERE RAB_POURCENTAGE=15.00 AND RAB_ACTIF=1),
    'expédiée','2025-08-28','Commande coffret SV',90;

INSERT INTO COMMANDE (PER_ID, LIV_ID, RAB_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE)
SELECT
    (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='sophie.durand@example.com'),
    (SELECT LIV_ID FROM LIVRAISON WHERE LIV_DATE='2025-08-30' AND LIV_MODE='retrait' AND LIV_STATUT='livrée'),
    NULL,
    'livrée','2025-08-20','Commande roses rouges',30;

-- COMMANDE_PRODUIT (corrigé : nom de la rose)
INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
SELECT c.COM_ID, p.PRO_ID, 1, 'bouquet'
FROM COMMANDE c, PRODUIT p
WHERE c.COM_DESCRIPTION='Commande bouquet 12' AND p.PRO_NOM='Bouquet 12';

INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
SELECT c.COM_ID, p.PRO_ID, 1, 'coffret'
FROM COMMANDE c, PRODUIT p
WHERE c.COM_DESCRIPTION='Commande coffret SV' AND p.PRO_NOM='Coffret SV';

INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
SELECT c.COM_ID, p.PRO_ID, 12, 'fleur'
FROM COMMANDE c, PRODUIT p
WHERE c.COM_DESCRIPTION='Commande roses rouges' AND p.PRO_NOM='Rose Rouge';

-- COMMANDE_SUPP
INSERT INTO COMMANDE_SUPP (SUP_ID, COM_ID, CS_QTE_COMMANDEE)
SELECT s.SUP_ID, c.COM_ID, 1
FROM SUPPLEMENT s, COMMANDE c
WHERE s.SUP_NOM='Mini ourson' AND c.COM_DESCRIPTION='Commande bouquet 12';

-- COMMANDE_EMBALLAGE
INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE) VALUES (1, 1, 1); -- Papier blanc
INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE) VALUES (1, 2, 1); -- Papier noir
INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE) VALUES (3, 3, 2); -- Papier rose pâle
INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE) VALUES (3, 5, 1); -- Papier violet

-- PAIEMENT
INSERT INTO PAIEMENT (PAI_MODE, PAI_MONTANT, PAI_DATE) VALUES
                                                           ('Twint',30.00,'2025-09-01'),
                                                           ('Carte',90.00,'2025-08-28'),
                                                           ('Revolut',60.00,'2025-08-20');

-- COMMANDE_PAIEMENT
INSERT INTO COMMANDE_PAIEMENT (COM_ID, PAI_ID)
SELECT c.COM_ID, p.PAI_ID
FROM COMMANDE c, PAIEMENT p
WHERE c.COM_DESCRIPTION='Commande bouquet 12' AND p.PAI_MODE='Twint' AND p.PAI_DATE='2025-09-01';

INSERT INTO COMMANDE_PAIEMENT (COM_ID, PAI_ID)
SELECT c.COM_ID, p.PAI_ID
FROM COMMANDE c, PAIEMENT p
WHERE c.COM_DESCRIPTION='Commande coffret SV' AND p.PAI_MODE='Carte' AND p.PAI_DATE='2025-08-28';

INSERT INTO COMMANDE_PAIEMENT (COM_ID, PAI_ID)
SELECT c.COM_ID, p.PAI_ID
FROM COMMANDE c, PAIEMENT p
WHERE c.COM_DESCRIPTION='Commande roses rouges' AND p.PAI_MODE='Revolut' AND p.PAI_DATE='2025-08-20';

-- AVIS (corrigé : nom de la rose)
INSERT INTO AVIS (PRO_ID, PER_ID, AVI_NOTE, AVI_DESCRIPTION, AVI_DATE)
SELECT (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Rose Rouge'),
       (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='alice.dupont@example.com'),
       9,'Très belles roses, fraîcheur impeccable.','2025-09-01';

INSERT INTO AVIS (PRO_ID, PER_ID, AVI_NOTE, AVI_DESCRIPTION, AVI_DATE)
SELECT (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Bouquet 12'),
       (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='jean.martin@example.com'),
       8,'Bouquet bien arrangé, livraison rapide.','2025-08-29';

INSERT INTO AVIS (PRO_ID, PER_ID, AVI_NOTE, AVI_DESCRIPTION, AVI_DATE)
SELECT (SELECT PRO_ID FROM PRODUIT WHERE PRO_NOM='Coffret SV'),
       (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='sophie.durand@example.com'),
       7,'Coffret sympa mais un peu cher.','2025-08-21';

-- CLIENT_RABAIS
INSERT INTO CLIENT_RABAIS (RAB_ID, PER_ID)
SELECT r.RAB_ID, (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='alice.dupont@example.com')
FROM RABAIS r WHERE r.RAB_POURCENTAGE=10.00 AND r.RAB_ACTIF=1;

INSERT INTO CLIENT_RABAIS (RAB_ID, PER_ID)
SELECT r.RAB_ID, (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='jean.martin@example.com')
FROM RABAIS r WHERE r.RAB_POURCENTAGE=15.00 AND r.RAB_ACTIF=1;

INSERT INTO CLIENT_RABAIS (RAB_ID, PER_ID)
SELECT r.RAB_ID, (SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL='sophie.durand@example.com')
FROM RABAIS r WHERE r.RAB_POURCENTAGE=25.00 AND r.RAB_ACTIF=0;



-- AUTRES REQUESTS EFFECTUÉS APRÈS

ALTER TABLE PERSONNE MODIFY PER_MDP VARCHAR(255) NOT NULL;
