<?php
/**
 * @file Contains class Siel\Acumulus\Magento\InvoiceAdd.
 */

use Siel\Acumulus\Common\ConfigInterface;
use Siel\Acumulus\Common\WebAPI;

/**
 * Class InvoiceAdd defines the logic to add an invoice to Acumulus via their
 * web API.
 */
class Siel_Acumulus_Model_InvoiceAdd extends Siel_Acumulus_Model_InvoiceAddBase {
  /**
   * Add the invoice part to the Acumulus invoice.
   *
   * @param Mage_Sales_Model_Order $order
   * @param array $customer
   *
   * @return array
   */
  protected function addInvoice(Mage_Sales_Model_Order $order, array $customer) {
    $result = array();

    /** @var \Mage_Sales_Model_Order_Invoice $invoice */
    $invoices = $order->getInvoiceCollection();
    $invoice = count($invoices) > 0 ? $invoices->getFirstItem() : null;

    // Set concept to 0: Issue invoice, no concept.
    $result['concept'] = WebAPI::Concept_No;

    $invoiceNrSource = $this->acumulusConfig->get('invoiceNrSource');
    if ($invoiceNrSource != ConfigInterface::InvoiceNrSource_Acumulus) {
      $result['number'] = $order->getIncrementId();
      if ($invoiceNrSource == ConfigInterface::InvoiceNrSource_ShopInvoice && $invoice !== null) {
        $result['number'] = $invoice->getIncrementId();
      }
    }

    $dateToUse = $this->acumulusConfig->get('dateToUse');
    if ($dateToUse != ConfigInterface::InvoiceDate_Transfer) {
      // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
      $result['issuedate'] = substr($order->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
      if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate  && $invoice !== null) {
        $this->addIfNotEmpty($result, 'issuedate', substr($invoice->getCreatedAt(), 0, strlen('yyyy-mm-dd')));
      }
    }

    // Allow for float errors on checking the due amount.
    if ($order->getBaseTotalDue() <= 0.005) {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Paid;
      // Take date of last payment as payment date.
      $paymentDate = null;
      foreach($order->getAllPayments() as $payment) {
        /** @var Mage_Sales_Model_Order_Payment $payment */
        if (!$paymentDate || substr($payment->getUpdatedAt(), 0, strlen('yyyy-mm-dd')) > $paymentDate) {
          $paymentDate = substr($payment->getUpdatedAt(), 0, strlen('yyyy-mm-dd'));
        }
      }
      if ($paymentDate) {
        $result['paymentdate'] = $paymentDate;
      }
    }
    else {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Due;
    }

    $result['description'] = $this->acumulusConfig->t('order_id') . ' ' . $order->getIncrementId();

    // Add all order lines.
    $result['line'] = $this->addInvoiceLines($order);

    // Determine vat type.
    $result['vattype'] = $this->webAPI->getVatType($customer, $result);

    return $result;
  }

  /**
   * Add the oder lines to the Acumulus invoice.
   *
   * This includes:
   * - all product lines
   * - discount lines, if any
   * - gift wrapping line, if available
   * - shipping costs, if any
   *
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   */
  protected function addInvoiceLines(Mage_Sales_Model_Order $order) {
    $result = array_merge(
      $this->addOrderLines($order),
      $this->addShippingLines($order),
      $this->addDiscountLines($order)
    );

    return $result;
  }

  /**
   * Magento has many types of products:
   * Simple product:
   * Appears once, with price and possibly with a parentId set, in both getAllItems() and getAllVisibleItems().
   *
   * Grouped product (e.g. a couch, chair and table of the same series):
   * A grouped product itself does not appear in the list of items, only its parts.
   *
   * Configurable product (e.g. size and color variations):
   * Appears twice in getAllItems(), once with price not set and parentId set, once with price set and parentId not set.
   * Appears only once (with price) in getAllVisibleItems().
   *
   * Bundle product (e.g. a computer with a choice of cpu, harddisk, memory, extended warranty):
   * The bundle appears with price; parts may appear with or without price but with parentId set, thus not in getAllVisibleItems().
   * If the bundle contains various tax levels, getTaxPercent() will not be set,
   * but instead the price and tax percent will be set on the child items.
   *
   * Virtual product (e.g. extended warranty):
   * Is normally part of a bundle, otherwise it acts like a simple product
   *
   * Downloadable product: ?
   *
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   */
  protected function addOrderLines(Mage_Sales_Model_Order $order) {
    $result = array();
    $lines = $order->getAllVisibleItems();
    foreach ($lines as $line) {
      $result = array_merge($result, $this->addLineItem($line));
    }
    return $result;
  }

