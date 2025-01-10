<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\ID\TabIDElement;
use byteShard\Internal\Layout;
use byteShard\Internal\NavigationItem;
use byteShard\Internal\TabLegacyInterface;
use byteShard\Layout\Enum\Pattern;
use byteShard\Utils\Strings;

abstract class TabNew implements TabLegacyInterface, NavigationItem
{
    private ID\ID  $id;
    private array  $tabs     = [];
    private Layout $layout;
    private bool   $selected = false;
    private bool   $closable = false;
    //Todo: private Toolbar $toolbar;
    //Todo: private string  $label;

    public function __construct()
    {
        $this->id = \byteShard\ID\ID::factory(new TabIDElement(get_called_class()));
    }

    public function getSelected(): bool
    {
        return $this->selected;
    }

    /**
     * @API
     */
    public function addTab(TabNew ...$tabs): void
    {
        foreach ($tabs as $tab) {
            if (!array_key_exists($tab->getId(), $this->tabs)) {
                $this->tabs[$tab->getId()] = $tab;
            }
        }
    }

    public function selectFirstTabIfNoneSelected(): void
    {
        $found = false;
        foreach ($this->tabs as $tab) {
            if ($tab->getSelected() === true) {
                $found = true;
            }
            $tab->selectFirstTabIfNoneSelected();
        }
        if ($found === false && !empty($this->tabs)) {
            reset($this->tabs);
            $firstTab = key($this->tabs);
            $this->tabs[$firstTab]->setSelected();
        }
    }

    public function setSelected(string $name = ''): bool
    {
        if ($name === '') {
            $this->selected = true;
            return true;
        } else {
            $currentTab = $this->id->getTabId();
            if ($currentTab === $name) {
                $this->selected = true;
                return true;
            } else {
                $idParts  = explode('\\', $name);
                $namePart = [];
                for ($i = 0; $i < count($idParts); $i++) {
                    $namePart[] = $idParts[$i];
                    if (implode('\\', $namePart) === $currentTab) {
                        $this->selected = true;
                        if (array_key_exists(($i + 1), $idParts)) {
                            $this->selected = true;
                            $namePart[]     = $idParts[$i + 1];
                            $child          = implode('\\', $namePart);
                            if (array_key_exists($child, $this->tabs)) {
                                return $this->tabs[$child]->setSelected($name);
                            }
                            break;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function getTabNew(\byteShard\ID\ID $id): ?self
    {
        if (!empty($this->tabs)) {
            $tabId = $id->getTabId();
            if (array_key_exists($tabId, $this->tabs)) {
                return $this->tabs[$tabId];
            } else {
                if (str_contains($tabId, '\\')) {
                    $idParts       = explode('\\', $tabId);
                    $parentIdParts = [];

                    $parentId = implode('\\', $parentIdParts);
                    if (array_key_exists($parentId, $this->tabs)) {
                        return $this->tabs[$parentId]->getTabNew($id);
                    }
                }
            }
        }
        return null;
    }

    public function selectFirstTab(): void
    {
        $this->selected = true;
        if (!empty($this->tabs)) {
            reset($this->tabs);
            $this->tabs[key($this->tabs)]->selectFirstTab();
        }
    }

    public function getEncryptedId(): string
    {
        return $this->id->getEncryptedContainerId();
    }

    public function getId(): string
    {
        return $this->id->getTabId();
    }

    public function getLabel(): string
    {
        if (isset($this->label)) {
            return $this->label;
        }
        return Strings::purify(Locale::get(str_replace('\\', '_', $this->id->getTabId()).'::Tab.Label'));
    }

    /**
     * @return Cell[]
     */
    public function getCells(): array
    {
        $cells = isset($this->layout) ? $this->layout->getCells() : [];
        foreach ($this->tabs as $tab) {
            foreach ($tab->getCells() as $cell) {
                $cells[] = $cell;
            }
        }
        return $cells;
    }

    /**
     * @param Pattern $pattern
     * @return void
     * @API
     */
    public function setPattern(Pattern $pattern): void
    {
        $layout = $this->initLayout();
        $layout->setPattern($pattern);
    }

    /**
     * @param Cell ...$cells
     * @return void
     * @throws Exception
     * @API
     */
    public function addCell(Cell ...$cells): void
    {
        $layout = $this->initLayout();
        foreach ($cells as $cell) {
            $layout->addCell($cell);
        }
    }

    private function initLayout(): Layout
    {
        if (!isset($this->layout)) {
            $this->layout = new Layout($this->id->getEncryptedContainerId(), $this->id->getTabId(), $this->id);
        }
        return $this->layout;
    }

    /**
     * @internal
     */
    public function getNavigationData(): array
    {
        $this->defineTabContent();
        $result['ID']    = $this->id->getEncryptedContainerId();
        $result['label'] = $this->getLabel();
        if ($this->selected === true) {
            $result['selected'] = true;
        }
        if ($this->closable === true) {
            $result['closable'] = true;
        }
        if (isset($this->toolbar)) {
            $result['toolbar'] = true;
        }
        if (isset($this->layout)) {
            $result['layout'] = $this->layout->getNavigationData();
            $result['bubble'] = $this->layout->bubble();
        } else {
            $bubble = 0;
            foreach ($this->tabs as $tab) {
                if ($tab instanceof TabNew) {
                    $nestedTabData      = $tab->getNavigationData();
                    $result['nested'][] = $nestedTabData;
                    $bubble             += $nestedTabData['bubble'];
                }
            }
            $result['bubble'] = $bubble;
        }
        return $result;
    }

    /**
     * @return void
     * @API
     */
    abstract public function defineTabContent(): void;
}
