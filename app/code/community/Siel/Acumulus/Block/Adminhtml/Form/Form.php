<?php

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

  /**
   * {@inheritdoc}
   */
  protected function _prepareLayout() {
    /** @var Mage_Page_Block_Html_Head $head */
    if ($head = $this->getLayout()->getBlock('head')) {
      $head->addCss('siel-acumulus-config-form.css');
    }
    return parent::_prepareLayout();
  }

  /**
   * {@inheritdoc}
   */
  protected function _prepareForm() {
    $acumulusForm = $this->helper->getAcumulusContainer()->getForm($this->formType);
    $form = new Varien_Data_Form();
    /** @var \Siel\Acumulus\Magento\Helpers\FormMapper $mapper */
    $mapper = $this->helper->getAcumulusContainer()->getFormMapper();
    $mapper->setMagentoForm($form)->map($acumulusForm);

    $form->setValues($acumulusForm->getFormValues());
    /** @noinspection PhpUndefinedMethodInspection */
    /** @noinspection PhpUnhandledExceptionInspection */
    $form->setAction($this->getUrl("*/*/{$this->formType}"));
    /** @noinspection PhpUndefinedMethodInspection */
    /** @noinspection PhpUnhandledExceptionInspection */
    $form->setMethod('post');
    /** @noinspection PhpUndefinedMethodInspection */
    /** @noinspection PhpUnhandledExceptionInspection */
    $form->setUseContainer(true);
    $form->setId('edit_form');
    $this->setForm($form);

    return parent::_prepareForm();
  }

}
