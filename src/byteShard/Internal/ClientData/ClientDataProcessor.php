<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\ClientData;

use byteShard\Enum\AccessType;
use byteShard\Enum\Cast;
use byteShard\Enum\LogLevel;
use byteShard\Form\Control\Combo;
use byteShard\Form\Control\TagInput;
use byteShard\Form\Control\Upload;
use byteShard\Internal\Debug;
use byteShard\Internal\Validation\Validation;
use byteShard\Locale;
use byteShard\Session;
use byteShard\Utils\Strings;
use DateTime;
use DateTimeZone;
use Exception;

class ClientDataProcessor
{
    public function __construct(
        private readonly string        $nonceToCheck = '',
        private readonly ?DateTimeZone $clientTimeZone = null,
        private readonly array         $objectProperties = []
    )
    {
    }

    /**
     * @param string $encryptedObject
     * @param string $clientValue
     * @param bool $validate
     * @return ProcessedClientData
     * @throws Exception
     */
    public function process(string $encryptedObject, string $clientValue = '', bool $validate = true): ProcessedClientData
    {
        $decrypted = $this->decryptObject($encryptedObject);
        if ($decrypted !== null) {
            [$objectType, $objectId, $objectAccessType, $objectCast, $objectValidations, $objectLabel, $allowInvalidValueDecoding] = $decrypted;
            $result = new ProcessedClientData(
                id                     : $objectId,
                objectType             : $objectType,
                accessType             : $objectAccessType,
                encryptedImplementation: true,
                clientId               : $encryptedObject,
                label                  : $objectLabel
            );
            //TODO: 3rd parameter in ::cast is dateFormat instead of objectType
            // $preexistingComboOption is for combo boxes where the user didn't select a preexisting option but entered a new text
            [$clientValue, $preexistingComboOption] = $this->decryptClientValue($clientValue, $objectType, $allowInvalidValueDecoding, $objectLabel);
            $clientValue = $this->explodeClientValue($clientValue, $objectType);
            if ($preexistingComboOption === true && $objectType === Combo::class || is_subclass_of($objectType, Combo::class)) {
                $result->preexistingComboOption = true;
            }
            if ($objectType === Upload::class || is_subclass_of($objectType, Upload::class)) {
                $clientValue = $this->decryptUploadIds($clientValue);
            }
            if ($validate === true && !empty($objectValidations)) {
                if (is_array($clientValue)) {
                    foreach ($clientValue as $value) {
                        $validationResult = Validation::validate($objectValidations, $value, $objectLabel);
                        if ($validationResult->isValid()) {
                            $result->value[] = $this->cast($value, $objectCast);
                        } else {
                            foreach ($validationResult->getFailedValidation() as $failedValidation) {
                                $result->failedValidationMessages[] = $failedValidation;
                            }
                        }
                    }
                } else {
                    $validationResult = Validation::validate($objectValidations, $clientValue, $objectLabel);
                    if ($validationResult->isValid()) {
                        $result->value = $this->cast($clientValue, $objectCast);
                    } else {
                        foreach ($validationResult->getFailedValidation() as $failedValidation) {
                            $result->failedValidationMessages[] = $failedValidation;
                        }
                    }
                }
                return $result;
            }
            $result->value = $this->cast($clientValue, $objectCast);
            return $result;
        }
        return new ProcessedClientData(encryptedImplementation: false, clientId: $encryptedObject);
    }

    private function decryptUploadIds(string $id): array
    {
        try {
            $uploadIds = json_decode($id);
        } catch (Exception) {
            return [];
        }
        $clientValue = [];
        if (is_array($uploadIds)) {
            foreach ($uploadIds as $uploadId) {
                try {
                    $clientValue[] = Session::decrypt(urldecode($uploadId));
                } catch (Exception) {
                }
            }
        }
        return $clientValue;
    }

    private function explodeClientValue(string $value, string $objectType): string|array
    {
        $objectsWithImplodedValues = [
            TagInput::class
        ];
        foreach ($objectsWithImplodedValues as $class) {
            if ($objectType === $class || is_subclass_of($objectType, $class)) {
                $array  = json_decode($value);
                $result = [];
                if (!empty($array) && is_array($array)) {
                    foreach ($array as $item) {
                        $result[] = $item->value;
                    }
                }
                return $result;
            }
        }
        return $value;
    }

