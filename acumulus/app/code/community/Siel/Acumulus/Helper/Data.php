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
      $srcDir = __DIR__ . '/../lib/siel/acumulus/src/';
      if (!is_dir($srcDir)) {
        // Magento has been "compiled", use a more specific autoloader.
        $srcDir = __DIR__ . DIRECTORY_SEPARATOR . 'Siel_Acumulus_lib_siel_acumulus_src' . DIRECTORY_SEPARATOR;
      }
      $this->registerSielAutoloader($srcDir);
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

  /**
   * Registers an autoloader for the Siel\Acumulus namespace library.
   *
   * As not all web shops support auto-loading based on namespaces or have
   * other glitches, eg. expecting lower cased file names, we define our own
   * autoloader. If the module cannot use the autoloader of the web shop, this
   * function should be loaded during bootstrapping of the module.
   *
   * Thanks to https://gist.github.com/mageekguy/8300961
   *
   * @param string $dir
   *   The PSR4 root directory where the classes from the Siel\Acumulus
   *   namespace can be found. Should include a directory separator at the
   *   end.
   */
  private function registerSielAutoloader($dir) {
    $our_namespace = 'Siel\\Acumulus\\';
    $ourNamespaceLen = strlen($our_namespace);
    spl_autoload_register(
      function ($class) use ($our_namespace, $ourNamespaceLen, $dir) {
        if (strncmp($class, $our_namespace, $ourNamespaceLen) === 0) {
          $fileName = $dir . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $ourNamespaceLen)) . '.php';
          // Checking if the file exists prevent warnings in OpenCart1 where
          // using just @include(...) did not help prevent them.
          if (is_readable($fileName)) {
            /** @noinspection PhpIncludeInspection */
            include($fileName);
          }
        }
      },
      // Do not throw an exception, we only load our own classes, so other
      // autoloaders may be registered as well.
      false,
      // Prepend this autoloader: it will not throw, nor warn, while the shop
      // specific autoloader might do so.
      true);
  }

}
