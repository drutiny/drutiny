<?php

namespace Drutiny\Console\Command;

use Drutiny\Policy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Drutiny\ProfileFactory;
use Drutiny\PolicyFactory;
use Drutiny\Profile\PolicyDefinition;
use Twig\Environment;

/**
 *
 */
class ProfileInfoCommand extends Command
{

    protected ProfileFactory $profileFactory;
    protected PolicyFactory $policyFactory;
    protected Environment $twig;

    public function __construct(ProfileFactory $factory, Environment $twig, PolicyFactory $policyFactory)
    {
        $this->profileFactory = $factory;
        $this->policyFactory = $policyFactory;
        $this->twig = $twig;
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('profile:info')
        ->setDescription('Display information about a profile.')
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to display.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $render = new SymfonyStyle($input, $output);

        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'));

        $policies = array_map(fn (PolicyDefinition $p) => $p->getPolicy($this->policyFactory), $profile->policies);
        $dependencies = array_map(fn (PolicyDefinition $d) => $d->getPolicy($this->policyFactory), $profile->dependencies);


        $render->title($profile->title);
        $render->block($profile->description ?? '');

        $render->section('Usage');
        $render->block('drutiny profile:run ' . $profile->name . ' <target>');

        if (!empty($dependencies)) {
            $render->section('Dependencies');
            $headers = ['Title', 'Name', 'Class', 'Source'];
            $render->block(
                'These are dependency policies. All of these policies must pass for the'
                .' profile to be assessed on the target.'
            );
            $render->table($headers, array_map(function (Policy $policy) {
                return [$policy->title, $policy->name, $policy->class, $policy->source];
            }, $dependencies));
        }

        $render->section('Policies');
        $headers = ['Title', 'Name', 'Severity', 'Class', 'Source'];
        $render->table($headers, $rows = array_map(function (Policy $policy) {
            return [$policy->title, $policy->name, $policy->severity->value, $policy->class, $policy->source];
        }, $policies));

        $render->text(count($rows) . ' policies.');

        return 0;
    }
}
