<?php

use Siel\Acumulus\Api;
use Siel\Acumulus\Meta;

/**
 * This module contains example code to:
 * - Customise the invoice before it is sent to Acumulus.
 * - Prevent sending the invoice to Acumulus.
 * - Process the results of sending the invoice to Acumulus.
 *
 * Usage of this module:
 * You can use and modify this example module as you like:
 * - only register the events you are going to use.
 * - add your own event handling in those event handler methods.
 *
 * Documentation for the events:
 * The events defined by the Acumulus module:
 * 1) acumulus_invoice_created
 * 2) acumulus_invoice_send_before
 * 2) acumulus_invoice_send_after
 *
 * ad 1)
 * This event is triggered after the raw invoice has been created but before it
 * is "completed". The raw invoice contains all data from the original order or
 * refund needed to create an invoice in the Acumulus format. The raw invoice
 * needs to be completed before it can be sent. Completing includes:
 * - Determining vat rates for those lines that do not yet have one (mostly
 *   discount lines or other special lines like processing or payment costs).
 * - Correcting vat rates if they were based on dividing a vat amount (in cents)
 *   by a price (in cents).
 * - Splitting discount lines over multiple vat rates.
 * - Making prices ex vat more precise to prevent invoice amount differences.
 * - Converting non Euro currencies (future feature).
 * - Flattening composed products or products with options.
 *
 * So with this event you can make changes to the raw invoice based on your
 * specific situation. By setting the invoice to null, you can prevent the
 * invoice from being sent to Acumulus. Normally you should prefer the 2nd
 * event, where you can assume that the invoice has been flattened and all
 * fields are filled in and have a valid value.
 *
 * However, in some specific cases this event maybe needed, e.g. setting or
 * correcting tax rates before the completor strategies are executed.
 *
 * ad 2)
 * This event is triggered just before the invoice is sent to Acumulus. You can
 * make changes to the invoice or add warnings or errors to the Result object.
 * By setting the invoice to null, you can prevent the invoice from being sent
 * to Acumulus.
 *
 * Typical use cases are:
 * - Template, account number, or cost center selection based on order
 *   specifics, e.g. in a multi-shop environment.
 * - Adding descriptive info to the invoice or invoice lines based on custom
 *   order meta data or data from not supported modules.
 * - Correcting payment info based on specific knowledge of your situation or on
 *   payment modules not supported by this module.
 *
 * ad 3)
 * This event is triggered after the invoice has been sent to Acumulus. The
 * Result object will tell you if there was an exception or if errors or
 * warnings were returned by the Acumulus API. On success, the entry id and
 * token for the newly created invoice in Acumulus are available, so you can
 * e.g. retrieve the pdf of the Acumulus invoice.
 *
 * External Resources:
 * - https://apidoc.sielsystems.nl/content/invoice-add.
 * - https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls
 */
class Siel_AcumulusCustomiseInvoice_Model_AcumulusInvoice_Observer extends Mage_Core_Model_Abstract {

  /** @var Siel_Acumulus_Helper_Data */
  private $helper;

  public function __construct() {
    $this->helper = Mage::helper('acumulus');
    parent::__construct();
  }

  /**
   * Event handler for the acumulus_invoice_created event.
   *
   * The Event contains the following data properties:
   * array|null &invoice
   *   The invoice in Acumulus format as will be sent to Acumulus or null if
   *   another observer already decided that the invoice should not be sent to
   *   Acumulus.
   * \Siel\Acumulus\Invoice\Source invoiceSource
   *   Wrapper around the original Magento order or refund for which the
   *   invoice has been created.
   * \Siel\Acumulus\Invoice\Result result
   *   Any local error or warning messages that were created locally.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function acumulusInvoiceCreated(Varien_Event_Observer $observer) {
    $event = $observer->getEvent();
    /** @var array $invoice */
    $invoice = $event->getData('invoice');
    /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
    $invoiceSource = $event->getData('source');
    /** @var \Siel\Acumulus\Invoice\Result $localResult */
    $localResult = $event->getData('localResult');

