<?php

namespace DrutinyTests;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\DynamicParameterType;
use Drutiny\Audit\InputDefinition;
use Drutiny\Audit\SyntaxProcessor;
use Drutiny\AuditFactory;
use Drutiny\Policy;
use Drutiny\Policy\AuditClass;
use Drutiny\Policy\Compatibility\IncompatibleVersionException;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;

class PolicyTest extends KernelTestCase
{

    protected TargetInterface $target;
    protected SyntaxProcessor $syntax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->target = $this->container->get(TargetFactory::class)->create('none:none');
    }

    public function testPass()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertTrue($response->isSuccessful());
    }

    public function testFail()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Fail');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertFalse($response->isSuccessful());
    }

    public function testError()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Error');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->hasError());
    }

    public function testWarning()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Warning');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->hasWarning());
    }

    public function testNotApplicable()
    {
        $policy = $this->container->get('policy.factory')->loadPolicyByName('Test:NA');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isNotApplicable());
    }

    public function testNotice()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Notice');
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isNotice());
    }

    public function testSerializable()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Notice');
        $serial = serialize($policy);
        $this->assertIsString($serial, "Policy can be serialized");
        $this->assertInstanceOf(Policy::class, unserialize($serial), "Policy can be unserialized");
    }

    public function testBuildParameters()
    {
        $policy = $this->container->get(PolicyFactory::class)->loadPolicyByName('Test:Pass')->with(
            build_parameters: [
            'foo' => "'site.env'|split('.')|last"
            ]
        );
        $this->assertTrue($policy->build_parameters->has('foo'));
        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertEquals('env', $response->tokens['foo'], "build parameter was correctly evaluated and converted into a token.");

        $policy = $policy->with(build_parameters: [
        '!bypass' => ['foo' => 'bar']
        ]);

        $audit = $this->container->get(AuditFactory::class)->get($policy, $this->target);
        $response = $audit->execute($policy);
        $this->assertIsArray($response->tokens['bypass'], "build parameter was correctly evaluated and converted into a token.");
    }

    public function testSyntaxProcessor()
    {
        $this->syntax = $this->container->get(SyntaxProcessor::class);
        $contexts = ['foo' => 'bar'];

        $parameters = [
        '^message' => 'hello {foo}',
        '$array' => '[foo]'
        ];

        $values = $this->syntax->processParameters($parameters, $contexts);

        $this->assertIsArray($values);
        $this->assertArrayHasKey('message', $values);
        $this->assertEquals($values['message'], 'hello bar');
        $this->assertArrayHasKey('array', $values);
        $this->assertIsArray($values['array']);
        $this->assertContains('bar', $values['array']);
    }

    public function testDynamicParameterType()
    {
        $this->assertEquals('foo', DynamicParameterType::EVALUATE->stripParameterName('$foo'));
        $this->assertEquals('$foo', DynamicParameterType::REPLACE->stripParameterName('$foo'));
        $this->assertEquals('$foo', DynamicParameterType::STATIC->stripParameterName('$foo'));

        $this->assertEquals('^foo', DynamicParameterType::EVALUATE->stripParameterName('^foo'));
        $this->assertEquals('foo', DynamicParameterType::REPLACE->stripParameterName('^foo'));
        $this->assertEquals('^foo', DynamicParameterType::STATIC->stripParameterName('^foo'));

        $this->assertEquals('!foo', DynamicParameterType::EVALUATE->stripParameterName('!foo'));
        $this->assertEquals('!foo', DynamicParameterType::REPLACE->stripParameterName('!foo'));
        $this->assertEquals('foo', DynamicParameterType::STATIC->stripParameterName('!foo'));

        $this->assertEquals('foo', DynamicParameterType::EVALUATE->stripParameterName('foo'));
        $this->assertEquals('foo', DynamicParameterType::REPLACE->stripParameterName('foo'));
        $this->assertEquals('foo', DynamicParameterType::STATIC->stripParameterName('foo'));

        $this->assertEquals(DynamicParameterType::EVALUATE, DynamicParameterType::fromParameterName('$foo'));
        $this->assertEquals(DynamicParameterType::REPLACE, DynamicParameterType::fromParameterName('^foo'));
        $this->assertEquals(DynamicParameterType::STATIC, DynamicParameterType::fromParameterName('!foo'));
        $this->assertEquals(DynamicParameterType::NONE, DynamicParameterType::fromParameterName('foo'));
    }

    public function testProcessParameters()
    {
        $parameter = new Parameter(
            name: 'url',
            description: 'The url to request',
            type: Type::STRING,
            default: '{foo}',
            preprocess: DynamicParameterType::REPLACE
        );

        $definition = new InputDefinition();
        $definition->addParameter($parameter);

        $contexts = [
        'foo' => 'bar'
        ];
    
        $params = [];

        $syntax = $this->container->get(SyntaxProcessor::class);
        $params = $syntax->processParameters($params, $contexts, $definition);

        $this->assertArrayHasKey('url', $params);
        $this->assertEquals('bar', $params['url']);

        $parameters = [
        '^list' => [
        '{foo}'
        ]
        ];
        $params = $syntax->processParameters($parameters, $contexts);

        $this->assertArrayHasKey('list', $params);
        $this->assertArrayHasKey(0, $params['list']);
        $this->assertEquals('bar', $params['list'][0]);
    }

    public function testVersion()
    {
        $policy = new Policy(
            title: 'Test policy',
            name: 'Test:Policy',
            description: 'Test policy',
            uuid: uniqid('test'),
            source: 'phpunit',
            class: AbstractAnalysis::class
        );

        $this->assertNotEmpty($policy->audit_build_info);
        $this->assertArrayHasKey('audit_build_info', $policy->export());
        $this->assertIsArray($policy->export()['audit_build_info']);
        $this->assertIsString($policy->export()['audit_build_info'][0]);

        $this->assertStringContainsString(AbstractAnalysis::class, $policy->export()['audit_build_info'][0]);

        $build_info = $policy->export()['audit_build_info'][0];
        list($class, $version) = explode(':', $build_info);
        $this->assertEquals($class, $policy->class);
        $this->assertInstanceOf(AuditClass::class, $policy->audit_build_info[0]);
        $this->assertTrue($policy->audit_build_info[0]->isCompatible());
    }

    public function testVersionIncompatible()
    {
        $policy = new Policy(
            title: 'Test policy',
            name: 'Test:Policy',
            description: 'Test policy',
            uuid: uniqid('test'),
            source: 'phpunit',
            class: AbstractAnalysis::class,
            audit_build_info: [AbstractAnalysis::class . ':1.0']
        );

        $this->expectException(IncompatibleVersionException::class);
    
        $policy->audit_build_info[0]->isCompatible();
    }
}
