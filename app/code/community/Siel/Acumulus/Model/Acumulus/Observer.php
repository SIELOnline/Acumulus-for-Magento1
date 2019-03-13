<?php

use Siel\Acumulus\Invoice\Creator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

class Siel_Acumulus_Model_Acumulus_Observer extends Mage_Core_Model_Abstract {

  /** @var Siel_Acumulus_Helper_Data */
  protected $helper;

  public function __construct() {
    $this->helper = Mage::helper('acumulus');
    parent::__construct();
  }

  /**
   * Event handler for the acumulus_invoice_created event.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function acumulusInvoiceCreated(Varien_Event_Observer $observer) {
    /** @var Varien_Event $event */
    $event = $observer->getEvent();
    /** @var array $invoice */
    $invoice = $event->getData('invoice');
    /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
    $invoiceSource = $event->getData('source');

    $this->supportServiceCost($invoice, $invoiceSource);

    $event->setData('invoice', $invoice);
    return true;
  }

  /**
   * Adds support for payment fees of the MultiSafePay.com module.
   *
   * @param array $invoice
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   */
  protected function supportServiceCost(array &$invoice, Source $invoiceSource) {
    if ($invoiceSource->getType() === Source::Order) {
      if ($invoiceSource->getSource()->getData('base_servicecost') !== null) {
        $sign = $invoiceSource->getType() === Source::CreditNote ? -1 : 1;
        $paymentInc = (float) $sign * $invoiceSource->getSource()->getData('base_servicecost');
        $paymentVat = (float) $sign * $invoiceSource->getSource()->getData('base_servicecost_tax');
        $paymentEx = $paymentInc - $paymentVat;

        $line = array(
          Tag::Product => $this->helper->t('payment_costs'),
          Tag::Quantity => 1,
          Tag::UnitPrice => $paymentEx,
          Meta::UnitPriceInc => $paymentInc,
        );
        $line += Creator::getVatRangeTags($paymentVat, $paymentEx, 0.001, 0.002);
        $line += array(
          Meta::FieldsCalculated => array(Tag::UnitPrice),
          Meta::LineType => Creator::LineType_PaymentFee,
        );
        $invoice['customer']['invoice']['line'][] = $line;

        // @todo: is this necessary?
//        // Add these amounts to the invoice totals.
//        // @see \Siel\Acumulus\Magento\Invoice\Source::getAvailableTotals()
//        $invoice['customer']['invoice'][Meta::InvoiceAmountInc] += $paymentInc;
//        $invoice['customer']['invoice'][Meta::InvoiceVatAmount] += $paymentVat;

      }
    }
  }
}
