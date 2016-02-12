<?php

/**
 * Acumulus entry resource
 */
class Siel_Acumulus_Model_Resource_Entry extends Mage_Core_Model_Resource_Db_Abstract {

  /**
   * Magento "internal constructor" not receiving any parameters.
   */
  protected function _construct() {
    $this->_init('acumulus/entry', 'entity_id');
  }

}
