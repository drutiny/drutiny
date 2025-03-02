<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 *
 */
class NoAdministrators extends Audit
{

  /**
   * @inheritDoc
   */
    public function audit(Sandbox $sandbox)
    {
        $variable = $sandbox->drush(['format' => 'json'])->vget('user_admin_role');
        $admin_rid = (int) $variable['user_admin_role'];
        $role_list = $sandbox->drush(['format' => 'json'])->roleList();

        $roles = [];
        foreach ($role_list as $role) {
          // Find out the role name of the role ID defined in 'user_admin_role'.
            if ($role['rid'] === $admin_rid) {
                $roles[$admin_rid] = $role['label'];
            } elseif ($role['label'] === 'administrator') {
                $roles[$role['rid']] = $role['label'];
            }
        }

      // No administrator roles defined, and no roles called 'administrator' in
      // Drupal.
        if (empty($roles)) {
            return;
        }

      // '0' is disabled.
      // @see https://github.com/drupal/drupal/blob/7.x/modules/user/user.admin.inc#L305
        if (count($roles) === 1 && $admin_rid === 0) {
            return;
        }

        $rows = $sandbox->drush()
        ->sqlQuery("SELECT CONCAT(ur.uid, ',', u.name)
      FROM {users_roles} ur
      LEFT JOIN {users} u ON ur.uid = u.uid
      WHERE ur.uid > 1 AND ur.rid IN (" . implode(',', array_keys($roles)) . ");");

      // Remove blank rows.
        $rows = array_filter($rows);

      // Format rows into token data.
        $rows = array_map(function ($row) {
            $row = trim($row);
            list($uid, $name) = explode(',', $row, 2);
            return "{$name} - [UID {$uid}]";
        }, $rows);

        $sandbox->setParameter('count', count($rows));
        $sandbox->setParameter('issues', $rows);
        $sandbox->setParameter('plural', count($rows) > 1 ? 's' : '');

        return empty($rows);
    }
}
