<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Enum\Access;
use byteShard\Enum\ContentType;
use byteShard\ID\TabIDElement;
use byteShard\Internal\ClientData\EventContainerInterface;
use byteShard\Internal\NavigationItem;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Internal\TabLegacyInterface;
use byteShard\Internal\TabNewDeprecation;
use byteShard\Internal\Toolbar\ToolbarContainer;
use byteShard\Internal\Traits\Toolbar;
use byteShard\Toolbar\ToolbarInterface;
use byteShard\Utils\Strings;
use UnitEnum;

abstract class TabNew implements TabLegacyInterface, NavigationItem, ToolbarContainer, EventContainerInterface
{
    use PermissionImplementation;
    use TabNewDeprecation;
    use Toolbar;

    private ID\ID  $id;
    private array  $tabs     = [];
    private Layout $layout;
    private bool   $selected = false;
    private bool   $closable = false;
    //Todo: private Toolbar $toolbar;
    private string                     $label;
    private bool                       $initialized = false;
    private Layout|TabBar|SideBar|null $content     = null;
    private array                      $cellConfig  = [];
    protected ?ClientData              $clientData;

    public function __construct(string|UnitEnum ...$permissions)
    {
        $this->id = \byteShard\ID\ID::factory(new TabIDElement(get_called_class()));
        foreach ($permissions as $permission) {
            $this->setPermission($permission);
        }
    }

    public function setLabel(string $label): TabNew
    {
        $this->label = $label;
        return $this;
    }

    public function setProcessedClientData(?ClientData $clientData): void
    {
        if ($clientData !== null) {
            $this->clientData = $clientData;
        }
    }

    public function setContent(Layout|TabBar|SideBar $content): TabNew
    {
        $this->content = $content;
        if ($content instanceof Layout) {
            $this->content->setContentContainerId($this->id);
        }
        return $this;
    }

    public function getContent(): Layout|TabBar|SideBar|null
    {
        if (!$this->isInitialized()) {
            $this->defineTabContent();
            $this->setInitialized();
        }
        return $this->content;
    }

    public function getSelected(): bool
    {
        return $this->selected;
    }

    public function isClosable(): bool
    {
        return $this->closable;
    }

    public function setClosable(bool $closable = true): TabNew
    {
        $this->closable = $closable;
        return $this;
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

    public function getScopeLocaleTokenBasedOnNamespace(string $type = 'Tab'): string
    {
        $namespace = str_replace('App\\Tab\\', '', get_class($this));
        return str_replace('\\', '_', $namespace).'::'.$type;
    }

    public function getNonce(): string
    {
        return Session::getTopLevelNonce();
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function setInitialized(): void
    {
        $this->initialized = true;
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
                $content[]       = $this->content->getItemConfig(Access::from($this->getAccessType()));
                $setup['bubble'] = $this->content->bubble();
                break;
            case $this->content instanceof TabBar:
            case $this->content instanceof SideBar:
                $content[] = $this->content->getRootParameters($selectedId, Access::from($this->getAccessType()));
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
        if ($this instanceof ToolbarInterface) {
            $toolbar = $this->getToolbarComponent();
            if ($toolbar !== null) {
                $content[] = $toolbar;
            }
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
