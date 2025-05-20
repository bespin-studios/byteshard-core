<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Database\Struct\Parameters;
use byteShard\Enum\LogLevel;
use JsonSerializable;

/**
 * Class BaseConnection
 * @package byteShard\Internal\Database
 */
abstract class BaseConnection implements ConnectionInterface, JsonSerializable
{
    protected ?object $connection = null;
    protected bool    $connected  = false;
    protected string  $server     = '';
    protected ?int    $port       = null;
    protected string  $db         = '';
    protected string  $user       = '';
    protected string  $pass       = '';
    protected string  $schema     = '';

    protected string $login_user = '';
    protected string $login_pass = '';
    protected string $read_user  = '';
    protected string $read_pass  = '';
    protected string $write_user = '';
    protected string $write_pass = '';
    protected string $admin_user = '';
    protected string $admin_pass = '';


    protected ConnectionType $type;

    protected ?string $sqLiteEncryptionKey = null;

    private ?ParametersInterface $db_params;
    private ?string              $name;

    /**
     * escape character to open table or column encapsulation
     * @var string
     */
    protected string $escapeStart = '';

    /**
     * escape character to close table or column encapsulation
     * @var string
     */
    protected string $escapeEnd = '';

    /**
     * BaseConnection constructor.
     */
    public function __construct(ConnectionType $type = ConnectionType::READ, ?ParametersInterface $connectionParameters = null, ?string $name = null)
    {
        $this->type      = $type;
        $this->db_params = $connectionParameters;
        $this->name      = $name;
        if ($connectionParameters === null) {
            global $env;
            if ($env instanceof ParametersInterface) {
                $connectionParameters = $env;
            }
        }
        if ($connectionParameters !== null) {
            $parameters = $connectionParameters->getDbParameters($type, $name);
            if ($parameters->server !== '') {
                $this->server = $parameters->server;
            }
            if ($parameters->port !== '') {
                $this->port = $parameters->port;
            }
            if ($parameters->database !== '') {
                $this->db = $parameters->database;
            }
            if (property_exists($this, 'schema') && $parameters->schema !== '') {
                $this->schema = $parameters->schema;
            }
            if ($this->user === '' || $this->pass === '') {
                switch ($type) {
                    case ConnectionType::LOGIN:
                        if ($this->user === '') {
                            $this->user = $this->login_user === '' ? $parameters->username : $this->login_user;
                        }
                        if ($this->pass === '') {
                            $this->pass = $this->login_pass === '' ? $parameters->password : $this->login_pass;
                        }
                        break;
                    case ConnectionType::READ:
                        if ($this->user === '') {
                            $this->user = $this->read_user === '' ? $parameters->username : $this->read_user;
                        }
                        if ($this->pass === '') {
                            $this->pass = $this->read_pass === '' ? $parameters->password : $this->read_pass;
                        }
                        break;
                    case ConnectionType::WRITE:
                        if ($this->user === '') {
                            $this->user = $this->write_user === '' ? $parameters->username : $this->write_user;
                        }
                        if ($this->pass === '') {
                            $this->pass = $this->write_pass === '' ? $parameters->password : $this->write_pass;
                        }
                        break;
                    case ConnectionType::ADMIN:
                        if ($this->user === '') {
                            $this->user = $this->admin_user === '' ? $parameters->username : $this->admin_user;
                        }
                        if ($this->pass === '') {
                            $this->pass = $this->admin_pass === '' ? $parameters->password : $this->admin_pass;
                        }
                        break;
                }
            }
        }
    }

    public function getConnection(bool $newConnection = false): ?object
    {
        if ($newConnection === true) {
            $parameters = new Parameters($this->server, $this->port, $this->db, $this->user, $this->pass);
            if (property_exists($this, 'schema') && $this->schema !== '') {
                $parameters->schema = $this->schema;
            }
            $connectionClass = get_class($this);
            $connection      = new $connectionClass($this->type, $this->db_params, $this->name);
            $connection->setParameters($parameters);
            $connection->connect();
            return $connection;
        }
        if ($this->connected === false) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * @param Parameters $parameters
     * @return $this
     */
    public function setParameters(Parameters $parameters): self
    {
        $this->server = $parameters->server;
        $this->port   = $parameters->port;
        $this->db     = $parameters->database;
        $this->user   = $parameters->username;
        $this->pass   = $parameters->password;
        if (property_exists($this, 'schema') && $parameters->schema !== '') {
            $this->schema = $parameters->schema;
        }
        return $this;
    }

    public function setServer(string $server): self
    {
        $this->server = $server;
        return $this;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function setDB(string $db): self
    {
        $this->db = $db;
        return $this;
    }

    public function getConnectionState(): bool
    {
        return $this->connected;
    }

    public function getType(): ConnectionType
    {
        return $this->type;
    }

    /**
     * BaseConnection connect. Doesn't open a connection but needs to be called by all child classes to initialize metadata
     */
    public function connect(): void
    {
        $this->connected = true;
    }

    /**
     * BaseConnection disconnect. Doesn't close a connection but needs to be called by all child classes to initialize metadata
     */
    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function getEscapeStart(): string
    {
        return $this->escapeStart;
    }

    public function getEscapeEnd(): string
    {
        return $this->escapeEnd;
    }

    public function getDB(): string
    {
        return $this->db;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function __debugInfo(): array
    {
        if (defined('DISCLOSE_CREDENTIALS') && DISCLOSE_CREDENTIALS === true && defined('LOGLEVEL') && LOGLEVEL === LogLevel::DEBUG) {
            return get_object_vars($this);
        }
        $debug_info               = get_object_vars($this);
        $debug_info['pass']       = $this->pass === '' ? '' : 'CONFIDENTIAL';
        $debug_info['login_pass'] = $this->login_pass === '' ? '' : 'CONFIDENTIAL';
        $debug_info['read_pass']  = $this->read_pass === '' ? '' : 'CONFIDENTIAL';
        $debug_info['write_pass'] = $this->write_pass === '' ? '' : 'CONFIDENTIAL';
        $debug_info['admin_pass'] = $this->admin_pass === '' ? '' : 'CONFIDENTIAL';
        return $debug_info;
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
