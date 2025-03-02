<?php

namespace Drutiny\Console;

use Composer\Semver\Comparator;
use Drutiny\Attribute\Plugin;
use Drutiny\Attribute\PluginField;
use Drutiny\Http\Client;
use Drutiny\Plugin as DrutinyPlugin;
use Drutiny\Plugin\FieldType;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Process\Process;

#[Plugin(name: 'github')]
#[PluginField(
    name: 'personal_access_token',
    description: "github personal oauth token",
    type: FieldType::CREDENTIAL
)]
#[PluginField(
    name: 'last_checked',
    description: "the date updates were last checked.",
    type: FieldType::STATE
)]
#[Autoconfigure(constructor: 'create')]
class UpdateManager
{

    protected GuzzleHttpClient $client;

    protected const GITHUB_API_URL = 'https://api.github.com';
    protected const GITHUB_ACCEPT_VERSION = 'application/vnd.github.v3+json';

    public function __construct(
        protected string $name,
        protected Application $app,
        protected DrutinyPlugin $plugin,
        protected LoggerInterface $logger,
        Client $httpClient,
    ) {
        if (!$this->plugin->isInstalled()) {
            return;
        }
        
        $headers = [
            'User-Agent' => $this->app->getName() . '/' .  $this->app->getVersion(),
            'Accept' => self::GITHUB_ACCEPT_VERSION,
            'Accept-Encoding' => 'gzip',
            'Authorization' =>  'token ' . $this->plugin->personal_access_token
        ];
        $this->client = $httpClient->create([
            'base_uri' => self::GITHUB_API_URL,
            'headers' => $headers,
            'decode_content' => 'gzip',
        ]);
    }

    public static function create(ContainerInterface $container, DrutinyPlugin $plugin):self
    {
        $composer_json = json_decode(file_get_contents(DRUTINY_LIB . '/composer.json'), true);

        return new static(
            name: $composer_json['name'],
            app: $container->get(Application::class),
            httpClient: $container->get(Client::class),
            plugin: $plugin,
            logger: $container->get(LoggerInterface::class),
        );
    }

    public function checkForUpdates(InputInterface $input, OutputInterface $output, ?array $args = null):int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->plugin->isInstalled()) {
            Command::INVALID;
        }

        if (isset($this->plugin->last_checked) && (time() - $this->plugin->last_checked) < 86400) {
            return Command::INVALID;
        }

        $this->plugin->saveAs([
            'last_checked' => time(),
        ]);

        if (!$release = $this->updatesAvailable()) {
          // $io->success("No new updates.");
            return Command::INVALID;
        }

        $io->title('New update available: ' . $release['tag_name']);

        if (!$io->confirm('Would you like to download and install the newest version ('.$release['tag_name'].')?')) {
            return Command::INVALID;
        }
        $asset = $io->choice('Which version would you like to download?', array_map(fn ($a) => $a['name'], $release['assets']));

        $download = array_filter($release['assets'], function ($a) use ($asset) {
            return $a['name'] == $asset;
        });
        $download = reset($download);

        $this->install(...$download);

        return Command::SUCCESS;
    }

    public function wrap(?array $args = null): int
    {
        $bin = $GLOBALS['_composer_bin_dir'] . '/drutiny';

        if ($args == null) {
            $args ??= $_SERVER['argv'];
            $args[0] = $bin;
        } else {
            array_unshift($args, $bin);
        }
        $process = new Process($args);
        $process->setTty(Process::isTtySupported());
        $process->setPty(Process::isPtySupported());
        $process->setTimeout(0);
        $process->run();

        return $process->getExitCode();
    }

    /**
     * Get the latest update or false if current version is the latest.
     */
    protected function updatesAvailable():bool|array
    {
        if (strpos($this->app->getVersion(), 'dev') !== false) {
            return false;
        }
        $response = $this->client->get('repos/' . $this->name . '/releases');
        $releases = json_decode($response->getBody(), true);

        $latest_release = current($releases);
        $new_version = $latest_release['tag_name'];

        return Comparator::greaterThan($new_version, $this->app->getVersion()) ? $latest_release : false;
    }

    protected function install(string $name, string $id, string $content_type, ...$args): void
    {
        $tmpfile = tempnam(sys_get_temp_dir(), $name);
        $resource = fopen($tmpfile, 'w');

        $this->logger->notice("Downloading {$name}...");

        $response = $this->client->get('repos/' . $this->name . '/releases/assets/' . $id, [
          'headers' => [
            'Accept' => $content_type,
          ],
        ]);

        fwrite($resource, $response->getBody());
        fclose($resource);

        chmod($tmpfile, 0766);

        $process = new Process([$tmpfile, '--version']);
        $this->logger->notice("{$name} downloaded to $tmpfile.");
        $status = $process->setTty(true)->setPty(true)->run();
        unlink($tmpfile);
    }
}
