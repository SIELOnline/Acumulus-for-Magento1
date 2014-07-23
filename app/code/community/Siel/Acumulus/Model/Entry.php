<?php

/**
 * @class Siel_Acumulus_Model_Entry Acumulus entry model class.
 *
 * @method Siel_Acumulus_Model_Entry setEntryId(int $value)
 * @method int getEntryId()
 * @method Siel_Acumulus_Model_Entry setToken(string $value)
 * @method string getToken()
 * @method Siel_Acumulus_Model_Entry setOrderId(int $value)
 * @method int getOrderId()
 * @method Siel_Acumulus_Model_Entry setInvoiceId(int $value)
 * @method int|null getInvoiceId()
 * @method Siel_Acumulus_Model_Entry setCreditMemoId(int $value)
 * @method int|null getCreditMemoId()
 */
class Siel_Acumulus_Model_Entry extends Mage_Core_Model_Abstract {

  protected function _construct() {
    $this->_init('acumulus/entry');
  }

  /**
   * Creates a model for the given order or credit memo. This may be an existing
   * model that will be updated or a new model.
   *
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return Siel_Acumulus_Model_Entry
   */
  public function getByOrder($order) {
    if ($order instanceof Mage_Sales_Model_Order) {
      /** @var Siel_Acumulus_Model_Resource_Entry_Collection $collection */
      $collection = $this->getCollection();
      $result = $collection
        ->addFieldToFilter('order_id', $this->getOrder($order)->getId())
        ->addFieldToFilter('creditmemo_id', array('null' => true))
        ->getFirstItem();
    }
    else {
      $result = $this->load($this->getCreditMemo($order)->getId(), 'creditmemo_id');
    }
    return $result;
  }

  /**
   * Creates or updates a record that links the order (or credit memo) to the
   * received Acumulus identifiers.
   *
   * When adding an invoice to Acumulus we receive from Acumulus (a.o.):
   * - entry_id (boekstuknummer)
   * - token
   *  We link this to the orders (first) invoice or to the credit memo. If we
   *  already have an Acumulus entry_id and token for the given order or credit
   *  memo, we update that record, assuming that the previous Acumulus entry now
   *  has been deleted.
   *
   * @param array $acumulusInvoice
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return $this
   */
  public function saveEntry(array $acumulusInvoice, $order) {
    $this->setEntryId($acumulusInvoice['entryid'])
      ->setToken($acumulusInvoice['token'])
      ->setOrderId($this->getOrder($order)->getId())
      ->setInvoiceId($this->getInvoice($order)->getId())
      ->setCreditmemoId($this->getCreditMemo($order)->getId())
      ->unsetData('created')
      ->unsetData('updated');
    return $this->save();
  }

  /**
   * Overrides the save() method to clear the created and updated columns,
   * before being written to the database. These timestamps are set by the
   * database ands should not be set by the application.
   *
   * @return $this
   */
  public function save() {
    $this
      ->unsetData('created')
      ->unsetData('updated');
    return parent::save();
  }

  /**
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return Mage_Sales_Model_Order
   */
  protected function getOrder($order) {
    return $order instanceof Mage_Sales_Model_Order ? $order : $order->getOrder();
  }

  /**
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return Mage_Sales_Model_Order_Invoice
   */
  protected function getInvoice($order) {
    return $order instanceof Mage_Sales_Model_Order && $order->hasInvoices() ? $order->getInvoiceCollection()->getFirstItem() : Mage::getModel('sales/order_invoice');
  }

  /**
   * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order
   *
   * @return Mage_Sales_Model_Order_Creditmemo
   */
  protected function getCreditMemo($order) {
    return $order instanceof Mage_Sales_Model_Order ? Mage::getModel('sales/order_creditmemo') : $order;
  }

}
