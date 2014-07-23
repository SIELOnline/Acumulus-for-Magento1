<?php

/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()->newTable($installer->getTable('acumulus/entry'))
  ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => false,
    'primary' => true,
    'identity' => true,
  ), 'Technical key')
  ->addColumn('entry_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => false,
  ), 'Acumulus entry id')
  ->addColumn('token', Varien_Db_Ddl_Table::TYPE_CHAR, 32, array(
    'nullable' => false,
  ), 'Acumulus invoice token')
  ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => false,
  ), 'Magento order id')
  ->addColumn('invoice_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => true,
  ), 'Magento invoice id')
  ->addColumn('creditmemo_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned' => true,
    'nullable' => true,
  ), 'Magento creditmemo id')
  ->addColumn('created', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
    'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
  ), 'Timestamp created')
  ->addColumn('updated', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
    'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
  ), 'Timestamp updated')
  ->addIndex('idx_entry_id', 'entry_id', array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
  ->addForeignKey('fk_order_id', 'order_id', 'sales_flat_order', 'entity_id', Varien_Db_Ddl_Table::ACTION_NO_ACTION, Varien_Db_Ddl_Table::ACTION_NO_ACTION)
  ->addForeignKey('fk_invoice_id', 'invoice_id', 'sales_flat_invoice', 'entity_id', Varien_Db_Ddl_Table::ACTION_NO_ACTION, Varien_Db_Ddl_Table::ACTION_NO_ACTION)
  ->addForeignKey('fk_creditmemo_id', 'creditmemo_id', 'sales_flat_creditmemo', 'entity_id', Varien_Db_Ddl_Table::ACTION_NO_ACTION, Varien_Db_Ddl_Table::ACTION_NO_ACTION)
  ->setComment('Acumulus entry table');


$installer->getConnection()->createTable($table);
$installer->endSetup();
