<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\MySQL\PDO;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Exception;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\DeleteInterface;
use byteShard\Internal\Database\GetArrayInterface;
use byteShard\Internal\Database\GetIndexArrayInterface;
use byteShard\Internal\Database\GetMultidimensionalIndexArrayInterface;
use byteShard\Internal\Database\GetSingleInterface;
use byteShard\Internal\Database\InsertInterface;
use byteShard\Internal\Database\UpdateInterface;
use config;
use PDO;
use PDOException;
use stdClass;

/**
 * Class Recordset
 * @exceptionId 00002
 * @package byteShard\Internal\Database\MySQL\MySQLi
 */
class Recordset implements GetArrayInterface, GetIndexArrayInterface, GetMultidimensionalIndexArrayInterface, GetSingleInterface, InsertInterface, DeleteInterface, UpdateInterface
{
    static private function utf8_decode_mix(mixed $input, bool $encode_keys = false): mixed
    {
        if (is_array($input)) {
            $result = [];
            if ($encode_keys === true) {
                foreach ($input as $k => $v) {
                    $result[mb_convert_encoding($k,  'ISO-8859-1', 'UTF-8')] = self::utf8_decode_mix($v, true);
                }
            } else {
                foreach ($input as $k => $v) {
                    $result[$k] = self::utf8_decode_mix($v);
                }
            }
        } elseif (is_object($input)) {
            $result = new stdClass();
            if ($encode_keys === true) {
                foreach ($input as $k => $v) {
                    $result->{mb_convert_encoding($k,  'ISO-8859-1', 'UTF-8')} = self::utf8_decode_mix($v, true);
                }
            } else {
                foreach ($input as $k => $v) {
                    $result->{$k} = self::utf8_decode_mix($v);
                }
            }
        } elseif ($input !== null) {
            if (preg_match('//u', $input)) {
                // output is already utf8
                $result = $input;
            } else {
                $result = mb_convert_encoding($input,  'ISO-8859-1', 'UTF-8');
            }
        } else {
            $result = null;
        }
        unset($input);
        return $result;
    }

