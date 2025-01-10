<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Enum\AccessType;
use byteShard\Exception;
use byteShard\ID;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Struct;
use byteShard\Layout\Enum\Pattern;
use byteShard\Layout\Separator;
use byteShard\Popup;
use byteShard\Tab;
use byteShard\TabNew;
use UnitEnum;

/**
 * Class LayoutContainer
 * @package byteShard\Internal
 */
abstract class LayoutContainer implements TabParentInterface, ContainerInterface
{
    use PermissionImplementation {
        setPermission as PermissionTrait_setPermission;
        setAccessType as PermissionTrait_setAccessType;
    }

    /** @var Tab[] */
    private array     $tabs   = [];
    protected array   $event  = [];
    protected ?Layout $layout = null;
    protected array   $meta   = [];
    private ID\ID     $id;

    /**
     * LayoutContainer constructor.
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->id                = ID\ID::factory(($this instanceof Popup) ? new ID\PopupIDElement(trim($name, '\\')) : new ID\TabIDElement($name));
        $this->meta['name']      = $name;
        $this->meta['namespace'] = '\\'.trim($name, '\\');
    }

    public function getNewId(): ID\ID
    {
        return $this->id;
    }

    public function addTabAndCellIDElement(ID\TabIDElement $tabId, ID\CellIDElement $cellId): void
    {
        $this->id->addIdElement($tabId, $cellId);
    }

    public function addTabIdElement(ID\TabIDElement $tabId): void
    {
        $this->id->addIdElement($tabId);
    }

    protected function addParentTabId(?string $parentTabId): void
    {
        //deprecated
        if ($parentTabId !== null) {
            if ($this->id->isTabId() === true) {
                $id = $this->id->getTabId();
                $this->id->addIdElement(new ID\TabIDElement(implode('\\', [$parentTabId, $id])));
            }
        }
    }

    public function getContentClass(): string
    {
        //Todo: return tab class
        return '';
    }

    public function getEncodedId(): string
    {
        return $this->id->getEncodedContainerId();
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        if (array_key_exists('namespace', $this->meta)) {
            return $this->meta['namespace'];
        }
        return null;
    }

    public function addIdElementToAllCells(ID\IDElementInterface $element): void
    {
        if ($this->layout !== null) {
            foreach ($this->layout->getCells() as $cell) {
                $cell->getNewId()->addIdElement($element);
            }
        }
    }

    /**
     * @return Cell[]
     */
    public function getCells(): array
    {
        $cells = $this->layout !== null ? $this->layout->getCells() : [];
        foreach ($this->tabs as $tab) {
            foreach ($tab->getCells() as $cell) {
                $cells[] = $cell;
            }
        }
        return $cells;
    }

    /**
     * @return Tab[]
     */
    protected function getTabs(): array
    {
        return $this->tabs;
    }


    /**
     * is overwritten in Class Tab
     * @return string|null
     * @internal
     */
    public function getToolbarName(): ?string
    {
        trigger_error(__METHOD__.': name meta is deprecated', E_USER_DEPRECATED);
        if (isset($this->meta['name'])) {
            return $this->meta['name'];
        }
        return null;
    }

    // SETTERS

    /**
     * @param Event ...$event_objects
     * @return static
     */
    abstract public function addEvents(Event ...$event_objects): static;

    /**
     * @param Cell ...$cells
     * @return $this
     * @throws Exception
     */
    public function addCell(Cell ...$cells): self
    {
        if (!empty($this->tabs)) {
            reset($this->tabs);
            $e = new Exception(__METHOD__.': You cannot add Cells. The content has already been defined as '.get_class($this->tabs[key($this->tabs)]));
            $e->setLocaleToken('byteShard.layoutContainer.addCell.logic.contentNotLayout');
            throw $e;
        }
        if (!isset($this->layout)) {
            // if no content has been defined, attach Layout
            $this->addLayout();
        }
        if (isset($this->layout)) {
            foreach ($cells as $cell) {
                // pass this accessType down to every cell
                $cell->setParentAccessType($this->getAccessType());
                $this->layout->addCell($cell);
            }
        }
        return $this;
    }

    public function addNavigationItem(NavigationItem ...$navigationItems): void
    {
        foreach ($navigationItems as $navigationItem) {
            if ($navigationItem instanceof Tab || $navigationItem instanceof TabNew) {
                $this->addTab($navigationItem);
            }
        }
    }

