<?php
/**
 * @file Contains class MagentoAcumulusConfig.
 */
namespace Siel\Acumulus\Magento;

use Mage;
use Siel\Acumulus\Common\BaseConfig;
use Siel\Acumulus\Common\TranslatorInterface;

/**
 * Class MagentoAcumulusConfig
 *
 * An Magento specific implementation of the Acumulus ConfigInterface that the
 * WebAPI and the OrderAdd classes need.
 */
class MagentoAcumulusConfig extends BaseConfig {
  private $configKey = 'siel_acumulus/';

  /**
   * @param string|TranslatorInterface $language
   */
  public function __construct($language) {
    parent::__construct($language);
    $this->values = array_merge($this->values, array(
      'moduleVersion' => $version = Mage::getConfig()->getModuleConfig("Siel_Acumulus")->version,
      'shopName' => 'Magento',
      'shopVersion' => Mage::getVersion(),
      // @todo: comment out in official release.
      'debug' => true, // Uncomment to debug.
    ));
  }

  /**
   * @inheritdoc
   */
  public function load() {
    // Load the values from the web shop specific configuration.
    foreach ($this->getKeys() as $key) {
      $value = Mage::getStoreConfig($this->configKey . $key);
      // Do not overwrite defaults if no value is set.
      if ($value !== NULL) {
        $this->values[$key] = $value;
      }
    }
    // And cast them to their correct types.
    $this->castValues($this->values);
    return true;
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    foreach ($this->getKeys() as $key) {
      if (isset($values[$key]) && ($values[$key] !== '' || $key === 'emailonerror')) {
        Mage::getModel('core/config')->saveConfig($this->configKey . $key, $values[$key]);
      }
      else {
        // Do not save this value in the internal store.
        unset($values['$key']);
      }
    }
    Mage::getConfig()->reinit();
    return parent::save($values);
  }
}
