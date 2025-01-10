<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\ID\TabIDElement;
use byteShard\Layout\Enum\Pattern;
use byteShard\Locale;
use byteShard\SideBarItem;
use byteShard\Tab;
use byteShard\TabNew;
use byteShard\ID\ID;

class SessionTabs
{
    private const PARENT_TAB_ID = 0;

    private array $tabs = [];
    /** @var Tab[] */
    private array  $legacyTabs   = [];
    private array  $selectedTabs = [];
    private string $type         = 'TabBar';

    private function unsetLastSelectedTabOnSameLevelAndSetNewSelectedTab(int $level, string $parent, string $selectedTab): void
    {
        foreach ($this->selectedTabs as $tab => $sel) {
            $splitTabs = explode('\\', $tab);
            if (count($splitTabs) === $level) {
                array_pop($splitTabs);
                $iterationParent = implode('\\', $splitTabs);
                if ($iterationParent === $parent) {
                    unset($this->selectedTabs[$tab]);
                    $this->selectedTabs[$selectedTab] = false;
                    return;
                }
            }
        }
    }

    public function setSelectedTab(string $selectedTab): void
    {
        $split   = explode('\\', $selectedTab);
        $current = $selectedTab;

        // fill selected tabs array with all selected tabs per level
        while (!empty($split)) {
            $level = count($split);
            array_pop($split);
            $parent = implode('\\', $split);
            $this->unsetLastSelectedTabOnSameLevelAndSetNewSelectedTab($level, $parent, $current);
            $current = $parent;
        }

        foreach (array_filter($this->selectedTabs) as $key => $value) {
            $this->selectedTabs[$key] = false;
        }
        $this->selectedTabs[$selectedTab] = true;
    }

    public function getSelectedTab(): string
    {
        foreach ($this->selectedTabs as $tab => $selected) {
            if ($selected === true) {
                return $tab;
            }
        }
        return '';
    }

