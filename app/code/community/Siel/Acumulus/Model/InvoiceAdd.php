<?php
/**
 * @file Contains class Siel\Acumulus\Magento\InvoiceAdd.
 */

use Siel\Acumulus\Common\ConfigInterface;
use Siel\Acumulus\Common\WebAPI;
use Siel\Acumulus\Magento\MagentoAcumulusConfig;

/**
 * Class InvoiceAdd defines the logic to add an invoice to Acumulus via their
 * web API.
 */
class Siel_Acumulus_Model_InvoiceAdd {

  /** @var MagentoAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /**
   * @param MagentoAcumulusConfig $config
   */
  public function __construct(MagentoAcumulusConfig $config) {
    $this->acumulusConfig = $config;
    $this->webAPI = Mage::helper('acumulus')->getWebAPI();
  }

  /**
   * Send an order to Acumulus.
   *
   * For now we don't check if the order is already sent to Acumulus (in which
   * case we might just update the payment status), we just send it.
   *
   * @param Mage_Sales_Model_Order $order
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
  public function send(Mage_Sales_Model_Order $order) {
    // Create the invoice array.
    $invoice = $this->convertOrderToAcumulusInvoice($order);

    // Send it.
    $result = $this->webAPI->invoiceAdd($invoice, $order->getRealOrderId());

    if ($result['invoice']) {
      // Attach token and invoice number to order: not yet implemented.
    }

    return $result;
  }

  /**
   * @param Mage_Sales_Model_Order $order
   *
   * @return array
   */
  protected function convertOrderToAcumulusInvoice(Mage_Sales_Model_Order $order) {
    $invoice = array();
    $invoice['customer'] = $this->addCustomer($order);
    $invoice['customer']['invoice'] = $this->addInvoice($order, $invoice['customer']);
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
  protected function addCustomer(Mage_Sales_Model_Order $order) {
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
        /** @var \Mage_Sales_Model_Order_Payment $payment */
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
        // Use price without VAT.
        $result['unitprice'] = number_format($line->getPrice(), 4, '.', '');
      }

      $result['quantity'] = number_format($line->getQtyOrdered(), 2, '.', '');
      $result['vatrate'] = number_format($line->getTaxPercent(), 0);
      $result = array($result);
    }
    else {
      // Composed products without tax or price information: add a line per child.
      foreach($line->getChildrenItems() as $child) {
        $result = array_merge($result, $this->addLineItem($child));
      }
    }
    return $result;
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
    return array(
      'itemnumber' => '',
      'product' => $this->acumulusConfig->t('shipping_costs'),
      'unitprice' => number_format($order->getShippingAmount(), 4, '.', ''),
      'vatrate' => number_format(100.0 * $order->getShippingTaxAmount() / $order->getShippingAmount(), 0),
      'quantity' => 1,
    );
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
   * Returns whether the margin scheme should be used for this product.
   *
   * Note: with a standard Prestashop install, the margin scheme is not
   * supported.
   *
   * @param \Mage_Sales_Model_Order_Item $line
   *
   * @return bool
   */
  protected function useMarginScheme(Mage_Sales_Model_Order_Item $line) {
    return false;
  }
}
