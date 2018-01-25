<?php
/*
Wij hebben ooit een melding gehad over fouten bij deze upgrade:
  "app/code/community/Siel/Acumulus/sql/acumulus_setup/upgrade-3.4.4-4.0.0.php"
  - SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
  'Order-8644' for key 'siel_acumulus_source', query was: insert into
  acumulus_entries (entry_id, token, source_type, source_id, created, updated)
  select entry_id, token, 'Order' as source_type, order_id as source_id,
  created, updated from acumulus_entries_old where creditmemo_id is null;"

Om de een of andere reden zitten er dan voor een aantal bestellingen meerdere
records in de tabel acumulus_entries. Dit is niet de bedoeling. Hoe dat gekomen
is, is nu niet meer te achterhalen,maar heeft waarschijnlijk te maken met een
melding voor Magento 1 die wij vaker hebben gekregen betreffende het dubbel
versturen van bestellingen naar Acumulus. Dit lijkt in geval deze fout optreedt,
ook gebeurd te zijn. De oorzaak hiervan hebben wij nooit weten te achterhalen,
maar heeft ws te maken met race-condities waarbij de factuur binnen een paar
seconden meerder malen verstuurd wordt voordat het antwoord van Acumulus op de
eerste versturing terug en verwerkt is.

Als dit voor u ook het geval is, dan betekent dit dat deze bestellingen dus
meervoudig in uw Acumulus administratie zijn opgenomen!

Hoe de tabel te "repareren"?
----------------------------
Voer deze 4 queries uit:

CREATE TABLE acumulus_entries_tmp AS
SELECT *
FROM acumulus_entries
WHERE creditmemo_id is null
  AND updated = (SELECT max(updated) FROM acumulus_entries ae2 WHERE acumulus_entries.order_id = ae2.order_id);

INSERT INTO acumulus_entries_tmp
SELECT *
FROM acumulus_entries
WHERE creditmemo_id is not null;

ALTER TABLE acumulus_entries RENAME TO acumulus_entries_org;

ALTER TABLE acumulus_entries_tmp RENAME TO acumulus_entries;

Hierna zou de update goed moeten verlopen (verwijder ook de acumulus_entries_old
tabel voordat u opnieuw gaat updaten).

Hoe de facturen te op te vragen die meervoudig verstuurd zijn?
--------------------------------------------------------------
(NB: doe dit op de tabel acumulus_entries_org als bovenstaande queries al zijn
 uitgevoerd)
SELECT order_id, count(entity_id)
FROM acumulus_entries
WHERE creditmemo_id is null
group by order_id
having count(entity_id) > 1
order by order_id;
*/

/** @var Mage_Core_Model_Resource $resource */
$resource = Mage::getSingleton('core/resource');
/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

$tableName = $installer->getTable('acumulus/entry');
$oldTableName = $tableName . '_old';

// Rename current table.
$connection->renameTable($tableName, $oldTableName);

// Create new table.
$table = $installer->getTableDefinition();
$connection->createTable($table);

// Copy data from old to new table.
// - Orders:
$insertOrders = <<<SQL
insert into $tableName
(entry_id, token, source_type, source_id, created, updated)
select entry_id, token, 'Order' as source_type, order_id as source_id, created, updated
from $oldTableName
where creditmemo_id is null;
SQL;
$connection->query($insertOrders);

// - Credit memos:
$insertCreditNotes = <<<SQL
insert into $tableName
(entry_id, token, source_type, source_id, created, updated)
select entry_id, token, 'CreditNote' as source_type, creditmemo_id as source_id, created, updated
from $oldTableName
where creditmemo_id is not null;
SQL;
$connection->query($insertCreditNotes);

// Delete old table.
$connection->dropTable($oldTableName);

$installer->endSetup();
