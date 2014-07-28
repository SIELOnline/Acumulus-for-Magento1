<?php
/**
 * @file Contains class Siel\Acumulus\Magento\InvoiceAddBase.
 */

use Siel\Acumulus\Common\WebAPI;
use Siel\Acumulus\Magento\MagentoAcumulusConfig;

/**
 * Class InvoiceAddBase defines basic logic to add an order or credit memo to
 * Acumulus via their web API.
 */
abstract class Siel_Acumulus_Model_InvoiceAddBase {

  /** @var MagentoAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /** @var array Discount amounts per vat rate. */
  protected $discountAmounts;

  /** @var array */
  protected $discountTaxAmounts;

  public function __construct() {
    $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
    $this->webAPI = Mage::helper('acumulus')->getWebAPI();
  }

  /**
   * Send an order to Acumulus.
   *
   * For now we don't check if the order is already sent to Acumulus (in which
   * case we might just update the payment status), we just send it.
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *   The order to send to Acumulus
   *
   * @return array
   *   A keyed array with the following keys:
   *   - errors
   *   - warnings
   *   - status
   *   - invoice (optional)
   *   If the key invoice is present, it indicates success.
   *
   * See https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls
   * for more information on the contents of the returned array.
   */
  public function send($order) {
    // Create the invoice array.
    $invoice = $this->convertOrderToAcumulusInvoice($order);

    // Send it.
    $result = $this->webAPI->invoiceAdd($invoice, $order->getIncrementId());

    if ($result['invoice']) {
      // Attach token and invoice number to order: not yet implemented.
    }

    return $result;
  }

  /**
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return array
   */
  protected function convertOrderToAcumulusInvoice($order) {
    $this->discountTaxAmounts = array();
    $invoice = array();
    $invoice['customer'] = $this->addCustomer($order);
    $invoice['customer']['invoice'] = $this->addInvoice($order);
    $invoice['customer']['invoice']['line'] = $this->addInvoiceLines($order);
    return $invoice;
  }

  /**
   * Add the customer part to the Acumulus invoice.
   *
   * Fields that do not exist in Prestashop:
   * - salutation: ignore, we don't try to create it based on gender or if it is
   *     a company.
   * - company2: empty
   * - bankaccountnumber: ignore, it may be available somewhere in payments, but
   *     I could not find it.
   * - mark
   *
   * As we can't provide all fields, the customer data will only be overwritten,
   * if explicitly set via the config. This because overwriting is an all or
   * nothing operation that includes emptying not provided fields.
   *
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   */
  protected function addCustomer($order) {
    $result = array();

    $invoiceAddress = $order->getBillingAddress();
    $this->addEmpty($result, 'companyname1', $invoiceAddress->getCompany());
    $result['companyname2'] = '';
    $result['fullname'] = $invoiceAddress->getFirstname() . ' ' . $invoiceAddress->getLastname();
    $this->addEmpty($result, 'address1', $invoiceAddress->getStreet(1));
    $this->addEmpty($result, 'address2', $invoiceAddress->getStreet(2));
    $this->addEmpty($result, 'postalcode', $invoiceAddress->getPostcode());
    $this->addEmpty($result, 'city', $invoiceAddress->getCity());
    if ($invoiceAddress->getCountryId()) {
      $result['countrycode'] = $invoiceAddress->getCountry();
      $result['locationcode'] = $this->webAPI->getLocationCode($result['countrycode']);
    }
    $this->addIfNotEmpty($result, 'vatnumber', $order->getCustomerTaxvat());
    $this->addIfNotEmpty($result, 'telephone', $invoiceAddress->getTelephone());
    $this->addIfNotEmpty($result, 'fax', $invoiceAddress->getFax());
    $result['email'] = $invoiceAddress->getEmail();
    $result['overwriteifexists'] = $this->acumulusConfig->get('overwriteIfExists') ? WebAPI::OverwriteIfExists_Yes : WebAPI::OverwriteIfExists_No;

    return $result;
  }

  /**
   * Add the oder lines to the Acumulus invoice.
   *
   * This includes:
   * - all product lines
   * - shipping costs, if any
   * - payment processing fees, if any
   * - discount lines, if any
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *   Order or credit memo.
   *
   * @return array
   */
  protected function addInvoiceLines($order) {
    $itemLines = $this->addItemLines($order);
    $maxVatRate = $this->getMaxVatRate($itemLines);
    $feeLines = $this->addFeeLines($order, $maxVatRate);
    $discountLines = $this->addDiscountLines($order);

    $result = array_merge($itemLines, $feeLines, $discountLines);
    return $result;
  }

