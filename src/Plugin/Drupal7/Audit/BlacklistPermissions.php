<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * BlackList Permissions
 * @Param(
 *  name = "permissions",
 *  description = "An array of permissions to ensure are not available to non-administrator roles",
 *  type = "array"
 * )
 */
class BlacklistPermissions extends Audit
{

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {
        $perms = $sandbox->getParameter('permissions');

        if (empty($perms)) {
            return true;
        }

        $where = [];
        foreach ($perms as $perm) {
            $where[] = 'rp.permission = \'' . $perm . '\'';
        }

      // We don't care about the 'administrator' role having access.
        $variable = $sandbox->drush(['format' => 'json'])->variableGet('user_admin_role');
        $user_admin_role = $variable['user_admin_role'];

        try {
            $output = $sandbox->drush()->sqlQuery('SELECT r.rid, r.name, rp.permission FROM role r INNER JOIN role_permission rp ON rp.rid = r.rid WHERE r.rid != ' . $user_admin_role . ' AND (' . implode(' OR ', $where) . ');');
            $output = array_filter($output);
        } catch (\Exception $e) {
            $sandbox->logger()->info(get_class($e) . ': ' . $e->getMessage());
            return false;
        }

        if (empty($output)) {
            return true;
        }

        $black_roles = [];
        foreach ($output as $line) {
            list($rid, $role, $permission) = explode("\t", $line);
            $black_roles[$role][] = $permission;
        }

        $roles = [];

        foreach ($black_roles as $role => $perms) {
            $roles[] = [
            'role' => $role,
            'perms' => $perms,
            ];
        }

        $sandbox->setParameter('roles', $roles);

        return false;
    }
}
