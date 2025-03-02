<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

/**
 * Views Analysis
 */
class ViewsAnalysis extends AbstractAnalysis
{

  /**
   * {@inheritdoc}
   */
    public function gather(Sandbox $sandbox)
    {
      // Gather limited views data since views recurse and can result in a
      // lot of data being transfered.
        $views = $this->target->getService('drush')->runtime(function () {
            $data = [];
            $f = function ($d) {
                return $d->display_title;
            };
            $views = function_exists('views_get_all_views') ? views_get_all_views() : [];
            foreach ($views as $view) {
                $data[$view->name] = [
                'name' => $view->name,
                'human_name' => $view->human_name,
                'base_table' => $view->base_table,
                'export_module' => $view->export_module,
                'display' => array_map($f, $view->display),
                ];
            }
            return $data;
        });

        $this->set('views', $views);
    }
}
