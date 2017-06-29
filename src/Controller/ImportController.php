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
    $temp = fopen('php://memory', 'rw');
    fwrite($temp, $data);
    rewind($temp);
    $csvArray = [];
    while (!feof($temp)) {
      if ($csvRow = fgetcsv($temp)) {
        $csvArray[] = $csvRow;
      }
    }
    fclose($temp);
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

    if ($csvArray[0] == $keys_noid || $csvArray[0] == $keys_id) {
      drupal_set_message(t('The header keys were not included in the import.'), 'warning');
      unset($csvArray[0]);
    }
    foreach ($csvArray as $csvLine) {
      $keys = [];
      $num_of_lines = count($csvLine);
      if (in_array($num_of_lines, [8, 9])) {
        $keys = $keys_id;
      }
      elseif (in_array($num_of_lines, [5, 6])) {
        $keys = $keys_noid;
      }
      else {
        drupal_set_message(t('Line with "@part" could not be parsed. Incorrect number of values: @count.',
          [
            '@part' => implode(',', $csvLine),
            '@count' => count($csvLine),
          ]), 'error');
        continue;
      }
      if (in_array($num_of_lines, [6, 9])) {
        $keys[] = 'fields';
      }
      $this->data[] = array_combine($keys, $csvLine);
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
        drupal_set_message(t('The term @name with id @tid already exists. Ignoring.', ['@name' => $row['name'], '@tid' => $row['tid']]));
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
          drupal_set_message(t('The Term ID already exists.'), 'error');
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
          drupal_set_message(t('More than 1 terms are named @name. Cannot distinguish by name. Try using id export/import.', ['@name' => $row['parent_name']]), 'error');
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

      // Import all other non-default taxonomy fields if the row is there.
      if (isset($row['fields']) && !empty($row['fields'])) {
        parse_str($row['fields'], $field_array);
        if (!is_array($field_array)) {
          drupal_set_message(t('The field data <em>@data</em> is not formatted correctly. Please use the export function.', ['@data' => $row['fields']]), 'error');
          continue;
        }
        else {
          foreach ($field_array as $field_name => $field_values) {
            if ($new_term->hasField($field_name)) {
              $new_term->set($field_name, $field_values);
            }
            else {
              drupal_set_message(t('The field data <em>@data</em> could not be imported. Please add the appropriate fields to the vocabulary you are importing into.', ['@data' => $row['fields']]), 'warning');
            }
          }
        }
      }

      $new_term->save();
      $processed++;
    }
    drupal_set_message(t('Imported @count terms.', ['@count' => $processed]));
  }

}
