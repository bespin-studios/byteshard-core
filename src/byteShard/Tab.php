<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Internal\Action\ClientExecutionInterface;
use byteShard\Internal\Event\EventStorage;
use byteShard\Internal\Event\EventStorageInterface;
use byteShard\Internal\LayoutContainer;
use byteShard\Internal\NavigationItem;
use byteShard\Internal\Struct;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Event\TabEvent;
use byteShard\Internal\TabLegacyInterface;
use byteShard\Internal\Toolbar\ToolbarContainer;
use byteShard\Utils\Strings;

/**
 * Class Tab
 * @package byteShard
 */
class Tab extends LayoutContainer implements EventStorageInterface, ToolbarContainer, TabLegacyInterface, NavigationItem
{
    use EventStorage;

    /**
     * @var array
     */
    private array $popups = [];

    /**
     * @var array
     */
    private array $toolbar  = [];
    private bool  $selected = false;

    /**
     * Tab constructor.
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->meta['level'] = 1;
        parent::__construct($name);
    }

    public function selectFirstTab(): void
    {
        $this->selected = true;
        $tabs = $this->getTabs();
        if (!empty($tabs)) {
            $tabs[array_key_first($tabs)]->selectFirstTab();
        }
    }

    public function getSelected(): bool
    {
        return $this->selected;
    }

    public function selectFirstTabIfNoneSelected(): void
    {
        $found = false;
        $tabs = $this->getTabs();
        foreach ($tabs as $tab) {
            if ($tab->getSelected() === true) {
                $found = true;
            }
            $tab->selectFirstTabIfNoneSelected();
        }
        if ($found === false && !empty($tabs)) {
            $tabs[array_key_first($tabs)]->setSelected();
        }
    }

    public function getDirectChildren(): array
    {
        return $this->getTabs();
    }

    public function recursiveUnsetSelected(): void
    {
        $this->selected = false;
        foreach ($this->getTabs() as $tab) {
            $tab->recursiveUnsetSelected();
        }
    }

    public function setSelected(string $name = ''): bool
    {
        if ($name === '') {
            $this->selected = true;
        } else {
            $currentTab = $this->getNewId()->getTabId();
            if ($currentTab === $name) {
                $this->selected = true;
                return true;
            } else {
                $idParts  = explode('\\', $name);
                $namePart = [];
                $tabs = $this->getTabs();
                while (!empty($idParts)) {
                    $namePart[] = array_shift($idParts);
                    if (implode('\\', $namePart) === $currentTab) {
                        $this->selected = true;
                        while (!empty($idParts)) {
                            $namePart[] = array_shift($idParts);
                            $child      = implode('\\', $namePart);
                            if (array_key_exists($child, $tabs)) {
                                return $tabs[$child]->setSelected($name);
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param string $objectName
     * @return array
     */
    public function getEventIDForInteractiveObject(string $objectName): array
    {
        // interactive Object is in this cell
        if (isset($this->event['EventIDs'], $this->event['EventIDs'][$objectName])) {
            // Object with that name already registered in this cell, return the ID
            $result['name']       = $this->event['EventIDs'][$objectName];
            $result['registered'] = true;
            return $result;
        }
        // Object not yet registered, generate ID
        // At least one interactive object already registered, get the current object counter
        // Else start with a count of 1
        $objectIDCounter = $this->event['EventIDCounter'] ?? 1;
        // Generate Object ID
        $objectID = ID::getID('Event_ID', $objectIDCounter);
        // Save Object ID in Tab Object to keep track of registered interactive objects
        $this->event['EventIDs'][$objectName] = $objectID;
        // Increment object counter
        $objectIDCounter++;
        // Save Object counter
        $this->event['EventIDCounter'] = $objectIDCounter;
        $result['name']                = $objectID;
        $result['registered']          = false;
        return $result;
    }

    /**
     * @API
     * @return string
     * @deprecated use getScopeLocaleToken() instead
     */
    public function getBaseLocale(): string
    {
        return $this->getScopeLocaleToken();
    }

    /**
     * @return string
     */
    public function getScopeLocaleToken(): string
    {
        //TODO: create scope locale for popup on tab toolbar
        return '';
    }

    /**
     * @return string|null
     */
    public function getToolbarName(): ?string
    {
        if (isset($this->toolbar['name'])) {
            return $this->toolbar['name'];
        }
        if (isset($this->meta['name'])) {
            return $this->meta['name'];
        }
        return null;
    }

