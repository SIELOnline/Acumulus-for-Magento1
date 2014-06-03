<?php
/**
 * @file Contains class Siel\Acumulus\Magento\CreditInvoiceAdd.
 */

use Siel\Acumulus\Common\ConfigInterface;
use Siel\Acumulus\Common\WebAPI;

/**
 * Class InvoiceAdd defines the logic to add an invoice to Acumulus via their
 * web API.
 */
class Siel_Acumulus_Model_CreditInvoiceAdd extends Siel_Acumulus_Model_InvoiceAddBase {

  protected function addCustomer(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    return parent::addCustomer($creditMemo->getOrder());
  }

  /**
   * Add the invoice part to the Acumulus invoice.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   * @param array $customer
   *
   * @return array
   */
  protected function addInvoice(Mage_Sales_Model_Order_Creditmemo $creditMemo, array $customer) {
    $result = array();

    // Set concept to 0: Issue invoice, no concept.
    $result['concept'] = WebAPI::Concept_No;

    // Always use the number of the credit memo, regardless any setting about what
    // number to use.
    $invoiceNrSource = $this->acumulusConfig->get('invoiceNrSource');
    if ($invoiceNrSource != ConfigInterface::InvoiceNrSource_Acumulus) {
      // Differentiate between order invoices and credit memos.
      $result['number'] = 'CM' . $creditMemo->getIncrementId();
    }

    // For date to use we can use the setting 'dateToUse', but we should not
    // differentiate between order date or invoice date: always use the credit
    // memo date.
    $dateToUse = $this->acumulusConfig->get('dateToUse');
    if ($dateToUse != ConfigInterface::InvoiceDate_Transfer) {
      // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
      $result['issuedate'] = substr($creditMemo->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }

    // Detemine payment status based on credit memo state..
    if ($creditMemo->getState() == Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED) {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Paid;
      // @todo: can we find the date that it got refunded.
      // For now: take date created.
      $result['paymentdate'] = substr($creditMemo->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }
    else {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Due;
    }

    $result['description'] = $this->acumulusConfig->t('refund') . ' ' .$this->acumulusConfig->t('order_id') . ' ' . $creditMemo->getOrder()->getIncrementId();

    // Add all order lines.
    $result['line'] = $this->addCreditMemoLines($creditMemo);

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
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   */
  protected function addCreditMemoLines(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    $result = array_merge(
      $this->addItemLines($creditMemo),
      $this->addShippingLines($creditMemo),
      $this->addDiscountLines($creditMemo)
    );

    return $result;
  }

  /**
   * All discount costs are collected in 1 line as we only have discount totals.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   *   0 or 1 discount lines.
   */
  protected function addItemLines(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    $result = array();
    $lines = $creditMemo->getAllItems();
    foreach($creditMemo->getAllItems() as $item) {
      // Only items for which row total is set, are refunded
      /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
      if ($item->getRowTotal() > 0) {
        $result[] = $this->addItemLine($item);
      }
    }
    return $result;
  }

  /**
   * @param Mage_Sales_Model_Order_Creditmemo_Item $item
   *
   * @return array
   */
  protected function addItemLine(Mage_Sales_Model_Order_Creditmemo_Item $item) {
    $result = array();

    $result['product'] = $item->getName();
    $this->addIfNotEmpty($result, 'itemnumber', $item->getSku());

    $vatRate = round(100.0 * $item->getTaxAmount() / $item->getPrice());
    if ($this->useMarginScheme($item)) {
      // Send price with VAT.
      $result['unitprice'] = number_format(-$item->getPriceInclTax(), 4, '.', '');
      // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
      $result['costprice'] = number_format(-$item->getBaseCost(), 4, '.', '');
    }
    else {
      // Send price without VAT.
      // For higher precision, we use the prices as entered by the admin.
      $unitPrice = $this->productPricesIncludeTax() ? $item->getPriceInclTax() / (100 + $vatRate) * 100 : $item->getPrice();
      $result['unitprice'] = number_format(-$unitPrice, 4, '.', '');
    }

    $result['quantity'] = number_format($item->getQty(), 2, '.', '');
    $result['vatrate'] = number_format($vatRate, 0);

    return $result;
  }

  /**
   * All shipping costs are collected in 1 line as we only have shipping totals.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   *   0 or 1 shipping lines.
   */
  protected function addShippingLines(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    $result = array();
    if ($creditMemo->getShippingAmount() > 0) {
      $result[] = $this->addShippingLine($creditMemo);
    }
    return $result;
  }

  protected function addShippingLine(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    return array(
      'itemnumber' => '',
      'product' => $this->acumulusConfig->t('shipping_costs'),
      'unitprice' => number_format(-$creditMemo->getShippingAmount(), 4, '.', ''),
      'vatrate' => number_format(100.0 * $creditMemo->getShippingTaxAmount() / $creditMemo->getShippingAmount(), 0),
      'quantity' => 1,
    );
  }

  /**
   * All discount costs are collected in 1 line as we only have discount totals.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   *   0 or 1 discount lines.
   */
  protected function addDiscountLines(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    $result = array();
    if ($creditMemo->getDiscountAmount() < 0) {
      $result[] = $this->addDiscountLine($creditMemo);
    }
    return $result;
  }

  /**
   * We assume the hidden_tax_amount to be the tax on the discount, but it is
   * a positive number so take care.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   */
  protected function addDiscountLine(Mage_Sales_Model_Order_Creditmemo $creditMemo) {
    $amount = $creditMemo->getDiscountAmount(); // negative.
    $tax = $creditMemo->getHiddenTaxAmount(); // positive.
    $product = '';
    if ($creditMemo->getDiscountDescription()) {
      $product =  $creditMemo->getDiscountDescription();
    }
    else if ($creditMemo->getOrder()->getCouponCode()) {
      $product =  $creditMemo->getOrder()->getCouponCode();
    }
    $product = $product ? $this->acumulusConfig->t('discount_code') . ' ' . $product : $this->acumulusConfig->t('discount');
    return array(
      'itemnumber' => '',
      'product' => $product,
      'unitprice' => number_format($amount + $tax, 4, '.', ''),
      'vatrate' => number_format(100.0 * $tax / (-$amount - $tax), 0),
      'quantity' => 1,
    );
  }

}
