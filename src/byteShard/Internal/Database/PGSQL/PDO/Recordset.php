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
     * function to get the name of connection class name
     * @return string
     */
    public static function getConnectionClassName(): string
    {
        return Connection::class;
    }

    /**
     * function to create an object of Connection class when the connection is null/ not null
     */
    private static function checkConnection(?BaseConnection $connection = null, ConnectionType $type = ConnectionType::READ): ?BaseConnection
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
     * function to fetch a single record from a table
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
        //TODO: Implement encoding handling to deal column names with umlauts
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
                if ($classMap !== null) {
                    if ($fetchPropsLate === true) {
                        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $classMap);
                    } else {
                        $stmt->setFetchMode(PDO::FETCH_CLASS, $classMap);
                    }
                    $result = $stmt->fetch();
                } else {
                    $result = $stmt->fetch(PDO::FETCH_OBJ);
                }
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
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return array
     * @throws Exception
     */
    public static function getArray(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
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
                if ($classMap !== null) {
                    if ($fetchPropsLate === true) {
                        $result = $stmt->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $classMap);
                    } else {
                        $result = $stmt->fetchAll(PDO::FETCH_CLASS, $classMap);
                    }
                } else {
                    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
                }
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
     * function to insert record into a table
     *
     *  - returns `true` or `int` (id) in case of success
     *  - returns `false` in case of error
     *  - returns an array with objects in case a classMap is given and the statement uses RETURNING
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
                    if ($classMap !== null) {
                        if ($fetchPropsLate === true) {
                            $response = $stmt->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $classMap);
                        } else {
                            $response = $stmt->fetchAll(PDO::FETCH_CLASS, $classMap);
                        }
                    } else {
                        try {
                            /*
                             * We try to get the inserted ID.
                             * This only works in case the table has an auto increment.
                             * Without auto increment an exception is thrown. In that case we return `true` to indicate success.
                             */
                            $response = (int)$tempConnection->lastInsertId();
                        } catch (PDOException) {
                            $response = true;
                        }
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
        return $response ?? false;
    }

    /**
     *  function to delete record from a table
     *   - returns an array with objects in case a classMap is given and the statement uses RETURNING
     *   - returns otherwise the number of the affected rows
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
        $response         = 0;
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
                    if ($classMap !== null) {
                        if ($fetchPropsLate === true) {
                            $response = $stmt->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $classMap);
                        } else {
                            $response = $stmt->fetchAll(PDO::FETCH_CLASS, $classMap);
                        }
                    } else {
                        $response = $stmt->rowCount();
                    }
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
        return $response;
    }

    /**
     * function to update record in a table
     *    - returns an array with objects in case a classMap is given and the statement uses RETURNING
     *    - returns otherwise the number of the affected rows
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return int|array
     * @throws Exception
     */
    public static function update(string $query, array $parameters = [], ?Baseconnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): int|array
    {
        $response         = 0;
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
                    if ($classMap !== null) {
                        if ($fetchPropsLate === true) {
                            $response = $stmt->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $classMap);
                        } else {
                            $response = $stmt->fetchAll(PDO::FETCH_CLASS, $classMap);
                        }
                    } else {
                        $response = $stmt->rowCount();
                    }
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
        return $response;
    }
}
