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
  public function execute($include_ids, $include_headers) {
    // TODO Inject.
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $this->vocabulary);
    $tids = $query->execute();
    $terms = Term::loadMultiple($tids);
    $fp = fopen('php://memory', 'rw');
    if ($include_headers) {
      $to_export = ['name', 'description', 'format', 'weight', 'parent_name'];
      if ($include_ids) {
        $to_export = array_merge(['tid', 'uuid'], $to_export);
        $to_export[] = 'parent_tid';
      }
      fputcsv($fp, $to_export);
    }
    foreach ($terms as $term) {
      // TODO - Inject.
      $parent = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($term->id()));
      $parent_name = '';
      $parent_id = '';
      $to_export = [];
      if (!empty($parent)) {
        $parent_name = $parent->getName();
        $parent_id = $parent->id();
      }
      $to_export = [
        $term->getName(),
        $term->getDescription(),
        $term->getFormat(),
        $term->getWeight(),
        $parent_name,
      ];
      if ($include_ids) {
        $to_export = array_merge([$term->id(), $term->uuid()], $to_export);
        $to_export[] = $parent_id;
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
