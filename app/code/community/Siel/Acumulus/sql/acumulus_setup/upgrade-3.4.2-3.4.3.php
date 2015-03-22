<?php

/** @var Mage_Core_Model_Resource $resource */
$resource = Mage::getSingleton('core/resource');
/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
$tableName = $installer->getTable('acumulus/entry');
$installer->getConnection()
  ->dropForeignKey($tableName, 'siel_acumulus_order_id')
  ->dropForeignKey($tableName, 'siel_acumulus_invoice_id')
  ->dropForeignKey($tableName, 'siel_acumulus_creditmemo_id');
$installer->getConnection()
  ->addForeignKey('siel_acumulus_order_id', $tableName, 'order_id', $resource->getTableName('sales/order'), 'entity_id', Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);
$installer->getConnection()
  ->addForeignKey('siel_acumulus_invoice_id', $tableName, 'invoice_id', $resource->getTableName('sales/invoice'), 'entity_id', Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);
$installer->getConnection()
  ->addForeignKey('siel_acumulus_creditmemo_id', $tableName, 'creditmemo_id', $resource->getTableName('sales/creditmemo'), 'entity_id', Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);
$installer->endSetup();
