<?php

class Siel_Acumulus_Block_Adminhtml_Form extends Mage_Adminhtml_Block_Widget_Form_Container {

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

  /** @var string */
  protected $formType;

  /**
   * Constructor.
   *
   * @param array $args
   */
  public function __construct(array $args = array()) {
    $this->helper = Mage::helper('acumulus');
    $this->formType = $args['formType'];

    parent::__construct();

    $this->_blockGroup = 'acumulus';
    $this->_controller = 'adminhtml_form';
    $this->_removeButton('delete');
    $this->_removeButton('back');
    if ($this->formType === 'batch') {
      $this->_updateButton('save', 'label', $this->t('button_send'));
    }
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
    $this->setChild('form', $this->getLayout()->createBlock('acumulus/adminhtml_form_form', '', array('formType' => $this->formType)));
    $result = parent::_prepareLayout();
    $this->_mode = $old;
    return $result;
  }

  public function getHeaderText() {
    return $this->t("{$this->formType}_form_header");
  }
}
