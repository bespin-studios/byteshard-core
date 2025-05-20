<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Internal\Sanitizer;
use byteShard\Internal\Session\EncryptedIDStorageInterface;
use byteShard\Internal\Struct;
use byteShard\Internal\Struct\Navigation_ID;

/**
 * Class ID
 * @package byteShard
 */
abstract class ID
{
    private static int $ENCRYPTED_ID_LENGTH = 24;
    private static int $ID_LENGTH           = 2;

    const ID_SEPARATOR = '_';

    /** private constructor, that way not even inherited classes can be instantiated */
    private function __construct()
    {
    }

    //This is the array all id_indexes are resolved into
    public static array $id_index = [];
    //This array is used to declare ID Types to be of string type
    private static array $string_IDs = [];
    //This array is only used to check that no application IDs interfere with byteShard specific IDs.
    //The array $id_index cannot be used because it will be appended by more entries whereas this array stays static
    private static array $reserved_id_indexes = ['Event_ID' => 'EV', 'LCell_ID' => 'LC', 'Popup_ID' => 'PU', 'Timestamp' => 'TS', 'Milestone_ID' => 'MI', 'AccessRight_ID' => 'AR', 'Confirmation_ID' => 'CO'];
    //This array is only used to declare byteShard ID Types to be of string type
    private static array $reserved_string_IDs = [
        'Confirmation_ID' => [
            'minLength' => 32,
            'maxLength' => 32,
            'pregMatch' => "/^([a-zA-Z0-9]{32})$/"
        ],
        'LCell_ID'        => [
            'maxLength' => 1,
            'pregMatch' => "/^([a-z]{1,1})$/"
        ]
    ];

    public static function getNavigationID(int $level, int $navigationId, mixed $parentId = '', mixed $session = null): string
    {
        trigger_error('byteShard\ID is deprecated', E_USER_DEPRECATED);
        return '';
    }

    public static function addIDs(array $array, mixed $session = null): void
    {
        trigger_error('byteShard\ID is deprecated', E_USER_DEPRECATED);
    }

    /**
     * @param string $idType
     * @param int $maxStringLength
     * @param string $pregMatch
     */
    public static function setStringTypeForIDType(string $idType, int $maxStringLength, string $pregMatch = ''): void
    {
        trigger_error('byteShard\ID is deprecated', E_USER_DEPRECATED);
    }

    /**
     * @param string $id
     * @param null|int|string $value
     * @param ?EncryptedIDStorageInterface $session
     * @return string
     */
    public static function getID(string $id, null|int|string $value = null, ?EncryptedIDStorageInterface $session = null): string
    {
        if (array_key_exists($id, self::$id_index)) {
            if ($value === null) {
                return self::$id_index[$id];
            }
            return self::$id_index[$id].$value;
        }

        $id_map              = ($session !== null) ? $session->encryptID($id) : Session::encryptID($id);
        self::$id_index[$id] = $id_map;
        if ($value === null) {
            return $id_map;
        }
        return $id_map.$value;
    }

    public static function explode(array|Struct\ID|null|string $IDs): null|Struct\ID|array
    {
        if ($IDs instanceof Struct\ID) {
            return $IDs;
        }
        if ($IDs === null) {
            return null;
        }
        if (!is_array($IDs)) {
            $IDs = array($IDs);
        }
        $isCryptoId = false;
        foreach ($IDs as $index => $id) {
            if ($id !== null && $index !== 'timestamp' && strlen($id) > 30 && !str_contains($id, self::ID_SEPARATOR)) {
                $isCryptoId = true;
                break;
            }
        }
        if ($isCryptoId === true) {
            return self::explodeCryptoID($IDs);
        }
        if (isset($IDs['timestamp'])) {
            $ts = $IDs['timestamp'];
            unset($IDs['timestamp']);
            foreach ($IDs as $k => $v) {
                $IDs[$k] = $v.self::ID_SEPARATOR.self::getID('Timestamp', $ts);
            }
        }
        if (count($IDs) > 0) {
            return self::explodeEncryptedID($IDs);
        }
        return null;
    }

