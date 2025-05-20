<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\MySQL\MySQLi;

use byteShard\Exception;
use byteShard\Internal\Config;
use byteShard\Internal\Database\MySQL\Connection as MySQLConnection;

use mysqli;
use mysqli_sql_exception;

/**
 * Class Connection
 * @exceptionId 00001
 * @package byteShard\Internal\Database\MySQL
 */
class Connection extends MySQLConnection
{
    /**
     * @var ?mysqli
     */
    protected ?object $connection;
    protected ?int    $port        = 3306;
    protected string  $escapeStart = '`';
    protected string  $escapeEnd   = '`';

    /**
     * @throws \Exception
     */
    public function connect(): void
    {
        mysqli_report(MYSQLI_REPORT_STRICT);
        if (class_exists('\config') && is_subclass_of('\config', Config::class)) {
            $config  = new \config();
            $options = $config->getDbOptions();
            if (empty($options)) {
                try {
                    $this->connection = new mysqli($this->server, $this->user, $this->pass, $this->db, (int)$this->port);
                } catch (mysqli_sql_exception $e) {
                    $exception = new Exception($e->getMessage(), 100001001);
                    $exception->setTrace($e->getTrace());
                    $exception->setTraceLogFunctionArgumentsIndexConfidential(array('__construct' => 2));
                    throw $exception;
                }
            } else {
                $resource = mysqli_init();
                if ($resource !== false) {
                    $this->connection = $resource;
                }
                if (isset($options['verifySSL']) && is_bool($options['verifySSL'])) {
                    $this->connection?->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $options['verifySSL']);
                }
                if (isset($options['sslSet']) && count($options['sslSet']) === 5) {
                    $this->connection?->ssl_set(...$options['sslSet']);
                }
                try {
                    $this->connection?->real_connect($this->server, $this->user, $this->pass, $this->db, (int)$this->port, null, MYSQLI_CLIENT_SSL);
                } catch (mysqli_sql_exception $e) {
                    $exception = new Exception($e->getMessage(), 100001003);
                    $exception->setTrace($e->getTrace());
                    $exception->setTraceLogFunctionArgumentsIndexConfidential(array('__construct' => 2));
                    throw $exception;
                }
            }

            if ($this->connection?->connect_errno) {
                $exception = new Exception('Failed to connect to MySQL Server', 100001002);
                $exception->setInfo('Database: '.$this->db, 'Port: '.$this->port, 'Error: '.$this->connection?->connect_error, 'MySQL Error Number: '.$this->connection?->connect_errno);
                throw $exception;
            }
            $this->connection?->set_charset('utf8');
            if ($this->db !== '') {
                $this->connection?->select_db($this->db);
            }
            parent::connect();
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function execute(string $query): bool
    {
        if ($this->connected === false) {
            $this->connect();
        }
        $result = $this->connection?->query($query);
        if ($this->connection?->errno) {
            throw new Exception('Failed to execute query: '.$query."<br>\n Error: ".$this->connection->error.' ('.$this->connection->errno.')', 100001004);
        }
        return $result !== false;
    }

    /**
     * @throws \Exception
     */
    public function setDB(string $db): self
    {
        $this->db = $db;
        if ($this->connected === true) {
            if ($this->connection?->select_db($db) === false) {
                throw new \Exception('Failed to select database \''.$db.'\'');
            }
        }
        return $this;
    }

    public function disconnect(): void
    {
        if ($this->connected === true) {
            $this->connection?->close();
            parent::disconnect();
        }
    }

    public function getConnection(bool $newConnection = false): ?object
    {
        return parent::getConnection($newConnection);
    }
}
