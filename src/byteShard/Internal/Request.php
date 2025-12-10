<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\ID\ID;
use byteShard\Internal\Request\ElementType;
use byteShard\Internal\Request\EventType;
use DateTime;
use DateTimeZone;
use Exception;

class Request
{
    private ?DateTimeZone $clientTimeZone;
    private ?DateTime     $dataAge;
    private ?ElementType  $elementType;
    private ?EventType    $event;
    private string        $affectedId;
    private string        $cellNonce;
    private mixed         $data;
    private ?ID           $id;
    private array         $objectProperties;
    private string        $context;

    public function __construct()
    {
        try {
            $input   = file_get_contents('php://input');
            $request = json_decode($input, true);
        } catch (Exception) {
            return;
        }

        $this->id               = ID::decryptFinalImplementation($request['xid'] ?? '');
        $this->cellNonce        = isset($request['cn']) ? base64_decode($request['cn']) : '';
        $this->clientTimeZone   = $this->setClientTimeZone($request['tz']);
        $this->event            = EventType::tryFrom($request['ev'] ?? '');
        $this->dataAge          = $this->setDataAge($request['tq'] ?? '');
        $this->elementType      = ElementType::tryFrom($request['ty'] ?? '');
        $this->affectedId       = $request['id'] ?? '';
        $this->data             = isset($request['dat']) ? Sanitizer::sanitize($request['dat']) : null;
        $this->objectProperties = $this->decryptObjectProperties($request['op'] ?? '');
        $this->context          = isset($request['ctx']) ? \byteShard\Session::decrypt($request['ctx']) : '';
        $this->mapLegacyRequestData($request);
    }

    public function getContext(): string
    {
        return $this->context;
    }

    private function decryptObjectProperties(string $objectProperties): array
    {
        $result = [];
        try {
            if (extension_loaded('zlib') === true) {
                $decrypted = json_decode(gzuncompress(\byteShard\Session::decrypt($objectProperties)));
            } else {
                $decrypted = json_decode(\byteShard\Session::decrypt($objectProperties));
            }
            foreach ($decrypted as $object => $properties) {
                $result[$object]    = $properties;
                $result[$object]->i = $object;
            }
        } catch (Exception) {

        }
        return $result;
    }

    public function getObjectProperties(): array
    {
        return $this->objectProperties;
    }

    public function getId(): ?ID
    {
        return $this->id;
    }

    private function mapLegacyRequestData(array $request): void
    {
        if (array_key_exists('evName', $request) && $this->event === null) {
            $this->event = EventType::tryFrom($request['evName']);
        }
        if (array_key_exists('elType', $request) && $this->elementType === null) {
            $this->elementType = ElementType::tryFrom($request['elType']);
        }
        if (array_key_exists('affectedID', $request) && is_string($request['affectedID']) && $this->affectedId === '') {
            $this->affectedId = $request['affectedID'];
        }
        if (array_key_exists('data', $request) && $this->data === null) {
            $this->data = Sanitizer::sanitize($request['data']);
        }
        if (array_key_exists('tabID', $request) && $this->id === null) {
            $this->id = ID::decryptSeparateTabAndCellId($request['tabID'], $request['cellID'] ?? '');
        }
        if (array_key_exists('timestamp', $request) && $this->dataAge === null) {
            $this->dataAge = $this->setDataAge($request['timestamp']);
        }
    }

    public function getClientTimeZone(): ?DateTimeZone
    {
        return $this->clientTimeZone;
    }

    public function getDataAge(): ?DateTime
    {
        return $this->dataAge;
    }

    public function getElementType(): ?ElementType
    {
        return $this->elementType;
    }

    public function getEvent(): ?EventType
    {
        return $this->event;
    }

    public function getAffectedId(): string
    {
        return $this->affectedId;
    }

    public function getCellNonce(): string
    {
        return $this->cellNonce;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    private function setDataAge(string $timeStamp): ?DateTime
    {
        $date = DateTime::createFromFormat('YmdHis', $timeStamp, new DateTimeZone('UTC'));
        if ($date === false) {
            return null;
        }
        return $date;
    }

    private function setClientTimeZone(string $timeZone): ?DateTimeZone
    {
        try {
            $timeZone = new DateTimeZone($timeZone);
        } catch (Exception) {
            return null;
        }
        return $timeZone;
    }
}