  /**
   * Returns a collection of fee lines.
   *
   * Known fees:
   * - shipping costs
   * - payment charges
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   * @param int $maxVatRate
   *
   * @return array
   *   array of fee lines.
   */
  protected function addFeeLines($order, $maxVatRate) {
    $result = array();
    // Also add a line with free shipping (0.0 versus null)
    if ($order->getShippingAmount() !== null) {
      $result[] = $this->addShippingLine($order, $maxVatRate);
    }
    // Do not add a line at all if there are no payment charges.
    if ($order->getPaymentchargeAmount() != 0) {
      $result[] = $this->addPaymentChargeLine($order, $maxVatRate);
    }
    return $result;
  }

  /**
   * Returns a line with the shipping costs.
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   * @param int $maxVatRate
   *
   * @return array
   */
  protected function addShippingLine($order, $maxVatRate) {
    // If we have free shipping we still want to give the line the "correct"
    // vat rate (for tax reports in Acumulus).
    $vatRate = $order->getShippingAmount() > 0 ? round(100.0 * $order->getShippingTaxAmount() / $order->getShippingAmount()) : $maxVatRate;
    // For higher precision, we use the prices as entered by the admin.
    $unitPrice = $this->productPricesIncludeTax() ? $order->getShippingInclTax() / (100 + $vatRate) * 100 : $order->getShippingAmount();
    $shippingDescription = $order->getShippingDescription();
    $result = array(
      'itemnumber' => '',
      'product' => !empty($shippingDescription) ? $shippingDescription : $this->acumulusConfig->t('shipping_costs'),
      'unitprice' => number_format($unitPrice, 4, '.', ''),
      'vatrate' => number_format($vatRate, 0),
      'quantity' => 1,
    );

    // Administer taxes on discount per tax rate.
    if ($order->getShippingDiscountAmount() > 0.0) {
      if (array_key_exists($vatRate, $this->discountTaxAmounts)) {
        $this->discountAmounts[$vatRate] += $order->getShippingDiscountAmount();
      }
      else {
        $this->discountAmounts[$vatRate] = $order->getShippingDiscountAmount();
      }
      $taxDifference = 0.01 * $vatRate * $order->getShippingAmount() - $order->getShippingTaxAmount();
      if (array_key_exists($vatRate, $this->discountTaxAmounts)) {
        $this->discountTaxAmounts[$vatRate] += $taxDifference;
      }
      else {
        $this->discountTaxAmounts[$vatRate] = $taxDifference;
      }
    }

    return $result;
  }

  /**
   * Returns an invoice line for a payment charge.
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   * @param int $maxVatRate
   *
   * @return array
   */
  protected function addPaymentChargeLine(Mage_Sales_Model_Order_Creditmemo $order, $maxVatRate) {
    // If we have free shipping we still want to give the line the "correct"
    // vat rate (for tax reports in Acumulus).
    $vatRate = $order->getShippingAmount() > 0 ? round(100.0 * $order->getShippingTaxAmount() / $order->getShippingAmount()) : $maxVatRate;
    // For higher precision, we use the prices as entered by the admin.
    $paymentAmount = $order->getPaymentchargeAmount();
    $paymentTax =  $order->getPaymentchargeTaxAmount();
    if ($this->productPricesIncludeTax()) {
      // Product prices incl. VAT => payment charges are also incl. VAT.
      $paymentAmount -= $paymentTax;
    }
    $vatRate = 100.0 * $paymentTax / $paymentAmount;
    return array(
      'itemnumber' => '',
      'product' => $this->acumulusConfig->t('payment_costs'),
      'unitprice' => number_format($paymentAmount, 4, '.', ''),
      'vatrate' => number_format($vatRate, 0),
      'quantity' => 1,
    );
  }

  /**
   * Returns a line with the discount on this order (if any).
   *
   * Magento only supports 1 discount code per order, so at most 1 line is returned.
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return array
   *   0 or 1 discount lines.
   */
  protected function addDiscountLines($order) {
    $result = array();
    if ($order->getDiscountAmount() != 0) {
      if ($this->floatsAreEqual(array_sum($this->discountTaxAmounts), 0.0, 0.02)) {
        // If it is an order, treat the discount as a partial payment.
        // If this is a credit memo, the partial payment is to be refunded as
        // well, so it should not be deducted from the amount to refund.
        if ($order instanceof Mage_Sales_Model_Order) {
          $result[] = $this->addPartialPaymentLine($order);
        }
      }
      else {
        $result += $this->addDiscountLinePerTaxRate($order);
      }
    }
    return $result;
  }

