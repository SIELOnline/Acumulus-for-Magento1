<?php

/**
 * Acumulus resource setup
 */
class Siel_Acumulus_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup {

  /**
   * Defines the table definition.
   *
   * Called by the install script and the update script.
   *
   * @return Varien_Db_Ddl_Table
   */
  public function getTableDefinition() {
    $table = $this->getConnection()->newTable($this->getTable('acumulus/entry'))
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
      ->addColumn('source_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'nullable' => false,
      ), 'Invoice source type')
      ->addColumn('source_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => true,
      ), 'Magento invoice source id')
      ->addColumn('created', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
      ), 'Timestamp created')
      ->addColumn('updated', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Timestamp updated')
      ->addIndex('siel_acumulus_entry_id', 'entry_id', array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
      ->addIndex('siel_acumulus_source', array('source_type', 'source_id'), array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
      ->setComment('Acumulus entry table');
    return $table;
  }

}
