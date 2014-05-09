<?php

use Siel\Acumulus\Magento\MagentoAcumulusConfig;

class Siel_Acumulus_Adminhtml_AcumulusController extends Mage_Adminhtml_Controller_Action {
  /** @var MagentoAcumulusConfig */
  private $acumulusConfig;

  protected function _construct() {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
  }


  protected function _isAllowed() {
    return Mage::getSingleton('admin/session')->isAllowed('system/acumulus_configform');
  }

  public function settingsAction() {
    $this->_title($this->__('System'))->_title($this->t('page_title'));

    if ($this->getRequest()->getMethod() === 'POST') {
      $post = $this->getRequest()->getPost();
      try {
        if (empty($post)) {
          Mage::throwException($this->__('Invalid form data.'));
        }

        /* Process the submitted form */
        $values = array();
        foreach ($this->acumulusConfig->getKeys() as $key) {
          if (isset($post[$key])) {
            $values[$key] = $post[$key];
            // If value of password was not set on loading the form ...
            if ($key === 'password' && empty($values[$key])) {
              $values[$key] = $this->acumulusConfig->get('password');
            }
          }
          else if (($key === 'genericCustomer' || $key === 'overwriteIfExists') && isset($post['defaultCustomerType'])) {
            // Not checked checkboxes are not set at all in the post values.
            // Set the unchecked value if it was available on the form
            $values[$key] = 0;
          }
        }
        $this->processForm($values);
        if ($values['password']) {
          $message = Mage::helper('acumulus')->checkAccountSettings();
          if (is_string($message)) {
            Mage::getSingleton('adminhtml/session')->addError($message);
          }
        }
      } catch (Exception $e) {
        Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
      }
    }

    $this->loadLayout();
    $this->_setActiveMenu('system/acumulus_settings_form');
    $this->_addContent($this->getLayout()->createBlock('siel_acumulus_block_adminhtml_settings'));
    $this->renderLayout();
  }

  /**
   * Processes a submitted config form.
   *
   * @param array $values
   *
   * @return bool
   */
  private function processForm(array $values) {
    if ($result = $this->validateForm($values, $output)) {
      $this->acumulusConfig->castValues($values);
      $this->acumulusConfig->save($values);
      Mage::getSingleton('adminhtml/session')->addSuccess($this->t('message_config_saved'));
    }
    return $result;
  }

  /**
   * Validates the form submission.
   *
   * @param array $values
   * @param string $output
   *
   * @return bool
   */
  private function validateForm(array $values, &$output) {
    $messages = $this->acumulusConfig->validateValues($values);
    foreach ($messages as $message) {
      Mage::getSingleton('adminhtml/session')->addError($this->t($message));
    }
    return empty($messages);
  }

  private function t($key) {
    return Mage::helper('acumulus')->t($key);
  }

//  public function testAction() {
//    $this->_title($this->__('System'))->_title('Test Page');
//
//    /** @var Mage_Sales_Model_Order_Creditmemo $model */
//    $model = Mage::getModel('sales/order_creditmemo');
//
//    /** @var Siel_Acumulus_Model_Order_Observer $observer */
//    $observer = Mage::getModel('acumulus/order_observer');
//    $observer->sendCreditMemoToAcumulus($model->load(8));
//  }

}