    /**
     * @param int $parentLevel
     * @param string|null $parentID
     * @return string
     * @throws Exception
     */
    public function setParentTabID(int $parentLevel, string $parentID = null): string
    {
        $this->meta['parentID'] = $parentID;
        $this->meta['level']    = $parentLevel + 1;
        //TODO: store tab Ids plain array in the session, then we don't have to decode/encode all the time. Encode before ids are sent to the client, decode on entrypoint, anything else, work with plain values
        if ($this->meta['parentID'] === null) {
            $id = ['!#tab' => [$parentLevel => $this->meta['name']]];
        } else {
            $id                        = json_decode(Session::decrypt($this->meta['parentID']), true);
            if (!is_array($id)) {
                Debug::error('Could not decrypt/decode id');
                return '';
            }
            $id['!#tab'][$parentLevel] = $this->meta['name'];
        }
        $this->meta['ID'] = Session::encrypt(json_encode($id), Session::getTopLevelNonce());
        return $this->meta['ID'];
    }

    /**
     * @return Tab|null
     */
    public function getParent(): ?Tab
    {
        trigger_error('Tab::getParent is deprecated.', E_USER_DEPRECATED);
        return null;
    }

    public function getNonce(): string
    {
        //TODO: tabs need their own nonce because they can have a toolbar which in turn needs a nonce for it's actions 
        return '';
    }

    public function getActionId(): string
    {
        //TODO: tabs will be first class citizens and implement their own event interfaces. For this we will need the possibility to set and retrieve actionIds
        return '';
    }

    /**
     * @param string $namespace
     * @return Tab
     * @internal
     */
    public function setNamespace(string $namespace): self
    {
        $this->meta['namespace'] = rtrim($namespace, '\\').'\\'.trim($this->meta['name'], '\\');
        return $this;
    }


    public function getTabNew(\byteShard\ID\ID $id): ?Tab
    {
        $tabs = $this->getTabs();
        if (!empty($tabs)) {
            $tabId = $id->getTabId();
            if (array_key_exists($tabId, $tabs)) {
                return $tabs[$tabId];
            } else {
                if (str_contains($tabId, '\\')) {
                    $idParts       = explode('\\', $tabId);
                    $parentIdParts = [];
                    if (count($idParts) >= $this->meta['level']) {
                        for ($i = 0; $i < $this->meta['level']; $i++) {
                            $parentIdParts[] = $idParts[$i];
                        }
                    }
                    $parentId = implode('\\', $parentIdParts);
                    if (array_key_exists($parentId, $tabs)) {
                        return $tabs[$parentId]->getTabNew($id);
                    }
                }
            }
        }
        return null;
    }

