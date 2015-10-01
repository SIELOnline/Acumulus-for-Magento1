<?php
/**
 * @file This file registers an autoloader for the Siel namespace library when
 *   Magento has been compiled.
 */
namespace Siel;

// Prepend this autoloader: it will not throw, nor warn, while the shop specific
// autoloader will do so.
spl_autoload_register(function($class) {
    if (strpos($class, __NAMESPACE__ . '\\') === 0) {
      $fileName = __DIR__ . DIRECTORY_SEPARATOR . 'Siel_Acumulus_libraries_' . str_replace('\\', '_', $class) . '.php';
      /** @noinspection PhpIncludeInspection */
      include($fileName);
    }
  }, FALSE, TRUE);
