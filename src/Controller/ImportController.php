<?php

namespace Drupal\term_csv_export_import\Controller;

use Drupal\Core\Database\Database;
use Drupal\taxonomy\Entity\Term;

/**
 * Class ImportController.
 */
class ImportController {
  protected  $data = [];
  protected  $vocabulary;

  /**
   * {@inheritdoc}
   */
  public function __construct($data, $vocabulary) {
    $this->vocabulary = $vocabulary;
    $parts = array_filter(array_map('trim', preg_split('/;\r\n|;\r|;\n/', $data)));
    $keys_noid = ['name', 'description', 'format', 'weight', 'parent_name'];
    $keys_id = [
      'tid',
      'uuid',
      'name',
      'description',
      'format',
      'weight',
      'parent_name',
      'parent_tid',
    ];
    foreach ($parts as $part) {
      $array = str_getcsv($part);
      $keys = [];
      if (count($array) == 8) {
        $keys = $keys_id;
      }
      elseif (count($array) == 5) {
        $keys = $keys_noid;
      }
      else {
        drupal_set_message('Line with "' . $part . '" could not be parsed. Incorrect number of values.', 'error');
        continue;
      }
      $this->data[] = array_combine($keys, $array);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $processed = 0;
    foreach ($this->data as $row) {
      // Check for existence of terms.
      if (isset($row['tid'])) {
        $term_existing = Term::load($row['tid']);
      }
      else {
        $term_existing = taxonomy_term_load_multiple_by_name($row['name'], $this->vocabulary);
      }
      if ($term_existing) {
        drupal_set_message('The term ' . $row['name'] . ' with id ' . $row['tid'] . ' already exists. Ignoring.');
        continue;
      }
      // Set temp parent var.
      $parent_term = NULL;
      // Create the term.
      if (isset($row['tid'])) {
        // Double check for Term ID cause this could go bad.
        $db = Database::getConnection();
        $query = $db->select('taxonomy_term_data')
          ->fields('taxonomy_term_data', ['tid'])
          ->condition('taxonomy_term_data.tid', $row['tid'], '=');
        $tids = $query->execute()->fetchAll(\PDO::FETCH_OBJ);
        $query1 = $db->select('taxonomy_term_field_data')
          ->fields('taxonomy_term_field_data', ['tid'])
          ->condition('taxonomy_term_field_data.tid', $row['tid'], '=');
        $tids1 = $query1->execute()->fetchAll(\PDO::FETCH_OBJ);
        if (!empty($tids) || !empty($tids1)) {
          drupal_set_message('The Term ID already exists.', 'error');
          continue;
        }
        // TODO Inject.
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $db->insert('taxonomy_term_data')
          ->fields([
            'tid' => $row['tid'],
            'vid' => $this->vocabulary,
            'uuid' => $row['uuid'],
            'langcode' => $langcode,
          ])
          ->execute();
        $db->insert('taxonomy_term_field_data')
          ->fields([
            'tid' => $row['tid'],
            'vid' => $this->vocabulary,
            'name' => $row['name'],
            'langcode' => $langcode,
            'default_langcode' => 1,
            'weight' => $row['weight'],
          ])
          ->execute();
        $new_term = Term::load($row['tid']);
        if (!empty($row['parent_tid'])) {
          $parent_term = Term::load($row['parent_tid']);
        }
      }
      else {
        $new_term = Term::create(['name' => $row['name'], 'vid' => $this->vocabulary]);
      }
      $new_term->setDescription($row['description'])
        ->setFormat($row['format'])
        ->setWeight($row['weight']);
      // Check for parents.
      if ($parent_term == NULL && !empty($row['parent_name'])) {
        $parent_term = taxonomy_term_load_multiple_by_name($row['parent_name'], $this->vocabulary);
        if (count($parent_term) > 1) {
          unset($parent_term);
          drupal_set_message('More than 1 terms are named ' . $row['parent_name'] . '. Cannot distinguish by name. Try using id export/import.', 'error');
        }
        else {
          $parent_term = array_values($parent_term)[0];
        }
      }
      $parent_term_id = 0;
      if ($parent_term) {
        $parent_term_id = $parent_term->id();
      }
      $new_term->set('parent', ['target_id' => $parent_term_id]);
      $new_term->save();
      $processed++;
    }
    drupal_set_message(t('Imported @count terms.', ['@count' => $processed]));
  }

}
