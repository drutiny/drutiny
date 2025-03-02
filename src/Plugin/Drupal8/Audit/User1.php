<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Composer\Semver\Comparator;

/**
 * User #1
 */
class User1 extends Audit
{


    public function configure():void
    {
        $this->addParameter(
            'email',
            static::PARAMETER_OPTIONAL,
            'The email the user account should be.',
        );
        $this->addParameter(
            'blacklist',
            static::PARAMETER_OPTIONAL,
            'List of usernames that are not acceptable.',
        );
        $this->addParameter(
            'status',
            static::PARAMETER_OPTIONAL,
            'Whether the account should be enabled or disabled.',
        );
        $this->setDeprecated();
    }

  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {
        $drush = $this->target->getService('drush');

        if (Comparator::greaterThan($this->target['drush.drush-version'], '10.0.0')) {
            $command = $drush->userInformation([
              'uid' => 1,
              'format' => 'json'
            ]);
        } else {
            $command = $drush->userInformation(1, ['format' => 'json']);
        }

        $user = $command->run(function ($output) {
            $json = json_decode($output, true);
            return (object) array_pop($json);
        });

        $this->set('user', $user);
        $this->set('blacklist_fail', false);
        $this->set('email_fail', false);
        $this->set('status_fail', false);

        $errors = [];
        $fixups = [];

      // Username.
        $pattern = $this->getParameter('blacklist');
        if (preg_match("#$pattern#i", $user->name)) {
            $errors[] = "Username '$user->name' is too easy to guess.";
            $this->set('blacklist_fail', true);
        }
        $this->set('username', $user->name);

      // Email address.
        $email = $this->getParameter('email');

        if (!empty($email) && ($email !== $user->mail)) {
            $errors[] = "Email address '$user->mail' is not set correctly.";
            $this->set('email_fail', true);
        }

      // Status.
        $status = (bool) $this->getParameter('status');
        if ($status !== (bool) $user->user_status) {
            $errors[] = 'Status is not set correctly. Should be ' . ($user->user_status ? 'active' : 'inactive') . '.';
            $this->set('status_fail', true);
        }

        $this->set('errors', $errors);
        return empty($errors) ? true : Audit::WARNING;
    }
}
