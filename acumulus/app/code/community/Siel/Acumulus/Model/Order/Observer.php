<?php

use Siel\Acumulus\Invoice\Source;

class Siel_Acumulus_Model_Order_Observer extends Mage_Core_Model_Abstract {

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

  public function __construct() {
    $this->helper = Mage::helper('acumulus');
    parent::__construct();
  }

  /**
   * Event handler for the sales_order_save_after event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function orderSaveAfter(Varien_Event_Observer $observer) {
    /** @var Varien_Event $event */
    $event = $observer->getEvent();
    /** @var Mage_Sales_Model_Order $order */
    /** @noinspection PhpUndefinedMethodInspection */
    $order = $event->getOrder();
    $source = $this->helper->getAcumulusContainer()->getSource(Source::Order, $order);
    $result = $this->helper->getAcumulusContainer()->getManager()->sourceStatusChange($source);
    return $result->getException() === null;
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
    /** @noinspection PhpUndefinedMethodInspection */
    $creditMemo = $event->getCreditmemo();
    $source = $this->helper->getAcumulusContainer()->getSource(Source::CreditNote, $creditMemo);
    $result = $this->helper->getAcumulusContainer()->getManager()->sourceStatusChange($source);
    return $result->getException() === null;
  }

  /**
   * Event handler for the sales_order_invoice_save_after event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function invoiceSaveAfter(Varien_Event_Observer $observer) {
    /** @var Varien_Event $event */
    $event = $observer->getEvent();
    /** @var Mage_Sales_Model_Order_Invoice $invoice */
    /** @noinspection PhpUndefinedMethodInspection */
    $invoice = $event->getInvoice();
    $source = $this->helper->getAcumulusContainer()->getSource(Source::Order, $invoice->getOrderId());
    $result = $this->helper->getAcumulusContainer()->getManager()->invoiceCreate($source);
    return $result->getException() === null;
  }

}
