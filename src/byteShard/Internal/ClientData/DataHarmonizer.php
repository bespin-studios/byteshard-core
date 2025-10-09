<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\ClientData;

use byteShard\Cell;
use byteShard\Form\Control\Radio;
use byteShard\Form\Control\Upload;
use byteShard\Form\FormInterface;
use byteShard\Grid\GridInterface;
use byteShard\ID\ID;
use byteShard\Internal\CellContent;
use byteShard\Internal\Request;
use byteShard\Internal\Request\ElementType;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Validate;
use byteShard\Locale;
use byteShard\Session;
use byteShard\Upload\File;
use Closure;
use DateTime;
use DateTimeZone;
use Exception;

class DataHarmonizer
{
    public function __construct(
        private readonly Request       $request,
        private readonly string        $nonce,
        private readonly array         $objectProperties,
        private readonly ?DateTimeZone $clientTimeZone,
        private readonly ?Cell         $cell,
        private readonly ?DateTime     $clientRequestDataTime,
        private readonly Closure       $getCellContent
    )
    {
    }

    /**
     * @throws Exception
     */
    public static function getHarmonizedData(
        Request       $request,
        string        $nonce,
        array         $objectProperties,
        ?DateTimeZone $clientTimeZone,
        ?Cell         $cell,
        ?DateTime     $clientRequestDataTime,
        Closure       $getCellContent): array
    {
        $harmonizer = new self($request,
            $nonce,
            $objectProperties,
            $clientTimeZone,
            $cell,
            $clientRequestDataTime,
            $getCellContent);
        return $harmonizer->getData();
    }

    /**
     * @throws Exception
     */
    public function getData(): array
    {
        $affectedId = $this->request->getAffectedId();
        $data       = $this->request->getData();

        return match ($this->request->getElementType()) {
            ElementType::DhxForm, ElementType::DhxToolbar, ElementType::DhxRibbon => $this->harmonizeFormData($affectedId, $data),
            ElementType::DhxGrid, ElementType::DhxTree                            => $this->harmonizeGridData($affectedId, $data),
            ElementType::BsPoll                                                   => $this->harmonizePollData($affectedId, $data),
            default                                                               => ['', '', null, null, []],
        };
    }

    /**
     * @throws Exception
     */
    private function harmonizeFormData(string $objectId, array $data): array
    {
        [$eventId, $objectValue] = $this->getEventId($objectId, $data);
        $processedDataStructure = $this->getProcessedClientData($data);
        return [
            $eventId,
            $objectValue,
            $processedDataStructure->getClientData(),
            $processedDataStructure->getGetCellData(),
            $processedDataStructure->getErrorMessages()
        ];
    }

    /**
     * @throws Exception
     */
    private function harmonizePollData(string $objectId, array $data): array
    {
        [$eventId, $objectValue] = $this->getEventId($objectId, $data);
        if (str_starts_with($eventId, 'pollOn:')) {
            $processedDataStructure = $this->getProcessedClientData($data);
            return [
                $eventId,
                $objectValue,
                $processedDataStructure->getClientData(),
                $processedDataStructure->getGetCellData(),
                $processedDataStructure->getErrorMessages()
            ];
        }
        return [$eventId, $objectValue, null, null, []];
    }

    /**
     * @throws Exception
     */
    private function harmonizeGridData(string|array $rowIds, array $columnData): array
    {
        if (!is_array($rowIds)) {
            $rowIds = [$rowIds];
        }
        if (array_key_exists('newVal', $columnData)) {
            $objectValueField = $columnData['newVal'];
        } elseif (array_key_exists('objID', $columnData)) {
            $objectValueField = $columnData['objID'];
        }
        $rawClientData = [];
        if (array_key_exists('colID', $columnData)) {
            $rawClientData[$columnData['colID']] = $objectValueField ?? '';
        }
        $processedDataStructure = $this->getProcessedClientData($rawClientData);
        $clientData             = ClientData::createForGrid(
            $this->clientRequestDataTime,
            $this->clientTimeZone,
            $this->decryptRowIds($rowIds),
            ...$processedDataStructure->getProcessedClientData()
        );
        return [
            $clientData->getColumn()->name ?? '',
            $clientData->getColumn()->value ?? '',
            $clientData,
            $processedDataStructure->getGetCellData(),
            $processedDataStructure->getErrorMessages()
        ];
    }

