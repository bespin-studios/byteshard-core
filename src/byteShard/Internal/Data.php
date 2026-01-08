<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Database;
use byteShard\Enum;
use byteShard\Exception;
use byteShard\Internal\Data\Constraint;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Struct\ClientData;
use byteShard\Locale;
use byteShard\Popup\Message;
use DateTimeZone;

/**
 * Class Data
 * @exceptionId 00009
 * @package byteShard\Internal
 */
abstract class Data
{
    /** @var Cell used in Archive */
    protected Cell $cell;
    /** @var ClientData used in: Archive */
    protected ClientData      $clientData;
    protected ?BaseConnection $dbConnection;
    protected ?int            $userId;
    protected DateTimeZone    $dbTimezone;
    protected string          $dbColumnDateFormat              = 'Y-m-d';
    protected string          $dbColumnSmalldatetimeFormat     = 'Y-m-d H:i:s';
    protected string          $dbColumnDatetimeFormat          = 'Y-m-d H:i:s.u';
    protected int             $dbColumnDatetimePrecision       = 3;
    protected string          $dbColumnDatetime2Format         = 'Y-m-d H:i:s.u';
    protected int             $dbColumnDatetime2Precision      = 7;
    protected string          $dbColumnDatetimeoffsetFormat    = 'Y-m-d H:i:s.u';
    protected int             $dbColumnDatetimeoffsetPrecision = 7;
    protected string          $dbColumnBigintdateFormat        = 'YmdHis';
    protected string          $dbColumnTimeFormat              = 'H:i:s.u';
    protected int             $dbColumnTimePrecision           = 7;

    protected bool $changes = false;
    protected bool $success = false;
    /**
     * @var Action[]
     */
    protected array $successActions = [];
    /**
     * @var Action[]
     */
    protected array $changesActions = [];
    /**
     * @var Action[]
     */
    protected array $noChangesActions = [];
    /**
     * @var Action[]
     */
    protected array $errorActions = [];
    /**
     * @var array
     */
    protected array   $unique     = [];
    protected array   $table      = [];
    protected array   $query      = [];
    protected ?string $sourceCell = null;
    /**
     * @var array
     */
    protected array  $definedFields       = [];
    protected array  $references          = [];
    protected array  $constraints         = [];
    protected array  $strippedClientData  = [];
    protected string $escapeCharacterPre  = '';
    protected string $escapeCharacterPost = '';

    protected bool   $useCreateLog       = true;
    protected string $columnNameCreateBy = 'created_by';
    protected string $columnNameCreateOn = 'created_on';
    protected string $columnFormatCreateOn;

    protected bool   $useModifyLog       = true;
    protected string $columnNameModifyBy = 'modified_by';
    protected string $columnNameModifyOn = 'modified_on';
    protected string $columnFormatModifyOn;

    protected bool   $useArchiveLog       = false;
    protected string $columnNameArchive   = 'archived';
    protected string $columnNameArchiveBy = 'archived_by';
    protected string $columnNameArchiveOn = 'archived_on';
    protected string $columnFormatArchiveOn;

