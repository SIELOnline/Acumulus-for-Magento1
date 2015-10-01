<?php
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\Magento\Shop\BatchForm;
use Siel\Acumulus\Magento\Shop\ConfigForm;
use Siel\Acumulus\Magento\Shop\ConfigStore;
use Siel\Acumulus\Magento\Shop\InvoiceManager;
use Siel\Acumulus\Magento\Helpers\Log;

class Siel_Acumulus_Helper_Data extends Mage_Core_Helper_Abstract {

  /** @var bool */
  protected $initialized = FALSE;

  /** @var \Siel\Acumulus\Helpers\Translator */
  protected $translator;

  /** @var \Siel\Acumulus\Shop\Config */
  protected $acumulusConfig;

  /** @var \Siel\Acumulus\Helpers\Form[]  */
  protected $form;

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
    if (!$this->initialized) {
      // Our library structure is incompatible with autoload in Magento: we
      // register our own auto loader.
      $acumulusDir = dirname(dirname(__FILE__));
      if (!@include_once($acumulusDir . '/libraries/Siel/psr4.php')) {
        // Magento has been "compiled", use a more specific autoloader.
        require_once(dirname(__FILE__) . '/CompiledMagentoAutoLoader.php');
      }

      Log::createInstance();
      $languageCode = Mage::app()->getLocale()->getLocaleCode();
      $this->translator = new Translator($languageCode);
      $this->acumulusConfig = new Config(new ConfigStore(), $this->translator);

      $this->initialized = TRUE;
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
    return $this->translator->get($key);
  }

  /**
   * Returns the configuration settings object central to this extension.
   *
   * @return \Siel\Acumulus\Shop\Config
   *   The Acumulus config.
   */
  public function getAcumulusConfig() {
    $this->init();
    return $this->acumulusConfig;
  }

  /**
   * @param string $formType
   *
   * @return \Siel\Acumulus\Helpers\Form
   */
  public function getForm($formType) {
    // Get the form.
    if (!isset($this->form[$formType])) {
      switch ($formType) {
        case 'batch':
          $invoiceManager = new InvoiceManager($this->acumulusConfig, $this->translator);
          $this->form[$formType] = new BatchForm($this->translator, $invoiceManager);
          break;
        case 'config':
          $this->form[$formType] = new ConfigForm($this->translator, $this->acumulusConfig);
          break;
      }
    }
    return $this->form[$formType];
  }

}
