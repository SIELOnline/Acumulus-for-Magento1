<?php

/** @var Mage_Core_Model_Resource $resource */
$resource = Mage::getSingleton('core/resource');
/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('acumulus/entry');
$installer->getConnection()
    ->modifyColumn($tableName, 'entry_id', array(
        'TYPE' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => true,
        'default' => null,
    ))
    ->modifyColumn($tableName, 'token', array(
        'TYPE' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'LENGTH' => 32,
        'nullable' => true,
        'default' => null,
    ));

$installer->endSetup();
