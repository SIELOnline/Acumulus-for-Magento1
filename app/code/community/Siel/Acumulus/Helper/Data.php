<?php
use Siel\Acumulus\Common\WebAPI;
use Siel\Acumulus\Magento\MagentoAcumulusConfig;

class Siel_Acumulus_Helper_Data extends Mage_Core_Helper_Abstract {
  /** @var bool */
  private $initialized = FALSE;

  /** @var \Siel\Acumulus\Magento\MagentoAcumulusConfig */
  private $acumulusConfig;

  /** @var array|string */
  private $connectionTestResult = NULL;

  /** @var string */
  private $connectionTestDetail = '';

  /**
   * @return string
   */
  public function getConnectionTestDetail() {
    return $this->connectionTestDetail;
  }

  public function t($key) {
    $this->init();
    return $this->acumulusConfig->t($key);
  }

  public function getAcumulusConfig() {
    $this->init();
    return $this->acumulusConfig;
  }

  public function getWebAPI() {
    $this->init();
    return new WebAPI($this->acumulusConfig);
  }

  /**
   * Check if we can retrieve a picklist. This indicates if the account
   * settings are known and correct.
   *
   * The picklist will be returned for possible later use.
   *
   * @return array|string
   *   On success the contact types picklist (array), otherwise a user readable
   *   message indicating that the account settings were incorrect or that no
   *   connection could be made.
   */
  public function checkAccountSettings() {
    // Check if we can retrieve a picklist. This indicates if the account
    // settings are known and correct.
    if ($this->connectionTestResult === NULL) {
      $this->connectionTestResult = $this->getWebAPI()->getPicklistContactTypes();
      if (!empty($this->connectionTestResult['errors'])) {
        if ($this->connectionTestResult['errors'][0]['code'] == 401) {
          $this->connectionTestResult = $this->t('message_error_auth');
        }
        else {
          $this->connectionTestDetail = $this->getWebAPI()->resultToMessages($this->connectionTestResult);
          $this->connectionTestDetail = $this->getWebAPI()->messagesToHtml($this->connectionTestDetail);
          $this->connectionTestResult = $this->t('message_error_comm');
        }
      }
    }
    return $this->connectionTestResult;
  }


  /**
   * Helper method that initializes some object properties:
   * - language
   * - model_Setting_Setting
   * - webAPI
   * - acumulusConfig
   */
  private function init() {
    if (!$this->initialized) {
      // Our lib structure is incompatible with autoload in Magento: load manually.
      $acumulusDir = dirname(dirname(__FILE__)) . '/';
      require_once($acumulusDir . 'Common/TranslatorInterface.php');
      require_once($acumulusDir . 'Common/BaseTranslator.php');
      require_once($acumulusDir . 'Common/ConfigInterface.php');
      require_once($acumulusDir . 'Common/BaseConfig.php');
      require_once($acumulusDir . 'Common/WebAPICommunication.php');
      require_once($acumulusDir . 'Common/WebAPI.php');
      require_once($acumulusDir . 'Model/MagentoAcumulusConfig.php');

      // Load the Acumulus settings and WebAPI objects.
      $this->acumulusConfig = new MagentoAcumulusConfig(Mage::app()->getLocale()->getLocaleCode());

      $this->initialized = TRUE;
    }
  }
}
