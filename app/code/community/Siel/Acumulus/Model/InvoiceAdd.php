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
    $itemLines = $this->addItemLines($order);
    $maxVatRate = $this->getMaxVatRate($itemLines);
    $shippingLines = $this->addShippingLines($order, $maxVatRate);
    $discountLines = $this->addDiscountLines($order);

    $result = array_merge($itemLines, $shippingLines, $discountLines);
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
  protected function addItemLines(Mage_Sales_Model_Order $order) {
    $result = array();
    $lines = $order->getAllVisibleItems();
    foreach ($lines as $line) {
      $result = array_merge($result, $this->addItemLine($line));
    }
    return $result;
  }

  /**
   * Get an (array of) invoice line(s) for the current order line.
   *
   * As Magento supports bundles, 1 order line may comprise multiple invoice
   * lines. Whether the price and vat info is specified on the bundle or on the
   * children depends on the situation:
   * - does the bundle have price and vat info on itself?
   * - do all child lines have the same vat rate?
   *
   * @param \Mage_Sales_Model_Order_Item $line
   *
   * @return array
   */
  protected function addItemLine(Mage_Sales_Model_Order_Item $line) {
    $result = array();
    $childLines = array();

    // Simple products (products without children): add as 1 line.
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
      $unitPrice = $line->getPriceInclTax();
      $result['unitprice'] = number_format($unitPrice, 4, '.', '');
      // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
      $result['costprice'] = number_format($line->getBaseCost(), 4, '.', '');
    }
    else {
      // Normal case: send price without VAT.
      // For higher precision, we use the prices as entered by the admin.
      $unitPrice = $this->productPricesIncludeTax() ? $line->getPriceInclTax() / (100 + $line->getTaxPercent()) * 100 : $line->getPrice();
      $result['unitprice'] = number_format($unitPrice, 4, '.', '');
    }

    $result['quantity'] = number_format($line->getQtyOrdered(), 2, '.', '');
    $result['vatrate'] = number_format($line->getTaxPercent(), 0);

    if (count($line->getChildrenItems()) > 0) {
      // Composed product: also add child lines, a.o. to be able to print a
      // packing slip in Acumulus.
      foreach($line->getChildrenItems() as $child) {
        $childLines[] = reset($this->addItemLine($child));
      }

      if ($line->getPriceInclTax() > 0.0 && ($line->getTaxPercent() > 0 || $line->getTaxAmount() == 0.0)) {
        // If the bundle line contains valid price and tax info, we remove that
        // info from all child lines (to prevent accounting amounts twice).
        foreach ($childLines as &$childLine) {
          $childLine['unitprice'] = 0;
          $childLine['vatrate'] = -1;
        }
      }
      else {
        // Do all children have the same vat?
        $vatRate = null;
        foreach ($childLines as $childLine) {
          // Check if this is not an empty price/vat line.
          if ($childLine['unitprice'] != 0 && $childLine['vatrate'] !== -1) {
            // Same vat?
            if ($vatRate === null || $childLine['vatrate'] === $vatRate) {
              $vatRate = $childLine['vatrate'];
            }
            else {
              $vatRate = null;
              break;
            }
          }
        }

        if ($vatRate !== null && $vatRate == $result['vatrate'] && $unitPrice != 0.0) {
          // Bundle has price info and same vat as ALL children: use price and
          // vat info from bundle line and remove it from child lines to prevent
          // accounting amounts twice.
          foreach ($childLines as &$childLine) {
            $childLine['unitprice'] = 0;
            $childLine['vatrate'] = -1;
          }
        }
        else {
          // All price and vat info is/remains on the child lines.
          // Make sure no price and vat info is left on the bundle line.
          $result['unitprice'] = 0;
          $result['vatrate'] = -1;
        }
      }
    }

    $result = array_merge(array($result), $childLines);
    return $result;
  }

  /**
   * All shipping costs are collected in 1 line as we only have shipping totals.
   *
   * @param Mage_Sales_Model_Order $order
   * @param int $maxVatRate
   *
   * @return array
   *   0 or 1 shipping lines.
   */
  protected function addShippingLines(Mage_Sales_Model_Order $order, $maxVatRate) {
    $result = array();
    $result[] = $this->addShippingLine($order, $maxVatRate);
    return $result;
  }

  protected function addShippingLine(Mage_Sales_Model_Order $order, $maxVatRate) {
    // If we have free shipping we still want to give the line the "correct"
    // vat rate (for tax reports in Acumulus).
    $vatRate = $order->getShippingAmount() > 0 ? round(100.0 * $order->getShippingTaxAmount() / $order->getShippingAmount()) : $maxVatRate;
    // For higher precision, we use the prices as entered by the admin.
    $unitPrice = $this->productPricesIncludeTax() ? $order->getShippingInclTax() / (100 + $vatRate) * 100 : $order->getShippingAmount();
    $shippingDescription = $order->getShippingDescription();
    return array(
      'itemnumber' => '',
      'product' => !empty($shippingDescription) ? $shippingDescription : $this->acumulusConfig->t('shipping_costs'),
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
