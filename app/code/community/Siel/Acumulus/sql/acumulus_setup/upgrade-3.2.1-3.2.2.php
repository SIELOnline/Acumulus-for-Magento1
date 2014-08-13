<?php

/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
$tableName = $installer->getTable('acumulus/entry');
$installer->getConnection()
  ->dropForeignKey($tableName, 'fk_order_id')
  ->dropForeignKey($tableName, 'fk_invoice_id')
  ->dropForeignKey($tableName, 'fk_creditmemo_id');
$installer->getConnection()
  ->addForeignKey('siel_acumulus_order_id', $tableName, 'order_id', 'sales_flat_order', 'entity_id', Varien_Db_Ddl_Table::ACTION_NO_ACTION, Varien_Db_Ddl_Table::ACTION_NO_ACTION);
$installer->getConnection()
  ->addForeignKey('siel_acumulus_invoice_id', $tableName, 'invoice_id', 'sales_flat_invoice', 'entity_id', Varien_Db_Ddl_Table::ACTION_NO_ACTION, Varien_Db_Ddl_Table::ACTION_NO_ACTION);
$installer->getConnection()
  ->addForeignKey('siel_acumulus_creditmemo_id', $tableName, 'creditmemo_id', 'sales_flat_creditmemo', 'entity_id', Varien_Db_Ddl_Table::ACTION_NO_ACTION, Varien_Db_Ddl_Table::ACTION_NO_ACTION);
$installer->endSetup();
