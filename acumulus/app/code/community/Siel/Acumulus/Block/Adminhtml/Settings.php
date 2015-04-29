<?php

class Siel_Acumulus_Block_Adminhtml_Settings extends Mage_Adminhtml_Block_Widget_Form_Container {
  public function __construct() {
    parent::__construct();

    $this->_blockGroup = 'acumulus';
    $this->_controller = 'adminhtml_settings';
    $this->_removeButton('delete');
    $this->_removeButton('back');
  }

  protected function _prepareLayout()
  {
    // Don't let the parent create the block.
    $old = $this->_mode;
    $this->_mode = '';
    $this->setChild('form', $this->getLayout()->createBlock('acumulus/adminhtml_settings_form'));
    $result = parent::_prepareLayout();
    $this->_mode = $old;
    return $result;
  }

  private function t($key) {
    return Mage::helper('acumulus')->t($key);
  }

  public function getHeaderText() {
    return $this->t('page_title');
  }
}
