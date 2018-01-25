<?php

use Siel\Acumulus\Helpers\Container;

class Siel_Acumulus_Helper_Data extends Mage_Core_Helper_Abstract {

  /**
   * The directory where the library can be found in non-compiled mode.
   *
   * @var string
   */
  protected $baseDir;

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
      $this->baseDir = MAGENTO_ROOT . '/app/code/community/Siel/Acumulus/lib/siel/acumulus/src';
      // Our library structure is incompatible with autoload in Magento: we
      // register our own auto loader.
      $this->registerSielAutoloader();
      static::$acumulusContainer = new Container('Magento\\Magento1', Mage::app()->getLocale()->getLocaleCode());
      if ($this->isCompiled()) {
          static::$acumulusContainer->setBaseDir($this->baseDir);
      }
    }
  }

  /**
   * Returns whether Magento runs in compiled mode.
   *
   * @return bool
   *   True if Magento runs in compiled mode, false otherwise.
   */
  protected function isCompiled() {
      return defined('COMPILER_INCLUDE_PATH');
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

  /**
   * Registers an autoloader for the Siel\Acumulus namespace library.
   *
   * As not all web shops support auto-loading based on namespaces or have
   * other glitches, eg. expecting lower cased file names, we define our own
   * autoloader. If the module cannot use the autoloader of the web shop, this
   * function should be loaded during bootstrapping of the module.
   *
   * Thanks to https://gist.github.com/mageekguy/8300961
   */
  private function registerSielAutoloader() {
    if ($this->isCompiled()) {
      // Magento has been "compiled" to the includes/src directory.
      $filePrefix = COMPILER_INCLUDE_PATH . DIRECTORY_SEPARATOR . 'Siel_Acumulus_lib_siel_acumulus_src_';
      $ourNamespace = 'Siel\\Acumulus\\';
      $ourNamespaceLen = strlen($ourNamespace);
      $autoloadFunction = function ($class) use ($ourNamespace, $ourNamespaceLen, $filePrefix) {
        if (strncmp($class, $ourNamespace, $ourNamespaceLen) === 0) {
          $fileName = $filePrefix . str_replace('\\', '_', substr($class, $ourNamespaceLen)) . '.php';
          if (is_readable($fileName)) {
            /** @noinspection PhpIncludeInspection */
            include($fileName);
          }
        }
      };
      // Prepend this autoloader: it will not throw, nor warn, while the shop
      // specific autoloader might do so.
      spl_autoload_register($autoloadFunction, true, true);
    }
    else {
      // Magento has not been compiled: classes can be found in their default
      // place.
      /** @noinspection PhpIncludeInspection */
      require_once($this->baseDir . '/../SielAcumulusAutoloader.php');
      SielAcumulusAutoloader::register();
    }
  }

}
