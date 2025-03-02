<?php

namespace Drutiny\Console\Command;

use DateTime;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\Entity\DataBag;
use Drutiny\Policy;
use Drutiny\Policy\DependencyBehaviour;
use Drutiny\Profile;
use Drutiny\Profile\FormatDefinition;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\Report;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

#[AsCommand(
    name: 'report:render',
    description: 'Render a report from a json output file produced by the profile:run command.'
)]
class ReportRenderCommand extends Command
{

    public function __construct(
        protected FormatFactory $formatFactory,
        protected TargetFactory $targetFactory,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('json-file', InputArgument::REQUIRED, 'The json file rendered by profile:run.');
        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Specify which output format to render the report (terminal, html, json). Defaults to terminal.',
            ['terminal']
        )
        ->addOption(
            'report-dir',
            'o',
            InputOption::VALUE_OPTIONAL,
            'For file based formats, use this option to write report to a file directory. Drutiny will automate a filepath if the option is omitted',
            getenv('DRUTINY_REPORT_DIR') ?: getenv('PWD')
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$json = file_get_contents($input->getArgument('json-file'))) {
            throw new InvalidArgumentException("json-file does not exist.");
        }
        if (($data = json_decode($json, true)) === null) {
            throw new InvalidArgumentException("json-file did not contain valid json.");
        }
        unset($data['profile']['reportingPeriodStart'], $data['profile']['reportingPeriodEnd']);

        $profile = new Profile(...$data['profile']);
        $profile->setReportingPeriod(new DateTime($data['reportingPeriodStart']), new DateTime($data['reportingPeriodEnd']));

        $report = new Report(
            uri: $data['uri'],
            profile: $profile,
            target: $this->loadTarget($data['uri'], $data['target']),
            results: array_map(fn ($r) => $this->loadAuditResponse($r), $data['results']),
            timing: $data['timing']
        );

        $io = new SymfonyStyle($input, $output);

        foreach ($input->getOption('format') as $format) {
            $options = $data['profile']['format'][$format] ?? ['name' => $format];
            $format = $this->formatFactory->create($format, new FormatDefinition(...$options));

            if ($format instanceof FilesystemFormatInterface) {
                $format->setWriteableDirectory($input->getOption('report-dir'));
            }

            $format->setNamespace($this->getReportNamespace($report));
            $format->render($report);
            foreach ($format->write() as $written_location) {
                 $io->success("Written $written_location");
            }
        }
        return Command::SUCCESS;
    }

    /**
     * Determine a default filepath.
     */
    protected function getReportNamespace(Report $report):string
    {
        return strtr('target-profile-uri-date.language', [
          'uri' => strtr($report->uri, [
            ':' => '',
            '/' => '',
            '?' => '',
            '#' => '',
            '&' => '',
          ]),
          'target' => $report->target->getTargetName(),
          'profile' => $report->profile->name,
          'date' => $report->reportingPeriodEnd->format('Ymd-His'),
          'language' => $report->profile->language,
        ]);
    }

    /**
     * Load a target from report metadata.
     */
    protected function loadTarget(string $uri, array $metadata = []): TargetInterface
    {
        $target = $this->targetFactory->create('none:none', $uri);
        foreach ($metadata as $key => $value) {
            try {
                $value = is_array($value) ? new DataBag($value) :  $value;
                $target->setProperty($key, $value);
            } catch (NoSuchPropertyException $e) {
            }
        }
            
        return $target;
    }

    /**
     * Load an audit response object.
     */
    protected function loadAuditResponse(array $response): AuditResponse
    {
        unset($response['policy']['rendered']);
        $response['policy']['type'] = $response['policy']['type']['value'];
        $response['policy']['severity'] = $response['policy']['severity']['value'];
        $response['policy']['tags'] = array_map(fn(array $t) => $t['name'], $response['policy']['tags']);
        $response['policy']['depends'] = array_map(function (array $depends) {
            if (isset($depends['onFail'])) {
                $depends['on_fail'] = DependencyBehaviour::from($depends['onFail']['value'])->label();
                unset($depends['onFail']);
            }
            return $depends;
        }, $response['policy']['depends']);

        $policy = new Policy(...$response['policy']);
        $state = State::fromValue($response['state']['value']);
        return new AuditResponse(
            policy: new Policy(...$response['policy']),
            state: $state->withPolicyType($policy->type),
            tokens: $response['tokens'],
            timestamp: $response['timestamp'],
            timing: $response['timing']
        );
    }
}
