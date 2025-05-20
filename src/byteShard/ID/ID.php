<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\ID;

use byteShard\Session;
use byteShard\TabNew;
use DateTime;
use Exception;

class ID
{
    public const TABID       = '!#tb';
    public const POPUPID     = '!#pp';
    public const CELLID      = '!#cl';
    public const PATTERNID   = '!#pt';
    public const DATEID      = '!#dt';
    public const CONTAINERID = '!#ct';

    /** @var IDElementInterface[] */
    private array         $idElements = [];
    private string        $tabId      = '';
    private string        $popupId    = '';
    private string        $cellId     = '';
    private string        $patternId  = '';
    private DateIDElement $date;
    private string        $decrypted  = '';
    private string        $containerId;

    private function __construct(IDElementInterface ...$elements)
    {
        $this->addIdElement(...$elements);
    }

    public static function factory(IDElementInterface ...$elements): self
    {
        return new self(...$elements);
    }

    public static function decryptSeparateTabAndCellId(string $containerId, string $cellId = ''): ?self
    {
        // this method should be suspended once all ID related refactorings have been completed.
        // currently the client always passes a tab/popup and a separate cell id
        // we need to merge them here
        // in the future, the client will only send one single id which incorporates all necessary information
        $id = self::decrypt($containerId);
        if ($id !== null && $cellId !== '') {
            $id->setPatternCellId($cellId);
            if ($id->isPopupId()) {
                $id->addIdElement(new CellIDElement(trim($id->getPopupId(), '\\').'\\'.$cellId), new PatternIDElement($cellId));
            } elseif ($id->isTabId()) {
                $id->addIdElement(new CellIDElement(trim($id->getTabId(), '\\').'\\'.$cellId), new PatternIDElement($cellId));
            }
        }
        return $id;
    }

    private function setPatternCellId(string $patternCellId): void
    {
        $this->patternId = $patternCellId;
    }

    public static function decrypt(string $encryptedId): ?self
    {
        try {
            $decrypted = Session::decrypt($encryptedId);
        } catch (Exception) {
            $decrypted = $encryptedId;
        }
        $decoded = json_decode($decrypted, true);
        if (!is_array($decoded)) {
            return null;
        }
        $elements = [];
        if (array_key_exists('!#cel', $decoded)) {
            //!#cel is only the cell id. This will be migrated to a full object id and is then encoded with !#cl
            if (array_key_exists(self::CELLID, $decoded)) {
                $elements[] = new CellIDElement($decoded['cl']);
                unset($decoded[self::CELLID]);
            } else {
                if (array_key_exists(self::POPUPID, $decoded)) {
                    $elements[] = new CellIDElement(trim($decoded[self::POPUPID], '\\').'\\'.$decoded['!#cel']);
                } elseif (array_key_exists(self::TABID, $decoded)) {
                    $elements[] = new CellIDElement(trim($decoded[self::TABID], '\\').'\\'.$decoded['!#cel']);
                } elseif (array_key_exists('!#tab', $decoded)) {
                    $elements[] = new CellIDElement(trim(implode('\\', $decoded['!#tab']), '\\').'\\'.$decoded['!#cel']);
                }
            }
            unset($decoded['!#cel']);
        }
        if (array_key_exists('!#tab', $decoded)) {
            $elements[] = new TabIDElement(implode('\\', $decoded['!#tab']));
        }
        foreach ($decoded as $id => $value) {
            switch ($id) {
                case self::CELLID:
                    $elements[] = new CellIDElement($value);
                    break;
                case self::TABID:
                    $elements[] = new TabIDElement($value);
                    break;
                case self::POPUPID:
                    $elements[] = new PopupIDElement($value);
                    break;
                default:
                    if (is_int($value) || is_string($value)) {
                        $elements[] = new IDElement($id, $value);
                    }
                    break;
            }
        }
        return self::factory(...$elements);
    }

    public static function decryptFinalImplementation(string $encryptedId): ?self
    {
        try {
            $decrypted = Session::decrypt($encryptedId);
        } catch (Exception) {
            $decrypted = $encryptedId;
        }
        $decoded = json_decode($decrypted, true);
        if (!is_array($decoded)) {
            return null;
        }
        $elements = [];
        foreach ($decoded as $id => $value) {
            switch ($id) {
                case self::CELLID:
                    $elements[] = new CellIDElement($value);
                    break;
                case self::PATTERNID:
                    $elements[] = new PatternIDElement($value);
                    break;
                case self::TABID:
                    $elements[] = new TabIDElement($value);
                    break;
                case self::POPUPID:
                    $elements[] = new PopupIDElement($value);
                    break;
                case self::CONTAINERID:
                    $elements[] = new ContainerIDElement($value);
                    break;
                default:
                    if (is_int($value) || is_string($value)) {
                        $elements[] = new IDElement($id, $value);
                    }
                    break;
            }
        }
        $result = self::factory(...$elements);
        $result->addDecrypted($decrypted);
        return $result;
    }

    private function addDecrypted(string $decrypted): void
    {
        $this->decrypted = $decrypted;
    }

    public function getId(string $id): null|string|int
    {
        return ($this->idElements[$id] ?? null)?->getValue() ?? null;
    }

    public function getIds(): array
    {
        $result = [];
        foreach ($this->idElements as $element) {
            $result = array_merge($result, $element->getIdElement());
        }
        return $result;
    }

    public function getPatternCellId(): string
    {
        return $this->patternId;
    }

    public function getCellId(): string
    {
        return $this->cellId;
    }

    public function getTabId(): string
    {
        return $this->tabId;
    }

