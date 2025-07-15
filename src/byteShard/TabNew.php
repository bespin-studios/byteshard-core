<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Enum\ContentType;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Layout;
use byteShard\Internal\NavigationItem;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Internal\TabLegacyInterface;
use byteShard\Internal\Toolbar\ToolbarContainer;
use byteShard\Layout\Enum\Pattern;
use byteShard\Utils\Strings;
use UnitEnum;

abstract class TabNew implements TabLegacyInterface, NavigationItem, ToolbarContainer
{
    use PermissionImplementation;

    private ID\ID  $id;
    private array  $tabs     = [];
    private Layout $layout;
    private bool   $selected = false;
    private bool   $closable = false;
    //Todo: private Toolbar $toolbar;
    //Todo: private string  $label;
    private bool                       $initialized = false;
    private Layout|TabBar|SideBar|null $content     = null;
    private bool                       $toolbar     = false;

    public function __construct(string|UnitEnum ...$permissions)
    {
        $this->id = \byteShard\ID\ID::factory(new TabIDElement(get_called_class()));
        foreach ($permissions as $permission) {
            $this->setPermission($permission);
        }
    }

    public function getSelected(): bool
    {
        return $this->selected;
    }

    public function setToolbar(): void
    {
        $this->toolbar = true;
    }

    /**
     * @API
     */
    public function addTab(TabNew ...$tabs): void
    {
        trigger_error('byteShard\TabNew::addTab is deprecated. Please create a new \byteShard\TabBar and add it with byteShard\TabNew::setTabBar', E_USER_DEPRECATED);
        if (!$this->content instanceof TabBar) {
            $this->content = new TabBar();
        }
        $this->content->addTabs(...$tabs);
    }

    public function setTabBar(TabBar $tabBar): void
    {
        $this->content = $tabBar;
    }

    public function isClosable(): bool
    {
        return $this->closable;
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
        trigger_error('byteShard\TabNew::getCells is deprecated.', E_USER_DEPRECATED);
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
        $this->initLayout();
        $this->content->setPattern($pattern);
    }

    /**
     * @param Cell ...$cells
     * @return void
     * @throws Exception
     * @API
     */
    public function addCell(Cell ...$cells): void
    {
        $this->initLayout();
        foreach ($cells as $cell) {
            $this->content->addCell($cell);
        }
    }

    private function initLayout(): void
    {
        if (!$this->content instanceof Layout) {
            $this->content = new Layout($this->id->getEncryptedContainerId(), $this->id->getTabId(), $this->id);
        }
    }


    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function setInitialized(): void
    {
        $this->initialized = true;
    }

    /**
     * @internal
     */
    public function getNavigationData(): array
    {
        if (!$this->isInitialized()) {
            $this->defineTabContent();
            $this->setInitialized();
        }
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

    public function getItemConfig(string $selectedId = ''): ContentComponent
    {
        if (!$this->isInitialized()) {
            $this->defineTabContent();
            $this->setInitialized();
        }
        $content = [];
        $setup   = [];
        switch (true) {
            case $this->content instanceof Layout:
                $cells = $this->content->getCells();
                foreach ($cells as $cell) {
                    Session::registerCell($cell);
                }
                $content[]       = $this->content->getItemConfig();
                $setup['bubble'] = $this->content->bubble();
                break;
            case $this->content instanceof TabBar:
            case $this->content instanceof SideBar:
                $content[] = $this->content->getRootParameters($selectedId);
                break;
        }
        $setup['ID']    = $this->id->getEncryptedContainerId();
        $setup['label'] = $this->getLabel();
        if ($this->selected === true) {
            $setup['selected'] = true;
        }
        if ($this->closable === true) {
            $setup['closable'] = true;
        }
        if (isset($this->toolbar) && $this->toolbar === true) {
            //TODO: implement toolbar. events have to be routed to the tab class where the toolbar is defined.
            //TODO: some work has to be done in the toolbar repo
            $content[] = new ContentComponent(
                type   : ContentType::DhtmlxToolbar,
                content: '<?xml version="1.0" encoding="utf-8"?>
                    <toolbar>
                        <item type="button" id="id0" img="add.svg" text="Position hinzufügen"/>
                        <item type="button" id="id1" enabled="" imgdis="add.svg" img="add.svg" text="Personen zuweisen"/>
                        <item type="button" id="id2" enabled="" imgdis="tick.svg" img="tick.svg" text="Position schließen"/>
                    </toolbar>',
                events : [new ClientCellEvent('onClick', 'doOnClick')]);
        }
        return new ContentComponent(
            type   : ContentType::DhtmlxTab,
            content: $content,
            setup  : $setup
        );
    }

    /**
     * @return void
     * @API
     */
    abstract public function defineTabContent(): void;
}
