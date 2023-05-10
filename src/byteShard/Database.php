<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Internal\Database\ParametersInterface;
use byteShard\Internal\Database\PGSQL;
use byteShard\Internal\Debug;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\MySQL;

/**
 * Class Database
 * @package byteShard
 */
class Database
{
    /**
     * @param ConnectionType $accessType
     * @param ParametersInterface|null $parameters
     * @return BaseConnection
     * @throws Exception
     */
    public static function getConnection(ConnectionType $accessType = ConnectionType::READ, ParametersInterface $parameters = null): BaseConnection
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MYSQL_PDO:
                return new MySQL\PDO\Connection($accessType, $parameters);
            case Environment::DRIVER_MySQL_mysqli:
                return new MySQL\MySQLi\Connection($accessType, $parameters);
            case Environment::DRIVER_PGSQL_PDO:
                return new Internal\Database\PGSQL\PDO\Connection($accessType, $parameters);
        }
        throw new Exception('no DB Type defined');
    }

    /**
     * function to get connection to access data/records
     * @param BaseConnection|null $connection
     * @return MySQL\MySQLi\Recordset|PGSQL\PDO\Recordset
     * @throws Exception
     */
    public static function getRecordset(BaseConnection $connection = null): MySQL\MySQLi\Recordset|PGSQL\PDO\Recordset
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MySQL_mysqli:
                return new MySQL\MySQLi\Recordset($connection !== null ? $connection : self::getConnection());
            case Environment::DRIVER_PGSQL_PDO:
                return new PGSQL\PDO\Recordset($connection !== null ? $connection : self::getConnection());
        }
        throw new Exception('no DB Type defined');
    }

    /**
     * @return string
     */
    public static function getColumnEscapeStart(): string
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MySQL_mysqli:
                $connection = new MySQL\MySQLi\Connection();
                return $connection->getEscapeStart();
        }
        return '';
    }

    /**
     * @return string
     */
    public static function getColumnEscapeEnd(): string
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MySQL_mysqli:
                $connection = new MySQL\MySQLi\Connection();
                return $connection->getEscapeEnd();
        }
        return '';
    }

    /**
     * function to get set of records from database/table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getArray(string $query, array $parameters = [], BaseConnection $connection = null): array
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MySQL_mysqli:
                return MySQL\MySQLi\Recordset::getArray($query, $parameters, $connection);
            case Environment::DRIVER_MYSQL_PDO:
                return MySQL\PDO\Recordset::getArray($query, $parameters, $connection);
            case Environment::DRIVER_PGSQL_PDO:
                return PGSQL\PDO\Recordset::getArray($query, $parameters, $connection);
            default:
                Debug::debug('No DB Driver specified');
                break;
        }
        return [];
    }

    public static function getColumn(string $query, array $parameters = [], BaseConnection $connection = null): array
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MYSQL_PDO:
                return MySQL\PDO\Recordset::getColumn($query, $parameters, $connection);
            default:
                Debug::debug('No DB Driver specified');
                break;
        }
        return [];
    }

    /**
     * @API
     * @param string $query
     * @param string $indexColumn
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getIndexArray(string $query, string $indexColumn, array $parameters = [], BaseConnection $connection = null): array
    {
        global $dbDriver;
        switch ($dbDriver) {
            case Environment::DRIVER_MySQL_mysqli:
                return MySQL\MySQLi\Recordset::getIndexArray($query, $indexColumn, $parameters, $connection);
            case Environment::DRIVER_MYSQL_PDO:
                return MySQL\PDO\Recordset::getIndexArray($query, $indexColumn, $parameters, $connection);
            case Environment::DRIVER_PGSQL_PDO:
                return PGSQL\PDO\Recordset::getArray($query, $parameters, $connection);
            default:
                Debug::debug('No DB Driver specified');
                break;
        }
        return [];
    }

    /**
     * function to get single record from database/table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return object|null
     * @throws Exception
     */
    public static function getSingle(string $query, array $parameters = [], BaseConnection $connection = null): ?object
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MySQL_mysqli => MySQL\MySQLi\Recordset::getSingle($query, $parameters, $connection),
            Environment::DRIVER_MYSQL_PDO    => MySQL\PDO\Recordset::getSingle($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO    => PGSQL\PDO\Recordset::getSingle($query, $parameters, $connection),
            default                          => null,
        };
    }

    /**
     * function to insert record into a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return bool|int
     * @throws Exception
     */
    public static function insert(string $query, array $parameters = [], BaseConnection $connection = null): int|bool
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MYSQL_PDO => MySQL\PDO\Recordset::insert($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO => PGSQL\PDO\Recordset::insert($query, $parameters, $connection),
            default                       => false,
        };
    }

    /**
     * function to delete records from table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int
     * @throws Exception
     */
    public static function delete(string $query, array $parameters = [], BaseConnection $connection = null): int
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MYSQL_PDO => MySQL\PDO\Recordset::delete($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO => PGSQL\PDO\Recordset::delete($query, $parameters, $connection),
            default                       => 0,
        };
    }

    /**
     * function to update records in table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int
     * @throws Exception
     */
    public static function update(string $query, array $parameters = [], BaseConnection $connection = null): int
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MYSQL_PDO => MySQL\PDO\Recordset::update($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO => PGSQL\PDO\Recordset::update($query, $parameters, $connection),
            default                       => 0,
        };
    }
}
