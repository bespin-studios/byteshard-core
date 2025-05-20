<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\PGSQL\PDO;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\ParametersInterface;
use PDOException;
use byteShard\Exception;
use PDO;
use PDOStatement;

/**
 * Class Connection
 * @package byteShard\Internal\Database\PGSQL\PDO
 */
class Connection extends BaseConnection
{
    /** @var PDO|null */
    protected ?object $connection = null;
    protected ?int    $port       = 5432;
    protected string  $charset    = 'utf8';
    protected string  $schema     = 'public'; // if not other specified use public default namespace
    protected array   $options    = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    /**
     * Connection constructor.
     * @param ConnectionType $type
     * @param ParametersInterface|null $environment
     */
    public function __construct(ConnectionType $type = ConnectionType::READ, ?ParametersInterface $environment = null)
    {
        parent::__construct($type, $environment);
    }

    /**
     * function to create PDO connection
     * @throws Exception
     */
    public function connect(): void
    {
        $dsn = 'pgsql:host = '.$this->server.';'.($this->port !== null ? 'port = '.$this->port : '').';dbname = '.$this->db.";options = '-c client_encoding=utf8'";
        try {
            $this->connection = new PDO($dsn, $this->user, $this->pass, $this->options);
        } catch (PDOException $e) {
            throw new Exception('Connection failed : '.$e->getMessage(), 110500001);
        }
        $this->connection->exec('SET search_path TO '.$this->schema);
        parent::connect();
    }

    /**
     * function to execute query
     * @param string $query
     * @return bool
     * @throws Exception
     */
    public function execute(string $query): bool
    {
        try {
            if (!isset($this->connection)) {
                $this->connect();
            }
            return $this->connection?->exec($query) !== false;
        } catch (PDOException $e) {
            throw new Exception('Error occurred : '.$e->getMessage().'<br>Executing query: '.$query, 110500002);
        }
    }

    /**
     * close PDO connection
     */
    public function disconnect(): void
    {
        $this->connection = null;
        parent::disconnect();
    }

    /**
     * function to get connection
     */
    public function getConnection(bool $newConnection = false): object
    {
        return parent::getConnection($newConnection);
    }

    /**
     * @param string $db
     * @return Connection
     * @throws Exception
     */
    public function setDB(string $db): self
    {
        $this->db = $db;
        if ($this->connected === true) {
            $query = $this->connection?->query('select current_database()');
            if ($query instanceof PDOStatement) {
                $currentDb = $query->fetch();
                if (is_array($currentDb) && array_key_exists('current_database', $currentDb) && $currentDb['current_database'] !== $this->db) {
                    throw new Exception('Failed to select database \''.$db.'\'', 110500003);
                }
            }
        }
        return $this;
    }
}
