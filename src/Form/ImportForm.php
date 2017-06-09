<?php

namespace Drupal\term_csv_export_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\term_csv_export_import\Controller\ImportController;

/**
 * Class ImportForm.
 *
 * @package Drupal\term_csv_export_import\Form
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'default_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['input'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Input'),
      '#description' => $this->t('Enter in the form of: <pre>"name,description,format,weight,parent_name;"</pre> or <pre>"tid,name,description,format,weight,parent_name,parent_tid;"</pre> depending on checkbox. See CSV Export for example.'),
    ];
    $form['include_ids'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Term Ids in import.'),
    ];
    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxonomy'),
      '#options' => taxonomy_vocabulary_get_names(),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),

    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $import = new ImportController(
      $form_state->getValue('input'),
      $form_state->getValue('vocabulary')
    );
    $import->execute($form['include_ids']['#value']);
  }

}