    private function cast(mixed $value, ?string $cast, string $dateFormat = ''): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->castItem($item, $cast, $dateFormat);
            }
            return $result;
        }
        return $this->castItem($value, $cast, $dateFormat);
    }

    private function castItem(mixed $value, ?string $cast, string $dateFormat = ''): mixed
    {
        if ($cast === null) {
            return $value;
        }
        $enum = Cast::tryFrom($cast);
        switch ($enum) {
            case Cast::INT:
                return (int)$value;
            case Cast::BOOL:
                return (bool)$value;
            case Cast::STRING:
                return (string)$value;
            case Cast::FLOAT:
                return (float)$value;
            case Cast::DATE:
            case Cast::DATETIME:
            case Cast::TIME:
                if (empty($value)) {
                    return null;
                }
                if ($dateFormat === '') {
                    switch ($enum) {
                        case Cast::DATE:
                            $dateFormat = '!Y-m-d';
                            break;
                        case Cast::DATETIME:
                            $dateFormat = 'Y-m-d H:i:s';
                            break;
                        case Cast::TIME:
                            $dateFormat = '!H:i:s';
                            break;
                    }
                }
                $date = DateTime::createFromFormat($dateFormat, $value, $this->clientTimeZone);
                if ($date !== false) {
                    //TODO: discuss expected behaviour.
                    //$date->setTimezone(new \DateTimeZone('UTC'));
                    return $date;
                } else {
                    Debug::warning('Failed to create DateTime object. ClientValue: ['.$value.'] - DateFormat: ['.$dateFormat.']');
                }
                return null;
            case Cast::ARRAY:
                if (is_array($value)) {
                    return $value;
                }
                return (array)$value;
            default:
                return $value;
        }
    }

    private function decryptObject(string $encryptedObject): ?array
    {
        try {
            $decrypted = Session::decrypt($encryptedObject);
        } catch (Exception) {
            return null;
        }
        $object = json_decode($decrypted);

        //check if nonce matches current cell nonce, don't accept "old" request fields
        $objNonce = substr(md5($this->nonceToCheck.$object->i), 0, 24);
        if (Session::checkNonce($encryptedObject, $objNonce) === false) {
            return null;
        }
        try {
            $objectType = null;
            if (array_key_exists($object->i, $this->objectProperties)) {
                $objectType = $this->objectProperties[$object->i]->t;
            } elseif (property_exists($object, 't')) {
                $objectType = $object->t;
            } elseif (str_starts_with($object->i, '!#up=')) {
                // upload hidden field currently has neither objectType nor access type.
                // create internal form object which inherits from hidden and implement a proper object
                // currently the server path and so on are stored in the hidden id, move to hidden value
                $objectType = '';
                if (!property_exists($object, 'a')) {
                    $object->a = 1;
                }
            }
            if ($objectType !== null) {
                if (str_starts_with($objectType, '!')) {
                    // we stripped the namespace to reduce encrypted payload. In case a custom validation is used, the fully qualified classname is encoded
                    switch (substr($objectType, 1, 1)) {
                        case 'f':
                            $objectType = 'byteShard\\Form\\Control\\'.substr($objectType, 2);
                            break;
                        case 't':
                            $objectType = 'byteShard\\Toolbar\\Control\\'.substr($objectType, 2);
                            break;
                        case 'g':
                            $objectType = 'byteShard\\Grid\\Column\\'.substr($objectType, 2);
                            break;
                        case 'r':
                            $objectType = 'byteShard\\Ribbon\\Control\\'.substr($objectType, 2);
                            break;
                    }
                }
            } else {
                Debug::error('No object type defined for object');
                return null;
            }
            if (array_key_exists($object->i, $this->objectProperties)) {
                $obj = $this->objectProperties[$object->i];
                if (property_exists($obj, 'c')) {
                    $cast = $obj->c;
                } else {
                    try {
                        $cast = $objectType::getCast();
                    } catch (Exception) {
                        $cast = null;
                    }
                }
                return [
                    $objectType,
                    $object->i,
                    $obj->a ?? AccessType::RW,
                    $cast,
                    $obj->v ?? null,
                    $obj->l ?? '',
                    $obj->d ?? false
                ];
            }
            return [
                $objectType,
                $object->i,
                $object->a,
                $object->c ?? null,
                $object->v ?? null,
                $object->l ?? '',
                $object->d ?? false
            ];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @throws Exception
     */
    private function decryptClientValue(string $encryptedValue, string $objectType, bool $allowInvalidValueDecoding, string $objectLabel): array
    {
        if (empty($encryptedValue)) {
            return [$encryptedValue, false];
        }
        if (is_subclass_of($objectType, EncryptedObjectValueInterface::class)) {
            try {
                $result = Session::decrypt($encryptedValue);
            } catch (Exception $e) {
                if ($allowInvalidValueDecoding === true) {
                    return [$encryptedValue, false];
                }
                if ($objectType === Combo::class || is_subclass_of($objectType, Combo::class)) {
                    $exception = new \byteShard\Exception('Could not decrypt value in combo box. If the combo box should allow new values by the client implement Combo::allowNewEntries()', 12805001);
                    $exception->setLogLevel(LogLevel::DEBUG);
                    $exception->setClientMessage(Strings::replace(Locale::get('byteShard.clientDataProcessor.decryptClientValue.invalidValueDecodingNotAllowed'), ['LABEL' => $objectLabel]));
                    throw $exception;
                }
                throw $e;
            }
            return [$result, true];
        }
        return [$encryptedValue, false];
    }
}