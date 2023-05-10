<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\PGSQL\PDO;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\DeleteInterface;
use byteShard\Internal\Database\GetArrayInterface;
use byteShard\Internal\Database\GetSingleInterface;
use byteShard\Internal\Database\InsertInterface;
use byteShard\Internal\Database\UpdateInterface;
use byteShard\Exception;
use PDOException;
use PDO;

/**
 * Class Recordset
 * @package byteShard\Internal\Database\PGSQL\PDO
 */
class Recordset implements GetArrayInterface, GetSingleInterface, InsertInterface, DeleteInterface, UpdateInterface
{
    protected ?object $connection;

    /**
     * Recordset constructor.
     * @param BaseConnection $connection
     */
    public function __construct(BaseConnection $connection)
    {
        $this->connection = $connection->getConnection();
    }

    /**
     * function to get name of connection class name
     * @return string
     */
    public static function getConnectionClassName(): string
    {
        return Connection::class;
    }

    /**
     * function to create object of Connection class when connection is null/ not null
     */
    private static function checkConnection(BaseConnection $connection = null, ConnectionType $type = ConnectionType::READ): ?BaseConnection
    {
        if ($connection instanceof Connection) {
            $connectionClassObject = &$connection;
            if ($connectionClassObject instanceof Connection) {
                return $connectionClassObject;
            }
        } else {
            $connectionClass = static::getConnectionClassName();
            if (class_exists($connectionClass) || is_subclass_of($connectionClass, BaseConnection::class)) {
                return new $connectionClass($type);
            }
        }
        return null;
    }

    /**
     * function to fetch single record from a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return object|null
     * @throws Exception
     */
    public static function getSingle(string $query, array $parameters = [], BaseConnection $connection = null): ?object
    {
        //TODO: Implement encoding handling to deal column names with  umlauts
        $connectionObject = self::checkConnection($connection);
        if ($connectionObject !== null) {
            try {
                $tempConnection = $connectionObject->getConnection();
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600001);
            }
            try {
                /** @var PDO $tempConnection */
                $stmt = $tempConnection->prepare($query);
                $stmt->execute($parameters);
                $result = $stmt->fetch(PDO::FETCH_OBJ);
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600002);
            }
            if ($connection === null) {
                // closing the new connection which is opened in this call
                $connectionObject->disconnect();
            }
        } else {
            throw new Exception('Connection is null  ', 110600003);
        }
        // returning null when no record found
        return is_object($result) ? $result : null;
    }

    /**
     * function to fetch multiple records from a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getArray(string $query, array $parameters = [], BaseConnection $connection = null): array
    {
        //TODO: Implement encoding handling to deal column names with  umlauts
        $connectionObject = self::checkConnection($connection);
        if ($connectionObject !== null) {
            try {
                $tempConnection = $connectionObject->getConnection();
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600004);
            }
            try {
                /** @var PDO $tempConnection */
                $stmt = $tempConnection->prepare($query);
                $stmt->execute($parameters);
                $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600005);
            }
            if ($connection === null) {
                // closing the new connection which is opened in this call
                $connectionObject->disconnect();
            }
        } else {
            throw new Exception('Connection is null ', 110600006);
        }
        return $result;
    }


    /**
     * @param string $query
     * @param BaseConnection|null $connection
     * @param array $parameters
     * @return bool|int
     * @throws Exception
     */
    public static function insert(string $query, array $parameters = [], BaseConnection $connection = null): int|bool
    {
        $connectionObject = self::checkConnection($connection, ConnectionType::WRITE);
        if ($connectionObject !== null) {
            try {
                $tempConnection = $connectionObject->getConnection();
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600007);
            }
            try {
                /** @var PDO $tempConnection */
                $type = $connectionObject->gettype();
                if ($type === ConnectionType::WRITE) {
                    $stmt = $tempConnection->prepare($query);
                    $stmt->execute($parameters);
                    try {
                        $id = (int)$tempConnection->lastInsertId();
                    } catch (PDOException $e) {
                        // throw new Exception($e->getMessage());
                        $id = true;
                    }
                }
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600008);
            }
            if ($connection === null) {
                // closing the new connection which is opened in this call
                $connectionObject->disconnect();
            }
        } else {
            throw new Exception('Connection is null ', 110600009);
        }
        return $id ?? false;
    }

    /**
     *  function to delete record from a table
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int
     * @throws Exception
     */
    public static function delete(string $query, array $parameters = [], BaseConnection $connection = null): int
    {
        $affectedRows     = 0;
        $connectionObject = self::checkConnection($connection, ConnectionType::WRITE);
        if ($connectionObject !== null) {
            try {
                $tempConnection = $connectionObject->getConnection();
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600010);
            }
            try {
                /** @var PDO $tempConnection */
                $type = $connectionObject->gettype();
                if ($type === ConnectionType::WRITE) {
                    $stmt = $tempConnection->prepare($query);
                    $stmt->execute($parameters);
                    $affectedRows = $stmt->rowCount();
                }
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600011);
            }
            if ($connection === null) {
                $connectionObject->disconnect();
            }
        } else {
            throw new Exception('Connection is null ', 110600012);
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
    public static function update(string $query, array $parameters = [], Baseconnection $connection = null): int
    {
        $affectedRows     = 0;
        $connectionObject = self::checkConnection($connection, ConnectionType::WRITE);
        if ($connectionObject !== null) {
            try {
                $tempConnection = $connectionObject->getConnection();
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600013);
            }
            try {
                /** @var PDO $tempConnection */
                $type = $connectionObject->gettype();
                if ($type === ConnectionType::WRITE) {
                    $stmt = $tempConnection->prepare($query);
                    $stmt->execute($parameters);
                    $affectedRows = $stmt->rowCount();
                }
            } catch (PDOException $e) {
                throw new Exception($e->getMessage(), 110600014);
            }
            if ($connection === null) {
                $connectionObject->disconnect();
            }
        } else {
            throw new Exception('Connection is null ', 110600015);
        }
        return $affectedRows;
    }
}
