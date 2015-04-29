<?php

use Siel\Acumulus\Magento\MagentoAcumulusConfig;

class Siel_Acumulus_Adminhtml_AcumulusController extends Mage_Adminhtml_Controller_Action {
  /** @var MagentoAcumulusConfig */
  private $acumulusConfig;

  protected function _construct() {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
  }


  protected function _isAllowed() {
    return Mage::getSingleton('admin/session')->isAllowed('acumulus/acumulus_'. $this->getRequest()->getRequestedActionName() . '_form');
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
          else if (($key === 'sendCustomer' || $key === 'overwriteIfExists') && isset($post['defaultCustomerType'])) {
            // Not checked checkboxes are not set at all in the post values.
            // Set the unchecked value if it was available on the form
            $values[$key] = in_array($key, $post['clientData']);
          }
        }
        $this->processSettingsForm($values);
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
    $this->_setActiveMenu('acumulus/acumulus_settings_form');
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
  private function processSettingsForm(array $values) {
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

  public function manualAction() {
    $this->_title($this->__('System'))->_title($this->t('page_title_manual'));

    if ($this->getRequest()->getMethod() === 'POST') {
      $post = $this->getRequest()->getPost();
      try {
        if (empty($post)) {
          Mage::throwException($this->__('Invalid form data.'));
        }

        /* Process the submitted form */
        if (!empty($post['manual_order'])) {
          /** @var Mage_Sales_Model_Order $order */
          $order = Mage::getModel('sales/order');
          $incrementId = (int) $post['manual_order'];
          $order->loadByIncrementId($incrementId);
          if ($order->getId()) {
            /** @var Siel_Acumulus_Model_Order_Observer $observer */
            $observer = Mage::getModel('acumulus/order_observer');
            $observer->sendInvoiceToAcumulus($order);
            Mage::getSingleton('adminhtml/session')->addSuccess(sprintf($this->t('manual_order_sent'), $incrementId));
          }
          else {
            Mage::getSingleton('adminhtml/session')->addError(sprintf($this->t('manual_order_not_found'), $incrementId));
          }
        }
        else if (!empty($post['manual_invoice'])) {
          /** @var Mage_Sales_Model_Order_Invoice $invoice */
          $invoice = Mage::getModel('sales/order_invoice');
          $incrementId = (int) $post['manual_invoice'];
          $invoice->loadByIncrementId($incrementId);
          if ($invoice->getId()) {
            $order = $invoice->getOrder();
            /** @var Siel_Acumulus_Model_Order_Observer $observer */
            $observer = Mage::getModel('acumulus/order_observer');
            $observer->sendInvoiceToAcumulus($order);
            Mage::getSingleton('adminhtml/session')->addSuccess(sprintf($this->t('manual_invoice_sent'), $incrementId));
          }
          else {
            Mage::getSingleton('adminhtml/session')->addError(sprintf($this->t('manual_invoice_not_found'), $incrementId));
          }
        }
        else if (!empty($post['manual_creditmemo'])) {
          /** @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
          $creditmemo = Mage::getModel('sales/order_creditmemo');
          $incrementId = (int) $post['manual_creditmemo'];
          $creditmemo->load($incrementId, 'increment_id');
          if ($creditmemo->getId()) {
            /** @var Siel_Acumulus_Model_Order_Observer $observer */
            $observer = Mage::getModel('acumulus/order_observer');
            $observer->sendCreditMemoToAcumulus($creditmemo);
            Mage::getSingleton('adminhtml/session')->addSuccess(sprintf($this->t('manual_creditmemo_sent'), $incrementId));
          }
          else {
            Mage::getSingleton('adminhtml/session')->addError(sprintf($this->t('manual_creditmemo_not_found'), $incrementId));
          }
        }
      } catch (Exception $e) {
        Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
      }
    }

    $this->loadLayout();
    $this->_setActiveMenu('acumulus/acumulus_manual_form');
    $this->_addContent($this->getLayout()->createBlock('siel_acumulus_block_adminhtml_manual'));
    $this->renderLayout();
  }

}

