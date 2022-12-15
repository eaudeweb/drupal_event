<?php

namespace Drupal\drupal_event\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\Date;

/**
 * Event basic sort handler for Events.
 *
 * Display events closest to the present date otherwise display past events.
 *
 * @ViewsSort("closest_events")
 */
class ClosestDate extends Date {

  /**
   * Called to add the sort to a query.
   */
  public function query() {
    $this->ensureMyTable();

    $dateAlias = "UNIX_TIMESTAMP($this->tableAlias.$this->realField)";
    // Is this event in the past?
    $this->query->addOrderBy(NULL,
      "UNIX_TIMESTAMP() > $dateAlias",
      $this->options['order'],
      "in_past"
    );
    // How far in the past/future is this event?
    $this->query->addOrderBy(NULL,
      "$dateAlias - UNIX_TIMESTAMP()",
      $this->getReverseOrder($this->options['order']),
      "distance_from_now"
    );

    $results = $this->query->query()->execute()->fetchAll();
    $pager = $this->displayHandler->getPlugin('pager');
    $limit = $pager->options['items_per_page'];
    if (count(array_keys(array_column($results, 'in_past'), 0)) >= $limit) {
      $results = array_filter($results, function ($result) {
        return (!$result->in_past);
      });
      if ($limit > 0) {
        $results = array_slice($results, -$limit, $limit, TRUE);
      }
      if (empty($results)) {
        return;
      }
      $this->query->addWhere(NULL,
        'node__field_date_range.entity_id', array_column($results, 'nid'), 'IN'
      );
    }
  }

  /**
   * Return the reverse ordering direction.
   *
   * @param string $direction
   *   Initial direction.
   *
   * @return string
   *   Get the reverse direction.
   */
  private function getReverseOrder(string $direction) {
    return $direction == 'DESC' ? 'ASC' : 'DESC';
  }

}
