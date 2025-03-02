<?php

namespace Drutiny\Console\Command;

use Drutiny\Audit\DynamicParameterType;
use Drutiny\LanguageManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Drutiny\PolicyFactory;
use Symfony\Component\Yaml\Inline;
use Twig\Environment;

/**
 *
 */
class PolicyShowCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;
    public function __construct(
        protected PolicyFactory $policyFactory,
        protected LanguageManager $languageManager,
        protected Environment $twig
    ) {
        parent::__construct();
    }
  

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:show')
        ->setDescription('Show a policy definition.')
        ->addArgument(
            'policy',
            InputArgument::REQUIRED,
            'The name of the profile to show.'
        )
        ->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'An output format. Default YAML. Support: yaml, json',
            'yaml'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);
        $policy = $this->policyFactory->loadPolicyByName($input->getArgument('policy'));
        $export = $policy->export();

        foreach (['description', 'success', 'remediation', 'failure', 'warning'] as $field) {
            if (isset($export[$field])) {
                $export[$field] = str_replace("\r", '', $export[$field]);
            }
        }

        $key_order = [
          'title', 'name', 'uuid', 'class', 'description', 'language',
          'tags', 'severity', 'type',
          'depends', 'build_parameters', 'parameters',
          'success', 'failure', 'remediation', 'warning',
          'chart'
        ];

        $yaml = [];
        // Set the YAML file in a given order.
        foreach ($key_order as $key) {
            $yaml[$key] = $export[$key] ?? null;
        }

        // Catch all, add any missed fields.
        foreach (array_keys($export) as $key) {
            $yaml[$key] = $export[$key];
        }

        switch ($input->getOption('format')) {
            case 'json':
                $format = json_encode($yaml, JSON_PRETTY_PRINT);
                break;
            default:
                $format = Yaml::dump($yaml, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                $format = $this->colorizeYaml($format, $yaml, '', $this->getHighlightKeywords());
                break;
        }

        $output->write($format);

        return 0;
    }

    protected function getHighlightKeywords(): array
    {
        $keywords = [
        '{%' => '<comment>{%</comment>',
        '%}' => '<comment>%}</comment>',
        '{{' => '<comment>{{</comment>',
        '}}' => '<comment>}}</comment>',
        ];

        foreach (array_keys($this->twig->getFilters()) as $filter) {
            $keywords["|$filter"] = '|<fg=yellow>'.$filter.'</>';
            $keywords["| $filter"] = '| <fg=yellow>'.$filter.'</>';
        }

        foreach (array_keys($this->twig->getFunctions()) as $function) {
            $keywords["$function("] = '<fg=magenta>' . $function . '</>(';
        }

        foreach (array_keys($this->twig->getTests()) as $test) {
            $keywords[$test] = '<fg=cyan>'.$test.'</>';
        }

        return $keywords;
    }

    protected function colorizeYaml(string $yaml, array $data, string $prefix = '', array $keywords = []): string
    {
        $colorized_yaml = [];
        $lines = explode("\n", $yaml);

        $value = current($data);
        $key = key($data);

        $wait_till_key_is_found = false;

        foreach ($lines as $line) {
            $line = $prefix.$line;

          // Highlight simple twig syntax.
            $line = strtr($line, $keywords);
          // $line = preg_replace('/([a-z0-9A-Z_]+)\(/', '<fg=yellow>$1</>(', $line);
        
            $regex_key = preg_quote(Inline::dump($key));
        
            if (!preg_match("/^(\s*)$regex_key:/", $line, $matches)) {
              // If a recursive call colorized assoc array output then we dont'
              // want to add lines here until the next key is found.
                if (!$wait_till_key_is_found && (!is_array($value) || array_is_list($value))) {
                    $colorized_yaml[] = $line;
                }
                continue;
            }
            $wait_till_key_is_found = false;

            $line = preg_replace_callback("/^(\s*)($regex_key):/", function ($matches) {
                $tag = match (DynamicParameterType::fromParameterName($matches[2])) {
                    DynamicParameterType::EVALUATE => 'magenta',
                    DynamicParameterType::REPLACE => 'yellow',
                    DynamicParameterType::STATIC => 'cyan',
                    default => 'green'
                };
                return strtr($matches[0], [
                $matches[2] => "<fg=$tag>" . $matches[2] . "</>"
                ]);
            }, $line);

            $colorized_yaml[] = $line;
        
            if (is_array($value) && !array_is_list($value)) {
                $colorized_lines = explode("\n", $this->colorizeYaml(Yaml::dump($value, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK), $value, $prefix . '    ', $keywords));

                foreach ($colorized_lines as $colored_line) {
                    $colorized_yaml[] = $colored_line;
                }
                $wait_till_key_is_found = true;
            }

            $value = next($data);
            $key = key($data);
        }

        return implode("\n", $colorized_yaml);
    }
}