  /**
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return array
   */
  protected function addDiscountLinePerTaxRate($order) {
    $result= array();
    foreach ($this->discountAmounts as $vatRate => $discountAmount) {
      $result[] = $this->addDiscountLine($order, $discountAmount, $vatRate);
    }
    return $result;
  }

  /**
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return array
   */
  protected function addPartialPaymentLine($order) {
    return array(
      'itemnumber' => '',
      'product' => $this->getDiscountDescription($order, -1),
      'unitprice' => number_format(-$order->getDiscountAmount(), 4, '.', ''),
      'vatrate' => number_format(-1, 0),
      'quantity' => 1,
    );
  }

  /**
   * Add a discount line for 1 vat rate.
   *
   * @param Mage_Sales_Model_Order $order
   * @param float $discountAmount
   * @param int $vatRate
   *
   * @return array
   */
  protected function addDiscountLine($order, $discountAmount, $vatRate) {
    if ($this->productPricesIncludeTax()) {
      // Product prices incl. VAT => discount amounts are also incl. VAT: make
      // it an amount ex vat.
      $discountAmount = $discountAmount / (1 + $vatRate/100);
    }
    return array(
      'itemnumber' => '',
      'product' => $this->getDiscountDescription($order, $vatRate),
      'unitprice' => number_format($discountAmount * ($order instanceof Mage_Sales_Model_Order ? -1 : 1), 4, '.', ''),
      'vatrate' => number_format($vatRate, 0),
      'quantity' => 1,
    );
  }

  /**
   * @param Mage_Sales_Model_Order $order
   * @param int $vatRate
   *
   * @return string
   */
  protected function getDiscountDescription($order, $vatRate) {
    $description = '';
    if ($order->getDiscountDescription()) {
      $description =  $order->getDiscountDescription();
    }
    else if ($order->getCouponCode()) {
      $description =  $order->getCouponCode();
    }
    if ($vatRate == -1) {
      $description = $description ? $this->acumulusConfig->t('coupon_code') . ' ' . $description : $this->acumulusConfig->t('coupon_code');
    }
    else {
      $description = $description ? $this->acumulusConfig->t('discount_code') . ' ' . $description : $this->acumulusConfig->t('discount');
      if ($vatRate && count($this->discountTaxAmounts) > 1) {
       $description .= " ($vatRate%)";
      }
    }
    return $description;
  }

  /**
   * Adds a value only if it is not empty.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   *
   * @return bool
   *   whether the value was not empty and thus has been added.
   */
  protected function addIfNotEmpty(array &$array, $key, $value) {
    if (!empty($value)) {
      $array[$key] = $value;
      return true;
    }
    return false;
  }

  /**
   * Adds a value even if it is not set.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   * @param mixed $default
   *
   * @return bool
   *   whether the value was empty (true) or if the default was taken (false).
   */
  protected function addEmpty(array &$array, $key, $value, $default = '') {
    if (!empty($value)) {
      $array[$key] = $value;
      return true;
    }
    else {
      $array[$key] = $default;
      return false;
    }
  }

  /**
   * Gets the maximum vat rate that can be found in the given item lines.
   *
   * @param array $itemLines
   *
   * @return int
   */
  protected function getMaxVatRate(array $itemLines) {
    $maxVatRate = 0;
    foreach ($itemLines as $orderLine) {
      if (isset($orderLine['vatrate']) && $orderLine['vatrate'] > $maxVatRate) {
        $maxVatRate = $orderLine['vatrate'];
      }
    }
    return $maxVatRate;
  }

  /**
   * @return bool
   *   Whether the prices for the products are entered with or without tax.
   */
  protected function productPricesIncludeTax() {
    /** @var Mage_Tax_Model_Config $taxConfig */
    $taxConfig = Mage::getModel('tax/config');
    return $taxConfig->priceIncludesTax();
  }

  /**
   * Returns whether the margin scheme should be used for this product.
   *
   * Note: with a standard Prestashop install, the margin scheme is not
   * supported.
   *
   * param mixed $line
   *
   * @return bool
   */
  protected function useMarginScheme(/*$line*/) {
    return false;
  }

  /**
   * @param float $f1
   * @param float $f2
   * @param float $maxDiff
   *
   * @return bool
   */
  protected function floatsAreEqual($f1, $f2, $maxDiff = 0.005) {
    return abs($f2 - $f1) < $maxDiff;
  }

}
