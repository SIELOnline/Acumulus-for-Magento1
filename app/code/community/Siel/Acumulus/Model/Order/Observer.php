<?php

use Siel\Acumulus\Common\ConfigInterface;
use Siel\Acumulus\Common\WebAPI;
use Siel\Acumulus\Magento\MagentoAcumulusConfig;

class Siel_Acumulus_Model_Order_Observer extends Mage_Core_Model_Abstract {

  /** @var MagentoAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  public function __construct() {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
    $this->webAPI = Mage::helper('acumulus')->getWebAPI();
  }

  /**
   * Event handler for the sales_order_save_after event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function orderSaveAfter(Varien_Event_Observer $observer) {
    if ($this->acumulusConfig->get('triggerOrderEvent') == ConfigInterface::TriggerOrderEvent_OrderStatus) {
      /** @var Varien_Event $event */
      $event = $observer->getEvent();
      /** @var Mage_Sales_Model_Order $order */
      $order = $event->getOrder();
      $currentStatus = $order->getStatus();
      if ($this->acumulusConfig->get('triggerOrderStatus') == $currentStatus) {
        // getAllStatusHistory returns order history ordered by created_at desc,
        // but the current change is at the end!
        $history = $order->getAllStatusHistory();
        // Remove the current state.
        array_pop($history);
        // Look if we have had this status before.
        $isFirstTime = true;
        foreach ($history as $previousState) {
          if ($previousState->getStatus() == $currentStatus) {
            $isFirstTime = false;
            break;
          }
        }
        if ($isFirstTime) {
          return $this->sendInvoiceToAcumulus($order);
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

    $invoice = $invoiceAdd->convertOrderToAcumulusInvoice($order);
    $transportObject = new Varien_Object(array('invoice' => $invoice));
    Mage::dispatchEvent('acumulus_invoice_add', array('transport_object' => $transportObject, 'order' => $order));
    $invoice = $transportObject->getData('invoice');
    $result = $this->webAPI->invoiceAdd($invoice, $order->getIncrementId());

    $this->processResults($result, $order, $order->getIncrementId());
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

    $invoice = $invoiceAdd->convertOrderToAcumulusInvoice($creditMemo);
    $transportObject = new Varien_Object(array('invoice' => $invoice));
    Mage::dispatchEvent('acumulus_invoice_add', array('transport_object' => $transportObject, 'creditmemo' => $creditMemo));
    $invoice = $transportObject->getData('invoice');
    $result = $this->webAPI->invoiceAdd($invoice, $creditMemo->getIncrementId());

    $this->processResults($result, $creditMemo, $creditMemo->getIncrementId());
    return !empty($result['invoice']['invoicenumber']);
  }

  /**
   * @param array $result
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   * @param string $order_id
   */
  protected function processResults($result, $order, $order_id) {
    if (!empty($result['invoice'])) {
      /** @var Siel_Acumulus_Model_Entry $entry */
      $entry = Mage::getModel('acumulus/entry');
      $entry = $entry->getByOrder($order);
      $entry->saveEntry($result['invoice'], $order);
    }

    $messages = $this->webAPI->resultToMessages($result);
    if (!empty($messages)) {
      // Send email.
      $templateVars = array(
        '{order_id}' => $order_id,
        '{invoice_id}' => isset($result['invoice']['invoicenumber']) ? $result['invoice']['invoicenumber'] : $this->acumulusConfig->t('message_no_invoice'),
        '{status}' => $result['status'],
        '{status_text}' => $this->webAPI->getStatusText($result['status']),
        '{status_1_text}' => $this->webAPI->getStatusText(1),
        '{status_2_text}' => $this->webAPI->getStatusText(2),
        '{status_3_text}' => $this->webAPI->getStatusText(3),
        '{messages}' => $this->webAPI->messagesToText($messages),
        '{messages_html}' => $this->webAPI->messagesToHtml($messages),
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
