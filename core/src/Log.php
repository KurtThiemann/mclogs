<?php

use Aternos\Codex\Analysis\Analysis;
use Aternos\Codex\Log\AnalysableLogInterface;
use Aternos\Codex\Log\File\StringLogFile;
use Aternos\Codex\Log\LogInterface;
use Aternos\Codex\Minecraft\Detective\Detective;
use Printer\Printer;

class Log
{
    public static array $errorLogLevels = ["ERROR", "SEVERE", "FATAL", "CRITICAL", "EMERGENCY", "STDERR"];
    private bool $exists = false;
    private ?Id $id = null;
    private ?string $data = null;
    protected LogInterface $log;

    /**
     * @var Analysis
     */
    protected Analysis $analysis;

    /**
     * @var Printer
     */
    protected Printer $printer;

    /**
     * Log constructor.
     *
     * @param Id|null $id
     */
    public function __construct(Id $id = null)
    {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }

    /**
     * Load the log from storage
     */
    private function load()
    {
        $config = Config::Get('storage');

        if (!isset($config['storages'][$this->id->getStorage()])) {
            $this->exists = false;
            return;
        }

        /**
         * @var \Storage\StorageInterface $storage
         */
        $storage = $config['storages'][$this->id->getStorage()]['class'];

        $data = $storage::Get($this->id);

        if ($data === null) {
            $this->exists = false;
            return;
        } else {
            $this->data = $data;
            $this->exists = true;
        }

        $this->log = (new Detective())->setLogFile(new StringLogFile($this->data))->detect();
        $this->log->parse();
        $this->printer = (new Printer())->setLog($this->log)->setId($this->id);
        if ($this->log instanceof AnalysableLogInterface) {
            $this->analysis = $this->log->analyse();
        } else {
            $this->analysis = new Analysis();
        }
    }

    /**
     * Checks if the log exists
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Get the log
     *
     * @return LogInterface
     */
    public function get(): LogInterface
    {
        return $this->log;
    }

    /**
     * Get the log analysis
     *
     * @return Analysis
     */
    public function getAnalysis(): Analysis
    {
        return $this->analysis;
    }

    /**
     * @return Printer
     */
    public function getPrinter(): Printer
    {
        return $this->printer;
    }

    /**
     * Get the amount of lines in this log
     *
     * @return int
     */
    public function getLineNumbers(): int
    {
        return count(explode("\n", $this->data));
    }

    /**
     * Get the amount of error entries in the log
     *
     * @return int
     */
    public function getErrorCount(): int
    {
        $errorCount = 0;

        if (!$this->log) {
            return $errorCount;
        }

        foreach ($this->log as $entry) {
            if (in_array(strtoupper($entry->getLevel()), static::$errorLogLevels)) {
                $errorCount++;
            }
        }

        return $errorCount;
    }

    /**
     * Put data into the log
     *
     * @param string $data
     * @return ?Id
     */
    public function put(string $data): ?Id
    {
        $this->data = $data;
        $this->preFilter();

        $config = Config::Get('storage');

        /**
         * @var \Storage\StorageInterface $storage
         */
        $storage = $config['storages'][$config['storageId']]['class'];

        $this->id = $storage::Put($this->data);
        $this->exists = true;

        return $this->id;
    }

    /**
     * Renew the expiry timestamp to expand the ttl
     */
    public function renew()
    {
        $config = Config::Get('storage');

        /**
         * @var \Storage\StorageInterface $storage
         */
        $storage = $config['storages'][$this->id->getStorage()]['class'];

        $storage::Renew($this->id);
    }

    /**
     * Apply pre filters to data string
     */
    private function preFilter()
    {
        $config = Config::Get('filter');
        foreach ($config['pre'] as $preFilterClass) {
            /**
             * @var \Filter\Pre\PreFilterInterface $preFilterClass
             */

            $this->data = $preFilterClass::Filter($this->data);
        }
    }
}