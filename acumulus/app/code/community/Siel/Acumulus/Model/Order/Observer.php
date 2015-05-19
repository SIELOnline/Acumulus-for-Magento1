<?php

use Siel\Acumulus\Shop\Magento\Source;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

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
    $order = $event->getOrder();
    $source = new Source(Source::Order, $order);
    $newStatus = $order->getStatus();
    return $this->helper->getAcumulusConfig()->getManager()->sourceStatusChange($source, $newStatus) !== WebConfigInterface::Status_Exception;
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
    $source = new Source(Source::CreditNote, $creditMemo);
    return $this->helper->getAcumulusConfig()->getManager()->sourceStatusChange($source) !== WebConfigInterface::Status_Exception;
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
    $invoice = $event->getInvoice();
    $order = $invoice->getOrder();
    $source = new Source(Source::Order, $order);
    return $this->helper->getAcumulusConfig()->getManager()->invoiceCreate($source) !== WebConfigInterface::Status_Exception;
  }

}
