<?php

use Siel\Acumulus\Shop\ConfigInterface;

class Siel_Acumulus_Block_Adminhtml_Batch_Form extends Mage_Adminhtml_Block_Widget_Form {

  private function t($key) {
    return Mage::helper('acumulus')->t($key);
  }

  private function getNote($key) {
    return '<p class="note">' . $this->t($key) . '</p>';
  }

  protected function _prepareForm() {
    $form = new Varien_Data_Form();

    $fieldset = $form->addFieldset('manual_fieldset', array('legend' => $this->t('manualSelectIdHeader')));
    $fieldset->addField('manual_order', 'text', array(
        'name' => 'manual_order',
        'label' => $this->t('field_manual_order'),
        'title' => $this->t('field_manual_order'),
        'required' => FALSE,
      )
    );
    $fieldset->addField('manual_invoice', 'text', array(
        'name' => 'manual_invoice',
        'label' => $this->t('field_manual_invoice'),
        'title' => $this->t('field_manaul_invoice'),
        'required' => FALSE,
      )
    );
    $fieldset->addField('manual_creditmemo', 'text', array(
        'name' => 'manual_creditmemo',
        'label' => $this->t('field_manual_creditmemo'),
        'title' => $this->t('field_manual_creditmemo'),
        'required' => FALSE,
      )
    );
    $fieldset->addField('note1', 'note', array(
      'text' => $this->getNote('manual_form_desc'),
    ));


    $form->setAction($this->getUrl('*/*/manual'));
    $form->setMethod('post');
    $form->setUseContainer(true);
    $form->setId('edit_form');
    $this->setForm($form);

    return parent::_prepareForm();
  }

}
