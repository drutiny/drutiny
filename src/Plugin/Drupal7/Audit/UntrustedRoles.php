<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * Check untrusted roles for administrative permissions.
 * @Param(
 *  name = "untrusted_roles",
 *  type = "array",
 *  description = "The names of untrusted Roles.",
 *  default = "['anonymous user','authenticated user']",
 * )
 */
class UntrustedRoles extends Audit
{

    public function audit(Sandbox $sandbox)
    {
        $rows = $sandbox->drush()->evaluate(function ($roles) {
            $rows = [];
            $all_roles = user_roles();
            $untrusted_roles = array_intersect($all_roles, $roles);

            foreach ($untrusted_roles as $rid => $role_name) {
                $untrusted_permissions = [];

              // Grab all permissions associated with the role.
                $permissions = user_role_permissions([$rid => $role_name]);

              // Check each permission assigned to the untrusted role and determine if
              // it is administrative. Administrative permissions contain the string
              // "administer" in the name.
                $output['permissions'][$role_name] = $permissions;
                foreach ($permissions[$rid] as $permission => $foo) {
                    if (strstr($permission, 'administer') !== false) {
                        $untrusted_permissions[] = $permission;
                    }
                }

              // Add the untrusted role and administrative permission to an output array
                if (!empty($untrusted_permissions)) {
                    $rows[] = [
                    'role' => $role_name,
                    'permissions' => implode(', ', $untrusted_permissions),
                    ];
                }
            }
            return $rows;
        }, [
        'roles' => $sandbox->getParameter('untrusted_roles')
        ]);

        $sandbox->setParameter('rows', $rows);

        return empty($rows) ? AUDIT::SUCCESS : AUDIT::FAIL;
    }
}