    public function getPopupId(): string
    {
        return $this->popupId;
    }

    public function getContainerId(): string
    {
        return $this->containerId ?? '';
    }

    public function getEncodedCellId(bool $includePatternId = true, bool $includePopupId = true): string
    {
        $id = [self::TABID => $this->tabId];
        if ($includePopupId === true && $this->popupId !== '') {
            $id[self::POPUPID] = $this->popupId;
        }

        if ($this->isCellId()) {
            $id[self::CELLID] = $this->cellId;
            if ($includePatternId === true) {
                $id[self::PATTERNID] = $this->patternId;
            }
        }
        if (isset($this->containerId)) {
            // reset everything, use only containerId for now
            $id                    = [];
            $id[self::CONTAINERID] = $this->containerId;
        }
        ksort($id);
        $json = json_encode($id);
        return $json === false ? '' : $json;
    }

    public function getEncryptedCellId(): string
    {
        return Session::encrypt($this->getEncodedCellId(), Session::getTopLevelNonce());
    }

    public function getEncodedContainerId(): string
    {
        $id = [self::TABID => $this->tabId];
        if ($this->popupId !== '') {
            $id[self::POPUPID] = $this->popupId;
        }
        ksort($id);
        $json = json_encode($id);
        return $json === false ? '' : $json;
    }

    public function getEncryptedId(): string
    {
        if (empty($this->idElements)) {
            return $this->getEncryptedCellId();
        }
        $result = json_decode($this->getEncodedCellId(), true);
        if (!is_array($result)) {
            return '';
        }
        foreach ($this->idElements as $idElement) {
            $result[$idElement->getId()] = $idElement->getValue();
        }
        ksort($result);
        return Session::encrypt(json_encode($result), Session::getTopLevelNonce());
    }

    public function getEncryptedContainerId(): string
    {
        return Session::encrypt($this->getEncodedContainerId(), Session::getTopLevelNonce());
    }

    public function getEncryptedCellIdForEvent(): string
    {
        $id[self::CELLID] = $this->cellId;
        ksort($id);
        return Session::encrypt(json_encode($id), Session::getTopLevelNonce());
    }

    public function addIdElement(IDElementInterface ...$elements): void
    {
        foreach ($elements as $element) {
            if ($element instanceof CellIDElement) {
                $this->cellId = $element->getValue();
            } elseif ($element instanceof PatternIDElement) {
                $this->patternId = $element->getValue();
            } elseif ($element instanceof TabIDElement) {
                $this->tabId = $element->getValue();
            } elseif ($element instanceof PopupIDElement) {
                $this->popupId = $element->getValue();
            } elseif ($element instanceof DateIDElement) {
                $this->date = $element;
            } elseif ($element instanceof ContainerIDElement) {
                $this->containerId = $element->getValue();
            } else {
                $this->idElements[$element->getId()] = $element;
            }
        }
    }

    public function getSelectedDate(): ?DateTime
    {
        if (!isset($this->date)) {
            return null;
        }
        return $this->date->getDate();
    }

    public function isCellId(): bool
    {
        return $this->cellId !== '';
    }

    public function isTabId(): bool
    {
        return $this->tabId !== '';
    }

    public function isPopupId(): bool
    {
        return $this->popupId !== '';
    }

    public static function refactor(string $id, ?self $containerId = null): ?self
    {

        if ($containerId?->isTabId() === true) {
            $split     = explode('\\', $id);
            $patternId = array_pop($split);
            $container = implode('\\', $split);
            if ($container === $containerId->getTabId()) {
                // cell is on a tab
                return new self(new TabIDElement($container), new CellIDElement($id));
            }
            return new self(new TabIDElement($containerId->getTabId()), new PopupIDElement($container), new CellIDElement($id));
        } else {
            $cellId = trim($id, '\\');
            if (str_starts_with(strtolower($cellId), 'app\\cell\\')) {
                $cellId = substr($cellId, 9);
            }
            $split     = array_filter(explode('\\', $cellId));
            $patternId = array_pop($split);
            $container = implode('\\', $split);
            if (class_exists('\\App\\Tab\\'.$container) && is_subclass_of('\\App\\Tab\\'.$container, TabNew::class)) {
                return new self(new TabIDElement($container), new CellIDElement($cellId));
            } elseif (class_exists('\\App\\Popup\\'.$container)) {
                //TODO: implement popup
                //$id = ID\ID::factory(new ID\TabIDElement($containerId->getTabId()), new ID\PopupIDElement($container), new CellIDElement($cellName));
            } else {
                // legacy
                $tabId = new self(new TabIDElement($container), new CellIDElement($cellId));
                $tab   = Session::getTab($tabId);
                if ($tab !== null) {
                    return $tabId;
                }
                // could not find tab in session, check if popup. Refactor later
                return new self(new TabIDElement(Session::legacyGetSelectedTab()), new PopupIDElement($container), new CellIDElement($cellId));
            }
        }
        return null;
    }

    public static function CellIdHelper(string $encryptedTabId, string $cellId = ''): string
    {
        if ($cellId === '') {
            return $encryptedTabId;
        }
        $decrypted = json_decode(Session::decrypt($encryptedTabId), true);
        if (!is_array($decrypted)) {
            return $encryptedTabId;
        }
        if (array_key_exists('!#ocl', $decrypted)) {
            $decrypted['!#cel'] = $decrypted['!#ocl'];
            unset($decrypted['!#ocl'], $decrypted['!#uid']);
        } else {
            $decrypted['!#cel'] = $cellId;
        }
        return Session::encrypt(json_encode($decrypted), Session::getTopLevelNonce());
    }
}
