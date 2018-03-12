<?php

namespace Drutiny\Check\D8;

use Drutiny\Check\Check;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Check\RemediableInterface;

/**
 * @Drutiny\Annotation\CheckInfo(
 *  title = "User #1",
 *  description = "It is important to lock down user #1 in Drupal, this user is special an ignores access control.",
 *  remediation = "Change the username to be random, set the email address to go nowhere, set the password to something secure.",
 *  success = "User #1 is locked down.:fixups",
 *  failure = "User #1 is not secure.:errors",
 *  exception = "Could not determine user #1 settings.",
 *  supports_remediation = TRUE,
 * )
 */
class User1 extends Check implements RemediableInterface {

  /**
   *
   */
  public function check(Sandbox $sandbox) {
    // Get the details for user #1.
    $user = $sandbox->drush(['format' => 'json'])
                    ->userInformation(1);

    $user = (object) array_pop($user);

    $errors = [];
    $fixups = [];

    // Username.
    $pattern = $sandbox->getParameter('blacklist');
    if (preg_match("#${pattern}#i", $user->name)) {
      $errors[] = "Username '$user->name' is too easy to guess.";
    }

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

  public function remediate(Sandbox $sandbox)
  {

    // Get the details for user #1.
    $user = $sandbox->drush(['format' => 'json'])
                    ->userInformation(1);

    $user = (object) array_pop($user);

    $output = $sandbox->drush()->evaluate(function ($uid, $status, $password, $email, $username) {
      $user =  \Drupal\user\Entity\User::load($uid);
      if ($status) {
        $user->activate();
      }
      else {
        $user->block();
      }
      $user->setPassword($password);
      $user->setEmail($email);
      $user->setUsername($username);
      $user->set('init', $email);
      $user->save();
      return TRUE;
    }, [
      'uid' => $user->uid,
      'status' => (int) (bool) $sandbox->getParameter('status'),
      'password' => $this->generateRandomString(),
      'email' => $sandbox->getParameter('email'),
      'username' => $this->generateRandomString()
    ]);

    // $sandbox->drush()->sqlq("UPDATE users SET name = '$user->name', mail = '$email', init = '$email', status = $status WHERE uid = 1;");
    // $sandbox->drush()->userPassword("'$user->name'", "--password='$password'");
    return $this->check($sandbox);
  }

  /**
   * Generate a random string.
   *
   * @param int $length
   *   [optional]
   *   the length of the random string.
   *
   * @return string
   *   the random string.
   */
  public function generateRandomString($length = 32) {

    // Generate a lot of random characters.
    $state = bin2hex(random_bytes($length * 2));

    // Remove non-alphanumeric characters.
    $state = preg_replace("/[^a-zA-Z0-9]/", '', $state);

    // Trim it down.
    return substr($state, 0, $length);
  }

}
