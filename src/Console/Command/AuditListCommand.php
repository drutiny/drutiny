<?php

namespace Drutiny\Console\Command;

use Drutiny\Audit\AuditInterface;
use Drutiny\AuditFactory;
use Drutiny\Policy\AuditClass;
use Drutiny\PolicyFactory;
use Drutiny\Settings;
use Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 *
 */
class AuditListCommand extends DrutinyBaseCommand
{
    public function __construct(
        protected PolicyFactory $policyFactory,
        protected AuditFactory $auditFactory,
        protected LoggerInterface $logger,
        protected Settings $settings
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
        ->setName('audit:list')
        ->setDescription('Show all php audit classes available.')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $search_paths = [];
        
        // Audit classes are not autoloaded and there are not any easy ways to find
        // the audit classes. So we're going to find installed composer packages
        // that use drutiny and search them for Audit classes.

        // First up the project composer.json file.
        $package = json_decode(file_get_contents($this->settings->get('project_dir').'/composer.json'), true);
        foreach ($package['autoload']['psr-4'] as $namespace => $location) {
            $search_paths[$location] = $namespace;
        }

        // Secondly, any installed packages that depend or are drutiny/drutiny.
        $installed = json_decode(file_get_contents($this->settings->get('project_dir').'/vendor/composer/installed.json'), true);
        foreach ($installed['packages'] as $package) {
            if (($package['name'] != 'drutiny/drutiny') && !isset($package['require']['drutiny/drutiny'])) {
                continue;
            }
            foreach ($package['autoload']['psr-4'] as $namespace => $location) {
                $search_paths['vendor/composer/' . $package['install-path'] .'/'.$location] = $namespace;
            }
        }

        // For searching the filesystem, we need to know the absolute locations.
        $paths = array_map(fn ($path) => $this->settings->get('project_dir') . '/' . $path, array_keys($search_paths));
        $paths = array_filter($paths, 'file_exists');

        $finder = new Finder;
        $finder->in($paths)->files()->name('*.php');

        // Small function to find the search path for a given filepath.
        $findPath = function ($file) use ($search_paths) {
            foreach ($search_paths as $path => $namespace) {
                if (strpos((string) $file, $path) === 0) {
                    return $path;
                }
            }
        };

        $audits = [];
        foreach ($finder as $file) {
            $filepath = str_replace($this->settings->get('project_dir').'/', '', (string) $file);
            $relative_path = str_replace($findPath($filepath), '', $filepath);
            $namespace = $search_paths[$findPath($filepath)];
            $pathinfo = pathinfo($relative_path);

          // Not PSR-4 compatible.
            if (!ctype_upper($pathinfo['filename'][0])) {
                continue;
            }

            $pathinfo['dirname'] = $pathinfo['dirname'] == '.' ? '' : $pathinfo['dirname'];
            $class_name = $namespace . str_replace('/', '\\', $pathinfo['dirname']) . '\\' . $pathinfo['filename'];
            $class_name = str_replace('\\\\', '\\', $class_name);

            if (strpos($class_name, 'Audit') === false) {
                continue;
            }

            try {
                class_exists($class_name);
            } catch (Error) {
                continue;
            }

            $reflect = new \ReflectionClass($class_name);
            if ($reflect->isAbstract()) {
                continue;
            }
            if (!$reflect->implementsInterface(AuditInterface::class)) {
                continue;
            }
            $audits[] = $class_name;
        }

        sort($audits);
        $policy_list = $this->policyFactory->getPolicyList(true);

        $stats = [];
        foreach ($audits as $audit) {
            $audit = AuditClass::fromClass($audit);
            try {
                $instance = $this->auditFactory->mock($audit->name);
            } catch (ServiceNotFoundException $e) {
                $this->logger->error($e->getMessage());
                continue;
            }

            $deprecated = $instance->isDeprecated() ? ' <fg=yellow>(deprecated)</>' : '';
            $stats[] = [$audit->name.$deprecated, $audit->version?->version, count(array_filter($policy_list, function ($policy) use ($audit) {
                return $audit->name == $policy['class'];
            }))];
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Drutiny Audit Classes');
        $io->table(['Audit', 'Version', 'Policy utilisation'], $stats);
        return 0;
    }
}
