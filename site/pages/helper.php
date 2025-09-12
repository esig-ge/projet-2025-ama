<?php
function getOrderType(PDO $pdo, int $comId): string {
$sql = "SELECT DISTINCT CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID = :c";
$st  = $pdo->prepare($sql); $st->execute([':c'=>$comId]);
$types = array_column($st->fetchAll(PDO::FETCH_NUM), 0);
if (!$types) return 'none';
if (count($types) > 1) return 'mixed';          // ne devrait pas arriver si on contrôle
return $types[0];                               // 'bouquet' | 'fleur' | 'coffret'
}
function assertCanAdd(string $currentType, string $toAdd): void {
// règle: fleur seule / coffret seul / bouquet seul (suppléments/emballages sont autorisés uniquement si 'bouquet')
if ($currentType === 'none') return;
if ($toAdd === 'supp' || $toAdd === 'emb') {
if ($currentType !== 'bouquet') throw new RuntimeException("Les suppléments et emballages ne sont autorisés qu'avec un bouquet.");
return;
}
if ($currentType !== $toAdd) {
throw new RuntimeException("Cette commande est de type « $currentType », vous ne pouvez pas y ajouter « $toAdd ».");
}
}