    public function getToolbarData(): array
    {
        return $this->toolbar;
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self
    {
        $this->meta['label'] = $label;
        return $this;
    }

    /**
     * @API
     * @param string|null $php_class_name
     * @return $this
     */
    public function setToolbar(string $php_class_name = null): self
    {
        if ($php_class_name !== null) {
            $this->toolbar['name'] = $php_class_name;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getToolbarClass(): string
    {
        if (isset($this->toolbar['name'])) {
            return '\\App\\Cell\\'.$this->toolbar['name'];
        }
        return '\\App\\Cell\\'.$this->meta['name'].'_toolbar';
    }

    /**
     * @API
     * @param Struct\ID $id
     * @return $this
     */
    public function setToolbarDependency(Struct\ID $id): self
    {
        $this->toolbar['relatedID']['static'] = $id->ID;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        if (array_key_exists('label', $this->meta)) {
            return Strings::purify($this->meta['label']);
        }
        return Strings::purify(Locale::get(str_replace('\\', '_', trim($this->meta['namespace'], '\\')).'::Tab.Label'));
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getNavigationData(): array
    {
        //$result['ID']    = $this->meta['ID'];
        $result['ID']    = $this->getNewId()->getEncryptedContainerId();
        $result['label'] = $this->getLabel();
        $width           = $this->getWidth();
        if ($width !== null) {
            $result['width'] = $width;
        }
        if ($this->selected === true) {
            $result['selected'] = true;
        }
        if (isset($this->meta['closable']) && $this->meta['closable'] === true) {
            $result['closable'] = true;
        }
        if (!empty($this->toolbar)) {
            $result['toolbar'] = true;
        }
        if ($this->layout !== null) {
            $result['layout'] = $this->layout->getNavigationData();
            $result['bubble'] = $this->layout->bubble();
        } else {
            $bubble = 0;
            foreach ($this->getTabs() as $id => $tab) {
                //$id = $tab->getNewId()->getTabId();
                $result['nested'][$id] = $tab->getNavigationData();
                $bubble                += $result['nested'][$id]['bubble'];
            }
            $result['bubble'] = $bubble;
        }
        //$result['bubble'] = $this->bubble();
        return $result;
    }

    public function bubble(): int
    {
        if ($this->layout !== null) {
            return $this->layout->bubble();
        }
        $bubble = 0;
        foreach ($this->getTabs() as $tab) {
            if ($tab instanceof Tab) {
                $bubble += $tab->bubble();
            }
        }
        return $bubble;
    }

    public function bubbles(): array
    {
        $id = $this->getNewId()->getEncryptedContainerId();
        if ($this->layout !== null) {
            return [
                $id => $this->layout->bubble()
            ];
        }
        $bubbles   = [];
        $bubbleSum = 0;
        foreach ($this->getTabs() as $tab) {
            $subTabBubbles = $tab->bubbles();
            $bubbles       = array_merge($subTabBubbles, $bubbles);
        }
        foreach ($bubbles as $bubble) {
            $bubbleSum += $bubble;
        }
        $bubbles[$id] = $bubbleSum;
        return $bubbles;
    }

    /**
     * @return array
     */
    public function getLocale(): array
    {
        $result['label'] = $this->getLabel();
        if ($this->layout !== null && $this->selected === true) {
            $result['cell'] = $this->layout->getLocale();
        } else {
            foreach ($this->getTabs() as $tab_id => $tab) {
                $result['nested'][$tab_id] = $tab->getLocale();
            }
        }
        return $result;
    }

    /**
     * @return int|null
     */
    private function getWidth(): ?int
    {
        return isset($this->meta['width']) && is_int($this->meta['width']) ? $this->meta['width'] : null;
    }

    /**
     * @API
     * @param bool $bool
     * @return $this
     */
    public function setClosable(bool $bool = true): self
    {
        if (is_bool($bool)) {
            if ($bool === true) {
                $this->meta['closable'] = true;
            } else {
                if (isset($this->meta['closable'])) {
                    unset($this->meta['closable']);
                }
            }
        }
        return $this;
    }

    /**
     * @param Event ...$eventObjects
     */
    public function addEvents(Event ...$eventObjects): static
    {
        foreach ($eventObjects as $event_object) {
            if ($event_object instanceof TabEvent) {
                $name = $event_object->getContentEventName();
                if (!isset($this->event['content'][$name])) {
                    $this->event['content'][$name] = $event_object;
                }
                if (($event_object instanceof Tab\Event\OnClose) && (!isset($this->meta['closable']) || $this->meta['closable'] === false)) {
                    $this->meta['closable'] = true;
                }
            }
        }
        return $this;
    }

    /**
     * @param string $eventName
     * @return Action[]|ClientExecutionInterface[]
     */
    public function getContentActions(string $eventName): array
    {
        if (isset($this->event['content'], $this->event['content'][$eventName])) {
            $event = $this->event['content'][$eventName];
            if ($event instanceof Event) {
                return $event->getActionArray();
            }
        }
        return [];
    }

    /**
     * @param string $popupName
     * @return string
     * @throws Exception
     */
    public function getIDForPopup(string $popupName): string
    {
        //TODO: change doc to popup
        // Popup Object is already in this tab
        if (isset($this->popups[$popupName])) {
            // Object with that name already registered in this cell, return the ID
            return $this->popups[$popupName];
        }
        $decrypted                = json_decode(Session::decrypt($this->meta['ID']), true);
        if (!is_array($decrypted)) {
            Debug::error('Could not decrypt/decode id');
            return '';
        }
        $decrypted['!#pop']       = $popupName;
        $encrypted                = Session::encrypt(json_encode($decrypted), Session::getTopLevelNonce());
        $this->popups[$popupName] = $encrypted;
        return $encrypted;
    }

    /**
     * @internal
     */
    public function unRegisterPopups(): void
    {
        //cycle through all registered popups underneath this tab and remove them
        foreach ($this->popups as $popupId) {
            Session::removePopup($popupId);
        }
    }
}
