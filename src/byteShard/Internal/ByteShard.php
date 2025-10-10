<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Enum\LogFormat;
use byteShard\Enum\LogLevel;
use byteShard\Enum\LogLocation;
use byteShard\Environment;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ByteShard
{
    protected Config            $config;
    protected ?ErrorHandler     $errorHandler  = null;
    private ?StreamHandler      $streamHandler = null;
    private ?FormatterInterface $formatter     = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Initialize byteShard
     * @return Environment
     */
    public function init(): Environment
    {
        ini_set('zlib.output_compression', 'On');
        ini_set('zlib.output_compression_level', 1);
        ini_set('max_execution_time', '600');

        $this->errorHandler = new ErrorHandler(
            $this->config->getLogPath(),
            trim($this->config->getUrl(), '/').'/'.trim($this->config->getUrlContext(), '/'),
            ErrorHandler::RESULT_OBJECT_HTML,
            $this->config->getLogLocation()
        );

        // create a stream handler and two log channels and pass them to the error handler
        if ($this->config->getLogLocation() === LogLocation::STDERR) {
            $this->streamHandler = new StreamHandler('php://stderr', LogLevel::getMonologLevel($this->config->getLogLevel()));
            $deprecatedHandler   = $this->streamHandler;
        } else {
            $this->streamHandler = new StreamHandler($this->config->getLogFilePath(), LogLevel::getMonologLevel($this->config->getLogLevel()));
            $deprecatedHandler   = new StreamHandler($this->config->getLogPath().'/deprecated.log', LogLevel::getMonologLevel($this->config->getLogLevel()));
        }
        if ($this->config->getLogFormatting() === LogFormat::JSON) {
            $this->formatter = new JsonFormatter();
        } else {
            $this->formatter = new LineFormatter(null, null, false, true);
        }
        $this->streamHandler->setFormatter($this->formatter);
        $bsLogger = new Logger('byteShard');
        $bsLogger->pushHandler($this->streamHandler);
        $defaultLogger = new Logger($this->config->getLogChannelName());
        $defaultLogger->pushHandler($this->streamHandler);
        $deprecatedLogger = new Logger('deprecated');
        $deprecatedLogger->pushHandler($deprecatedHandler);
        $deprecatedHandler->setFormatter($this->formatter);
        $this->errorHandler->addLogger('byteShard', $bsLogger);
        $this->errorHandler->addLogger('default', $defaultLogger);
        $this->errorHandler->addLogger('deprecated', $deprecatedLogger);
        Debug::addLogger('byteShard', $bsLogger);
        Debug::addLogger('default', $defaultLogger);
        return \byteShard\Config\ByteShard::getInstance($this->config);
    }

    public function getErrorHandler(): ?ErrorHandler
    {
        return $this->errorHandler;
    }

    public function setLogFormatter(FormatterInterface $formatter): self
    {
        $this->formatter = $formatter;
        $this->streamHandler->setFormatter($formatter);
        return $this;
    }
}