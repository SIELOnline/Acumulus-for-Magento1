<?php

use Siel\Acumulus\Shop\Magento\FormMapper;

class Siel_Acumulus_Block_Adminhtml_Settings_Form extends Siel_Acumulus_Block_Adminhtml_Form {

  /** @var bool */
  protected $initialized = FALSE;

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

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

  /**
   * Helper method that initializes some object properties:
   * - language
   * - model_Setting_Setting
   * - webAPI
   * - acumulusConfig
   */
  private function init() {
    if (!$this->initialized) {
      $this->helper = Mage::helper('acumulus');
      $this->initialized = TRUE;
    }
  }

  protected function _prepareForm() {
    $this->init();

    $acumulusForm = $this->helper->getForm('config');
    $mapper = new FormMapper();
    $form = $mapper->map($acumulusForm->getFields());
    $form->setValues($acumulusForm->getFormValues());

    $form->setAction($this->getUrl('*/*/settings'));
    $form->setMethod('post');
    $form->setUseContainer(true);
    $form->setId('edit_form');
    $this->setForm($form);

    return parent::_prepareForm();
  }

}
