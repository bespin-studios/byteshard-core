<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Data;

use byteShard\Environment;
use byteShard\Internal\Data;
use byteShard\Popup\Message;
use byteShard\Enum;
use byteShard\Locale;
use byteShard\Exception;
use byteShard\Database;
use DateTime;

/**
 * Class Update
 * @package byteShard\Internal\Data
 */
class Update extends Data
{
    private array $queries = [];
    /**
     * @var array
     */
    private array $history_data = [];

    /**
     * @var array
     */
    private array $history_tables = [];

    /**
     * @return array
     * @throws Exception
     */
    public function process(): array
    {
        $msg = new Message('');
        $msg->setLabel(Locale::get('byteShard.data.update.failed.label'));
        if ($this->cell->getAccessType() !== Enum\AccessType::RW) {
            $msg->setMessage(Locale::get('byteShard.data.update.failed.permission'));
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if (is_array($this->table) === false || count($this->table) === 0) {
            $msg->setMessage(Locale::get('byteShard.data.update.failed.table_not_defined'));
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->checkClientData($msg) === false) {
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->checkUnique($msg) === false) {
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->buildQuery() === false) {
            $msg->setMessage(Locale::get('byteShard.data.update.failed.query'));
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->connect($msg) === true) {
            if ($this->updateData() === false) {
                $msg->setMessage(Locale::get('byteShard.data.update.failed.update'));
                $this->disconnect();
                return $this->getErrorResult($msg->getNavigationArray());
            }
            $this->disconnect();
        } else {
            return $this->getErrorResult($msg->getNavigationArray());
        }
        $this->success = true;
        return $this->getSuccessResult();
    }

    /**
     * whether to store the user_id and modification time
     *
     * @param bool $useModifyLog
     * @return $this
     */
    public function useModifyLog(bool $useModifyLog = true): self
    {
        $this->useModifyLog = $useModifyLog;
        return $this;
    }

    /**
     * @param array $tableMaps
     * @return $this
     */
    public function setHistoryTable(array $tableMaps): self
    {
        foreach ($tableMaps as $tableName => $historyTableName) {
            if (is_string($historyTableName) && is_string($tableName)) {
                $this->history_tables[$tableName] = $historyTableName;
            }
        }
        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function buildQuery(): bool
    {
        //TODO: if recordset implements prepare
        $result      = false;
        $this->query = array();
        global $dbDriver;
        if ($dbDriver === Environment::DRIVER_MYSQL_PDO) {
            if (count($this->strippedClientData) > 0) {
                foreach ($this->strippedClientData as $row => $stripped_client_data) {
                    if (count($this->definedFields) > 0 && count($stripped_client_data) > 0) {
                        $columns     = [];
                        $constraints = [];
                        // determine columns to be updated, they have to be defined and present in the client data
                        foreach (array_keys($this->definedFields) as $column) {
                            if (array_key_exists($column, $stripped_client_data)) {
                                $columns[] = $this->escapeCharacterPre.$column.$this->escapeCharacterPost;
                            }
                        }

                        if (!empty($columns)) {
                            // add modified_on and modified_by to the columns if modify log is set to true
                            if ($this->useModifyLog === true) {
                                $columns[] = $this->escapeCharacterPre.$this->columnNameModifyBy.$this->escapeCharacterPost;
                                $columns[] = $this->escapeCharacterPre.$this->columnNameModifyOn.$this->escapeCharacterPost;
                            }
                            $param = [];
                            $set   = [];
                            $where = [];
                            if (count($this->constraints) > 0) {
                                foreach ($this->constraints as $constraint) {
                                    if ($constraint->value === null) {
                                        // constraint is defined in client data for each row individually
                                        if (array_key_exists($constraint->field, $stripped_client_data)) {
                                            $param[$constraint->field] = $stripped_client_data[$constraint->field]->value;
                                            $where[]                   = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'=:'.$constraint->field;
                                        } else {
                                            throw new Exception(__METHOD__.': constraint not found in clientData');
                                        }
                                    } else {
                                        // constraint is defined for all rows
                                        $param[$constraint->field] = $constraint->value;
                                        $where[]                   = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'=:'.$constraint->field;
                                    }
                                }
                            }
                            foreach ($columns as $column) {
                                if (array_key_exists($column, $stripped_client_data)) {
                                    $param[$column] = $stripped_client_data[$column]->value;
                                }
                                $set[] = $column.'=:'.$column;
                            }

                            foreach ($this->table as $table) {
                                $this->queries[]           =
                                    [
                                        'query' => 'UPDATE '.$table.' SET '.implode(', ', $set).' WHERE '.implode(' AND ', $where),
                                        'param' => $param
                                    ];
                                $this->query[$row][$table] = 'SELECT '.implode(', ', $columns).' FROM  '.$table.((count($constraints) > 0) ? ' WHERE '.implode(' AND ', $constraints) : '');
                                if ($this->useModifyLog === true && array_key_exists($table, $this->history_tables)) {
                                    $this->history_data[$row][$table]['current_query'] = 'SELECT * FROM  '.$table.((count($constraints) > 0) ? ' WHERE '.implode(' AND ', $constraints) : '');
                                    $this->history_data[$row][$table]['history_query'] = 'SELECT TOP 1 * FROM  '.$this->history_tables[$table].((count($constraints) > 0) ? ' WHERE '.implode(' AND ', $constraints) : '').' ORDER BY '.$this->columnNameModifyOn.' DESC';
                                    $this->history_data[$row][$table]['history_table'] = $this->history_tables[$table];
                                    $this->history_data[$row][$table]['constraints']   = $constraints;
                                }
                            }
                            $result = true;
                        }
                    }
                }
            }
            return true;
        }
        if (count($this->strippedClientData) > 0) {
            foreach ($this->strippedClientData as $row => $stripped_client_data) {
                if (count($this->definedFields) > 0 && count($stripped_client_data) > 0) {
                    $columns     = array();
                    $constraints = array();
                    foreach ($this->definedFields as $column => $nil) {
                        if (array_key_exists($column, $stripped_client_data)) {
                            $columns[] = $this->escapeCharacterPre.$column.$this->escapeCharacterPost;
                        }
                    }
                    if (count($columns) > 0) {
                        if ($this->useModifyLog === true) {
                            $columns[] = $this->escapeCharacterPre.$this->columnNameModifyBy.$this->escapeCharacterPost;
                            $columns[] = $this->escapeCharacterPre.$this->columnNameModifyOn.$this->escapeCharacterPost;
                        }
                        if (count($this->constraints) > 0) {
                            foreach ($this->constraints as $constraint) {
                                if ($constraint->value === null) {
                                    if (array_key_exists($constraint->field, $stripped_client_data)) {
                                        $escape_char = '';
                                        if (Enum\DB\ColumnType::is_string($stripped_client_data[$constraint->field]->type)) {
                                            $escape_char = "'";
                                        }
                                        $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='.$escape_char.$stripped_client_data[$constraint->field]->value.$escape_char;
                                    } else {
                                        throw new Exception(__METHOD__.': constraint not found in clientData');
                                    }
                                } else {
                                    if (Enum\DB\ColumnType::is_numeric($constraint->type)) {
                                        $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='.$constraint->value;
                                    } else {
                                        $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='."'".$constraint->value."'";
                                    }
                                }
                            }
                        }
                        foreach ($this->table as $table) {
                            $this->query[$row][$table] = 'SELECT '.implode(', ', $columns).' FROM  '.$table.((count($constraints) > 0) ? ' WHERE '.implode(' AND ', $constraints) : '');
                            if ($this->useModifyLog === true && array_key_exists($table, $this->history_tables)) {
                                $this->history_data[$row][$table]['current_query'] = 'SELECT * FROM  '.$table.((count($constraints) > 0) ? ' WHERE '.implode(' AND ', $constraints) : '');
                                $this->history_data[$row][$table]['history_query'] = 'SELECT TOP 1 * FROM  '.$this->history_tables[$table].((count($constraints) > 0) ? ' WHERE '.implode(' AND ', $constraints) : '').' ORDER BY '.$this->columnNameModifyOn.' DESC';
                                $this->history_data[$row][$table]['history_table'] = $this->history_tables[$table];
                                $this->history_data[$row][$table]['constraints']   = $constraints;
                            }
                        }
                        $result = true;
                    }
                }
            }
        }
        return $result;
    }

    /**
     *
     */
    private function getCurrentHistory()
    {
        $current_date = $this->clientData->getSendDataTime();
        foreach ($this->history_data as $row => $tables) {
            if (array_key_exists($row, $this->strippedClientData)) {
                foreach ($tables as $table => $table_data) {
                    $current_data = Database::getSingle($table_data['current_query']);
                    $history_data = Database::getSingle($table_data['history_query']);
                    if ($history_data === null) {
                        $this->insertHistory($this->history_tables[$table], $table_data['current_query']);
                    } else {
                        if ($history_data->{$this->columnNameModifyBy} != $this->userId) {
                            // last history entry was created by a different user, create new entry
                            $this->insertHistory($this->history_tables[$table], $table_data['current_query']);
                        } else {
                            $history_date = new DateTime($history_data->{$this->columnNameModifyOn});
                            if ($history_date->format('Ymd') !== $current_date->format('Ymd')) {
                                // last history entry was created by the same user, but a different day, create new entry
                                $this->insertHistory($this->history_tables[$table], $table_data['current_query']);
                            } else {
                                // last history entry was created by the same user on the same day, check if the same columns have been modified
                                $update            = false;
                                $insert            = false;
                                $columns_to_update = array();
                                foreach ($this->strippedClientData[$row] as $column_name => $column_data) {
                                    if (isset($history_data->{$column_name}, $current_data->{$column_name})) {
                                        //TODO: type cast current_data according to column type, test with float, bool, date and datetime
                                        if ($column_data->value != $current_data->{$column_name}) {
                                            //column changed by client, check if the current_data and history differ: yes -> new entry, no: update entry
                                            if ($current_data->{$column_name} === $history_data->{$column_name}) {
                                                $update                          = true;
                                                $columns_to_update[$column_name] = $current_data->{$column_name};
                                            } else {
                                                $insert = true;
                                            }
                                        }
                                    }
                                }
                                if ($insert === true) {
                                    $this->insertHistory($this->history_tables[$table], $this->history_data[$row][$table]['current_query']);
                                } elseif ($update === true) {
                                    $this->updateHistory($this->history_tables[$table], $columns_to_update, $table_data['constraints'], $history_data->{$this->columnNameModifyOn});
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * insert the current record into the history table
     * @param $table
     * @param $select_statement
     * @throws Exception
     */
    private function insertHistory($table, $select_statement)
    {
        $insert_statement = 'INSERT INTO '.$table.' '.$select_statement;
        $cn               = Database::getConnection(Database\Enum\ConnectionType::WRITE);
        $cn->execute($insert_statement);
        $cn->disconnect();
    }

    /**
     * update the latest history record
     * @param string $table
     * @param array $column_data
     * @param array $constraints
     * @param string $modify_on
     * @throws Exception
     */
    private function updateHistory(string $table, array $column_data, array $constraints, string $modify_on): void
    {
        $columns          = array_keys($column_data);
        $update_statement = 'SELECT '.implode(', ', $columns).' FROM '.$table.' WHERE '.((count($constraints) > 0) ? implode(' AND ', $constraints).' AND ' : '').$this->columnNameModifyOn."='".$modify_on."'";
        $rs               = Database::getRecordset($cn = Database::getConnection(Database\Enum\ConnectionType::WRITE));
        $rs->open($update_statement);
        if ($rs->recordcount() === 1) {
            foreach ($column_data as $column_name => $column_value) {
                $rs->fields[$column_name] = $column_value;
            }
            $rs->update();
        }
        $rs->close();
        $cn->disconnect();
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function updateData(): bool
    {
        global $dbDriver;
        if ($dbDriver === Environment::DRIVER_MYSQL_PDO) {
            foreach ($this->queries as $update) {
                $numberOfChangedRecords = Database::update($update['query'], $update['param']);
                if (is_int($numberOfChangedRecords) && $numberOfChangedRecords > 0) {
                    $this->changes = true;
                }
            }
        } else {
            //TODO: if recordset implements prepare -> use prepare function and create query object to cycle through... this should be way faster. Stupid COM(ADODB)
            //TODO: catch timestamp violations
            if (count($this->query) > 0) {
                if (count($this->history_data) > 0) {
                    $this->getCurrentHistory();
                }
                $rs = Database::getRecordset($this->dbConnection);
                foreach ($this->query as $row => $queries) {
                    if (array_key_exists($row, $this->strippedClientData)) {
                        foreach ($queries as $query) {
                            if ($rs->open($query)) {
                                if ($rs->recordcount() > 0) {
                                    while (!$rs->EOF) {
                                        $update_this_record = false;
                                        foreach ($this->definedFields as $field => $nil) {
                                            if (array_key_exists($field, $this->strippedClientData[$row])) {
                                                if (($rs->fields[$field] !== $this->strippedClientData[$row][$field]->value) || ($rs->fields[$field] === null && $this->strippedClientData[$row][$field]->value !== null) || ($rs->fields[$field] !== null && $this->strippedClientData[$row][$field]->value === null)) {
                                                    $rs->fields[$field] = $this->strippedClientData[$row][$field]->value;
                                                    $update_this_record = true;
                                                }
                                            }
                                        }
                                        if ($update_this_record === true) {
                                            if ($this->useModifyLog === true) {
                                                $datetime = $this->clientData->getSendDataTime();
                                                $datetime->setTimezone($this->dbTimezone);
                                                $rs->fields[$this->columnNameModifyBy] = $this->userId;
                                                $rs->fields[$this->columnNameModifyOn] = $datetime->format($this->columnFormatModifyOn);
                                            }
                                            $this->changes = true;
                                            $rs->update();
                                        }
                                        $rs->movenext();
                                    }
                                }
                                $rs->close();
                            }
                        }
                    }
                }
            }
        }


        return true;
    }
}
