<?php

use Siel\Acumulus\Shop\Magento\FormMapper;

class Siel_Acumulus_Block_Adminhtml_Form_Form extends Mage_Adminhtml_Block_Widget_Form {

  /** @var bool */
  protected $initialized = FALSE;

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

  /** @var string */
  protected $formType;

  /**
   * Siel_Acumulus_Block_Adminhtml_Form constructor.
   *
   * @param array $attributes
   */
  public function __construct(array $attributes = array()) {
    $this->formType = $attributes['formType'];
    parent::__construct($attributes);
  }

  /**
   * Helper method that initializes some object properties:
   * - language
   * - model_Setting_Setting
   * - webAPI
   * - acumulusConfig
   */
  protected function init() {
    if (!$this->initialized) {
      $this->helper = Mage::helper('acumulus');
      $this->initialized = TRUE;
    }
  }

  protected function _prepareForm() {
    $this->init();

    $acumulusForm = $this->helper->getForm($this->formType);
    $form = new Varien_Data_Form();
    $mapper = new FormMapper();
    $mapper->map($form, $acumulusForm->getFields());

    $form->setValues($acumulusForm->getFormValues());
    $form->setAction($this->getUrl("*/*/{$this->formType}"));
    $form->setMethod('post');
    $form->setUseContainer(true);
    $form->setId('edit_form');
    $this->setForm($form);

    return parent::_prepareForm();
  }

}
