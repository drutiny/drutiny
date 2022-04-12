<?php

namespace DrutinyTests\Prophecies;

use Prophecy\Prophet;
use Prophecy\Argument;
use Drutiny\Target\Service\LocalService;

class LocalServiceDrushStub
{
  static public function get(Prophet $prophet)
  {
    $prophecy = $prophet->prophesize(LocalService::class);

    $prophecy->replacePlaceholders(Argument::type('string'))->will(function ($args) {
      return $args[0];
    });

    $prophecy->run(Argument::type('string'), Argument::any(), Argument::any())->will(function ($args) {
      preg_match('/echo ([^ ]+) /', $args[0], $matches);
      $cmd = preg_match('/echo ([^ ]+) /', $args[0], $matches) ? base64_decode($matches[1]) : $args[0];

      switch (true) {
        case strpos($cmd, 'which drush') !== false:
          $value = '/var/www/html/app.dev/vendor/drush/drush/drush';
          break;

        case strpos($cmd, 'drush status') !== false:
          $value = '{"drupal-version":"8.9.18","uri":"http://env1.app.com","db-driver":"mysql","db-hostname":"staging-123","db-port":3306,"db-username":"appuser","db-name":"appdev","db-status":"Connected","bootstrap":"Successful","theme":"app_claro","admin-theme":"claro","php-bin":"/usr/local/php7.4/bin/php","php-conf":{"/usr/local/php7.4/etc/cli/php.ini":"/usr/local/php7.4/etc/cli/php.ini"},"php-os":"Linux","drush-script":"/mnt/www/html/appdev/vendor/drush/drush/drush","drush-version":"10.6.0","drush-temp":"/mnt/tmp/appdev","drush-conf":["/etc/drush/drush.yml","/mnt/www/html/app.dev/vendor/drush/drush/drush.yml","/mnt/www/html/app.dev/drush/drush.yml"],"install-profile":"default","root":"/mnt/www/html/app.dev/docroot","site":"sites/default","files":"sites/default/files","private":"/mnt/files/app.dev/sites/default/files-private","temp":"/mnt/tmp/appdev"}';
          break;

        case strpos($cmd, 'drush site:alias') !== false:
          $value = '{"app.dev":{"root":"/var/www/html/app.dev/docroot","uri":"dev1.app.com","path-aliases":{"%drush-script":"drush8"},"dev.livedev":{"parent":"@app.dev","root":"/mnt/gfs/app.dev/livedev/docroot"},"remote-host":"appdev.ssh.app.com","remote-user":"app.dev"}}';
          break;
        case strpos($cmd, 'php -v') !== false:
          $value = '7.4.28';
          break;
        default:
          throw new \Exception("Unforseen: {$args[0]}.");
      }
      return is_callable($args[1]) ? $args[1]($value) : $value;
    });

    $prophecy->setTarget(Argument::any())->will(function () use ($prophecy) {
      return $prophecy->reveal();
    });
    $prophecy->hasEnvVar('DRUSH_ROOT')->willReturn(true);

    return $prophecy;
  }
}