    private static function explodeCryptoID(array $ids): null|array|Navigation_ID|Struct\ID
    {
        $timeStamp = null;
        if (array_key_exists('timestamp', $ids)) {
            $timeStamp = $ids['timestamp'];
            unset($ids['timestamp']);
        }
        $result = [];
        foreach ($ids as $id) {
            $json = '';
            try {
                $json = Session::decrypt($id);
            } catch (\Exception $e) {
            }
            if ($json !== '') {
                $array = json_decode($json);
                if (property_exists($array, '!#tab') || property_exists($array, '!#tb')) {
                    return self::generateNavigationIds($array, $id);
                }
                $idStruct = new Struct\ID();
                foreach ($array as $key => $val) {
                    $idStruct->{$key} = $val;
                }
                $idStruct->ID          = $id;
                $idStruct->encryptedId = $id;
                $idStruct->decryptedId = $json;
                $result[]              = $idStruct;
            }
        }
        if (count($result) === 0) {
            return null;
        }
        if ($timeStamp !== null) {
            foreach ($result as $item) {
                $item->timestamp = $timeStamp;
            }
        }
        if (count($result) === 1) {
            return $result[0];
        }
        return $result;
    }

    // ######################################
    // PRIVATE METHODS
    // ######################################

    private static function generateNavigationIds(object $decryptedId, string $encryptedId): Struct\Navigation_ID
    {
        $result  = new Struct\Tab_ID();
        $encoded = json_encode($decryptedId);
        if ($encoded !== false) {
            $decryptedId = json_decode($encoded, true);
            if (is_array($decryptedId)) {
                if (array_key_exists('!#pop', $decryptedId)) {
                    $result = new Struct\Popup_ID();
                }
                $result->ID = $encryptedId;
                $tabId      = [];
                if (array_key_exists('!#tb', $decryptedId) && !array_key_exists('!#tab', $decryptedId)) {
                    $decryptedId['!#tab'] = explode('\\', $decryptedId['!#tb']);
                }
                $tabId['!#tab'] = $decryptedId['!#tab'];
                $result->Tab_ID = Session::encrypt(json_encode($tabId), Session::getTopLevelNonce());
                while (!empty($decryptedId['!#tab'])) {
                    $index                      = count($decryptedId['!#tab']) - 1;
                    $id                         = [];
                    $id['!#tab']                = $decryptedId['!#tab'];
                    $result->Navigation[$index] = Session::encrypt(json_encode($id), Session::getTopLevelNonce());
                    unset($decryptedId['!#tab'][$index]);
                }
                if (array_key_exists('!#cel', $decryptedId)) {
                    $result->LCell_ID = $decryptedId['!#cel'];
                }
                if (array_key_exists('!#pop', $decryptedId)) {
                    $popupId          = $tabId;
                    $popupId['!#pop'] = $decryptedId['!#pop'];
                    $result->Popup_ID = Session::encrypt(json_encode($popupId), Session::getTopLevelNonce());
                    //TODO: @pop
                }
            }
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    private static function explodeEncryptedID(array $IDs): null|array|Struct\ID
    {
        $is_navigation_id  = false;
        $navigation_levels = array();
        $encrypted         = Session::getEncryptedIDs();
        $result            = array();
        foreach ($IDs as $key => $ID) {
            if ($ID instanceof Struct\ID) {
                $result[] = $ID;
            } elseif (!($ID === 'null' || $ID === null)) {
                $elements = explode(self::ID_SEPARATOR, $ID);
                if (is_array($elements) && count($elements) > 0) {
                    $tmpID     = new Struct\ID();
                    $tmpID->ID = $ID;
                    foreach ($elements as $element) {
                        $index = substr($element, 0, self::$ENCRYPTED_ID_LENGTH);
                        if (array_key_exists($index, $encrypted['navigation_level'])) {
                            $is_navigation_id                                              = true;
                            $navigation_levels[$encrypted['navigation_level'][$index] - 1] = $index;
                            $key                                                           = null;
                        } else {
                            $key = array_search($index, $encrypted['id'], true);
                            if ($key === false) {
                                $key = null;
                            }
                        }
                        if ($key !== null) {
                            $idElement = substr($element, self::$ENCRYPTED_ID_LENGTH);
                            if (isset(self::$reserved_string_IDs[$key]) && self::isValidReserved($key, $idElement, $ID)) {
                                $tmpID->{$key} = $idElement;
                            } elseif (isset(self::$string_IDs[$key]) && self::isValid($key, $idElement, $ID)) {
                                $tmpID->{$key} = $idElement;
                            } elseif (is_numeric($idElement)) {
                                $tmpID->{$key} = (int)$idElement;
                            } else {
                                $e = new Exception('Incorrect ID: "'.$ID.'". Numeric ID expected.');
                                $e->setIdData($key, $idElement);
                                throw $e;
                            }
                        }
                    }
                    if ($is_navigation_id === true) {
                        if (isset($tmpID->Popup_ID)) {
                            $nID = new Struct\Popup_ID();
                        } else {
                            $nID = new Struct\Tab_ID();
                        }
                        /** @phpstan-ignore-next-line */
                        foreach ($tmpID as $sub_key => $val) {
                            $nID->{$sub_key} = $val;
                        }
                        unset($tmpID);
                        $tmpID = $nID;
                        $level = 0;
                        $navID = '';
                        while (true) {
                            if (array_key_exists($level, $navigation_levels)) {
                                if ($navID === '') {
                                    $navID = $navigation_levels[$level];
                                } else {
                                    $navID = $navID.self::ID_SEPARATOR.$navigation_levels[$level];
                                }
                            } else {
                                break;
                            }
                            $tmpID->Navigation[$level] = $navID;
                            $level++;
                        }
                        $tmpID->Tab_ID = $navID;
                        if (isset($tmpID->LCell_ID)) {
                            $tmpID->TabAndCell_ID = $navID.self::ID_SEPARATOR.$encrypted['id']['LCell_ID'].$tmpID->LCell_ID;
                        }
                        if (isset($tmpID->Popup_ID)) {
                            $tmpID->Popup_ID = $navID.self::ID_SEPARATOR.$encrypted['id']['Popup_ID'].$tmpID->Popup_ID;
                            if (isset($tmpID->LCell_ID)) {
                                $tmpID->PopupAndCell_ID = $tmpID->Popup_ID.self::ID_SEPARATOR.$encrypted['id']['LCell_ID'].$tmpID->LCell_ID;
                            }
                        }
                    } elseif (isset($tmpID->Confirmation_ID)) {
                        $nID = new Struct\Confirmation_ID();
                        /** @phpstan-ignore-next-line */
                        foreach ($tmpID as $sub_key => $val) {
                            $nID->{$sub_key} = $val;
                        }
                        unset($tmpID);
                        $tmpID = $nID;
                        if (isset($tmpID->Confirmation_ID)) {
                            $tmpID->Confirmation_ID = $encrypted['id']['Confirmation_ID'].$tmpID->Confirmation_ID;
                            $tmpID->Popup_ID        = $tmpID->Confirmation_ID;
                            if (isset($tmpID->LCell_ID)) {
                                $tmpID->PopupAndCell_ID = $tmpID->Popup_ID.self::ID_SEPARATOR.self::$id_index['LCell_ID'].$tmpID->LCell_ID;
                            }
                        }
                    }
                    $result[] = $tmpID;
                }
            }
        }
        //self::$ENCRYPTED_ID_LENGTH;
        if (count($result) === 0) {
            return null;
        }
        if (count($result) === 1) {
            return $result[0];
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    private static function isValid(string $idType, string $idElement, string $totalIDString): bool
    {
        if ($idElement !== Sanitizer::sanitize($idElement)) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". No html entities allowed in ID');
            $e->setIdData($idType, $idElement);
            throw $e;
        }
        if (strlen($idElement) > self::$string_IDs[$idType]['maxLength']) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". Max ID length of '.self::$string_IDs[$idType]['maxLength'].' defined. Actual string length is '.strlen($idElement).'.');
            $e->setIdData($idType, $idElement);
            throw $e;
        }
        if (self::$string_IDs[$idType]['pregMatch'] !== '' && !preg_match(self::$string_IDs[$idType]['pregMatch'], $idElement)) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". "'.$idElement.'" does not match the defined regular expression');
            $e->setIdData($idType, $idElement, self::$string_IDs[$idType]['pregMatch']);
            throw $e;
        }
        return true;
    }

    private static function isValidReserved(string $idType, string $idElement, string $totalIDString): bool
    {
        if ($idElement !== Sanitizer::sanitize($idElement)) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". No html entities allowed in ID');
            $e->setIdData($idType, $idElement);
            throw $e;
        }
        if ((array_key_exists('minLength', self::$reserved_string_IDs[$idType])) && strlen($idElement) < self::$reserved_string_IDs[$idType]['minLength']) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". Min ID length of '.self::$reserved_string_IDs[$idType]['minLength'].' defined. Actual string length is '.strlen($idElement).'.');
            $e->setIdData($idType, $idElement);
            throw $e;
        }
        if (strlen($idElement) > self::$reserved_string_IDs[$idType]['maxLength']) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". Max ID length of '.self::$reserved_string_IDs[$idType]['maxLength'].' defined. Actual string length is '.strlen($idElement).'.');
            $e->setIdData($idType, $idElement);
            throw $e;
        }
        if (self::$reserved_string_IDs[$idType]['pregMatch'] !== '' && !preg_match(self::$reserved_string_IDs[$idType]['pregMatch'], $idElement)) {
            $e = new Exception('Incorrect ID: "'.$totalIDString.'". "'.$idElement.'" does not match the defined regular expression');
            $e->setIdData($idType, $idElement);
            throw $e;
        }
        return true;
    }

    public static function UUID(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }

    public static function GUID(): string
    {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }
}
