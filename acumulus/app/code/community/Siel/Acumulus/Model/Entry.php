<?php /** @noinspection PhpLanguageLevelInspection */

/**
 * Class Siel_Acumulus_Model_Entry Acumulus is used by
 * Siel\Acumulus\Shop\Magento\AcumulusEntryModel to access the entry table from
 * the database.
 *
 * @method $this setEntryId(int $value)
 * @method int getEntryId()
 * @method $this setToken(string $value)
 * @method string getToken()
 * @method $this setSourceType(string $value)
 * @method string getSourceType()
 * @method $this setSourceId(int $value)
 * @method int getSourceId()
 * @method $this setCreated(int $value)
 * @method int getCreated()
 * @method $this setUpdated(int $value)
 * @method int getUpdated()
 */
class Siel_Acumulus_Model_Entry extends Mage_Core_Model_Abstract {

  /**
   * Magento "internal constructor" not receiving any parameters.
   */
  protected function _construct() {
    $this->_init('acumulus/entry');
  }

  /**
   * Overrides the save() method to clear the created column and set the updated
   * column before being written to the database. The created timestamp is set
   * by the database and should not be set by the application. As MySQl < 5.6.5
   * only allows one timestamp with a default value, we do set the updated
   * timestamp in code (http://stackoverflow.com/a/17498167/1475662).
   *
   * @return $this
   */
  public function save() {
    $this
      ->setUpdated(Mage::app()->getLocale()->storeTimeStamp())
      ->unsetData('created');
    return parent::save();
  }

}