  /**
   * @param \Mage_Sales_Model_Order_Item $line
   *
   * @return array
   */
  protected function addLineItem(Mage_Sales_Model_Order_Item $line) {
    $result = array();

    // Add as 1 line:
    // - Simple products (products without children)
    // - Composed products that have price and tax info available.
    if (count($line->getChildrenItems()) == 0 || ($line->getPrice() > 0.0 && ($line->getTaxPercent() > 0.0 || $line->getTaxAmount() == 0.0))) {
      $result['product'] = $line->getName();
      $this->addIfNotEmpty($result, 'itemnumber', $line->getSku());

      // Magento does not support the margin scheme. So in a standard install
      // this method will always return false. But if this method happens to
      // return true anyway (customisation, hook), the costprice will trigger
      // vattype = 5 for Acumulus.
      if ($this->useMarginScheme($line)) {
        // Margin scheme:
        // - Do not put VAT on invoice: send price incl VAT as unitprice.
        // - But still send the VAT rate to Acumulus.
        $result['unitprice'] = number_format($line->getPriceInclTax(), 4, '.', '');
        // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
        $result['costprice'] = number_format($line->getBaseCost(), 4, '.', '');
      }
      else {
        // Send price without VAT.
        // For higher precision, we use the prices as entered by the admin.
        $unitPrice = $this->productPricesIncludeTax() ? $line->getPriceInclTax() / (100 + $line->getTaxPercent()) * 100 : $line->getPrice();
        $result['unitprice'] = number_format($unitPrice, 4, '.', '');
      }

      $result['quantity'] = number_format($line->getQtyOrdered(), 2, '.', '');
      $result['vatrate'] = number_format($line->getTaxPercent(), 0);
      $result = array($result);
    }
    else {
      // Composed products without tax or price information: add a summary line
      // with price 0 and a line per child item.
      $bundleLine = array(
        'itemnumber' => $line->getSku() ? $line->getSku() : '',
        'product' => $line->getName(),
        'unitprice' => number_format(0, 0, '.', ''),
        'vatrate' => number_format(0, 0),
        'quantity' => number_format($line->getQtyOrdered(), 2, '.', ''),
      );
      $result = array($bundleLine);
      foreach($line->getChildrenItems() as $child) {
        $result = array_merge($result, $this->addLineItem($child));
      }
    }
    return $result;
  }

  /**
   * All shipping costs are collected in 1 line as we only have shipping totals.
   *
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   *   0 or 1 shipping lines.
   */
  protected function addShippingLines(Mage_Sales_Model_Order $order) {
    $result = array();
    if ($order->getShippingAmount()) {
      $result[] = $this->addShippingLine($order);
    }
    return $result;
  }

  protected function addShippingLine(Mage_Sales_Model_Order $order) {
    // For higher precision, we use the prices as entered by the admin.
    $vatRate = round(100.0 * $order->getShippingTaxAmount() / $order->getShippingAmount());
    $unitPrice = $this->productPricesIncludeTax() ? $order->getShippingInclTax() / (100 + $vatRate) * 100 : $order->getShippingAmount();
    return array(
      'itemnumber' => '',
      'product' => $this->acumulusConfig->t('shipping_costs'),
      'unitprice' => number_format($unitPrice, 4, '.', ''),
      'vatrate' => number_format($vatRate, 0),
      'quantity' => 1,
    );
  }

  /**
   * All discount costs are collected in 1 line as we only have discount totals.
   *
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   *   0 or 1 discount lines.
   */
  protected function addDiscountLines(Mage_Sales_Model_Order $order) {
    $result = array();
    if ($order->getDiscountAmount() < 0) {
      $result[] = $this->addDiscountLine($order);
    }
    return $result;
  }

  /**
   * We assume the hidden_tax_amount to be the tax on the discount, but it is
   * a positive number so take care.
   *
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   */
  protected function addDiscountLine(Mage_Sales_Model_Order $order) {
    $amount = $order->getDiscountAmount();
    $tax = -$order->getHiddenTaxAmount();
    return array(
      'itemnumber' => '',
      'product' => $order->getCouponCode() ? $this->acumulusConfig->t('discount_code') . ' ' . $order->getCouponCode() : $this->acumulusConfig->t('discount'),
      'unitprice' => number_format($amount - $tax, 4, '.', ''),
      'vatrate' => number_format(100.0 * $tax / ($amount - $tax), 0),
      'quantity' => 1,
    );
  }

}