    /**
     * Data constructor.
     * @param Cell $cell
     * @param ClientData $clientData
     * @param Session|null $framework
     */
    public function __construct(Cell $cell, ClientData $clientData, ?Session $framework = null)
    {
        $this->cell       = $cell;
        $this->clientData = $clientData;
        $this->dbTimezone = new DateTimeZone('UTC');
        if ($framework !== null) {
            // two function calls, because column type will be supplied by the framework, whereas format for that column will be supplied by the respective DB Classes
            // precision needs to be overwriteable in the session
            $this->columnFormatCreateOn  = $framework->getDateTimeFormat($framework->getMetaDataDBColumnType('created_on'));
            $this->columnFormatModifyOn  = $framework->getDateTimeFormat($framework->getMetaDataDBColumnType('modified_on'));
            $this->columnFormatArchiveOn = $framework->getDateTimeFormat($framework->getMetaDataDBColumnType('archived_on'));

            $this->dbTimezone = $framework->getDBTimeZone();
            $this->userId     = $framework->getUserID();
        } elseif ($_SESSION[MAIN] instanceof Session) {
            $this->columnFormatCreateOn  = $_SESSION[MAIN]->getDateTimeFormat($_SESSION[MAIN]->getMetaDataDBColumnType('created_on'));
            $this->columnFormatModifyOn  = $_SESSION[MAIN]->getDateTimeFormat($_SESSION[MAIN]->getMetaDataDBColumnType('modified_on'));
            $this->columnFormatArchiveOn = $_SESSION[MAIN]->getDateTimeFormat($_SESSION[MAIN]->getMetaDataDBColumnType('archived_on'));
            $this->dbTimezone            = $_SESSION[MAIN]->getDBTimeZone();
            $this->userId                = $_SESSION[MAIN]->getUserID();
        } else {
            //TODO: throw exception
        }
        $this->escapeCharacterPre  = Database::getColumnEscapeStart();
        $this->escapeCharacterPost = Database::getColumnEscapeEnd();
    }

    /**
     * @return bool
     */
    public function processWasSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * @return bool
     */
    public function processHadChanges(): bool
    {
        return $this->changes;
    }

    /**
     * @return array
     */
    abstract public function process(): array;

    /**
     * @param string|int ...$names
     * @return $this
     */
    public function setFields(string|int ...$names): self
    {
        foreach ($names as $name) {
            $this->definedFields[$name] = true;
        }
        return $this;
    }

    /**
     * @param string ...$tableNames
     * @return $this
     */
    public function setTable(string ...$tableNames): self
    {
        foreach ($tableNames as $tableName) {
            $this->table[$tableName] = $tableName;
        }
        return $this;
    }

    /**
     * @param string|array $array
     * @param string|null $message
     * @return $this
     */
    public function setUnique(string|array $array, ?string $message = null): self
    {
        if (!is_array($array)) {
            $array = [$array];
        }
        $tmp = [];
        foreach ($array as $unique) {
            $tmp['fields'][$unique] = true;
        }
        if (is_string($message)) {
            $tmp['message'] = $message;
        }
        if (count($tmp) > 0) {
            $this->unique[] = $tmp;
        }
        return $this;
    }

    /**
     * @param string|array $array
     * @param string|null $message
     * @return $this
     */
    public function setCheckReferencesInTable(string|array $array, ?string $message = null): self
    {
        if (!is_array($array)) {
            $array = [$array];
        }
        foreach ($array as $table) {
            $this->references['tables'][$table] = true;
        }
        if (is_string($message)) {
            $this->references['message'] = $message;
        }
        return $this;
    }

