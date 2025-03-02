<?php

namespace Drutiny\Console\Command;

use Drutiny\AuditFactory;
use Drutiny\PolicyFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class AuditPolicyLibraryCommand extends Command
{
    public function __construct(
        protected PolicyFactory $policyFactory,
        protected AuditFactory $auditFactory
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setName('policy:library:audit')
        ->setHidden()
        ->setDescription('Audit the policy library for compliance recommendations.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->policyFactory->getPolicyList() as $definition) {
            $policy = $this->policyFactory->loadPolicyByName($definition['name']);
            file_put_contents('policies_to_upgrade/' . $policy->name.'.policy.yml', Yaml::dump($policy->export(), 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            continue;
            $dependencies = array_filter($policy->getDepends(), fn($d) => $d->syntax == 'expression_language' && $d->expression != 1);
            if (!empty($dependencies)) {
                array_walk($dependencies, function ($d) use ($policy, $output) {
                    $output->writeln("// {$policy->name} dependency '{$d->description}'.");
                    $output->writeln("Expression: $d->expression");
                });
                //$io->error(sprintf("{$policy->name} contains %d expression_language dependencies.", count($dependencies)));
                // $io->listing(array_map(fn($d) => $d->expression, $dependencies));
            }
            $audit = $this->auditFactory->mock($policy->class);
            $definition = $audit->getDefinition();
            if (!$definition->hasArgument('syntax')) {
                continue;
            }
            $syntax = $policy->parameters['syntax'] ?? 'expression_language';
            if ($syntax != 'expression_language') {
                continue;
            }
            if (isset($policy->parameters['expression']) && $policy->parameters['expression'] != 1) {
                $output->writeln("// {$policy->name} uses {$policy->class} with expression_language syntax.");
                $output->writeln("Expression: ".($policy->parameters['expression'] ?? 'Unknown expression'));
            }
            if (isset($policy->parameters['variables'])) {
                foreach ($policy->parameters['variables'] as $key => $expression) {
                    $output->writeln('Variable['.$key.']: '.$expression);
                }
            }
            file_put_contents('policies_to_upgrade/' . $policy->name.'.policy.yml', Yaml::dump($policy->export(), 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            // $io->text('---');
        }
        return 0;
    }
}
