<?php

use Siel\Acumulus\Magento\Helpers\FormMapper;

class Siel_Acumulus_Block_Adminhtml_Form_Form extends Mage_Adminhtml_Block_Widget_Form {

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper = NULL;

  /** @var string */
  protected $formType;

  /**
   * Siel_Acumulus_Block_Adminhtml_Form constructor.
   *
   * @param array $attributes
   */
  public function __construct(array $attributes = array()) {
    $this->helper = Mage::helper('acumulus');
    $this->formType = $attributes['formType'];
    parent::__construct($attributes);
  }

  protected function _prepareForm() {
    $acumulusForm = $this->helper-> getAcumulusConfig()->getForm($this->formType);
    $form = new Varien_Data_Form();
    $mapper = new FormMapper();
    $mapper->map($form, $acumulusForm->getFields());

    $form->setValues($acumulusForm->getFormValues());
    /** @noinspection PhpUndefinedMethodInspection */
    $form->setAction($this->getUrl("*/*/{$this->formType}"));
    /** @noinspection PhpUndefinedMethodInspection */
    $form->setMethod('post');
    /** @noinspection PhpUndefinedMethodInspection */
    $form->setUseContainer(true);
    $form->setId('edit_form');
    $this->setForm($form);

    return parent::_prepareForm();
  }

}
