<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Struct;

use byteShard\Exception;
use byteShard\Internal\ClientData\ProcessedClientData;
use byteShard\Upload\File;
use DateTime;
use DateTimeZone;
use Iterator;
use stdClass;

/**
 * Class ClientData
 * @package byteShard\Internal\Struct
 */
class ClientData extends stdClass implements Iterator, ClientDataInterface
{
    /**
     * the time the client requested data to be displayed in the client
     * @var DateTime|null
     */
    private ?DateTime $requestDataTime;

    /**
     * the time the client sent data to be processed
     * @var DateTime|null
     */
    private ?DateTime $sendDataTime = null;

    /** @var Rows */
    public Rows $rows;

    /**
     * holds a list of dynamically declared fields
     * whenever a field is added to a row, this list will be updated
     * it is used to iterate only over the properties added with addField in a row
     * @var array
     */
    private array $iterFields = [];
    /**
     * iterator position marker
     * @var int
     */
    private int $iterPosition = 0;
    /** @var array */
    private array         $fieldsToUpdate = [];
    private ?object       $column         = null;
    private ?DateTimeZone $clientTimeZone = null;

    public function __construct()
    {
        $this->rows = new Rows($this);
    }

    public static function createFromProcessedClientData(?DateTime $clientQueryTime, ?DateTimeZone $timeZone, ProcessedClientData ...$processedClientData): ClientData
    {
        $clientData = new ClientData();
        $time = DateTime::createFromFormat('U.u', microtime(true));
        if ($time !== false) {
            $clientData->setSendDataTime($time);
        }
        if ($timeZone !== null) {
            $clientData->setClientTimeZone($timeZone);
        }
        if ($clientQueryTime !== null) {
            $clientData->setRequestDataTime($clientQueryTime);
        }
        // Form implementation, check if it can be integrated with grid
        if (!empty($processedClientData)) {
            $row = $clientData->addRow();
            foreach ($processedClientData as $processedData) {
                $row->addField($processedData->id, $processedData->value, $processedData->objectType, $processedData->preexistingComboOption);
            }
        }
        return $clientData;
    }

    public static function createForGrid(?DateTime $clientQueryTime, ?DateTimeZone $timeZone, array $rows, ProcessedClientData ...$processedClientData): ClientData
    {
        $clientData = new ClientData();
        $clientData->setSendDataTime(DateTime::createFromFormat('U.u', microtime(true)));
        if ($timeZone !== null) {
            $clientData->setClientTimeZone($timeZone);
        }
        if ($clientQueryTime !== null) {
            $clientData->setRequestDataTime($clientQueryTime);
        }

        $column = null;
        if (array_key_exists(0, $processedClientData)) {
            $column = $processedClientData[0];
            $clientData->setColumn($column->id, $column->value, $column->objectType, $column->clientId);
        }
        foreach ($rows as $rowId) {
            $row = $clientData->addRow();
            if ($column !== null) {
                $row->addField($column->id, $column->value, $column->objectType, $column->clientId);
            }
            foreach ($rowId as $rowProperty => $rowValue) {
                if (!in_array($rowProperty, ['encryptedId', 'decryptedId'])) {
                    $row->addField($rowProperty, $rowValue, '');
                }
            }
        }
        return $clientData;
    }

    /**
     * @return DateTime|null
     * @API
     */
    public function getRequestDataTime(): ?DateTime
    {
        return $this->requestDataTime;
    }

    /**
     * @return ?DateTime
     */
    public function getSendDataTime(): ?DateTime
    {
        return $this->sendDataTime;
    }

    /**
     * @param DateTime $dateTime
     */
    public function setRequestDataTime(DateTime $dateTime)
    {
        $this->requestDataTime = $dateTime;
    }

    public function setClientTimeZone(DateTimeZone $timeZone)
    {
        $this->clientTimeZone = $timeZone;
    }

    /**
     * @API
     */
    public function getClientTimeZone(): ?DateTimeZone
    {
        return $this->clientTimeZone;
    }

    /**
     * @param DateTime $dateTime
     */
    public function setSendDataTime(DateTime $dateTime)
    {
        $this->sendDataTime = $dateTime;
    }

    /**
     * @param null $index
     * @return Row
     */
    public function addRow($index = null): Row
    {
        return $this->rows->addRow($index);
    }

    /**
     * @param string $name
     * @param Data $data
     * @internal
     */
    public function setRowData(string $name, Data $data)
    {
        $this->{$name} = $data->value;
        if (!in_array($name, $this->iterFields)) {
            $this->iterFields[] = $name;
        }
    }

