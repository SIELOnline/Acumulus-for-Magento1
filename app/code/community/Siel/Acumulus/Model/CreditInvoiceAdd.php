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

  /**
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   */
  protected function addCustomer($creditMemo) {
    return parent::addCustomer($creditMemo->getOrder());
  }

  /**
   * Add the invoice part to the Acumulus invoice.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   */
  protected function addInvoice($creditMemo) {
    $result = array();

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

    // Determine payment status based on credit memo state..
    if ($creditMemo->getState() == Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED) {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Paid;
      $result['paymentdate'] = substr($creditMemo->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }
    else {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Due;
    }

    $result['description'] = $this->acumulusConfig->t('refund') . ' ' .$this->acumulusConfig->t('order_id') . ' ' . $creditMemo->getOrder()->getIncrementId();

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

    $vatRate = $item->getPrice() != 0 ? 100.0 * ($item->getTaxAmount() + $item->getHiddenTaxAmount()) / ($item->getQty() * $item->getPrice()) : null;
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
    $result['vatrate'] = $vatRate !== null ? number_format($vatRate) : null;

    // Administer discount amounts and taxes per tax rate.
    if ($vatRate != -1 && $item->getDiscountAmount() > 0.0) {
      $taxDifference = (0.01 * $item->getTaxPercent() * $item->getQtyOrdered() * $unitPrice) - $item->getTaxAmount();
      if (array_key_exists($vatRate, $this->discountAmounts)) {
        $this->discountAmounts[$vatRate] += $item->getDiscountAmount();
      }
      else {
        $this->discountAmounts[$vatRate] = $item->getDiscountAmount();
      }
      if (!$this->floatsAreEqual($taxDifference, 0.0)) {
        if (array_key_exists($vatRate, $this->discountAmounts)) {
          $this->discountTaxAmounts[$vatRate] += $taxDifference;
        }
        else {
          $this->discountTaxAmounts[$vatRate] = $taxDifference;
        }
      }
    }

    return $result;
  }

  protected function addFeeLines($order, $maxVatRate) {
    $result = parent::addFeeLines($order, $maxVatRate);
    // Reverse sign of fee lines, remove 0.0 fee lines, those are not refunded,
    for ($i = count($result) - 1; $i >= 0; $i--) {
      if ((float) $result[$i]['unitprice'] != 0.0) {
        $result[$i]['unitprice'] = number_format(-$result[$i]['unitprice'], 4, '.', '');
      }
      else {
        unset($result[$i]);
      }
    }
    return $result;
  }

  /**
   * Returns a collection of lines added manually to the invoice.
   *
   * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
   *
   * @return array
   *   array of lines added manually to the invoice.
   */
  protected function addManualLines($creditMemo) {
    $result = array();

    if ((float) $creditMemo->getAdjustment() != 0.0) {
      $line['product'] = $this->acumulusConfig->t('refund_adjustment');
      $line['unitprice'] = number_format(-$creditMemo->getAdjustment(), 4, '.', '');
      $line['quantity'] = 1;
      $line['vatrate'] = 0;
      $result[] = $line;
    }
    return $result;
  }

}