    public function setCustomHeader(string $header): void
    {

    }

    /**
     * @param Tab ...$tabs
     * @return $this
     * @throws Exception
     */
    public function addTab(Tab|TabNew ...$tabs): self
    {
        if (isset($this->layout)) {
            $e = new Exception(__METHOD__.': You cannot add Tabs. The content has already been defined as Layout. Only Cells can be added');
            $e->setLocaleToken('byteShard.layoutContainer.addTab.logic.contentIsLayout');
            throw $e;
        }
        foreach ($tabs as $tab) {
            if ($tab instanceof Tab) {
                $tab->addParentTabId($this->id->getTabId());
                $tab->setParentTabID(
                    $this->meta['level'],
                    $this->meta['ID'] ?? null
                );
                $id = $tab->getNewId()->getTabId();
                $tab->setNamespace($this->meta['namespace']);

                $tab->setParentAccessType($this->getAccessType());
                if ($tab->getAccessType() > 0) {
                    if (!isset($this->meta['selectedID'])) {
                        $this->meta['selectedID'] = $id;
                        $tab->setSelected();
                    } else {
                        if ($id === $this->meta['selectedID']) {
                            $tab->setSelected();
                        }
                    }
                    $this->tabs[$id] = $tab;
                }
            }
        }
        return $this;
    }



    /**
     * @return bool
     */
    public function hasContentAttached(): bool
    {
        return !empty($this->tabs) || isset($this->layout);
    }

    /**
     * this function will remove a tab within the current layout container
     * @param ID\ID $id
     * @return bool
     * @throws Exception
     */
    public function removeTab(ID\ID $id): bool
    {
        $tabId = $id->getTabId();
        if (array_key_exists($tabId, $this->tabs)) {
            unset($this->tabs[$tabId]);
            return true;
        } elseif (str_contains($tabId, '\\')) {
            $exploded          = explode('\\', $tabId);
            $concatenatedTabId = [];
            foreach ($exploded as $item) {
                $concatenatedTabId[] = $item;
                if (array_key_exists(implode('\\', $concatenatedTabId), $this->tabs)) {
                    return $this->tabs[implode('\\', $concatenatedTabId)]->removeTab($id);
                }
            }
        }
        return false;
    }

    /**
     * this function will remove all included components of a layout container (e.g. included popups and layout cells)
     * @return void
     */
    public function removeLayoutContainer(): void
    {
        if ($this instanceof Tab) {
            $this->unRegisterPopups();
        }
        foreach ($this->tabs as $tab) {
            if ($tab instanceof LayoutContainer) {
                $tab->removeLayoutContainer();
            }
        }
    }

    /**
     * @param string $namespace
     * @return $this
     */
    public function setNamespace(string $namespace): self
    {
        $this->meta['namespace'] = '\\'.trim($namespace, '\\');
        return $this;
    }

    public function setPattern(Pattern $pattern): self
    {
        if (isset($this->layout)) {
            $this->layout->setPattern($pattern);
        } else {
            if (empty($this->tabs)) {
                $this->addLayout()->setPattern($pattern);
            } else {
                Debug::error('Trying to set pattern on a tab which already contains a content object and it\'s not a Layout object');
            }
        }
        return $this;
    }

    private function addLayout(): Layout
    {
        $name = '';
        if (array_key_exists('namespace', $this->meta)) {
            $name = $this->meta['namespace'];
        } elseif (array_key_exists('name', $this->meta)) {
            $name = $this->meta['name'];
        }
        $id           = $this->meta['ID'] ?? '';
        $this->layout = new Layout($id, $name, $this->id);
        return $this->layout;
    }

    /**
     * @param Separator ...$separators
     * @return $this
     * @throws Exception
     * @API
     */
    public function setSeparators(Separator ...$separators): self
    {
        if (isset($this->layout)) {
            $this->layout->setSeparators(...$separators);
        } else {
            if (empty($this->tabs)) {
                $this->addLayout()->setSeparators(...$separators);
            } else {
                $e = new Exception(__METHOD__.": Trying to set separator sizes on a layout container which already contains a content object and it's not a Layout object");
                $e->setLocaleToken('byteShard.layoutContainer.logic.setSeparators.contentAlreadySet');
                throw $e;
            }
        }
        return $this;
    }