    /**
     * adding constraints will generate the where clause of the query
     * only 'AND' is currently supported
     *
     * @param Constraint ...$constraints
     * @return $this
     */
    public function setConstraint(Constraint ...$constraints): self
    {
        foreach ($constraints as $constraint) {
            // loose checking intended. Otherwise, creating two identical objects will be added twice.
            // if there will ever be any trouble, a possible solution might be to serialize the objects and compare them
            if (in_array($constraint, $this->constraints, false) === false) {
                $this->constraints[] = $constraint;
            }
        }
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function setSuccessActions(Action ...$actions): self
    {
        foreach ($actions as $action) {
            if (!in_array($action, $this->successActions, true)) {
                $this->successActions[] = $action;
            }
        }
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function setChangesActions(Action ...$actions): self
    {
        foreach ($actions as $action) {
            if (!in_array($action, $this->changesActions, true)) {
                $this->changesActions[] = $action;
            }
        }
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function setNoChangesActions(Action ...$actions): self
    {
        foreach ($actions as $action) {
            if (!in_array($action, $this->noChangesActions, true)) {
                $this->noChangesActions[] = $action;
            }
        }
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function setErrorActions(Action ...$actions): self
    {
        foreach ($actions as $action) {
            if (!in_array($action, $this->errorActions, true)) {
                $this->errorActions[] = $action;
            }
        }
        return $this;
    }

    /**
     * @param $source
     * @return $this
     */
    public function setSourceCell($source): self
    {
        $this->sourceCell = $source;
        return $this;
    }

    /**
     * @param array $result
     * @return array
     */
    protected function getSuccessResult(array $result = []): array
    {
        $mergeArray = [];
        if ($this->changes === true) {
            foreach ($this->changesActions as $action) {
                $mergeArray[] = $action->getResult();
            }
        } else {
            foreach ($this->noChangesActions as $action) {
                $mergeArray[] = $action->getResult();
            }
        }
        foreach ($this->successActions as $action) {
            $mergeArray[] = $action->getResult();
        }
        $result            = array_merge_recursive($result, ...$mergeArray);
        $result['success'] = true;
        $result['changes'] = $this->changes;
        $result['state']   = Enum\HttpResponseState::SUCCESS->value;
        return $result;
    }

    /**
     * @param array $result
     * @return array
     */
    protected function getErrorResult(array $result = []): array
    {
        $mergeArray = [];
        foreach ($this->errorActions as $action) {
            $mergeArray[] = $action->getResult();
        }
        $result            = array_merge_recursive($result, ...$mergeArray);
        $result['success'] = false;
        $result['changes'] = $this->changes;
        $result['state']   = Enum\HttpResponseState::SUCCESS->value;
        return $result;
    }

    /**
     * @throws Exception
     */
    protected function connect(?Message $message = null): bool
    {
        if (!isset($this->dbConnection)) {
            $this->dbConnection = Database::getConnection(Database\Enum\ConnectionType::WRITE);
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function disconnect(): bool
    {
        if (isset($this->dbConnection)) {
            $this->dbConnection->disconnect();
            $this->dbConnection = null;
        }
        return true;
    }

    /**
     * this method will check if all constraints are present in clientData and will also remove all unused clientData
     * a new array stripped_client_data is created which will contain only the needed clientData
     * @param Message $message
     * @return bool
     * @throws Exception
     */
    protected function checkClientData(Message $message): bool
    {
        $allConstraintsFound = true;
        $fieldsNotFound      = [];
        $clientDataRows      = $this->clientData->getRows($this->sourceCell);

        //all constraint columns must be present
        $constraintColumns = [];
        if (count($this->constraints) > 0) {
            foreach ($this->constraints as $constraint) {
                if ($constraint->value === null) {
                    $constraintColumns[$constraint->field] = true;
                }
            }
        }
        if (count($constraintColumns) > 0) {
            foreach ($clientDataRows as $rowIndex => $row) {
                foreach ($constraintColumns as $constraintColumn => $nil) {
                    if (property_exists($row, $constraintColumn)) {
                        switch ($row->{$constraintColumn}->type) {
                            case Enum\DB\ColumnType::DATE:
                            case Enum\DB\ColumnType::SMALLDATETIME:
                            case Enum\DB\ColumnType::DATETIME:
                            case Enum\DB\ColumnType::DATETIME2:
                            case Enum\DB\ColumnType::DATETIMEOFFSET:
                            case Enum\DB\ColumnType::BIGINT_DATE:
                            case Enum\DB\ColumnType::TIME:
                                if ($row->{$constraintColumn}->value instanceof DateTime) {
                                    $format    = $_SESSION[MAIN]->getDateTimeFormat($row->{$constraintColumn}->type);
                                    $precision = $_SESSION[MAIN]->getDateTimePrecision($row->{$constraintColumn}->type);
                                    /* @var $dateObject DateTime */
                                    $dateObject = $row->{$constraintColumn}->value;
                                    if ($precision > 0) {
                                        $format     = str_replace('u', '', $format);
                                        $dateString = $dateObject->format($format).substr($dateObject->format('u'), 0, $precision - 1);
                                    } else {
                                        $dateString = $dateObject->format($format);
                                    }
                                    $this->strippedClientData[$rowIndex][$constraintColumn]        = $row->{$constraintColumn};
                                    $this->strippedClientData[$rowIndex][$constraintColumn]->value = $dateString;
                                } else {
                                    throw new Exception(__METHOD__.': DateTime Object expected in field: '.$row->{$constraintColumn}, 100009001);
                                }
                                break;
                            default:
                                $this->strippedClientData[$rowIndex][$constraintColumn] = $row->{$constraintColumn};
                                break;
                        }
                    } else {
                        $fieldsNotFound[$rowIndex.':'.$constraintColumn] = 'Row:'.$rowIndex.'-Field:'.$constraintColumn;
                        $allConstraintsFound                             = false;
                    }
                }
            }
        }
        if ($allConstraintsFound === false) {
            $message->setMessage(sprintf(Locale::get('byteShard.data.field_not_found'), implode(', ', $fieldsNotFound)));
            return false;
        }
        //loop over all defined_fields and put found columns in stripped_client_data
        if (count($this->definedFields) > 0) {
            foreach ($clientDataRows as $rowIndex => $row) {
                foreach ($this->definedFields as $fieldName => $nil) {
                    if (property_exists($row, $fieldName)) {
                        switch ($row->{$fieldName}->type) {
                            case Enum\DB\ColumnType::DATE:
                            case Enum\DB\ColumnType::SMALLDATETIME:
                            case Enum\DB\ColumnType::DATETIME:
                            case Enum\DB\ColumnType::DATETIME2:
                            case Enum\DB\ColumnType::DATETIMEOFFSET:
                            case Enum\DB\ColumnType::BIGINT_DATE:
                            case Enum\DB\ColumnType::TIME:
                                if ($row->{$fieldName}->value instanceof \DateTime) {
                                    $format    = $_SESSION[MAIN]->getDateTimeFormat($row->{$fieldName}->type);
                                    $precision = $_SESSION[MAIN]->getDateTimePrecision($row->{$fieldName}->type);
                                    /* @var $dateObject DateTime */
                                    $dateObject = $row->{$fieldName}->value;
                                    if ($precision > 0) {
                                        $format     = str_replace('u', '', $format);
                                        $dateString = $dateObject->format($format).substr($dateObject->format('u'), 0, $precision);
                                    } else {
                                        $dateString = $dateObject->format($format);
                                    }
                                    $this->strippedClientData[$rowIndex][$fieldName]        = $row->{$fieldName};
                                    $this->strippedClientData[$rowIndex][$fieldName]->value = $dateString;
                                } elseif ($row->{$fieldName}->value === null) {
                                    $this->strippedClientData[$rowIndex][$fieldName] = $row->{$fieldName};
                                } else {
                                    throw new Exception(__METHOD__.': DateTime Object expected in field: '.$row->{$fieldName}, 100009002);
                                }
                                break;
                            default:
                                $this->strippedClientData[$rowIndex][$fieldName] = $row->{$fieldName};
                                break;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param Message|null $message
     * @return bool
     * @throws Exception
     */
    protected function checkUnique(?Message $message = null): bool
    {
        //TODO: if recordset implements prepare
        $unique          = true;
        $queries         = [];
        $localeResolving = [];
        foreach ($this->unique as $checks) {
            if (array_key_exists('fields', $checks)) {
                foreach ($this->strippedClientData as $row => $strippedClientData) {
                    $where = [];
                    foreach ($checks['fields'] as $field => $nil) {
                        if (array_key_exists($field, $strippedClientData)) {
                            $localeResolving[$field] = $strippedClientData[$field]->value;
                            $enclose                 = '';
                            if (Enum\DB\ColumnType::is_string($strippedClientData[$field]->type)) {
                                $enclose = "'";
                            }
                            $where[] = $this->escapeCharacterPre.$field.$this->escapeCharacterPost.'='.$enclose.$strippedClientData[$field]->value.$enclose;
                        }
                    }
                    $constraints = [];
                    if (count($this->constraints) > 0) {
                        foreach ($this->constraints as $constraint) {
                            if ($constraint->value === null) {
                                if (array_key_exists($constraint->field, $strippedClientData)) {
                                    $enclose = '';
                                    if (Enum\DB\ColumnType::is_string($strippedClientData[$constraint->field]->type)) {
                                        $enclose = "'";
                                    }
                                    $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='.$enclose.$strippedClientData[$constraint->field]->value.$enclose;
                                } else {
                                    //TODO: FAIL
                                }
                            } else {
                                //TODO: if column is varchar this will fail...
                                $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='.$constraint->value;
                            }
                        }
                    }
                    if (count($where) > 0) {
                        foreach ($this->table as $table) {
                            $query = 'SELECT COUNT(1) AS Qty FROM '.$table.' WHERE '.implode(' AND ', $where);
                            if (count($constraints) > 0) {
                                $query .= ' AND ('.implode(' AND ', $constraints).')';
                            }
                            $queries[] = $query;
                        }
                    }
                }
            }
        }
        if (count($queries) > 0) {
            $checks = isset($checks) && is_array($checks) ? $checks : [];
            $tmp    = Database::getSingle('SELECT SUM(Qty) AS Qty FROM ('.implode(' UNION ', $queries).') AS R');
            if ($tmp->Qty > 0) {
                if ($message !== null) {
                    if (array_key_exists('message', $checks)) {
                        $message->setMessage($checks['message']);
                    } else {
                        $base  = $this->cell->getName().'.Cell.'.$this->cell->getID().'.Data.Unique.';
                        $found = false;
                        foreach ($this->unique as $columns) {
                            if (array_key_exists('fields', $columns)) {
                                foreach ($columns['fields'] as $field => $nil) {
                                    $locale = Locale::getArray($base.$field);
                                    if ($locale['found'] === true) {
                                        $message->setMessage($locale['locale']);
                                        $found = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        if ($found === false) {
                            $message->setMessage(Locale::get('byteShard.data.checkUnique.not_unique'));
                        }
                    }
                }
                return false;
            }
        }
        return $unique;
    }

    protected function checkReferences(?Message $message = null): bool
    {
        $hasNoReferences = true;
        if (array_key_exists('tables', $this->references) && is_array($this->references['tables']) && count($this->references['tables']) > 0) {
            if (count($this->constraints) > 0) {
                $tables = [];
                foreach ($this->strippedClientData as $row => $strippedClientData) {
                    $constraints = [];
                    foreach ($this->constraints as $constraint) {
                        $escapeChar = '';
                        if (Enum\DB\ColumnType::is_string($strippedClientData[$constraint->field]->type)) {
                            $escapeChar = "'";
                        }
                        if ($constraint->value === null) {
                            if (array_key_exists($constraint->field, $strippedClientData)) {
                                $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='.$escapeChar.$strippedClientData[$constraint->field]->value.$escapeChar;
                            }
                        } else {
                            $constraints[] = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'='.$escapeChar.$constraint->value.$escapeChar;
                        }
                    }
                    if (count($constraints) > 0) {
                        foreach ($this->references['tables'] as $tableName => $nil) {
                            $tables[] = 'SELECT COUNT(1) AS referenceCount FROM '.$tableName.' WHERE '.implode(' AND ', $constraints);
                        }
                    }
                }
                $references = Database::getSingle('SELECT SUM(referenceCount) AS referenceCount FROM ('.implode(' UNION ', $tables).') AS R');
                if ($references->referenceCount > 0) {
                    $hasNoReferences = false;
                    if ($message !== null) {
                        if (array_key_exists('message', $this->references)) {
                            $message->setMessage($this->references['message']);
                        } else {
                            $message->setMessage(Locale::get('byteShard.data.checkReferences.has_references'));
                        }
                    }
                }
            }
        }
        return $hasNoReferences;
    }
}
