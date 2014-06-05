<?php

use Siel\Acumulus\Common\ConfigInterface;

class Siel_Acumulus_Block_Adminhtml_Settings_Form extends Mage_Adminhtml_Block_Widget_Form {
  /** @var bool */
  private $initialized = FALSE;

  /** @var \Siel\Acumulus\Magento\MagentoAcumulusConfig */
  private $acumulusConfig;

  /** @var \Siel\Acumulus\Common\WebAPI */
  private $webAPI;

  /** @var array contact type picklist */
  private $connectionTestResult;

  private function t($key) {
    return Mage::helper('acumulus')->t($key);
  }

  private function getNote($key) {
    return '<p class="note">' . $this->t($key) . '</p>';
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
      $this->acumulusConfig = Mage::helper('acumulus')->getAcumulusConfig();
      $this->webAPI = Mage::helper('acumulus')->getWebAPI();
      $this->initialized = TRUE;
    }
  }

  protected function _prepareForm() {
    $this->init();

    $form = new Varien_Data_Form();

    $fieldset = $form->addFieldset('account_fieldset', array('legend' => $this->t('accountSettingsHeader')));
    $fieldset->addField('contractcode', 'text', array(
        'name' => 'contractcode',
        'label' => $this->t('field_code'),
        'title' => $this->t('field_code'),
        'class' => 'required-entry',
        'required' => TRUE,
      )
    );
    $fieldset->addField('username', 'text', array(
        'name' => 'username',
        'label' => $this->t('field_username'),
        'title' => $this->t('field_username'),
        'class' => 'required-entry',
        'required' => TRUE,
      )
    );
    $fieldset->addField('password', 'password', array(
        'name' => 'password',
        'label' => $this->t('field_password'),
        'title' => $this->t('field_password'),
        'class' => 'required-entry',
        'required' => TRUE,
      )
    );
    $fieldset->addField('emailonerror', 'text', array(
        'name' => 'emailonerror',
        'label' => $this->t('field_email'),
        'title' => $this->t('field_email'),
        'after_element_html' => $this->getNote('desc_email'),
        'class' => 'required-entry',
        'required' => TRUE,
      )
    );

    // 2nd fieldset: invoice settings
    $fieldset = $form->addFieldset('invoice_fieldset', array('legend' => $this->t('invoiceSettingsHeader')));
    // Check if we can retrieve a picklist. This indicates if the account
    if (!$this->checkAccountSettings()) {
      // Account details incomplete or incorrect: show message.
      $fieldset->addField('note2a', 'note', array(
        'text' => $this->connectionTestResult,
      ));
    }
    else {
      $fieldset->addField('note2b', 'note', array(
        'text' => '<style> input[type=radio] { float: left; clear: both; margin-top: 0.2em;} .value label.inline {float: left !important; max-width: 95%; padding-left: 1em;} .note {clear: both;}</style>',
      ));

      // Show invoice settings.
      $options = array(
        array(
          'value' => ConfigInterface::InvoiceNrSource_ShopInvoice,
          'label' => $this->t('option_invoiceNrSource_1')
        ),
        array(
          'value' => ConfigInterface::InvoiceNrSource_ShopOrder,
          'label' => $this->t('option_invoiceNrSource_2')
        ),
        array(
          'value' => ConfigInterface::InvoiceNrSource_Acumulus,
          'label' => $this->t('option_invoiceNrSource_3'),
        ),
      );
      $fieldset->addField('invoiceNrSource', 'radios', array(
        'label' => $this->t('field_invoiceNrSource'),
        'name' => 'invoiceNrSource',
        'values' => $options,
        'after_element_html' => $this->getNote('desc_invoiceNrSource'),
        'class' => 'validate-one-required-by-name',
      ));

      $options = array(
        array(
          'value' => ConfigInterface::InvoiceDate_InvoiceCreate,
          'label' => $this->t('option_dateToUse_1'),
        ),
        array(
          'value' => ConfigInterface::InvoiceDate_OrderCreate,
          'label' => $this->t('option_dateToUse_2')
        ),
        array(
          'value' => ConfigInterface::InvoiceDate_Transfer,
          'label' => $this->t('option_dateToUse_3')
        ),
      );
      $fieldset->addField('dateToUse', 'radios', array(
        'label' => $this->t('field_dateToUse'),
        'name' => 'dateToUse',
        'values' => $options,
        'after_element_html' => $this->getNote('desc_dateToUse'),
        'class' => 'validate-one-required-by-name',
      ));

      $fieldset->addField('clientData', 'checkboxes', array(
        'label' => $this->t('field_clientData'),
        'name' => 'clientData[]',
        'values' => array(
          array(
            'value' => 'sendCustomer',
            'label' => $this->t('option_sendCustomer'),
          ),
          array(
            'value' => 'overwriteIfExists',
            'label' => $this->t('option_overwriteIfExists'),
          ),
        ),
        'after_element_html' => $this->getNote('desc_clientData'),
      ));

//      $fieldset->addField('genericCustomerEmail', 'text', array(
//          'name' => 'genericCustomerEmail',
//          'label' => $this->t('field_genericCustomerEmail'),
//          'title' => $this->t('field_genericCustomerEmail'),
//          'after_element_html' => $this->getNote('desc_genericCustomerEmail'),
//          'required' => FALSE,
//        )
//      );

//      $fieldset->addField('overwriteIfExists', 'checkboxes', array(
//        'label' => $this->t('field_overwriteIfExists'),
//        'name' => 'overwriteIfExists',
//        'values' => array(
//        ),
//        'after_element_html' => $this->getNote('desc_overwriteIfExists'),
//      ));

      $options = $this->picklistToOptions($this->connectionTestResult['contacttypes']);
      $fieldset->addField('defaultCustomerType', 'select', array(
        'label' => $this->t('field_defaultCustomerType'),
        'name' => 'defaultCustomerType',
        'values' => $options,
        'required' => FALSE,
      ));

      $options = $this->webAPI->getPicklistAccounts();
      $options = $this->picklistToOptions($options['accounts']);
      $fieldset->addField('defaultAccountNumber', 'select', array(
        'label' => $this->t('field_defaultAccountNumber'),
        'name' => 'defaultAccountNumber',
        'values' => $options,
        'after_element_html' => $this->getNote('desc_defaultAccountNumber'),
        'required' => FALSE,
      ));

      $options = $this->webAPI->getPicklistCostCenters();
      $options = $this->picklistToOptions($options['costcenters']);
      $fieldset->addField('defaultCostCenter', 'select', array(
        'label' => $this->t('field_defaultCostCenter'),
        'name' => 'defaultCostCenter',
        'values' => $options,
        'after_element_html' => $this->getNote('desc_defaultCostCenter'),
        'required' => FALSE,
      ));

      $options = $this->webAPI->getPicklistInvoiceTemplates();
      $options = $this->picklistToOptions($options['invoicetemplates']);
      $fieldset->addField('defaultInvoiceTemplate', 'select', array(
        'label' => $this->t('field_defaultInvoiceTemplate'),
        'name' => 'defaultInvoiceTemplate',
        'values' => $options,
        'after_element_html' => $this->getNote('desc_defaultInvoiceTemplate'),
        'required' => FALSE,
      ));

      $options = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
      $options = $this->picklistToOptions($options);
      $options[0]['label'] = $this->t('option_empty_triggerOrderStatus');
      $fieldset->addField('triggerOrderStatus', 'select', array(
        'label' => $this->t('field_triggerOrderStatus'),
        'name' => 'triggerOrderStatus',
        'values' => $options,
        'after_element_html' => $this->getNote('desc_triggerOrderStatus'),
        'required' => FALSE,
      ));
    }

    // 3rd fieldset: version information.
    $env = $this->acumulusConfig->getEnvironment();
    $fieldset = $form->addFieldset('versioninfo_fieldset', array('legend' => $this->t('versionInformationHeader')));
    $fieldset->addField('note3', 'note', array(
      'text' => "Acumulus module {$env['moduleVersion']} (API: {$env['libraryVersion']}) voor {$env['shopName']} {$env['shopVersion']}",
      'after_element_html' => $this->getNote('desc_versionInformation'),
    ));

    $post = $this->getRequest()->getPost();
    unset($post['form_key']);

    $values = $post + $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getInvoiceSettings();
    $values['clientData'] = array();
    if (!empty($values['sendCustomer'])) {
      $values['clientData'][] = 'sendCustomer';
    }
    if (!empty($values['overwriteIfExists'])) {
      $values['clientData'][] = 'overwriteIfExists';
    }
    $form->setValues($values);


    $form->setAction($this->getUrl('*/*/settings'));
    $form->setMethod('post');
    $form->setUseContainer(true);
    $form->setId('edit_form');
    $this->setForm($form);

    return parent::_prepareForm();
  }

  /**
   * Check if we can retrieve a picklist. This indicates if the account
   * settings are known and correct.
   *
   * The picklist will be stored for later use.
   *
   * @return string
   *   A user readable message indicating if the account settings needs yet to
   *   be filled in or were incorrect. The empty string, if a successful
   *   connection was made.
   */
  private function checkAccountSettings() {
    // Check if we can retrieve a picklist. Thi indicates if the account
    // settings are known and correct.
    if ($this->acumulusConfig->get('password')) {
      $this->connectionTestResult = Mage::helper('acumulus')->checkAccountSettings();
    }
    else {
      $this->connectionTestResult = $this->t('message_auth_unknown');
    }
    return is_array($this->connectionTestResult);
  }

  private function picklistToOptions($picklist) {
    $result = array(array(
      'value' => 0,
      'label' => $this->t('option_empty'),
    ));
    foreach ($picklist as $item) {
      $result[] = array(
        'value' => reset($item),
        'label' => next($item),
      );
    }
    return $result;
  }
}
