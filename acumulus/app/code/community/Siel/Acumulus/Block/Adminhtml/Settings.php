<?php

class Siel_Acumulus_Block_Adminhtml_Settings extends Mage_Adminhtml_Block_Widget_Form_Container {

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->helper = Mage::helper('acumulus');

    parent::__construct();

    $this->_blockGroup = 'acumulus';
    $this->_controller = 'adminhtml_settings';
    $this->_removeButton('delete');
    $this->_removeButton('back');
  }

  /**
   * Helper method to translate strings.
   *
   * @param string $key
   *  The key to get a translation for.
   *
   * @return string
   *   The translation for the given key or the key itself if no translation
   *   could be found.
   */
  protected function t($key) {
    return $this->helper->t($key);
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

  public function getHeaderText() {
    return $this->t('page_title');
  }
}