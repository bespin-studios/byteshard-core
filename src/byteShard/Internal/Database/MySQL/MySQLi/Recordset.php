<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\MySQL\MySQLi;

use byteShard\Exception;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\BaseRecordset;
use byteShard\Internal\Database\GetArrayInterface;
use byteShard\Internal\Database\GetIndexArrayInterface;
use byteShard\Internal\Database\GetMultidimensionalIndexArrayInterface;
use byteShard\Internal\Database\GetSingleInterface;
use byteShard\Internal\Debug;
use config;
use mysqli;
use mysqli_stmt;
use stdClass;

/**
 * Class Recordset
 * @exceptionId 00002
 * @package byteShard\Internal\Database\MySQL\MySQLi
 */
class Recordset extends BaseRecordset implements GetArrayInterface, GetIndexArrayInterface, GetMultidimensionalIndexArrayInterface, GetSingleInterface
{
    private array  $data           = [];
    private string $table;
    private string $db;
    private bool   $writeAccess    = false;
    private bool   $addNew         = false;
    private bool   $updatePossible = true;
    private bool   $deletePossible = true;

    /** @var mysqli */
    protected mixed $connection;

    /** @var mysqli_stmt */
    public mixed $recordset;

    public function __construct(BaseConnection $connection)
    {
        $this->connection = $connection->getConnection();
        //TODO:
        //$type = $connection->gettype();
        $type = 'write';
        if ($type == 'login' || $type == 'write') {
            $this->writeAccess = true;
        }
    }

    static public function getConnectionClassName(): string
    {
        return 'byteShard\\Internal\\Database\\MySQL\\MySQLi\\Connection';
    }

    static private function cleanQuery(string $query): string
    {
        $lower_query = strtolower($query);
        $selectCount = substr_count($lower_query, 'select');
        if ($selectCount > substr_count($lower_query, 'from')) {
            $ePos     = 0;
            $totQuery = '';
            for ($i = 0; $i < $selectCount; $i++) {
                $sPos = strpos($lower_query, 'select', $ePos);
                $ePos = strpos($lower_query, 'select', $sPos + 1);
                if ($ePos !== false) {
                    $subQuery = substr($query, $sPos, $ePos - $sPos);
                } else {
                    $subQuery = substr($query, $sPos);
                }
                if (stripos($subQuery, 'where') !== false && stripos($subQuery, 'from') === false) {
                    $subQuery = substr($subQuery, 0, stripos($subQuery, 'where')).' FROM DUAL '.substr($subQuery, stripos($subQuery, 'where'));
                }
                $totQuery .= $subQuery;
            }
        }
        if (isset($totQuery) && $totQuery !== $query) {
            $query = $totQuery;
        }
        if (preg_match("//u", $query)) {
            $query = mb_convert_encoding($query,  'ISO-8859-1', 'UTF-8');
        }
        $query = str_ireplace('ISNULL', 'IFNULL', $query);
        $query = str_replace('AS VARCHAR', 'AS CHAR', $query);
        return $query;
    }

