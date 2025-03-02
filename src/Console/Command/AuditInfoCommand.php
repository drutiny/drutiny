<?php

namespace Drutiny\Console\Command;

use Drutiny\AuditFactory;
use Drutiny\Policy\AuditClass;
use Drutiny\PolicyFactory;
use Drutiny\Target\TargetFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class AuditInfoCommand extends Command
{
    public function __construct(
        protected PolicyFactory $policyFactory,
        protected AuditFactory $auditFactory,
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('audit:info')
        ->setDescription('Show all php audit classes available.')
        ->addArgument(
            'audit',
            InputArgument::REQUIRED,
            'The name of the audit class to display info about.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $audit = AuditClass::fromClass($input->getArgument('audit'));
        $reflection = new \ReflectionClass($audit->name);
        $audit_instance = $this->auditFactory->mock($audit->name);

        $info = [];

        $info[] = ['<info>Class</info>', new TableCell($audit->name, ['colspan' => 4])];
        $info[] = [
          '<info>Version</info>', $audit->version?->version ?? '',
          '<info>Supports</info>', $audit->version?->compatibilty ?? ''
        ];
        $info[] = ['<info>Extends</info>', new TableCell($reflection->getParentClass()->name, ['colspan' => 4])];

        $info[] = new TableSeparator();

        $params_by_class = [];

        foreach ($audit_instance->getDefinition()->getParameters() as $param) {
            $params_by_class[$param->class][] = $param;
        }

        foreach ($params_by_class as $class => $params) {
            $info[] = [ new TableCell("<info>Parameters from $class</info>", ['colspan' => 7]) ];
            $info[] = new TableSeparator();
            $info[] = [
            '<info>Parameters</info>',
            '<fg=yellow>Name</>',
            '<fg=yellow>Type</>',
            '<fg=yellow>Input mode</>',
            '<fg=yellow>Preprocessing</>',
            '<fg=yellow>Description</>',
            '<fg=yellow>Default value</>'
            ];
            foreach ($params as $param) {
                $info[] = [
                '',
                $param->name,
                $param->type?->value,
                $param->isRequired() ? '<fg=red>Required</>' : 'Optional',
                $param->preprocess->getTitle(),
                $param->description,
                Yaml::dump($param->default),
                ];
            }
            $info[] = new TableSeparator();
        }

        $policy_list = array_filter($this->policyFactory->getPolicyList(), function ($policy) use ($audit) {
            return $policy['class'] == $audit->name;
        });

        $info[] = ['<info>Policies</info>', new TableCell($this->listing(array_map(function ($policy) {
            return $policy['name'];
        }, $policy_list)), ['colspan' => 4])];

        $info[] = new TableSeparator();

        $info[] = ['<info>Filename</info>', new TableCell($reflection->getFilename(), ['colspan' => 4])];

        $io = new SymfonyStyle($input, $output);
        $io->title('Audit Info');

        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');

        $table = new Table($output);
        $table->setColumnMaxWidth(2, 7);
        $table->setColumnMaxWidth(3, 10);
        $table->setColumnMaxWidth(5, 50);
        $table->setRows($info);
        $table->setStyle($style);

        $table->render();
        // $io->table([], $info);
        return 0;
    }


    protected function listing($array)
    {
        return  "* ".implode("\n* ", array_filter($array));
    }
}