    /**
     * @throws Exception
     */
    private function getEventId(string $objectId, array $data): array
    {
        $actionId            = '';
        $eventId             = '';
        $radioValue          = '';
        $objectType          = '';
        $clientDataProcessor = new ClientDataProcessor($this->nonce, $this->clientTimeZone, $this->objectProperties);
        $processedClientData = $clientDataProcessor->process($objectId, '', false);
        if ($processedClientData->encryptedImplementation === true) {
            $objectType = $processedClientData->objectType;
            $eventId    = $processedClientData->id;
            $actionId   = $processedClientData->id;
        } else {
            // deprecated
            $formControls = $this->cell?->getContentControlType() ?? [];
            if (array_key_exists($objectId, $formControls)) {
                $eventId  = $formControls[$objectId]['objectType'];
                $actionId = $formControls[$objectId]['objectType'];
            } else {
                $eventActionId = $this->cell?->getIDForEvent($objectId);
                if ($eventActionId !== null) {
                    $eventId  = $objectId;
                    $actionId = $eventActionId;
                }
            }
        }
        if ($objectType === Radio::class && array_key_exists($objectId, $data)) {
            $radio = $clientDataProcessor->process($objectId, $data[$objectId]);
            if ($radio->id === $eventId) {
                $actionId   = $radio->value;
                $radioValue = $radio->value;
            }
        }
        $this->cell->setActionId($actionId);
        return [$eventId, $radioValue];
    }

    /**
     * @throws Exception
     */
    private function getProcessedClientData(array $rawClientData): ProcessedDataStructure
    {
        if (array_key_exists('GDR', $rawClientData)) {
            // GetCellData response
            return $this->processGetCellDataResponse($rawClientData);
        }
        return $this->decryptAndValidate($rawClientData, $this->nonce, $this->clientTimeZone);
    }