    // Here you can make changes to the raw invoice based on your specific
    // situation, e.g. setting or correcting tax rates before the completor
    // strategies execute.

    // NOTE: the example below is now an option in the advanced settings:
    // Prevent sending 0-amount invoices (free products).
    if (empty($invoice) || $invoice['customer']['invoice'][Meta::InvoiceAmountInc] == 0) {
      $invoice = NULL;
    }
    else {
      // Change invoice here.
      $invoice['customer']['invoice']['test'] = 'test';
    }

    // Pass changes back to Acumulus.
    $event->setData('invoice', $invoice);
    return TRUE;
  }

  /**
   * Event handler for the acumulus_invoice_send_before event.
   *
   * The Event contains the following data properties:
   * array|null &invoice
   *   The invoice in Acumulus format as will be sent to Acumulus or null if
   *   another observer already decided that the invoice should not be sent to
   *   Acumulus.
   * \Siel\Acumulus\Invoice\Source invoiceSource
   *   Wrapper around the original Magento order or refund for which the
   *   invoice has been created.
   * \Siel\Acumulus\Invoice\Result result
   *   Any local error or warning messages that were created locally.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function acumulusInvoiceSendBefore(Varien_Event_Observer $observer) {
    $event = $observer->getEvent();
    /** @var array $invoice */
    $invoice = $event->getData('invoice');
    /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
    $invoiceSource = $event->getData('source');
    /** @var \Siel\Acumulus\Invoice\Result $localResult */
    $localResult = $event->getData('localResult');

    // Here you can make changes to the raw invoice based on your specific
    // situation, e.g. setting or correcting tax rates before the completor
    // strategies execute.

    // NOTE: the example below is now an option in the advanced settings:
    // Prevent sending 0-amount invoices (free products).
    if (empty($invoice) || $invoice['customer']['invoice'][Meta::InvoiceAmountInc] == 0) {
      $invoice = NULL;
    }
    else {
      // Here you can make changes to the invoice based on your specific
      // situation, e.g. setting the payment status to its correct value:
      $invoice['customer']['invoice']['testpaymentstatus'] = Api::PaymentStatus_Due;
    }

    // Pass changes back to Acumulus.
    $event->setData('invoice', $invoice);
    return TRUE;
  }

  /**
   * Event handler for the acumulus_invoice_send_after event.
   *
   * The Event contains the following data properties:
   * array|null invoice
   *   The invoice in Acumulus format as will be sent to Acumulus or null if
   *   another observer already decided that the invoice should not be sent to
   *   Acumulus.
   * \Siel\Acumulus\Invoice\Source invoiceSource
   *   Wrapper around the original Magento order or refund for which the
   *   invoice has been created.
   * \Siel\Acumulus\Invoice\Result result
   *   Any local error or warning messages that were created locally.
   *
   * @param Varien_Event_Observer $observer
   *
   * @return bool
   */
  public function acumulusInvoiceSendAfter(Varien_Event_Observer $observer) {
    $event = $observer->getEvent();
    /** @var array $invoice */
    $invoice = $event->getData('invoice');
    /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
    $invoiceSource = $event->getData('source');
    /** @var \Siel\Acumulus\Invoice\Result $result */
    $result = $event->getData('result');

    if ($result->getException()) {
      // Serious error:
      if ($result->isSent()) {
        // During sending.
      }
      else {
        // Before sending.
      }
    }
    elseif ($result->hasError()) {
      // Invoice was sent to Acumulus but not created due to errors in the
      // invoice.
    }
    else {
      // Sent successfully, invoice has been created in Acumulus:
      if ($result->getWarnings()) {
        // With warnings.
      }
      else {
        // Without warnings.
      }

      // Check if an entry id was created.
      $acumulusInvoice = $result->getResponse();
      if (!empty($acumulusInvoice['entryid'])) {
        $token = $acumulusInvoice['token'];
        $entryId = $acumulusInvoice['entryid'];
      }
      else {
        // If the invoice was sent as a concept, no entryid will be returned.
      }
    }
    return TRUE;
  }

}
