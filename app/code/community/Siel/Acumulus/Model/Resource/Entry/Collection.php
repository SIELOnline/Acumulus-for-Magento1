<?php

/**
 * Acumulus entry collection class
 */
class Siel_Acumulus_Model_Resource_Entry_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {

  protected function _construct() {
    $this->_init('acumulus/entry');
  }

}
