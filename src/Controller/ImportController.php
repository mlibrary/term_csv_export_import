<?php

namespace Drupal\term_csv_export_import\Controller;

use Drupal\taxonomy\Entity\Term;

class ImportController {
  protected  $data = [];
  protected  $vocabulary;

  public function __construct($data, $vocabulary) {
    $this->vocabulary = $vocabulary;
    $parts = array_map('trim', explode(';', $data));
    $keys = str_getcsv($parts[0]);
    unset($parts[0]);
    foreach ($parts as $part) {
      if (empty($part)) {
        continue;
      }
      $this->data[] = array_combine($keys, str_getcsv($part));
    }
  }

  public function execute($include_ids) {
    $processed = 0;
    foreach ($this->data as $row) {
      // Check for existence of terms.
      if ($include_ids) {
        $term_existing = Term::load($row['tid']);
      }
      else {
        $term_existing = taxonomy_term_load_multiple_by_name($row['name'], $this->vocabulary);
      }
      if ($term_existing) {
        drupal_set_message('The term '.$row['name'].' already exists. Ignoring.');
        continue;
      }
      // Set temp parent var.
      $parent_term = NULL;
      // Create the term.
      if ($include_ids) {
        // Double check for Term ID cause this could go bad.
        $db = \Drupal\Core\Database\Database::getConnection();
        $query = $db->select('taxonomy_term_data')
          ->fields('taxonomy_term_data', array('tid'))
          ->condition('taxonomy_term_data.tid', $row['tid'], '=');
        $tids = $query->execute()->fetchAll(\PDO::FETCH_OBJ);
        $query1 = $db->select('taxonomy_term_field_data')
          ->fields('taxonomy_term_field_data', array('tid'))
          ->condition('taxonomy_term_field_data.tid', $row['tid'], '=');
        $tids1 = $query1->execute()->fetchAll(\PDO::FETCH_OBJ);
        if (!empty($tids) || !empty($tids1)) {
          drupal_set_message('The Term ID already exists.', 'error');
          continue;
        }
        // TODO Inject.
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $db->insert('taxonomy_term_data')
          ->fields(array('tid' => $row['tid'], 'vid' => $this->vocabulary, 'uuid' => $row['uuid'], 'langcode' => $langcode))
          ->execute();
        $db->insert('taxonomy_term_field_data')
          ->fields(array('tid' => $row['tid'], 'vid' => $this->vocabulary, 'name' => $row['name'], 'langcode' => $langcode, 'default_langcode' => 1, 'weight' => $row['weight']))
          ->execute();
        $new_term = Term::load($row['tid']);
        if (!empty($row['parent_tid'])) {
          $parent_term = Term::load($row['parent_tid']);
        }
      }
      else {
        $new_term = Term::create(['name' => $row['name'],'vid' => $this->vocabulary]);
      }
      $new_term->setDescription($row['description'])
        ->setFormat($row['format'])
        ->setWeight($row['weight']);
      // Check for parents.
      if (!$parent_term && !empty($row['parent_name'])) {
        $parent_term = taxonomy_term_load_multiple_by_name($row['parent_name'], $this->vocabulary);
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