    /**
     * @param int $accessType
     * @return $this
     * @throws Exception
     */
    public function setAccessType(int $accessType): self
    {
        $this->PermissionTrait_setAccessType($accessType);
        $this->passAccessType();
        return $this;
    }

    /**
     * @param string ...$permissions
     * @return $this
     * @throws Exception
     */
    public function setPermission(string|UnitEnum ...$permissions): self
    {
        $this->PermissionTrait_setPermission(...$permissions);
        $this->passAccessType();
        return $this;
    }

    /**
     * @param ID\ID $id
     * @return Cell|null
     */
    public function getCell(ID\ID $id): ?Cell
    {
        if ($this->layout !== null) {
            $cellId = explode('\\', $id->getCellId());
            return $this->layout->getCell(array_pop($cellId));
        }
        return null;
    }

    /**
     * @return Layout|null
     */
    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    /**
     * @return string|null
     */
    public function getID(): ?string
    {
        if (isset($this->meta['ID'])) {
            return $this->meta['ID'];
        }
        return null;
    }

    /**
     * @throws Exception
     * @internal
     */
    public function checkPredefinedChildren(): void
    {
        if (!empty($this->tabs)) {
            foreach ($this->tabs as $id => $tab) {
                if ($tab->getAccessType() === 0) {
                    unset($this->tabs[$id]);
                } else {
                    if ($tab instanceof Tab) {
                        $tmp_id = $tab->setParentTabID($this->meta['level'], $this->meta['ID']);
                        if ($id !== $tmp_id) {
                            unset($this->tabs[$id]);
                            $this->tabs[$tmp_id] = $tab;
                        }
                        $tab->checkPredefinedChildren();
                    }
                }
            }
            if (isset($this->meta['selectedID']) && count(
                    $this->tabs
                ) > 0 && !isset($this->tabs[$this->meta['selectedID']])) {
                reset($this->tabs);
                $id                       = key($this->tabs);
                $this->meta['selectedID'] = $id;
                $this->tabs[$id]->setSelected();
            }
        } else {
            if (isset($this->layout) && is_string($this->meta['ID'])) {
                $this->layout->setID($this->meta['ID']);
            }
        }
    }



    /**
     * @param mixed|null|array $id
     * @return bool
     * @throws Exception
     * @internal
     */
    /*public function setSelected(mixed $id = null): bool
    {
        $result = false;
        if ($this instanceof Tab) {
            if ($id === null) {
                $this->meta['selected'] = true;
            } else {
                if (!empty($this->tabs)) {
                    if (!($id instanceof Struct\ID)) {
                        $id = ID::explode($id);
                    }
                    if (($id instanceof Struct\Navigation_ID) && count(
                                                                     $id->Navigation
                                                                 ) === ($this->meta['level'] + 1)) {
                        if (isset($this->tabs[$id->Tab_ID])) {
                            if ($this->meta['selectedID'] !== $id->Tab_ID) {
                                if (isset($this->tabs[$this->meta['selectedID']])) {
                                    $this->tabs[$this->meta['selectedID']]->unsetSelected();
                                }
                                $this->tabs[$id->Tab_ID]->setSelected();
                                $this->meta['selectedID'] = $id->Tab_ID;
                            }
                            $result = true;
                        } else {
                            if (isset($this->tabs[$id->Navigation[$this->meta['level']]])) {
                                if ($this->meta['selectedID'] !== $id->Navigation[$this->meta['level']]) {
                                    $this->tabs[$this->meta['selectedID']]->unsetSelected();
                                    $this->tabs[$id->Navigation[$this->meta['level']]]->setSelected();
                                    $this->meta['selectedID'] = $id->Navigation[$this->meta['level']];
                                }
                                $result = $this->tabs[$id->Navigation[$this->meta['level']]]->setSelected($id);
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }*/


    /**
     * @throws Exception
     */
    private function passAccessType(): void
    {
        if ($this->getAccessType() === AccessType::NONE) {
            \byteShard\Session::removeTab($this->getNewId());
        } else {
            foreach ($this->tabs as $layoutContainer) {
                if ($layoutContainer instanceof LayoutContainer) {
                    $layoutContainer->setParentAccessType($this->getAccessType());
                }
            }
            if (isset($this->layout)) {
                $this->layout->setParentAccessType($this->getAccessType());
            }
        }
    }
}
