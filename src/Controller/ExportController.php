<?php

namespace Drupal\term_csv_export_import\Controller;

use Drupal\taxonomy\Entity\Term;

/**
 * Class ExportController.
 */
class ExportController {
  protected  $vocabulary;
  public $export;

  /**
   * {@inheritdoc}
   */
  public function __construct($vocabulary) {
    $this->vocabulary = $vocabulary;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($include_ids, $include_headers, $include_fields) {
    // TODO Inject.
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $this->vocabulary);
    $tids = $query->execute();
    $terms = Term::loadMultiple($tids);
    $fp = fopen('php://memory', 'rw');
    $standardTaxonomyFields = [
      'tid',
      'uuid',
      'langcode',
      'vid',
      'name',
      'description',
      'format',
      'weight',
      'parent_name',
      'parent',
      'changed',
      'default_langcode',
      'path',
    ];
    $to_export = [];

    if ($include_headers) {
      $to_export = ['name', 'description', 'format', 'weight', 'parent_name'];
      if ($include_ids) {
        $to_export = array_merge(['tid', 'uuid'], $to_export);
        $to_export[] = 'parent_tid';
      }
      if ($include_fields) {
        $to_export[] = 'fields';
      }
    }
    fputcsv($fp, $to_export);
    foreach ($terms as $term) {
      // TODO - Inject.
      $parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($term->id());
      $parent_names = '';
      $parent_ids = '';
      $to_export = [];
      if (!empty($parents)) {
        if (count($parents > 1)) {
          foreach ($parents as $parent) {
            $parent_names .= $parent->getName().';';
            $parent_ids .= $parent->id().';';
          }
        }
        else {
          $parent_names = $parents[0]->getName();
          $parent_ids = $parents[0]->id();
        }
      }
      $to_export = [
        $term->getName(),
        $term->getDescription(),
        $term->getFormat(),
        $term->getWeight(),
        $parent_names,
      ];
      if ($include_ids) {
        $to_export = array_merge([$term->id(), $term->uuid()], $to_export);
        $to_export[] = $parent_ids;
      }
      if ($include_fields) {
        $field_export = [];
        foreach ($term->getFields() as $field) {
          if (!in_array($field->getName(), $standardTaxonomyFields)) {
            foreach ($field->getValue() as $values) {
              foreach ($values as $value) {
                // Skip type ($key) here. More complicated, seems unnecessary.
                $field_export[$field->getName()][] = $value;
              }
            }
          }
        }
        $fields = http_build_query($field_export);
        $to_export[] = $fields;
      }
      fputcsv($fp, $to_export);
    }
    rewind($fp);
    while (!feof($fp)) {
      $this->export .= fread($fp, 8192);
    }
    fclose($fp);
    return $this->export;
  }

}
