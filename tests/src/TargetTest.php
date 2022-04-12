<?php

namespace DrutinyTests;

use Drutiny\Target\TargetInterface;
use Drutiny\Entity\EventDispatchedDataBag;
use Drutiny\Target\Service\ExecutionService;
use Drutiny\Target\Service\LocalService;
use Prophecy\Prophet;
use PHPUnit\Framework\TestCase;
use DrutinyTests\Prophecies\LocalServiceDrushStub;
use DrutinyTests\Prophecies\LocalServiceDdevStub;
use DrutinyTests\Prophecies\LocalServiceLandoStub;

class TargetTest extends KernelTestCase {


  protected function setUp(): void
  {
      parent::setup();
      $this->prophet = new \Prophecy\Prophet;
  }

  protected function tearDown(): void
  {
      $this->prophet->checkPredictions();
  }

  protected function runStandardTests(TargetInterface $target, $uri = 'https://mysite.com/')
  {
      $target['test.foo'] = 'bar';
      $this->assertEquals($target['test.foo'], 'bar');
      $this->assertEquals($target->getProperty('test.foo'), 'bar');
      $this->assertInstanceOf(EventDispatchedDataBag::class, $target['test']);

      $this->assertInstanceOf(TargetInterface::class, $target);
      $this->assertEquals($target->getUri(), $uri);

      $this->assertInstanceOf(ExecutionService::class, $target['service.exec']);
      $this->assertSame($target['service.exec'], $target->getService('exec'));
  }

  public function testDrushTarget()
  {
    $local = LocalServiceDrushStub::get($this->prophet)->reveal();

    // Load without service container so we can use our prophecy.
    $target = new \Drutiny\Target\DrushTarget(
      new ExecutionService($local),
      $this->container->get('logger'),
      $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
    );
    $this->assertInstanceOf(\Drutiny\Target\DrushTarget::class, $target);
    $target->parse('@app.env', 'https://env.app.com');
    $this->runStandardTests($target, 'https://env.app.com');

    $this->assertEquals($target['drush.drupal-version'], '8.9.18');
    $this->assertEquals($target->getUri(), 'https://env.app.com');

    $target = new \Drutiny\Target\DrushTarget(
      new ExecutionService($local),
      $this->container->get('logger'),
      $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
    );
    $target->parse('@app.env');
    $this->assertEquals($target->getUri(), 'dev1.app.com');
  }

  public function testDdevTarget()
  {
    $local = LocalServiceDdevStub::get($this->prophet)->reveal();

    // Load without service container so we can use our prophecy.
    $target = new \Drutiny\Target\DdevTarget(
      new ExecutionService($local),
      $this->container->get('logger'),
      $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
    );
    $this->assertInstanceOf(\Drutiny\Target\DdevTarget::class, $target);
    $target->parse('ddev_app', 'https://env.app.com');

    $this->assertEquals($target['drush.drupal-version'], '8.9.18');
    $this->assertEquals($target->getUri(), 'https://env.app.com');
  }

  public function testLandoTarget()
  {
    $local = LocalServiceLandoStub::get($this->prophet)->reveal();

    // Load without service container so we can use our prophecy.
    $target = new \Drutiny\Target\LandoTarget(
      new ExecutionService($local),
      $this->container->get('logger'),
      $this->container->get('Drutiny\Entity\EventDispatchedDataBag')
    );
    $this->assertInstanceOf(\Drutiny\Target\LandoTarget::class, $target);
    $target->parse('appenv', 'https://env.app.com');

    $this->assertEquals($target['drush.drupal-version'], '8.9.18');
    $this->assertEquals($target->getUri(), 'https://env.app.com');
  }
}