    static private function utf8_decode_mix(mixed $input, bool $encodeKeys = false): mixed
    {
        if (is_array($input)) {
            $result = [];
            if ($encodeKeys === true) {
                foreach ($input as $k => $v) {
                    $result[mb_convert_encoding($k,  'ISO-8859-1', 'UTF-8')] = self::utf8_decode_mix($v, $encodeKeys);
                }
            } else {
                foreach ($input as $k => $v) {
                    $result[$k] = self::utf8_decode_mix($v, $encodeKeys);
                }
            }
        } elseif (is_object($input)) {
            $result = new stdClass();
            if ($encodeKeys === true) {
                foreach ($input as $k => $v) {
                    $result->{mb_convert_encoding($k,  'ISO-8859-1', 'UTF-8')} = self::utf8_decode_mix($v, $encodeKeys);
                }
            } else {
                foreach ($input as $k => $v) {
                    $result->{$k} = self::utf8_decode_mix($v, $encodeKeys);
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
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return object|null
     * @throws Exception
     */
    public static function getSingle(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): ?object
    {
        $conn   = self::checkConnection($connection);
        $qy     = self::cleanQuery($query);
        $mysqli = $conn->getConnection();
        if (empty($parameters)) {
            if ($rs = mysqli_query($mysqli, $qy)) {
                //TODO: table doesn't exist etc..
                $result = null;
                $decode = true;
                if (class_exists('\\config')) {
                    $config = new config();
                    $decode = $config->useDecodeUtf8();
                }
                if ($decode) {
                    while ($row = $rs->fetch_object()) {
                        if ($result === null) {
                            $result = self::utf8_decode_mix($row);
                        } else {
                            Debug::error('Multiple records found for query: '.$query);
                            break;
                        }
                    }
                } else {
                    while ($row = $rs->fetch_object()) {
                        if ($result === null) {
                            $result = $row;
                        } else {
                            Debug::error('Multiple records found for query: '.$query);
                            break;
                        }
                    }
                }
                if ($connection === null) {
                    // new connection opened in this call, close it
                    $conn->disconnect();
                }
                return $result;
            }
        } else {
            $types  = '';
            $values = [];
            $qy     = $qy.' ';
            foreach ($parameters as $parameterName => $parameter) {
                if (is_int($parameter)) {
                    $types .= 'i';
                } elseif (is_float($parameter)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $parameter;
                $qy       = str_replace(':'.$parameterName.' ', '? ', $qy);
            }
            $statement = $mysqli->prepare($qy);
            $statement->bind_param($types, ...$values);
            $statement->execute();
            $rs     = $statement->get_result();
            $result = null;
            while ($row = $rs->fetch_object()) {
                if ($result === null) {
                    $result = self::utf8_decode_mix($row);
                } else {
                    Debug::error('Multiple records found for query: '.$query);
                    break;
                }
            }
            if ($connection === null) {
                // new connection opened in this call, close it
                $conn->disconnect();
            }
            return $result;
        }
        self::throwException($mysqli, $query, 1000020001, 1000020002, 1000020003);
    }

    /**
     * function to get multiple records
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getArray(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
    {
        $conn   = self::checkConnection($connection);
        $qy     = self::cleanQuery($query);
        $mysqli = $conn->getConnection();
        if ($rs = mysqli_query($mysqli, $qy)) {
            //TODO: table doesn't exist etc..
            $result = [];

            $decode = true;
            if (class_exists('\\config')) {
                $config = new config();
                $decode = $config->useDecodeUtf8();
            }
            if ($decode) {
                while ($row = $rs->fetch_object()) {
                    $result[] = self::utf8_decode_mix($row);
                }
            } else {
                while ($row = $rs->fetch_object()) {
                    $result[] = $row;
                }
            }

            if ($connection === null) {
                // new connection opened in this call, close it
                $conn->disconnect();
            }
            return $result;
        }
        self::throwException($mysqli, $qy, 1000020005, 1000020006, 1000020007);
    }

    /**
     * @param mysqli $mysqli
     * @param string $query
     * @param int $code1
     * @param int $code2
     * @param int $code3
     * @throws Exception
     */
    private static function throwException(mysqli $mysqli, string $query, int $code1, int $code2, int $code3): never
    {
        if ($mysqli->error) {
            if (str_contains($mysqli->error, 'Table') && str_contains($mysqli->error, "doesn't exist")) {
                $exception = new Exception($mysqli->error, $code1);
            } else {
                $exception = new Exception('Undefined Query Error', $code2);
                $exception->setInfo($mysqli->error);
                $exception->setQuery($query);
            }
        }
        if (!isset($exception)) {
            $exception = new Exception('Undefined Query Error', $code3);
        }
        $exception->setQuery($query);
        throw $exception;
    }

    /**
     * @param BaseConnection|null $connection
     * @return Connection
     * @throws Exception
     */
    private static function checkConnection(?BaseConnection $connection = null): Connection
    {
        if ($connection instanceof Connection) {
            $adHocConnection = &$connection;
        } else {
            $connectionClassName = static::getConnectionClassName();
            if (class_exists($connectionClassName) || is_subclass_of($connectionClassName, 'Connection')) {
                $adHocConnection = new $connectionClassName();
            } else {
                throw new Exception('No connection class defined', 1000020012);
            }
        }
        return $adHocConnection;
    }

    /**
     * function to get multiple records in an indexed array
     * @param string $query
     * @param string $indexColumn
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getIndexArray(string $query, string $indexColumn, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array
    {
        $conn   = self::checkConnection($connection);
        $qy     = self::cleanQuery($query);
        $mysqli = $conn->getConnection();
        if ($rs = mysqli_query($mysqli, $qy)) {
            //TODO: table doesn't exist etc..
            $result = array();
            while ($row = $rs->fetch_object()) {
                $result[$row->{$indexColumn}] = self::utf8_decode_mix($row);
            }

            if ($connection === null) {
                // new connection opened in this call, close it
                $conn->disconnect();
            }
            return $result;
        }
        self::throwException($mysqli, $query, 1000020014, 1000020015, 1000020016);
    }

    /**
     * @param string $query
     * @param array $indexColumns
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     * @throws Exception
     */
    public static function getMultidimensionalIndexArray(string $query, array $indexColumns, array $parameters = [], ?BaseConnection $connection = null): array
    {
        $conn   = self::checkConnection($connection);
        $qy     = self::cleanQuery($query);
        $mysqli = $conn->getConnection();
        if ($rs = mysqli_query($mysqli, $qy)) {
            //TODO: table doesn't exist etc..
            $dimensions = count($indexColumns) - 1;
            $result     = [];
            $pointer    = &$result;
            while ($row = $rs->fetch_object()) {
                for ($i = 0; $i <= $dimensions; $i++) {
                    if ($i !== $dimensions) {
                        if (isset($pointer[$row->{$indexColumns[$i]}])) {
                            $pointer = &$pointer[$row->{$indexColumns[$i]}];
                        } else {
                            $pointer[$row->{$indexColumns[$i]}] = array();
                            $pointer                            = &$pointer[$row->{$indexColumns[$i]}];
                        }
                    } else {
                        $pointer[$row->{$indexColumns[$i]}] = self::utf8_decode_mix($row);
                    }
                }
                $pointer = &$result;
            }

            if ($connection === null) {
                // new connection opened in this call, close it
                $conn->disconnect();
            }
            return $result;
        }
        self::throwException($mysqli, $query, 1000020014, 1000020015, 1000020016);
    }


    /**
     * @param string $query
     * @return bool
     * @throws Exception
     */
    public function open(string $query): bool
    {
        $qy = self::cleanQuery($query);
        if (!$this->recordset = $this->connection->prepare($qy)) {
            if ($this->connection->error) {
                if (str_contains($this->connection->error, 'Table') && str_contains($this->connection->error, "doesn't exist")) {
                    $exception = new Exception($this->connection->error, 1000020008);
                } elseif (str_contains($this->connection->error, 'Unknown column') && str_contains($this->connection->error, "in 'field list'")) {
                    $exception = new Exception($this->connection->error, 1000020009);
                } else {
                    $exception = new Exception('Undefined Query Error', 1000020010);
                    $exception->setInfo($this->connection->error);
                }
            }
            if (!isset($exception)) {
                $exception = new Exception('Undefined Query Error', 1000020011);
            }
            $exception->setQuery($query);
            throw $exception;
        }
        $this->fields = [];
        $this->data   = [];
        if ($this->writeAccess === true) {
            // if the record is opened to write data to db we have to append id fields to the query.

            //first let's check if the query contains more than one table or more than one db. In that case we cannot run update statements for now.
            $metadata      = $this->recordset->result_metadata();
            $table         = '';
            $db            = '';
            $query_columns = array();
            while ($col = $metadata->fetch_field()) {
                $query_columns[] = $col;
                if ($table === '') {
                    $table = $col->orgtable ?? $col->table;
                } else {
                    if (isset($col->orgtable)) {
                        if ($table !== $col->orgtable) {
                            $this->writeAccess = false;
                        }
                    } else {
                        if ($table !== $col->table) {
                            $this->writeAccess = false;
                        }
                    }
                }
                if ($db === '') {
                    $db = $col->db;
                } else {
                    if ($db !== $col->db) {
                        $this->writeAccess = false;
                    }
                }
            }
            if ($this->writeAccess === true) {
                // set table name
                $this->table = $table;
                // set db name
                //TODO: check if needed elsewhere, otherwise just use $db locally
                $this->db = $db;

                $lower_query   = strtolower($query);
                $from_position = strpos($lower_query, 'from');

                if (str_contains($lower_query, 'distinct') || str_contains($lower_query, 'group by') || str_contains($lower_query, 'join') || strpos($lower_query, 'union')) {
                    $this->updatePossible = false;
                    $this->deletePossible = false;
                } else {
                    // get and insert ID fields into query
                    $ids_to_add = '';
                    $coldata    = mysqli_query($this->connection, "SELECT * FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA`='".$db."' AND `TABLE_NAME`='".$table."' AND `COLUMN_KEY`='PRI'");
                    while ($row = $coldata->fetch_object()) {
                        $this->data[$row->COLUMN_NAME]['id_field'] = true;
                        $id_found_in_query                         = false;
                        foreach ($query_columns as $col) {
                            if ($row->COLUMN_NAME === $col->orgname) {
                                $id_found_in_query = true;
                                break;
                            }
                        }
                        if ($id_found_in_query === true) {
                            $this->data[$row->COLUMN_NAME]['in_query'] = true;
                        } else {
                            $this->data[$row->COLUMN_NAME]['in_query'] = false;
                            $ids_to_add                                .= ','.$row->COLUMN_NAME;
                        }
                    }
                    if ($ids_to_add !== '') {
                        // prepare the statement again with id fields which were not in the original query
                        if (!$this->recordset = $this->connection->prepare(substr($query, 0, $from_position).' '.$ids_to_add.' '.substr($query, $from_position))) {
                            trigger_error("Prepare failed: (".$this->connection->errno.") ".$this->connection->error, E_USER_ERROR);
                        }
                    }
                }
            }
        }
        $this->recordset->execute();
        $this->_bind();
        return true;
    }

    /**
     * @return int
     */
    public function recordcount(): int
    {
        if ($this->recordset instanceof mysqli_stmt) {
            return $this->recordset->num_rows;
        } else {
            return 0;
        }
    }

    /**
     * @return int|null
     * @throws Exception
     */
    public function update(): ?int
    {
        if ($this->writeAccess === true) {
            if ($this->addNew) {
                return $this->_runInsertStatement();
            } elseif ($this->updatePossible === true) {
                $this->_runUpdateStatement();
            }
        }
        return null;
    }

    public function getInsertedID(): mixed
    {
        return $this->connection->insert_id;
    }

    public function delete(): void
    {
        if ($this->writeAccess === true && $this->deletePossible === true) {
            $qy     = 'DELETE FROM '.$this->table;
            $params = [];
            foreach ($this->data as $name => $val) {
                if ($val['id_field'] === true) {
                    switch ($val['type']) {
                        case MYSQLI_TYPE_TINY_BLOB:
                        case MYSQLI_TYPE_MEDIUM_BLOB:
                        case MYSQLI_TYPE_LONG_BLOB:
                        case MYSQLI_TYPE_BLOB:
                            $params[] = array('type' => 'b', 'name' => $name, 'value' => $val['value']);
                            break;
                        case MYSQLI_TYPE_DECIMAL:
                        case MYSQLI_TYPE_NEWDECIMAL:
                        case MYSQLI_TYPE_FLOAT:
                        case MYSQLI_TYPE_DOUBLE:
                            $params[] = array('type' => 'd', 'name' => $name, 'value' => $val['value']);
                            break;
                        case MYSQLI_TYPE_BIT:
                        case MYSQLI_TYPE_TINY:
                        case MYSQLI_TYPE_SHORT:
                        case MYSQLI_TYPE_LONG:
                        case MYSQLI_TYPE_LONGLONG:
                        case MYSQLI_TYPE_INT24:
                        case MYSQLI_TYPE_CHAR: // tinyint
                            $params[] = array('type' => 'i', 'name' => $name, 'value' => $val['value']);
                            break;
                        case MYSQLI_TYPE_NULL:
                        case MYSQLI_TYPE_TIMESTAMP:
                        case MYSQLI_TYPE_DATE:
                        case MYSQLI_TYPE_TIME:
                        case MYSQLI_TYPE_DATETIME:
                        case MYSQLI_TYPE_YEAR:
                        case MYSQLI_TYPE_NEWDATE:
                        case MYSQLI_TYPE_INTERVAL:
                        case MYSQLI_TYPE_ENUM:
                        case MYSQLI_TYPE_SET:
                        case MYSQLI_TYPE_VAR_STRING:
                        case MYSQLI_TYPE_STRING:
                        case MYSQLI_TYPE_GEOMETRY:
                            $params[] = array('type' => 's', 'name' => $name, 'value' => $val['value']);
                            break;
                    }
                }
            }
            if (count($params) > 0) {
                /*@var mysqli $this->connection*/
                $statement = $this->connection->prepare($qy.' WHERE '.implode(' AND ', array_map(function ($column) {
                        return $column['name'].'=?';
                    }, $params)));
                /*@var mysqli_stmt $statement*/
                $statement->bind_param(implode('', array_map(function ($column) {
                    return $column['type'];
                }, $params)), ...array_map(function ($column) {
                    return $column['value'];
                }, $params));
                $statement->execute();
                $statement->close();
            }
            foreach ($this->fields as &$field) {
                $field = null;
            }
        }
    }

    public function addnew(): void
    {
        $this->addNew = true;
        if (is_array($this->fields) || is_object($this->fields)) {
            foreach ($this->fields as &$field) {
                $field = null;
            }
        }
    }

    public function close(): void
    {
        if ($this->recordset instanceof mysqli_stmt) {
            $this->recordset->close();
        }
    }

    public function movenext(): void
    {
        if ($this->BOF === true) {
            $this->BOF = false;
        }
        if ($this->recordset->fetch()) {
            if ($this->EOF === true) {
                $this->EOF = false;
            }
            foreach ($this->data as $k => $v) {
                if ($v['in_query'] === true) {
                    $this->fields[$k] = $v['value'];
                }
            }
        } else {
            if ($this->EOF === false) {
                $this->EOF = true;
            }
        }
    }

    private function _bind(): void
    {
        if ($this->recordset instanceof mysqli_stmt) {
            $this->recordset->store_result();
            $variables = array();
            $meta      = $this->recordset->result_metadata();
            //TODO: if there is a select like:
            // query: select b, c from tbl_d
            // addnew()
            // and the tbl_c has a field "a" with autoincrement / primary this will fail. The field will be classified as in_query=true and id_field=true
            // therefor $v['value'] won't be found.
            if ($meta) {
                while ($field = $meta->fetch_field()) {
                    if (!isset($this->data[$field->name])) {
                        // if the column is not in $this->data it cannot be an id_field because we already evaluated all that in open() method
                        $this->data[$field->name]['id_field'] = false;
                        $this->data[$field->name]['in_query'] = true;
                    }
                    $variables[] = &$this->data[$field->name]['value'];
                    if ($field->flags & MYSQLI_PRI_KEY_FLAG) {
                        $this->data[$field->name]['primary'] = true;
                    } else {
                        $this->data[$field->name]['primary'] = false;
                    }
                    if ($field->flags & MYSQLI_NOT_NULL_FLAG) {
                        $this->data[$field->name]['not_null'] = true;
                    } else {
                        $this->data[$field->name]['not_null'] = false;
                    }
                    if ($field->flags & MYSQLI_AUTO_INCREMENT_FLAG) {
                        $this->data[$field->name]['increment'] = true;
                    } else {
                        $this->data[$field->name]['increment'] = false;
                    }
                    $this->data[$field->name]['type'] = $field->type;
                }
                call_user_func_array(
                    array(
                        $this->recordset,
                        'bind_result'
                    ),
                    $variables
                );
                if ($this->recordset->fetch()) {
                    foreach ($this->data as $k => $v) {
                        if (isset($v['in_query']) && $v['in_query'] === true) {
                            $this->fields[$k] = $v['value'];
                        }
                    }
                    $this->BOF = true;
                    $this->EOF = false;
                } else {
                    $this->BOF = true;
                    $this->EOF = true;
                }
            }


        }
    }

    /**
     * @return int|null
     * @throws Exception
     */
    private function _runInsertStatement(): ?int
    {
        $qy           = 'INSERT INTO '.$this->table;
        $typeString   = '';
        $array[]      = &$typeString;
        $first        = true;
        $fieldsString = '';
        $valuesString = '';
        foreach ($this->fields as $field => &$val) {
            if (isset($this->data[$field])) {
                if (is_null($val) && $this->data[$field]['not_null'] === true && $this->data[$field]['increment'] === false) {
                    // error, not nullable field is left null
                } elseif ($this->data[$field]['increment'] === false) {
                    if ($first === true) {
                        $fieldsString = '`'.$field.'`';
                        $valuesString = '?';
                        $first        = false;
                    } else {
                        $fieldsString .= ',`'.$field.'`';
                        $valuesString .= ',?';
                    }
                    $array[] = &$val;
                    switch ($this->data[$field]['type']) {
                        //TODO: blob return 252 code, same as text, therefore text is treated as blob and update fails
                        //blob typeString is 'b'
                        case MYSQLI_TYPE_TINY_BLOB:
                        case MYSQLI_TYPE_MEDIUM_BLOB:
                        case MYSQLI_TYPE_LONG_BLOB:
                        case MYSQLI_TYPE_BLOB:
                            $typeString .= 's';
                            break;
                        case MYSQLI_TYPE_DECIMAL:
                        case MYSQLI_TYPE_NEWDECIMAL:
                        case MYSQLI_TYPE_FLOAT:
                        case MYSQLI_TYPE_DOUBLE:
                            $typeString .= 'd';
                            break;
                        case MYSQLI_TYPE_BIT:
                        case MYSQLI_TYPE_TINY:
                        case MYSQLI_TYPE_SHORT:
                        case MYSQLI_TYPE_LONG:
                        case MYSQLI_TYPE_LONGLONG:
                        case MYSQLI_TYPE_INT24:
                        case MYSQLI_TYPE_CHAR: // tinyint
                            $typeString .= 'i';
                            break;
                        case MYSQLI_TYPE_NULL:
                        case MYSQLI_TYPE_TIMESTAMP:
                        case MYSQLI_TYPE_DATE:
                        case MYSQLI_TYPE_TIME:
                        case MYSQLI_TYPE_DATETIME:
                        case MYSQLI_TYPE_YEAR:
                        case MYSQLI_TYPE_NEWDATE:
                        case MYSQLI_TYPE_INTERVAL:
                        case MYSQLI_TYPE_ENUM:
                        case MYSQLI_TYPE_SET:
                        case MYSQLI_TYPE_VAR_STRING:
                        case MYSQLI_TYPE_STRING:
                        case MYSQLI_TYPE_GEOMETRY:
                            $typeString .= 's';
                            break;
                    }
                } else {
                    $incrementField = $field;
                }
            }
        }
        $result = null;
        if ($first === false) {
            $this->connection->set_charset('utf8mb4');
            $record = $this->connection->prepare($qy.' ('.$fieldsString.') VALUES ('.$valuesString.')');
            $record->bind_param(...$array);
            if ($record->execute()) {
                //TODO rework result
            } else {
                $e = new Exception('Failed to update record: '.$record->error, 1000020013);
                $e->setQuery($qy.' ('.$fieldsString.') VALUES ('.$valuesString.')');
                throw $e;
            }
            $this->addNew = false;
            if (isset($incrementField)) {
                $this->fields[$incrementField] = $record->insert_id;
            }
            $result = $record->insert_id;
            $record->close();
        }
        return $result;
    }

    /**
     *
     */
    private function _runUpdateStatement()
    {
        $update     = false;
        $qy         = 'UPDATE '.$this->table.' SET ';
        $typeString = '';
        $array[]    = &$typeString;
        $fField     = true;
        foreach ($this->fields as $field => &$val) {
            if (isset($this->data[$field])) {
                if ($this->data[$field]['increment'] === false) {
                    if ($this->data[$field]['not_null'] === true && is_null($val)) {
                        // error
                    } else {
                        if ($this->data[$field]['value'] !== $val) {
                            if ($update === false) {
                                $update = true;
                            }
                            if ($fField === true) {
                                $qy     .= $field." = ? ";
                                $fField = false;
                            } else {
                                $qy .= ', '.$field." = ? ";
                            }
                            $array[] = &$val;
                            switch ($this->data[$field]['type']) {
                                //TODO: blob return 252 code, same as text, therefore text is treated as blob and update fails
                                //blob typeString is 'b'
                                case MYSQLI_TYPE_TINY_BLOB:
                                case MYSQLI_TYPE_MEDIUM_BLOB:
                                case MYSQLI_TYPE_LONG_BLOB:
                                case MYSQLI_TYPE_BLOB:
                                    $typeString .= 's';
                                    break;
                                case MYSQLI_TYPE_DECIMAL:
                                case MYSQLI_TYPE_NEWDECIMAL:
                                case MYSQLI_TYPE_FLOAT:
                                case MYSQLI_TYPE_DOUBLE:
                                    $typeString .= 'd';
                                    break;
                                case MYSQLI_TYPE_BIT:
                                case MYSQLI_TYPE_TINY:
                                case MYSQLI_TYPE_SHORT:
                                case MYSQLI_TYPE_LONG:
                                case MYSQLI_TYPE_LONGLONG:
                                case MYSQLI_TYPE_INT24:
                                case MYSQLI_TYPE_CHAR: // tinyint
                                    $typeString .= 'i';
                                    break;
                                case MYSQLI_TYPE_NULL:
                                case MYSQLI_TYPE_TIMESTAMP:
                                case MYSQLI_TYPE_DATE:
                                case MYSQLI_TYPE_TIME:
                                case MYSQLI_TYPE_DATETIME:
                                case MYSQLI_TYPE_YEAR:
                                case MYSQLI_TYPE_NEWDATE:
                                case MYSQLI_TYPE_INTERVAL:
                                case MYSQLI_TYPE_ENUM:
                                case MYSQLI_TYPE_SET:
                                case MYSQLI_TYPE_VAR_STRING:
                                case MYSQLI_TYPE_STRING:
                                case MYSQLI_TYPE_GEOMETRY:
                                    $typeString .= 's';
                                    break;
                            }
                        }
                    }
                }
            }
        }
        $where = '';
        if (is_array($this->data) && !empty($this->data)) {
            foreach ($this->data as $fieldName => $value) {
                if ($value['id_field'] === true) {
                    $value = match ($value['type']) {
                        MYSQLI_TYPE_NULL, MYSQLI_TYPE_TIMESTAMP, MYSQLI_TYPE_DATE, MYSQLI_TYPE_TIME, MYSQLI_TYPE_DATETIME, MYSQLI_TYPE_YEAR, MYSQLI_TYPE_NEWDATE, MYSQLI_TYPE_INTERVAL, MYSQLI_TYPE_ENUM, MYSQLI_TYPE_SET, MYSQLI_TYPE_VAR_STRING, MYSQLI_TYPE_STRING, MYSQLI_TYPE_GEOMETRY => "'".$value['value']."'",
                        default                                                                                                                                                                                                                                                             => $value['value'],
                    };
                    if ($where === '') {
                        $where = $fieldName.'='.$value;
                    } else {
                        $where .= ' AND '.$fieldName.'='.$value;
                    }
                }
            }
        }
        if ($update === true && $where !== '') {
            if ($record = $this->connection->prepare($qy.' WHERE '.$where)) {
                call_user_func_array(
                    array(
                        $record,
                        'bind_param'
                    ),
                    $array
                );
                if (!$record->execute()) {
                    $e = new Exception('Failed to update record: '.$record->error, 1000020017);
                    $e->setQuery($qy);
                    throw $e;
                }
                $record->close();
            }
        }
    }
}