    /**
     * if at least one record is found getArray will return an array of objects like
     * <br>$result[0]->columnA
     * <br>$result[0]->columnB
     * <br>$result[1]->columnA
     * <br>$result[1]->columnB
     *
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getArray(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
    {
        $connectionObject = self::checkConnection($connection);
        try {
            $tempConnection = $connectionObject->getConnection();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320004);
        }
        try {
            /** @var PDO $tempConnection */
            $stmt = $tempConnection->prepare($query);
            $stmt->execute($parameters);
            if ($classMap !== null) {
                if ($fetchPropsLate === true) {
                    $result = $stmt->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, $classMap);
                } else {
                    $result = $stmt->fetchAll(PDO::FETCH_CLASS, $classMap);
                }
            } else {
                $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            }
            if (class_exists('\\config')) {
                /** @noinspection PhpUndefinedClassInspection */
                $config = new config();
                if ($config->useDecodeUtf8()) {
                    $result = self::utf8_decode_mix($result);
                }
            }
        } catch (PDOException $e) {
            $previous = $e->getPrevious();
            if ($previous !== null) {
                throw new Exception($e->getMessage().'; '.$previous->getMessage(), 110320005);
            }
            throw new Exception($e->getMessage(), 110320005);
        }
        if ($connection === null) {
            // closing the new connection which is opened in this call
            $connectionObject->disconnect();
        }
        return $result;
    }

    public static function getColumn(string $query, array $parameters = [], ?BaseConnection $connection = null): array
    {
        $connectionObject = self::checkConnection($connection);
        try {
            $tempConnection = $connectionObject->getConnection();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320004);
        }
        try {
            /** @var PDO $tempConnection */
            $stmt = $tempConnection->prepare($query);
            $stmt->execute($parameters);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320005);
        }
        if ($connection === null) {
            // closing the new connection which is opened in this call
            $connectionObject->disconnect();
        }
        return $result;
    }

    /**
     * if at least one record is found getArray will return an array of objects like
     * <br>$result[columnValue1]->columnA
     * <br>$result[columnValue1]->columnB
     * <br>$result[columnValue2]->columnA
     * <br>$result[columnValue2]->columnB
     *
     * @param string $query
     * @param string $indexColumn
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getIndexArray(string $query, string $indexColumn, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
    {
        $records = self::getArray($query, $parameters, $connection, $classMap, $fetchPropsLate);
        $result  = [];
        foreach ($records as $record) {
            $result[$record->{$indexColumn}] = $record;
        }
        return $result;
    }

    /**
     * if at least one record is found getArray will return an array of objects like
     * <br>$result[columnValue1][columnValue2]->columnA
     * <br>$result[columnValue1][columnValue2]->columnB
     *
     * @param string $query
     * @param array $indexColumns
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     */
    public static function getMultidimensionalIndexArray(string $query, array $indexColumns, array $parameters = [], ?BaseConnection $connection = null): array
    {
        return [];
    }

    /**
     * if exactly one record is found getSingle will return an object like:
     * <br>$result->dbfield1
     * <br>$result->dbfield2
     * @param string $query The SQL query to be executed
     * @param array $parameters array of key-value pair where column name is key and column value is value
     * @param BaseConnection|null $connection an existing connection can be passed to be reused for the query
     * @return object|null if no record is found for the query null is returned
     * @throws Exception
     * @api
     */
    public static function getSingle(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): ?object
    {
        $connectionObject = self::checkConnection($connection);
        try {
            $tempConnection = $connectionObject->getConnection();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320001);
        }
        try {
            /** @var PDO $tempConnection */
            $stmt = $tempConnection->prepare($query);
            $stmt->execute($parameters);
            if ($classMap !== null) {
                if ($fetchPropsLate === true) {
                    $stmt->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, $classMap);
                } else {
                    $stmt->setFetchMode(PDO::FETCH_CLASS, $classMap);
                }
                $result = $stmt->fetch();
            } else {
                $result = $stmt->fetch(PDO::FETCH_OBJ);
            }
            if (class_exists('\\config')) {
                /** @noinspection PhpUndefinedClassInspection */
                $config = new config();
                if ($config->useDecodeUtf8()) {
                    $result = self::utf8_decode_mix($result);
                }
            }
        } catch (PDOException $e) {
            $previous = $e->getPrevious();
            if ($previous !== null) {
                throw new Exception($e->getMessage().'; '.$previous->getMessage(), 110320002);
            }
            throw new Exception($e->getMessage(), 110320002);
        }
        if ($connection === null) {
            // closing the new connection which is opened in this call
            $connectionObject->disconnect();
        }
        // returning null when no record found
        return is_object($result) ? $result : null;
    }

    /**
     * function to insert record into a table
     *
     *  - returns `true` or `int` (id) in case of success
     *  - returns `false` in case of error
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int|true
     * @throws Exception
     */
    public static function insert(string $query, array $parameters = [], ?BaseConnection $connection = null): int|bool
    {
        $connectionObject = self::checkConnection($connection);
        try {
            $tempConnection = $connectionObject->getConnection();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320007);
        }
        try {
            /** @var PDO $tempConnection */
            $stmt = $tempConnection->prepare($query);
            foreach ($parameters as $column => $value) {
                if (is_bool($value)) {
                    $stmt->bindValue($column, $value, PDO::PARAM_BOOL);
                } elseif (is_int($value)) {
                    $stmt->bindValue($column, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($column, $value);
                }
            }
            $stmt->execute();
            try {
                /*
                 * We try to get the inserted ID.
                 * This only works in case the table has an auto increment.
                 * Without auto increment an exception is thrown. In that case we return `true` to indicate success.
                 */
                $id = (int)$tempConnection->lastInsertId();
            } catch (PDOException $e) {
                // throw new Exception($e->getMessage());
                $id = true;
            }
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320008);
        }
        if ($connection === null) {
            // closing the new connection which is opened in this call
            $connectionObject->disconnect();
        }
        return $id;
    }

    /**
     *  function to delete record from a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int
     * @throws Exception
     */
    public static function delete(string $query, array $parameters = [], ?BaseConnection $connection = null): int
    {
        $connectionObject = self::checkConnection($connection);
        try {
            $tempConnection = $connectionObject->getConnection();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320010);
        }
        try {
            /** @var PDO $tempConnection */
            $stmt = $tempConnection->prepare($query);
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320011);
        }
        try {
            $stmt->execute($parameters);
            $affectedRows = $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320016, 'byteShard\\Database::delete');
        }
        if ($connection === null) {
            $connectionObject->disconnect();
        }
        return $affectedRows;
    }

    /**
     * function to update record in a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int
     * @throws Exception
     */
    public static function update(string $query, array $parameters = [], ?Baseconnection $connection = null): int
    {
        $connectionObject = self::checkConnection($connection);
        try {
            $tempConnection = $connectionObject->getConnection();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320013);
        }
        try {
            /** @var PDO $tempConnection */
            $stmt = $tempConnection->prepare($query);
            foreach ($parameters as $column => $value) {
                if (is_bool($value)) {
                    $stmt->bindValue($column, $value, PDO::PARAM_BOOL);
                } elseif (is_int($value)) {
                    $stmt->bindValue($column, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($column, $value);
                }
            }
            $stmt->execute();
            $affectedRows = $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 110320014);
        }
        if ($connection === null) {
            $connectionObject->disconnect();
        }
        return $affectedRows;
    }

    /**
     * @param BaseConnection|null $connection
     * @return Connection
     */
    private static function checkConnection(?BaseConnection $connection = null): Connection
    {
        if ($connection instanceof Connection) {
            return $connection;
        }
        return new Connection(ConnectionType::READ);
    }
}