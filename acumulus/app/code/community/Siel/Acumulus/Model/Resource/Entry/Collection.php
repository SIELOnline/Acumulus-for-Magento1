<?php

/**
 * Acumulus entry collection class is used by
 * Siel\Acumulus\Shop\Magento\AcumulusEntryModel to retrieve record sets from
 * the database.
 */
class Siel_Acumulus_Model_Resource_Entry_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {

  /**
   * Magento "internal constructor" not receiving any parameters.
   */
  protected function _construct() {
    $this->_init('acumulus/entry');
  }

}
