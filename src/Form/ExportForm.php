<?php

namespace Drupal\term_csv_export_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\term_csv_export_import\Controller\ExportController;

/**
 * Class ExportForm.
 *
 * @package Drupal\term_csv_export_import\Form
 */
class ExportForm extends FormBase {
  /**
   * Set a var to make stepthrough form.
   *
   * @var step
   */
  protected $step = 1;

  /**
   * Set a var for export values.
   *
   * @var get_export
   */
  protected $get_export = '';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    switch ($this->step) {
      case 1:
        $form['vocabulary'] = array(
          '#type' => 'select',
          '#title' => $this->t('Taxonomy'),
          '#options' => taxonomy_vocabulary_get_names(),
        );
        $form['include_ids'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Include Term Ids in export.'),
        ];
        $form['submit'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Export'),

        );
        break;

      case 2:
        $form['input'] = array(
          '#type' => 'textarea',
          '#title' => $this->t('CSV Data'),
          '#description' => $this->t('The formatted term data'),
          '#value' => $this->get_export,
        );
        break;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->step++;
    $export = new ExportController(
      $form_state->getValue('vocabulary')
    );
    $this->get_export = $export->execute($form['include_ids']['#value']);
    $form_state->setRebuild();
  }

}
