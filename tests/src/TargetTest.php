<?php

namespace DrutinyTests;

use Drutiny\Target\TargetInterface;
use Drutiny\Target\TargetPropertyException;
use Drutiny\Entity\DataBag;
use Drutiny\Target\DdevTarget;
use Drutiny\Target\DrushTarget;
use Drutiny\Target\LandoTarget;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;

class TargetTest extends KernelTestCase
{
    public function testProperties()
    {
        $target = $this->loadMockTarget();
        
        $this->assertFalse($target->hasProperty('a.b.c.d'));
        $this->assertFalse($target->hasProperty('a.b.c'));
        $this->assertFalse($target->hasProperty('a.b'));
        $this->assertFalse($target->hasProperty('a'));

        try {
            $target->hasProperty('a.b.c.');
        } catch (InvalidPropertyPathException $e) {
        } finally {
            $this->assertInstanceOf(InvalidPropertyPathException::class, $e);
        }

        $target['a.b.c'] = 'foo';

        $this->assertIsString($target['a.b.c']);
        $this->assertInstanceOf(DataBag::class, $target['a.b']);
        $this->assertInstanceOf(DataBag::class, $target['a']);
        $this->assertIsString($target->a->b->c);
        $this->assertInstanceOf(DataBag::class, $target->a);
        $this->assertInstanceOf(DataBag::class, $target->a->b);

        try {
            $target['a.b'] = ['c' => 'bar'];
        } catch (TargetPropertyException $e) {
        } finally {
            $this->assertInstanceOf(TargetPropertyException::class, $e);
        }

        $target['a.b.d'] = ['e' => 'bar'];
        $this->assertIsString($target['a.b.d[e]']);
        $this->assertIsArray($target['a.b.d']);
        $this->assertIsArray($target->a->b->d);
        $this->assertIsString($target->a->b->d['e']);
        $this->assertInstanceOf(DataBag::class, $target['a.b']);

        $target['h.i'] = 'hi';
        $this->assertIsString($target['h.i']);

        $target['h.o'] = 'ho';
        $this->assertIsString($target['h.o']);

        $this->assertNull($target['unset.value']);
    }

    protected function runStandardTests(TargetInterface $target, $uri = 'https://mysite.com/')
    {
        // Ensure the target can use dot syntax.
        $target['test.foo'] = 'bar';
        $this->assertEquals($target['test.foo'], 'bar', "Test using array reference syntax.");
        $this->assertEquals($target->test->foo, 'bar', "Test using property reference syntax.");
        $this->assertEquals($target->getProperty('test.foo'), 'bar', "Test using getProperty syntax.");

        $target['test.foo'] = 'bar:baz';
        $this->assertEquals($target['test.foo'], 'bar:baz', "Test using array reference syntax.");
        $this->assertEquals($target->test->foo, 'bar:baz', "Test using property reference syntax.");
        $this->assertEquals($target->getProperty('test.foo'), 'bar:baz', "Test using getProperty syntax.");
        $this->assertInstanceOf(DataBag::class, $target['test']);
        $this->assertInstanceOf(DataBag::class, $target->test);
        $this->assertInstanceOf(DataBag::class, $target->getProperty('test'));

        $this->assertInstanceOf(TargetInterface::class, $target);
        $this->assertEquals($target->getUri(), $uri);

        // Existing DataBag can be overridden.
        $this->expectException(TargetPropertyException::class);
        $target['test'] = 'fault';
    }

    public function testDrushTarget()
    {
        $target = $this->loadMockTarget(
            'drush',
            $this->getFixture('drush-site:alias'),
            $this->getFixture('drush-bin'),
            $this->getFixture('drush-status'),
            $this->getFixture('php-version')
        );
        $this->assertInstanceOf(DrushTarget::class, $target);

        $target->load('@app.env', 'https://env.app.com');
        $this->runStandardTests($target, 'https://env.app.com');

        $this->assertEquals($target['drush.drupal-version'], '8.9.18');
        $this->assertEquals($target->getUri(), 'https://env.app.com');

        $target->load('@app.env');
        $this->assertEquals($target->getUri(), 'dev1.app.com');
    }

    public function testDdevTarget()
    {
        $target = $this->loadMockTarget(
            'ddev',
            $this->getFixture('ddev-describe'),
            $this->getFixture('drush-bin'),
            $this->getFixture('drush-status'),
            $this->getFixture('php-version')
        );
        
        $this->assertInstanceOf(DdevTarget::class, $target);
        $target->load('ddev_app', 'https://env.app.com');

        $this->assertEquals($target['drush.drupal-version'], '9.5.2');
        $this->assertEquals($target->getUri(), 'https://env.app.com');
    }

    public function testLandoTarget()
    {
        $target = $this->loadMockTarget(
            'lando',
            $this->getFixture('lando-list'),
            $this->getFixture('lando-info'),
            $this->getFixture('drush-bin'),
            $this->getFixture('drush-status'),
            $this->getFixture('php-version')
        );
        
        $this->assertInstanceOf(LandoTarget::class, $target);
        $target->load('appenv', 'https://env.app.com');

        $this->assertEquals($target['drush.drupal-version'], '9.5.2');
        $this->assertEquals($target->getUri(), 'https://env.app.com');
    }

    public function setUp(): void
    {
        parent::setUp();

        if (!file_exists(getenv('HOME') . '/.ddev/bin/docker-compose')) {
            $fs = new Filesystem;
            $fs->mkdir(getenv('HOME') . '/.ddev/bin');
            $fs->touch(getenv('HOME') . '/.ddev/bin/docker-compose');
        }
    }
}
