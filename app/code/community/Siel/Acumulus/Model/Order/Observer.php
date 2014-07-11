<?php

use Siel\Acumulus\Common\ConfigInterface;
use Siel\Acumulus\Magento\MagentoAcumulusConfig;

class Siel_Acumulus_Model_Order_Observer extends Mage_Core_Model_Abstract {

  /** @var MagentoAcumulusConfig */
  protected $acumulusConfig;

  public function __construct() {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
  }

  /**
   * Event handler for the sales_order_save_after event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function orderSaveAfter(Varien_Event_Observer $observer) {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
    if ($this->acumulusConfig->get('triggerOrderEvent') == ConfigInterface::TriggerOrderEvent_OrderStatus) {
      /** @var Varien_Event $event */
      $event = $observer->getEvent();
      /** @var Mage_Sales_Model_Order $order */
      $order = $event->getOrder();
      $currentStatus = $order->getStatus();
      $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
      if ($this->acumulusConfig->get('triggerOrderStatus') == $currentStatus) {
        // This should return order history ordered by created_at desc, but the
        // current change is at the end! So the first entry is the previous state.
        $history = $order->getAllStatusHistory();
        $previousStatus = reset($history);
        if ($previousStatus) {
          $previousStatus = $previousStatus->getStatus();
          if ($previousStatus != $currentStatus) {
            return $this->sendInvoiceToAcumulus($order);
          }
        }
      }
    }
    return true;
  }

  /**
   * Event handler for the sales_order_invoice_save_after event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function invoiceSaveAfter(Varien_Event_Observer $observer) {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
    if ($this->acumulusConfig->get('triggerOrderEvent') == ConfigInterface::TriggerOrderEvent_InvoiceCreate) {
      /** @var Varien_Event $event */
      $event = $observer->getEvent();
      /** @var Mage_Sales_Model_Order_Invoice $invoice */
      $invoice = $event->getInvoice();
      $order = $invoice->getOrder();
      return $this->sendInvoiceToAcumulus($order);
    }
    return true;
  }

  /**
   * @param Mage_Sales_Model_Order $order
   *
   * @return bool
   */
  public function sendInvoiceToAcumulus($order) {
    /** @var Siel_Acumulus_Model_InvoiceAdd $invoiceAdd */
    $invoiceAdd = Mage::getModel('acumulus/invoiceAdd');
    $result = $invoiceAdd->send($order);
    $this->processResults($result, $order->getIncrementId());
    return !empty($result['invoice']['invoicenumber']);
  }

  /**
   * Event handler for the sales_order_creditmemo_save_after event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function creditMemoSaveAfter(Varien_Event_Observer $observer) {
    /** @var Varien_Event $event */
    $event = $observer->getEvent();
    /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
    $creditMemo = $event->getCreditmemo();
    return $this->sendCreditMemoToAcumulus($creditMemo);
  }

  /**
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return bool
   */
  public function sendCreditMemoToAcumulus(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    /** @var Siel_Acumulus_Model_CreditInvoiceAdd $invoiceAdd */
    $invoiceAdd = Mage::getModel('acumulus/creditInvoiceAdd');
    $result = $invoiceAdd->send($creditMemo);
    $this->processResults($result, 'Credit memo ' . $creditMemo->getIncrementId());
    return !empty($result['invoice']['invoicenumber']);
  }

  protected function processResults($result, $order_id) {
    /** @var \Siel\Acumulus\Common\WebAPI $webAPI */
    $webAPI = Mage::helper('acumulus')->getWebAPI();
    $messages = $webAPI->resultToMessages($result);
    if (!empty($messages)) {
      // Send email.
      $templateVars = array(
        '{order_id}' => $order_id,
        '{invoice_id}' => isset($result['invoice']['invoicenumber']) ? $result['invoice']['invoicenumber'] : $this->acumulusConfig->t('message_no_invoice'),
        '{status}' => $result['status'],
        '{status_text}' => $webAPI->getStatusText($result['status']),
        '{status_1_text}' => $webAPI->getStatusText(1),
        '{status_2_text}' => $webAPI->getStatusText(2),
        '{status_3_text}' => $webAPI->getStatusText(3),
        '{messages}' => $webAPI->messagesToText($messages),
        '{messages_html}' => $webAPI->messagesToHtml($messages),
      );
      /** @var Mage_Core_Model_Email_Template $emailTemplate */
      $emailTemplate = Mage::getModel('core/email_template');
      $emailTemplate->setTemplateText(strtr($this->acumulusConfig->t('mail_html'), $templateVars));
      $emailTemplate->setTemplateSubject($this->acumulusConfig->t('mail_subject'));
      $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
      $senderName = Mage::getStoreConfig('general/store_information/name', Mage::app()->getStore()->getId());
      $emailTemplate->setSenderName(!empty($senderName) ? $senderName : $this->acumulusConfig->t('mail_sender_name'));
      $credentials = $this->acumulusConfig->getCredentials();;
      $emailTemplate->send(!empty($credentials['emailonerror']) ? $credentials['emailonerror'] : Mage::getStoreConfig('trans_email/ident_general/email'),
        Mage::getStoreConfig('trans_email/ident_general/name'));
    }
  }

}