    /**
     * This adds a field to all rows
     * @param      $name
     * @param      $value
     * @param null $type
     * @param null $cellName
     */
    public function addField($name, $value, $type = null, $cellName = null)
    {
        if ($cellName !== null) {
            trigger_error('Method '.__METHOD__.' with cell_name parameter is deprecated', E_USER_DEPRECATED);
        }
        if (is_array($value)) {
            if ($cellName === null) {
                $rowsCopy = [];
                // first add the first value to all existing rows
                $val = array_shift($value);
                foreach ($this->rows as $row) {
                    // create copy of each row object, otherwise value will be only set the first time
                    $rowsCopy[] = clone $row;
                    /* @var $row Row */
                    $row->addField($name, $val, $type);
                }
                foreach ($value as $val) {
                    foreach ($rowsCopy as $rowCopy) {
                        $newRow = $this->addRow();
                        foreach ($rowCopy as $fieldName => $fieldProperties) {
                            $newRow->addField($fieldName, $fieldProperties->value, $fieldProperties->type);
                        }
                        $newRow->addField($name, $val, $type);
                        unset($newRow);
                    }
                }
            } elseif (property_exists($this, $cellName) && ($this->{$cellName} instanceof ClientData)) {
                $this->{$cellName}->addField($name, $value, $type);
            }
        } else {
            if ($cellName === null) {
                foreach ($this->rows as $row) {
                    /* @var $row Row */
                    $row->addField($name, $value, $type);
                }
            } elseif (property_exists($this, $cellName) && ($this->{$cellName} instanceof ClientData)) {
                $this->{$cellName}->addField($name, $value, $type);
            }
        }
    }

    public function setColumn(string $name, mixed $value, string $type, string $clientId, ?string $cellName = null)
    {
        if ($cellName !== null) {
            trigger_error('Method '.__METHOD__.' with cellName parameter is deprecated', E_USER_DEPRECATED);
        }
        if ($cellName === null) {
            $this->column        = new stdClass();
            $this->column->name  = $name;
            $this->column->value = $value;
            $this->column->type  = $type;
            $this->column->id    = $clientId;
        } elseif (property_exists($this, $cellName) && ($this->{$cellName} instanceof ClientData)) {
            $this->{$cellName}->setColumn($name, $value, $type, $clientId);
        }
    }

    public function getColumn(?string $cellName = null): ?object
    {
        if ($cellName !== null) {
            trigger_error('Method '.__METHOD__.' with cellName parameter is deprecated', E_USER_DEPRECATED);
        }
        if ($cellName === null) {
            return $this->column;
        } elseif (property_exists($this, $cellName) && ($this->{$cellName} instanceof ClientData)) {
            return $this->{$cellName}->getColumn();
        }
        return null;
    }

    /*public function setField($name, $value, $type = null, $cell_name = null) {
        if ($cell_name === null) {
            if (array_key_exists($name, $this->fields) === false) {
                $this->fields[$name] = new \stdClass();
                $this->fields[$name]->value = $value;
                $this->fields[$name]->type = $type;
            }
        } elseif (property_exists($this, $cell_name)) {
            if (property_exists($this->{$cell_name}, 'rows')) {
                if (is_array($this->{$cell_name}->rows)) {
                    foreach ($this->{$cell_name}->rows as $index => $row) {
                        if (array_key_exists($name, $row) === false) {
                            $this->{$cell_name}->rows[$index][$name] = new \stdClass();
                            $this->{$cell_name}->rows[$index][$name]->value = $value;
                            $this->{$cell_name}->rows[$index][$name]->type = $type;
                        }
                    }
                } else {
                    //TODO: throw exception
                }
            }
        } else {
            //TODO: thrown Invalid
        }
    }*/

    /*public function getFields() {
        return $this->fields;
    }*/

    /**
     * @param null $sourceCell
     * @return Row[]|null
     */
    public function getRows($sourceCell = null): ?array
    {
        if ($sourceCell === null) {
            return $this->rows->getRows();
        }
        if (property_exists($this, $sourceCell)) {
            return $this->{$sourceCell}->getRows();
        }
        return null;
    }

    /*public function getField($name) {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        } else {
            return null;
        }
    }*/

    public function __isset($name)
    {
        return property_exists($this, $name);
    }

    /**
     * @inheritDoc
     */
    public function current(): mixed
    {
        return $this->{$this->iterFields[$this->iterPosition]};
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        ++$this->iterPosition;
    }

    /**
     * @inheritDoc
     */
    public function key(): mixed
    {
        return $this->iterFields[$this->iterPosition];
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return isset($this->iterFields[$this->iterPosition]);
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        sort($this->iterFields);
        $this->iterPosition = 0;
    }

    /**
     * @param mixed ...$names
     * @return $this
     * @throws Exception
     * @API
     */
    public function setFieldsToUpdate(...$names): self
    {
        foreach ($names as $name) {
            if (is_string($name) || is_numeric($name)) {
                $this->fieldsToUpdate[$name] = true;
            } else {
                $e = new Exception(__METHOD__.": Method only accepts strings or numerics. Input was '".gettype($name)."'");
                $e->setLocaleToken('byteShard.clientData.invalidArgument.setFieldsToUpdate.names');
                throw $e;
            }
        }
        return $this;
    }

    /**
     * @param string $controlId
     * @return File[]
     * @API
     */
    public function getUploadedFiles(string $controlId): array
    {
        $files = [];
        if (property_exists($this, $controlId) && is_array($this->{$controlId})) {
            foreach ($this->{$controlId} as $file) {
                if ($file instanceof File) {
                    $files[] = $file;
                }
            }
        }
        return $files;
    }
}
