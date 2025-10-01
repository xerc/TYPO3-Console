<?php
declare(strict_types=1);
namespace Helhum\Typo3Console\Database\Process;

/*
 * This file is part of the TYPO3 Console project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read
 * LICENSE file that was distributed with this source code.
 *
 */

use Helhum\Typo3Console\Mvc\Cli\InteractiveProcess;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;

class MysqlCommand
{
    /**
     * @var array
     */
    private $dbConfig;

    /**
     * @var string
     */
    private static $mysqlTempFile;

    /**
     * @var ConsoleOutput
     */
    private $output;

    public function __construct(array $dbConfig, ?ConsoleOutput $output = null)
    {
        $this->dbConfig = $dbConfig;
        $this->output = $output ?: new ConsoleOutput(); // output being optional is @deprecated. Will become required in 6.0
    }

    /**
     * @param array $additionalArguments
     * @param resource $inputStream
     * @param null $outputCallback @deprecated will be removed with 6.0
     * @param bool $interactive
     * @return int
     */
    public function mysql(array $additionalArguments = [], $inputStream = STDIN, $outputCallback = null, $interactive = false)
    {
        $argv = array_merge(['mysql'], $this->buildConnectionArguments(), $additionalArguments);

        if (isset($this->dbConfig['password'])) {
            $command = implode(' ', array_map('escapeshellarg', array_merge($argv, ['-p'])));

            $interactiveProcess = new InteractiveProcess();
            return $interactiveProcess->run(
                $command,                           // CMD
                $inputStream,                       // STDIN
                $this->dbConfig['password'] . "\n"  // TTY
            );
        }

        $process = new Process($argv, null, null, $inputStream, 0.0);

        if ($interactive) {
            $interactiveProcess = new InteractiveProcess();
            return $interactiveProcess->run($process->getCommandLine(), $inputStream, null);
        }

        return $process->run($this->buildDefaultOutputCallback($outputCallback));
    }

    /**
     * @param array $additionalArguments
     * @param callable|null $outputCallback @deprecated will be removed with 6.0
     * @param string $connectionName
     * @return int
     */
    public function mysqldump(array $additionalArguments = [], $outputCallback = null, string $connectionName = 'Default'): int
    {
        $argv = array_merge(['mysqldump'], $this->buildConnectionArguments(), $additionalArguments);

        echo PHP_EOL . sprintf('-- Dump of TYPO3 Connection "%s"', $connectionName) . PHP_EOL;

        if (isset($this->dbConfig['password'])) {
            $command = implode(' ', array_map('escapeshellarg', array_merge($argv, ['-p'])));

            $interactiveProcess = new InteractiveProcess();
            return $interactiveProcess->run(
                $command,                           // CMD
                null,                               // STDIN
                $this->dbConfig['password'] . "\n"  // TTY
            );
        }

        $process = new Process($argv, null, null, null, 0.0);
        return $process->run($this->buildDefaultOutputCallback($outputCallback));
    }

    /**
     * @param callable $outputCallback
     * @return callable
     */
    private function buildDefaultOutputCallback($outputCallback): callable
    {
        if (!is_callable($outputCallback)) {
            $outputCallback = function ($type, $data) {
                if (Process::OUT === $type) {
                    echo $data;
                } elseif (Process::ERR === $type) {
                    $this->output->getErrorOutput()->write($data);
                }
            };
        }

        return $outputCallback;
    }

    private function buildConnectionArguments(): array
    {
        $arguments = [];

        if (!empty($this->dbConfig['user'])) {
            $arguments[] = '-u';
            $arguments[] = $this->dbConfig['user'];
        }
        if (!empty($this->dbConfig['host'])) {
            $arguments[] = '-h';
            $arguments[] = $this->dbConfig['host'];
        }
        if (!empty($this->dbConfig['port'])) {
            $arguments[] = '-P';
            $arguments[] = $this->dbConfig['port'];
        }
        if (!empty($this->dbConfig['unix_socket'])) {
            $arguments[] = '-S';
            $arguments[] = $this->dbConfig['unix_socket'];
        }
        if (isset($this->dbConfig['driverOptions']['flags']) && (int)$this->dbConfig['driverOptions']['flags'] === MYSQLI_CLIENT_SSL) {
            $arguments[] = '--ssl';
        }
        if (!empty($this->dbConfig['dbname'])) {
            $arguments[] = $this->dbConfig['dbname'];
        }

        return $arguments;
    }
}
