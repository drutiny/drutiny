<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * User #1 Locked Down
 * @Param(
 *  name = "blacklist",
 *  description = "The usernames of the the uid:1 user that are considered forbidden. Expression maybe a regular expression to match patterns.",
 *  type = "string"
 * )
 * @Param(
 *  name = "email",
 *  description = "The email that the uid:1 user should have. If an empty string is provided then this check is omitted.",
 *  type = "string"
 * )
 * @Param(
 *  name = "status",
 *  description = "Ensures the uid:1 user status reflects the same as this argument. Defaults to active (1).",
 *  type = "integer",
 *  default = 1
 * )
 */
class User1 extends Audit
{

    public function configure():void
    {
        $this->setDeprecated();
    }

  /**
   * @inheritdoc
   */
    public function audit(Sandbox $sandbox)
    {
      // Get the details for user #1.
        $user = $sandbox->drush(['format' => 'json'])->userInformation(1);
        $user = (object) array_pop($user);

        $errors = [];

      // Username.
        $pattern = $sandbox->getParameter('blacklist');
        if (preg_match("#$pattern#i", $user->name)) {
            $errors[] = "Username '$user->name' is too easy to guess.";
        }
        $sandbox->setParameter('username', $user->name);

      // Email address.
        $email = $sandbox->getParameter('email');
        if (!empty($email) && ($email !== $user->mail)) {
            $errors[] = "Email address '$user->mail' is not set correctly.";
        }

      // Status.
        $status = (bool) $sandbox->getParameter('status');
        if ($status !== (bool) $user->status) {
            $errors[] = 'Status is not set correctly. Should be ' . ($user->status ? 'active' : 'inactive') . '.';
        }

        $sandbox->setParameter('errors', $errors);
        return empty($errors);
    }
}
