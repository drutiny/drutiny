<?php

namespace DrutinyTests\Prophecies;

use Prophecy\Prophet;
use Prophecy\Argument;
use Drutiny\Target\Service\LocalService;

class LocalServiceLandoStub
{
  static public function get(Prophet $prophet)
  {
    $prophecy = $prophet->prophesize(LocalService::class);

    $prophecy->replacePlaceholders(Argument::type('string'))->will(function ($args) {
      return $args[0];
    });

    // Catch unforseen argument calls.
    $prophecy->run(Argument::type('string'), Argument::type('callable'))->will(function ($args) {
      throw new \Exception("Unforseen: {$args[0]}.");
    });

    $prophecy->run(Argument::containingString('lando list --format=json'), Argument::type('callable'), Argument::any())->will(function ($args) {
      $response = '[{"service":"database","name":"appenv_database_1","app":"appenv","src":["/Users/user.name/Sandbox/appenv/site/.lando.yml"],"kind":"app","status":"Up 47 seconds"},{"service":"appserver","name":"appenv_appserver_1","app":"appenv","src":["/Users/user.name/Sandbox/appenv/site/.lando.yml"],"kind":"app","status":"Up 47 seconds"},{"service":"proxy","name":"landoproxyhyperion5000gandalfedition_proxy_1","app":"_global_","src":"unknown","kind":"service","status":"Up 2 minutes"}]';
      return $args[1]($response);
    });

    $prophecy->run(Argument::containingString('lando info --format=json'), Argument::type('callable'), Argument::any())->will(function ($args) {
      $response = '[{"service":"appserver","urls":["https://localhost:57856","http://localhost:57857","http://contenta-cms.lndo.site:8000/","https://contenta-cms.lndo.site:444/"],"type":"php","healthy":true,"via":"apache","webroot":"web","config":{"php":"/Users/joshua.waihi/.lando/config/drupal9/php.ini"},"version":"7.4","meUser":"www-data","hasCerts":true,"hostnames":["appserver.contentacms.internal"]},{"service":"database","urls":[],"type":"mysql","healthy":true,"internal_connection":{"host":"database","port":"3306"},"external_connection":{"host":"127.0.0.1","port":"57855"},"healthcheck":"bash -c \"[ -f /bitnami/mysql/.mysql_initialized ]\"","creds":{"database":"drupal9","password":"drupal9","user":"drupal9"},"config":{"database":"/Users/joshua.waihi/.lando/config/drupal9/mysql.cnf"},"version":"5.7","meUser":"www-data","hasCerts":false,"hostnames":["database.contentacms.internal"]}]';
      return $args[1]($response);
    });

    $prophecy->run(Argument::type('string'), Argument::any(), Argument::any())->will(function ($args) {
      if (strpos($args[0], 'docker exec') === false) {
        throw new \Exception("Not a docker command: {$args[0]}.");
      }
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
