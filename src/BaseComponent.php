<?php declare(strict_types = 1);

namespace Keboola\Component;

use ErrorException;
use Keboola\Component\Config\BaseConfig;
use Keboola\Component\Config\BaseConfigDefinition;
use Keboola\Component\Manifest\ManifestManager;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use function error_reporting;
use function file_get_contents;

/**
 * This is the core class that does all the heavy lifting. By default you don't need to setup anything. There are some
 * extension points for you to use if you want to customise the behavior.
 */
class BaseComponent
{
    /** @var BaseConfig */
    private $config;

    /** @var string */
    private $dataDir;

    /** @var ManifestManager */
    private $manifestManager;

    public function __construct()
    {
        $this->setEnvironment();

        $dataDir = getenv('KBC_DATADIR') === false ? '/data/' : (string)getenv('KBC_DATADIR');
        $this->setDataDir($dataDir);

        $this->loadConfig();

        $this->loadManifestManager();
    }

    /**
     * Prepares environment. Sets error reporting for the app to fail on any
     * error, warning or notice. If your code emits notices and cannot be
     * fixed, you can set `error_reporting` in `$application->run()` method.
     */
    public function setEnvironment(): void
    {
        error_reporting(E_ALL);
        set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext): bool {
            if (!(error_reporting() & $errno)) {
                // respect error_reporting() level
                // libraries used in custom components may emit notices that cannot be fixed
                return false;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }

    /**
     * Automatically loads configuration from datadir, instantiates specified
     * config class and validates it with specified confing definition class
     */
    protected function loadConfig(): void
    {
        $jsonContents = file_get_contents($this->dataDir . '/config.json');
        $jsonEncoder = new JsonEncoder();
        $configClass = $this->getConfigClass();
        $configDefinitionClass = $this->getConfigDefinitionClass();
        try {
            $this->config = new $configClass(
                $jsonEncoder->decode($jsonContents, JsonEncoder::FORMAT),
                new $configDefinitionClass()
            );
        } catch (InvalidConfigurationException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Override this method if you have custom config definition class. This
     * allows you to validate and require config parameters and fail fast if
     * there is a missing parameter.
     */
    protected function getConfigDefinitionClass(): string
    {
        return BaseConfigDefinition::class;
    }

    /**
     * Data dir is set without the trailing slash
     *
     * @param string $dataDir
     */
    protected function setDataDir(string $dataDir): void
    {
        $this->dataDir = rtrim($dataDir, '/');
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function getConfig(): BaseConfig
    {
        return $this->config;
    }

    public function getManifestManager(): ManifestManager
    {
        return $this->manifestManager;
    }

    /**
     * This is the main method for your code to run in. You have the `Config`
     * and `ManifestManager` ready as well as environment set up.
     */
    public function run(): void
    {
        // to be implemented in subclass
    }

    /**
     * Class of created config. It's useful if you want to implment getters for
     * parameters in your config. It's prefferable to accessing configuration
     * keys as arrays.
     */
    protected function getConfigClass(): string
    {
        return BaseConfig::class;
    }

    /**
     * Loads manifest manager with application's datadir
     */
    protected function loadManifestManager(): void
    {
        $this->manifestManager = new ManifestManager($this->dataDir);
    }
}
