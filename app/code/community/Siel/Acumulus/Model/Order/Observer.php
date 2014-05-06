<?php

class Siel_Acumulus_Model_Order_Observer extends Mage_Core_Model_Abstract {
  public function saveAfter(Varien_Event_Observer $observer) {
    /** @var Varien_Event $event */
    $event = $observer->getEvent();
    /** @var Mage_Sales_Model_Order $order */
    $order = $event->getOrder();
    $currentStatus = $order->getStatus();
    /** @var \Siel\Acumulus\Magento\MagentoAcumulusConfig $acumulusConfig */
    $acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
    if ($acumulusConfig->get('triggerOrderStatus') == $currentStatus) {
      // This should return order history ordered by created_at desc, but the
      // current change is at the end! So the first entry is the previous state.
      $history = $order->getAllStatusHistory();
      $previousStatus = reset($history);
      if ($previousStatus) {
        $previousStatus = $previousStatus->getStatus();
        if ($previousStatus != $currentStatus) {
          $this->sendOrderToAcumulus($order, $acumulusConfig);
        }
      }
    }
  }

  /**
   * @param Mage_Sales_Model_Order $order
   * @param \Siel\Acumulus\Magento\MagentoAcumulusConfig $acumulusConfig
   *
   * @return bool
   */
  public function sendOrderToAcumulus(Mage_Sales_Model_Order $order, \Siel\Acumulus\Magento\MagentoAcumulusConfig $acumulusConfig) {
    /** @var Siel_Acumulus_Model_InvoiceAdd $invoiceAdd */
    $invoiceAdd = Mage::getModel('acumulus/invoiceAdd', $acumulusConfig);
    $result = $invoiceAdd->send($order);

    /** @var \Siel\Acumulus\Common\WebAPI $webAPI */
    $webAPI = Mage::helper('acumulus')->getWebAPI();
    $messages = $webAPI->resultToMessages($result);
    if (!empty($messages)) {
      // Send email.
      $templateVars = array(
        '{order_id}' => $order->getIncrementId(),
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
      $emailTemplate->setTemplateText(strtr($acumulusConfig->t('mail_html'), $templateVars));
      $emailTemplate->setTemplateSubject($acumulusConfig->t('mail_subject'));
      $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
      $senderName = Mage::getStoreConfig('general/store_information/name', Mage::app()->getStore()->getId());
      $emailTemplate->setSenderName(!empty($senderName) ? $senderName : $acumulusConfig->t('mail_sender_name'));
      $credentials = $acumulusConfig->getCredentials();;
      $emailTemplate->send(!empty($credentials['emailonerror']) ? $credentials['emailonerror'] : Mage::getStoreConfig('trans_email/ident_general/email'),
        Mage::getStoreConfig('trans_email/ident_general/name'));
    }

    return !empty($result['invoice']['invoicenumber']);
  }
}