    /**
     * @throws Exception
     */
    private function processGetCellDataResponse(array $rawClientData): ProcessedDataStructure
    {
        $result = new ProcessedDataStructure(null, $this->clientTimeZone);
        foreach ($rawClientData['GDR'] as $cellId => $data) {
            if (array_key_exists('data', $data)) {
                if ($data !== null) {
                    $id = ID::decryptFinalImplementation($cellId);
                    if (!is_array($data['data'])) {
                        $data['data'] = [$data['data']];
                    }
                    // TODO: move timestamp away from cell payload, put it on the top level and have clean data
                    if (array_key_exists('timestamp', $data['data'])) {
                        unset($data['data']['timestamp']);
                    }
                    $cell                   = Session::getCell($id);
                    $className              = $cell->getContentClass();
                    $cellContent            = new $className($cell);
                    $nonce                  = array_key_exists('cn', $data) ? base64_decode($data['cn']) : '';
                    $processedDataStructure = $this->decryptAndValidate($data['data'], $nonce, $this->clientTimeZone, $cellContent);
                    if ($cellContent instanceof GridInterface) {
                        $processedDataStructure->addGridRows($this->decryptRowIds($data['data']));
                    }
                    $result->addProcessedGetCellData($className, $processedDataStructure);
                }
            }
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    private function decryptAndValidate(array $rawClientData, string $cellNonce, ?DateTimeZone $clientTimeZone, ?CellContent $cellContent = null): ProcessedDataStructure
    {
        // check for upload controls
        $rawClientData       = $this->processClientUploads($rawClientData);
        $result              = [];
        $clientDataProcessor = new ClientDataProcessor($cellNonce, $clientTimeZone, $this->objectProperties);
        $uploadedFiles       = [];
        foreach ($rawClientData as $control => $value) {
            if ($value === null) {
                $value = '';
            }
            $decrypted = $clientDataProcessor->process($control, $value);
            if (str_starts_with($decrypted->id, '!#up=')) {
                // upload hidden field created by byteShard\Internal\Upload
                $file                                = new File($decrypted->id);
                $uploadedFiles[$file->getFileName()] = $file;
            } else {
                if ($decrypted->encryptedImplementation === true) {
                    $result[] = $decrypted;
                } else {
                    if ($cellContent === null) {
                        $cellContent = ($this->getCellContent)();
                    }
                    // deprecated transformation of session stored ids
                    if ($cellContent instanceof FormInterface) {
                        $item = $cellContent->getProcessedClientData($control, $value);
                        // check is needed since the interface cannot provide public properties
                        if ($item instanceof ProcessedClientData) {
                            $result[] = $item;
                        }
                    }
                }
            }
        }
        foreach ($result as $item) {
            if ($item->objectType === Upload::class || is_subclass_of($item->objectType, Upload::class)) {
                $item->value = $this->assignUploadedFilesToControl($uploadedFiles, $item->value);
            }
        }
        $processedDataStructure = new ProcessedDataStructure($this->clientRequestDataTime, $this->clientTimeZone);
        foreach ($result as $processedClientData) {
            // deprecated: check for session based validations and type cast
            if ($processedClientData->encryptedImplementation === false) {
                if ($cellContent === null) {
                    $cellContent = ($this->getCellContent)();
                }
                if ($cellContent instanceof FormInterface) {
                    // form validations are already 100% migrated, only type cast still missing
                    $this->castLegacyClientData($processedClientData);
                }
            }
            $processedDataStructure->addProcessedClientData($processedClientData);
        }
        return $processedDataStructure;
    }

    private function decryptRowIds(array $rowIds): array
    {
        $result = [];
        foreach ($rowIds as $rowId) {
            $decrypted     = json_decode(Session::decrypt($rowId));
            $decrypted->ID = $rowId;
            $result[]      = $decrypted;
        }
        return $result;
    }

    private function processClientUploads(array $rawClientData): array
    {
        foreach ($rawClientData as $control => $value) {
            // upload controls send 2x+1 entries to the server where x = the number of uploaded files
            // each file consists of a _r_x (real name) and _s_x (server name) entry plus one _count entries with the number of uploaded files
            if (str_ends_with($control, '_count')) {
                $count         = (int)$value;
                $uploadControl = substr($control, 0, -6);
                $uploadedFiles = [];
                for ($i = 0; $i < $count; $i++) {
                    if (isset($rawClientData[$uploadControl.'_s_'.$i])) {
                        // server file name
                        $uploadedFiles[] = $rawClientData[$uploadControl.'_s_'.$i];
                        unset($rawClientData[$uploadControl.'_s_'.$i]);
                    }
                    if (isset($rawClientData[$uploadControl.'_r_'.$i])) {
                        // real file name
                        unset($rawClientData[$uploadControl.'_r_'.$i]);
                    }
                }
                unset($rawClientData[$uploadControl.'_count']);
                $rawClientData[$uploadControl] = json_encode($uploadedFiles);
            }
        }
        return $rawClientData;
    }

    private function assignUploadedFilesToControl(array $uploadedFiles, array $serverFileNames): array
    {
        $files = [];
        foreach ($serverFileNames as $uploadedFileId) {
            if (array_key_exists($uploadedFileId, $uploadedFiles)) {
                $files[] = $uploadedFiles[$uploadedFileId];
            }
        }
        return $files;
    }

    /**
     * @throws \byteShard\Exception
     */
    private function castLegacyClientData(ProcessedClientData $clientData): void
    {
        $formControls = $this->cell?->getContentControlType() ?? [];
        // deprecated, only used to cast old style objects. Remove once type casting has been fully implemented
        if (array_key_exists($clientData->clientId, $formControls)) {
            // don't cast values for objects with encrypted implementation
            $objectType = $clientData->objectType;
            // validations aren't stored in the session anymore
            $validations      = [];
            $dateFormat       = array_key_exists('date_format', $formControls[$clientData->clientId]) ? $formControls[$clientData->clientId]['date_format'] : null;
            $validationResult = Validate::validate($clientData->value, $objectType, $validations, $dateFormat);
            if ($validationResult->validationsFailed > 0 && is_array($validationResult->failedRules)) {
                foreach ($validationResult->failedRules as $failureType => $failureText) {
                    $validationResult->failedRules[$failureType] = sprintf(Locale::get('byteShard.validate.form.field'), $clientData->label, $failureText);
                }
            }
        }
    }
}