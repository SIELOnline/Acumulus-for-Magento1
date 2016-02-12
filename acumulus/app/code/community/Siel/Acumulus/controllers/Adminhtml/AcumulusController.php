<?php

class Siel_Acumulus_Adminhtml_AcumulusController extends Mage_Adminhtml_Controller_Action {

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

  /**
   * Magento "internal constructor" not receiving any parameters.
   */
  protected function _construct() {
    parent::_construct();
    $this->helper = Mage::helper('acumulus');
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

  protected function _isAllowed() {
    /** @var Mage_Admin_Model_Session $session */
    $session = Mage::getSingleton('admin/session');
    return $session->isAllowed('acumulus/acumulus_'. $this->getRequest()->getRequestedActionName() . '_form');
  }

  protected function formAction($formType) {
    $activeMenu = "acumulus/acumulus_{$formType}_form";
    $block = "siel_acumulus_block_adminhtml_form";
    $titleKey = "{$formType}_form_title";

    /** @var Mage_Adminhtml_Model_Session $session */
    $session = Mage::getSingleton('adminhtml/session');
    try {
      // Create the form first: this will load the translations.
      $form = $this->helper->getAcumulusConfig()->getForm($formType);

      $this->_title($this->__('System'))->_title($this->t($titleKey));

      $form->process();
      foreach($form->getSuccessMessages() as $message) {
        $session->addSuccess($message);
      }
      foreach($form->getErrorMessages() as $message) {
        $session->addError($message);
      }
    } catch (Exception $e) {
      $session->addException($e, $e->getMessage());
    }

    $this->loadLayout();
    $this->_setActiveMenu($activeMenu);
    $this->_addContent($this->getLayout()->createBlock($block, '', array('formType' => $formType)));
    $this->renderLayout();
  }

  public function configAction() {
    $this->formAction('config');
  }

  public function batchAction() {
    $this->formAction('batch');
  }

}

