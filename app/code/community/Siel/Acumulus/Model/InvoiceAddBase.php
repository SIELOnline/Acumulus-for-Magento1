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
class Siel_Acumulus_Model_InvoiceAddBase {

  /** @var MagentoAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

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
  public function send(Mage_Sales_Model_Abstract $order) {
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
  protected function convertOrderToAcumulusInvoice(Mage_Sales_Model_Abstract $order) {
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
   * @param Mage_Sales_Model_Abstract $model
   * @param array $customer
   *
   * @return array
   */
  protected function addInvoice(Mage_Sales_Model_Abstract $model, array $customer) { }

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

}
