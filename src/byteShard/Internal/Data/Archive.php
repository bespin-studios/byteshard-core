<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Data;

use byteShard\Internal\Data;
use byteShard\Popup\Message;
use byteShard\Enum;
use byteShard\Locale;
use byteShard\Exception;
use byteShard\Database;

class Archive extends Data
{
    private string $checkUsageQuery;
    private string $checkUsageField;
    private string $usedMessage;

    private string $checkUnArchiveUsageQuery;
    private string $checkUnArchiveUsageField;
    private string $usedUnArchiveMessage;

    protected bool $useArchiveLog = true;

    private array $statements = [];

    /**
     * @return array
     * @throws Exception
     */
    public function process(): array
    {
        $msg = new Message('');
        $msg->setLabel(Locale::get('byteShard.data.archive.failed.label'));
        if ($this->cell->getAccessType() !== Enum\AccessType::RW) {
            $msg->setMessage(Locale::get('byteShard.data.archive.failed.permission'));
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if (count($this->table) === 0) {
            $msg->setMessage(Locale::get('byteShard.data.archive.failed.table_not_defined'));
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->checkClientData($msg) === false) {
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->buildQuery() === false) {
            $msg->setMessage(Locale::get('byteShard.data.archive.failed.query'));
            return $this->getErrorResult($msg->getNavigationArray());
        }
        $currentState = $this->isCurrentlyArchived();
        if ($currentState === null) {
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($currentState === true) {
            if ($this->checkUnArchiveUsage($msg) === false) {
                return $this->getErrorResult($msg->getNavigationArray());
            }
        } else {
            if ($this->checkUsage($msg) === false) {
                return $this->getErrorResult($msg->getNavigationArray());
            }
        }
        if ($this->checkUnique($msg) === false) {
            return $this->getErrorResult($msg->getNavigationArray());
        }
        if ($this->connect($msg) === true) {
            if ($this->archiveData() === false) {
                $msg->setMessage(Locale::get('byteShard.data.archive.failed.update'));
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

    private function isCurrentlyArchived(): ?bool
    {
        $archived['true']  = 0;
        $archived['false'] = 0;
        if (count($this->query) > 0) {
            foreach ($this->query as $query) {
                $tmp = Database::getArray($query);
                foreach ($tmp as $archive) {
                    if ($archive->archived) {
                        $archived['true']++;
                    } else {
                        $archived['false']++;
                    }
                }
            }
        }
        if ($archived['true'] > 0 && $archived['false'] > 0) {
            return null;
        }
        if ($archived['true'] > 0) {
            return true;
        }
        if ($archived['false'] > 0) {
            return false;
        }
        return null;
    }

    private function checkUsage(?Message $message = null): bool
    {
        if (isset($this->checkUsageQuery)) {
            $used = false;
            if (isset($this->checkUsageField)) {
                $quantity = Database::getSingle($this->checkUsageQuery);
                if (isset($quantity->{$this->checkUsageField}) && $quantity->{$this->checkUsageField} > 0) {
                    $used = true;
                }
            } else {
                $records = Database::getArray($this->checkUsageQuery);
                if (!empty($records)) {
                    $used = true;
                }
            }
            if ($used === true) {
                if ($message !== null) {
                    if (isset($this->usedMessage)) {
                        $message->setMessage($this->usedMessage);
                    } else {
                        $message->setMessage(Locale::get('byteShard.data.checkUsage.has_usages'));
                    }
                }
                return false;
            }
        }
        return true;
    }

    public function setCheckUsageQuery(string $query, ?string $message = null, ?string $field = null): self
    {
        $this->checkUsageQuery = $query;
        if ($message !== null) {
            $this->usedMessage = $message;
        }
        if ($field !== null) {
            $this->checkUsageField = $field;
        }
        return $this;
    }

    public function setCheckUnArchiveQuery(string $query, string $message, ?string $field = null): self
    {
        $this->checkUnArchiveUsageQuery = $query;
        $this->usedUnArchiveMessage     = $message;
        if ($field !== null) {
            $this->checkUnArchiveUsageField = $field;
        }
        return $this;
    }

    private function checkUnArchiveUsage(?Message $message = null): bool
    {
        if (isset($this->checkUnArchiveUsageQuery)) {
            $used = false;
            if (isset($this->checkUnArchiveUsageField)) {
                $quantity = Database::getSingle($this->checkUnArchiveUsageQuery);
                if (isset($quantity->{$this->checkUnArchiveUsageField}) && $quantity->{$this->checkUnArchiveUsageField} > 0) {
                    $used = true;
                }
            } else {
                $records = Database::getArray($this->checkUnArchiveUsageQuery);
                if (!empty($records)) {
                    $used = true;
                }
            }
            if ($used === true) {
                if ($message !== null) {
                    if (isset($this->usedUnArchiveMessage)) {
                        $message->setMessage($this->usedUnArchiveMessage);
                    } else {
                        $message->setMessage(Locale::get('byteShard.data.checkUnArchiveUsage.has_usages'));
                    }
                }
                return false;
            }
        }
        return true;
    }

    /**
     * whether to store the user_id and the time of archivation
     *
     * @param bool $archiveLog
     * @return $this
     * @API
     */
    public function useArchiveLog(bool $archiveLog = true): self
    {
        $this->useArchiveLog = $archiveLog;
        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function buildQuery(): bool
    {
        $columnsToUpdate[] = $this->columnNameArchive.'= !'.$this->columnNameArchive;
        if ($this->useArchiveLog === true) {
            $columnsToUpdate[] = $this->columnNameArchiveBy.'=:archiveBy';
            $columnsToUpdate[] = $this->columnNameArchiveOn.'=:archiveOn';
            $datetime          = $this->clientData->getSendDataTime();
            if ($datetime !== null) {
                $datetime->setTimezone($this->dbTimezone);
                $newConstraints['parameters']['archiveBy'] = $this->userId;
                $newConstraints['parameters']['archiveOn'] = $datetime->format($this->columnFormatArchiveOn);
            } else {
                $columnsToUpdate = null;
            }
        }

        if ($columnsToUpdate !== null) {
            if (count($this->strippedClientData) > 0) {
                foreach ($this->strippedClientData as $strippedClientData) {
                    $newConstraints = [];
                    foreach ($this->constraints as $constraint) {
                        if ($constraint->value === null) {
                            if (array_key_exists($constraint->field, $strippedClientData)) {
                                $newConstraints['parameters'][$constraint->field] = $strippedClientData[$constraint->field]->value;
                                $newConstraints['constraints'][]                  = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'=:'.$constraint->field;
                            } else {
                                throw new Exception(__METHOD__.': constraint not found in clientData');
                            }
                        } else {
                            $newConstraints['parameters'][$constraint->field] = $constraint->value;
                            $newConstraints['constraints'][]                  = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'=:'.$constraint->field;
                        }
                    }
                    if (!empty($newConstraints)) {
                        foreach ($this->table as $table) {
                            $this->statements[] = [
                                'query'      => '
                                    UPDATE '.$table.' 
                                    SET '.implode(', ', $columnsToUpdate).'
                                    WHERE '.implode(' AND ', $newConstraints['constraints']),
                                'parameters' => $newConstraints['parameters']
                            ];
                        }
                    }
                }
            } elseif (count($this->constraints) > 0) {
                $newConstraints = [];
                foreach ($this->constraints as $constraint) {
                    if ($constraint->value !== null) {
                        $newConstraints['parameters'][$constraint->field] = $constraint->value;
                        $newConstraints['constraints'][]                  = $this->escapeCharacterPre.$constraint->field.$this->escapeCharacterPost.'=:'.$constraint->field;
                    } else {
                        throw new Exception(__METHOD__.': constraint not found in clientData');
                    }
                }
                foreach ($this->table as $table) {
                    $this->statements[] = [
                        'query'      => '
                            UPDATE '.$table.' 
                            SET '.implode(', ', $columnsToUpdate).'
                            WHERE '.implode(' AND ', $newConstraints['constraints']),
                        'parameters' => $newConstraints['parameters']
                    ];
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function archiveData(): bool
    {
        //TODO: catch timestamp violations
        foreach ($this->statements as $statement) {
            Database::update($statement['query'], $statement['parameters']);
        }
        return true;
    }
}
