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
  public function execute($include_ids) {
    // TODO Inject.
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $this->vocabulary);
    $tids = $query->execute();
    $terms = Term::loadMultiple($tids);
    foreach ($terms as $term) {
      // TODO - Inject.
      $parent = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($term->id()));
      $parent_name = '';
      $parent_id = '';
      $to_export = '';
      if (!empty($parent)) {
        $parent_name = $parent->getName();
        $parent_id = $parent->id();
      }
      $to_export = '"' . $term->getName() . '","' . $term->getDescription() . '",' . $term->getFormat() . ',' . $term->getWeight() . ',"' . $parent_name . '"';
      if ($include_ids) {
        $to_export = $term->id() . ',' . $term->uuid() . ',' . $to_export . ',' . $parent_id;
      }
      $this->export .= $to_export . ";\n";
    }
    return $this->export;
  }

}