    public function addTab(NavigationItem ...$tabs): void
    {
        foreach ($tabs as $tab) {
            if ($tab instanceof SideBarItem) {
                $this->type = 'SideBar';
            }
            if (empty($this->tabs) && empty($this->legacyTabs)) {
                if ($tab instanceof SideBarItem) {
                    $this->type = 'SideBar';
                }
            }
            if ($tab instanceof Tab) {
                //legacy
                $tab->setParentTabID(self::PARENT_TAB_ID);
                $tab->setNamespace('');
                if ($tab->getAccessType() > 0) {
                    $tab->checkPredefinedChildren();
                    $id                    = $tab->getNewId()->getTabId();
                    $this->tabs[$id]       = null;
                    $this->legacyTabs[$id] = $tab;
                }
            } else {
                $this->tabs[$tab->getId()] = $tab::class;
            }
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function removeTab(ID $id): bool
    {
        $tabId = $id->getTabId();
        if (array_key_exists($tabId, $this->tabs)) {
            unset($this->tabs[$tabId]);
        }
        if (array_key_exists($tabId, $this->legacyTabs)) {
            $this->legacyTabs[$tabId]->removeLayoutContainer();
            unset($this->legacyTabs[$tabId]);
            return true;
        } elseif (str_contains($tabId, '\\')) {
            $parent = explode('\\', $tabId)[0];
            if (array_key_exists($parent, $this->legacyTabs)) {
                return $this->legacyTabs[$parent]->removeTab($id);
            }
        }
        return false;
    }

    public function getTabContent(): array
    {
        $result = [];
        $tabs   = $this->getTabs();
        if (!empty($tabs)) {
            foreach ($tabs as $tab) {
                $result['tabs'][] = $tab->getNavigationData();
                $cells            = $tab->getCells();
                foreach ($cells as $cell) {
                    \byteShard\Session::registerCell($cell);
                }
            }
        } else {
            //TODO: add a first-class citizen tab object once it's established.
            $result['tabs'][] = $this->getNoApplicationPermissionTab();
        }
        return $result;
    }

    private function getNoApplicationPermissionTab(): array
    {
        $tab = new Tab('no_permission');
        $tab->setParentTabID(self::PARENT_TAB_ID);
        $tab->setPattern(Pattern::PATTERN_1C);
        $tab->setLabel(Locale::get('byteShard.environment.tab.label.noPermission'));
        $tab->addCell($cell = new Cell());
        $tab->setSelected();
        $cell->setHideHeader();
        return $tab->getNavigationData();
    }

    private function getTabs(): array
    {
        if (empty($this->tabs)) {
            return [];
        }
        $tabs = $this->tabs;
        // keep old style tabs, add TabNew to array
        foreach ($tabs as $id => $tab) {
            if ($tab !== null) {
                $tabs[$id] = new $tab();
                $tabs[$id]->defineTabContent();
            } else {
                // legacy tabs
                $tabs[$id] = $this->legacyTabs[$id];
                $tabs[$id]->recursiveUnsetSelected();
            }
        }

        // set selected tab
        $found = false;
        // first select all tabs which were previously selected by the user
        foreach ($this->selectedTabs as $selectedTab => $selected) {
            $split    = explode('\\', $selectedTab);
            $tabDepth = count($split);
            if ($tabDepth === 1) {
                if (array_key_exists($selectedTab, $tabs)) {
                    // top level
                    $found = true;
                    $tabs[$selectedTab]->setSelected();
                }
            } elseif ($tabDepth > 1) {
                if ($selected === true) {
                    if (array_key_exists($split[0], $tabs)) {
                        $tabs[$split[0]]->setSelected($selectedTab);
                    }
                } else {
                    // if any multi level tab was selected but is not active, mark it as selected,
                    // so it will be selected once the user switched to the top level tab
                    $tabPath    = '';
                    $currentTab = $tabs;
                    foreach ($split as $index => $item) {
                        $tabPath .= $index === 0 ? $item : '\\'.$item;
                        if (isset($currentTab) && is_array($currentTab) && array_key_exists($tabPath, $currentTab)) {
                            $currentTab = $currentTab[$tabPath];
                            if ($currentTab instanceof Tab) {
                                if ($index > 0) {
                                    $currentTab->setSelected();
                                }
                                $currentTab = $currentTab->getDirectChildren();
                            }
                        } elseif (isset($currentTab) && $currentTab instanceof TabNew) {
                            $currentTab = $currentTab->getTabNew(ID::factory(new TabIDElement($tabPath)));
                            if ($index > 0 && $currentTab instanceof TabNew) {
                                $currentTab->setSelected();
                            }
                        }
                    }
                }
            }
        }
        if ($found === false) {
            reset($tabs);
            $firstTab = key($tabs);
            $tabs[$firstTab]->setSelected();
        }
        foreach ($tabs as $tab) {
            $tab->selectFirstTabIfNoneSelected();
        }
        return $tabs;
    }

    private function getLegacyTab(ID $id): ?Tab
    {
        $tabId = $id->getTabId();
        if (array_key_exists($tabId, $this->legacyTabs)) {
            return $this->legacyTabs[$tabId];
        } elseif (str_contains($tabId, '\\')) {
            $parent = explode('\\', $tabId)[0];
            if (array_key_exists($parent, $this->legacyTabs)) {
                return $this->legacyTabs[$parent]->getTabNew($id);
            }
        }
        return null;
    }

    public function getTab(ID $id): Tab|TabNew|null
    {
        $tabId = $id->getTabId();
        if (class_exists($tabId) && is_subclass_of(TabNew::class, $tabId)) {
            return new $tabId();
        }
        return $this->getLegacyTab($id);
    }

    public function getLocaleForAllTabs(): array
    {
        $result = [];
        foreach ($this->tabs as $tabId => $tab) {
            $result['Tab'][$tabId] = $tab->getLocale();
        }
        return $result;
    }
}