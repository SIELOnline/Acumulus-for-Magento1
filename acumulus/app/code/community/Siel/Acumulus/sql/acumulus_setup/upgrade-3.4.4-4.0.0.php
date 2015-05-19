<?php

/** @var Mage_Core_Model_Resource $resource */
$resource = Mage::getSingleton('core/resource');
/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

$tableName = $installer->getTable('acumulus/entry');
$oldTableName = $tableName . '_old';

// Rename current table.
$connection->renameTablesBatch(array(array('oldName' => $tableName, 'newName' => $oldTableName)));

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
