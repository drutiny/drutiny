<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Views Cache
 */
class ViewsCache extends Audit
{

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {
        $views = $sandbox->drush()->evaluate(function () {
            $bad_views = [];
            foreach (views_get_all_views() as $view_name => $view) {
                foreach ($view->display as $display_name => $display) {
                    if (empty($display->display_options['cache'])) {
                        $bad_views[] = "$view_name:$display_name";
                        continue;
                    }
                    $cache = $display->display_options['cache'];
                    if ($cache['type'] == 'none') {
                        $bad_views[] = "$view_name:$display_name";
                    }
                }
            }
            return $bad_views;
        });

        if (empty($views)) {
            return true;
        }

        $sandbox->setParameter('views', $views);
        return false;


      // $valid = 0;
      // $errors = [];
      //
      // // View settings are set per display so we need to query the views display table.
      // $views = $this->context->drush->sqlQuery("SELECT vd.vid, vd.display_title, vd.display_options, vv.name, vv.human_name FROM {views_display} vd JOIN {views_view} vv ON vv.vid = vd.vid");
      //
      // foreach ($views as $view) {
      //   list($display_id, $display_name, $display_options, $view_machine_name, $view_name) = explode("\t", $view);
      //   $display_options = Serializer::unserialize($display_options);
      //
      //   if (empty($display_options['pager']['options']['items_per_page'])) {
      //     continue;
      //   }
      //
      //   if ($display_options['pager']['options']['items_per_page'] > $this->getOption('threshold', 30)) {
      //     $errors[] = "<strong>$view_name</strong> <code>[$display_name]</code> is displaying <code>{$display_options['pager']['options']['items_per_page']}</code>";
      //     continue;
      //   }
      //
      //   $valid++;
      // }
      //
      // $this->setToken('total', $valid);
      // $this->setToken('plural', $valid > 1 ? 's' : '');
      // $this->setToken('error', implode('</li><li>', $errors));
      // $this->setToken('threshold', $this->getOption('threshold', 30));
      // $this->setToken('error_count', count($errors));
      //
      // return empty($errors) ? AuditResponse::AUDIT_SUCCESS : AuditResponse::AUDIT_FAILURE;
    }
}
