<?php

use Siel\Acumulus\Helpers\Container;

class Siel_Acumulus_Helper_Data extends Mage_Core_Helper_Abstract {

  /** @var \Siel\Acumulus\Helpers\ContainerInterface */
  protected static $acumulusContainer = NULL;

  /**
   * Siel_Acumulus_Helper_Data constructor.
   */
  public function __construct() {
    $this->init();
  }

  /**
   * Helper method that initializes our environment:
   * - autoloader for the library part.
   * - translator
   * - acumulusConfig
   */
  protected function init() {
    if (static::$acumulusContainer === NULL) {
      // Our library structure is incompatible with autoload in Magento: we
      // register our own auto loader.
      $acumulusDir = dirname(dirname(__FILE__));
      if (!@include_once($acumulusDir . '/libraries/Siel/psr4.php')) {
        // Magento has been "compiled", use a more specific autoloader.
        require_once(dirname(__FILE__) . '/CompiledMagentoAutoLoader.php');
      }
      static::$acumulusContainer = new Container('Magento\\Magento1', Mage::app()->getLocale()->getLocaleCode());
    }
  }

  /**
   * Helper method to translate strings.
   *
   * @param string $key
   *  The key to get a translation for.
   *
   * @return string
   *   The translation for the given key or the key itself if no translation
   *   could be found.
   */
  public function t($key) {
    return static::$acumulusContainer->getTranslator()->get($key);
  }

  /**
   * Returns the configuration settings object central to this extension.
   *
   * @return \Siel\Acumulus\Helpers\ContainerInterface
   *   The Acumulus config.
   */
  public function getAcumulusContainer() {
    $this->init();
    return static::$acumulusContainer;
  }

}
