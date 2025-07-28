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
    public static function getConnection(ConnectionType $accessType = ConnectionType::READ, ?ParametersInterface $parameters = null): BaseConnection
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
    public static function getRecordset(?BaseConnection $connection = null): MySQL\MySQLi\Recordset|PGSQL\PDO\Recordset
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
     * function to get a set of records from a database / table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return array
     * @throws Exception
     */
    public static function getArray(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MySQL_mysqli => MySQL\MySQLi\Recordset::getArray($query, $parameters, $connection, $classMap, $fetchPropsLate),
            Environment::DRIVER_MYSQL_PDO    => MySQL\PDO\Recordset::getArray($query, $parameters, $connection, $classMap, $fetchPropsLate),
            Environment::DRIVER_PGSQL_PDO    => PGSQL\PDO\Recordset::getArray($query, $parameters, $connection, $classMap, $fetchPropsLate),
            default                          => [],
        };
    }

    public static function getColumn(string $query, array $parameters = [], ?BaseConnection $connection = null): array
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
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return array
     * @throws Exception
     */
    public static function getIndexArray(string $query, string $indexColumn, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MySQL_mysqli => MySQL\MySQLi\Recordset::getIndexArray($query, $indexColumn, $parameters, $connection, $classMap, $fetchPropsLate),
            Environment::DRIVER_MYSQL_PDO    => MySQL\PDO\Recordset::getIndexArray($query, $indexColumn, $parameters, $connection, $classMap, $fetchPropsLate),
            Environment::DRIVER_PGSQL_PDO    => PGSQL\PDO\Recordset::getArray($query, $parameters, $connection, $classMap, $fetchPropsLate),
            default                          => [],
        };
    }

    /**
     * function to get single record from database/table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return object|null
     * @throws Exception
     */
    public static function getSingle(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): ?object
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MySQL_mysqli => MySQL\MySQLi\Recordset::getSingle($query, $parameters, $connection, $classMap, $fetchPropsLate),
            Environment::DRIVER_MYSQL_PDO    => MySQL\PDO\Recordset::getSingle($query, $parameters, $connection, $classMap, $fetchPropsLate),
            Environment::DRIVER_PGSQL_PDO    => PGSQL\PDO\Recordset::getSingle($query, $parameters, $connection, $classMap, $fetchPropsLate),
            default                          => null,
        };
    }

    /**
     * function to insert record into a table
     *
     *  - returns `true` or `int` (id) in case of success
     *  - returns `false` in case of error
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return int|bool|array
     * @throws Exception
     */
    public static function insert(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): int|bool|array
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MYSQL_PDO => MySQL\PDO\Recordset::insert($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO => PGSQL\PDO\Recordset::insert($query, $parameters, $connection, $classMap, $fetchPropsLate),
            default                       => false,
        };
    }

    /**
     * function to delete records from a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return int|array
     * @throws Exception
     */
    public static function delete(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): int|array
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MYSQL_PDO => MySQL\PDO\Recordset::delete($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO => PGSQL\PDO\Recordset::delete($query, $parameters, $connection, $classMap, $fetchPropsLate),
            default                       => 0,
        };
    }

    /**
     * function to update records in a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return int|array
     * @throws Exception
     */
    public static function update(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): int|array
    {
        global $dbDriver;
        return match ($dbDriver) {
            Environment::DRIVER_MYSQL_PDO => MySQL\PDO\Recordset::update($query, $parameters, $connection),
            Environment::DRIVER_PGSQL_PDO => PGSQL\PDO\Recordset::update($query, $parameters, $connection, $classMap, $fetchPropsLate),
            default                       => 0,
        };
    }
}
