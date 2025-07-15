<?php

namespace byteShard;

use byteShard\Enum\ContentType;
use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Permission\NoApplicationPermissionError;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ContentComponent;

class TabBar implements ApplicationRootInterface
{
    /** @var array<string, TabNew> */
    private array $tabs   = [];
    private array $events = [];

    public function __construct(TabNew ...$tabs)
    {
        $this->addTabs(...$tabs);
    }

    public function addTabs(TabNew ...$tabs): void
    {
        foreach ($tabs as $tab) {
            $this->tabs[$tab->getId()] = $tab;
            if ($tab->isClosable()) {
                $this->events[] = new ClientCellEvent('onTabClose', 'doOnTabClose');
            }
        }
    }

    public function getRootParameters(?string $selectedId = null): ContentComponent
    {
        $this->initTabs();
        $this->setSelectedTab($selectedId);
        $content = [];
        $events  = [];
        if (!empty($this->tabs)) {
            foreach ($this->tabs as $tab) {
                $content[] = $tab->getItemConfig($selectedId);
            }
            $events   = $this->events;
            $events[] = new ClientCellEvent('onSelect', 'doOnSelect');
        } else {
            $tab = new NoApplicationPermissionError();
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
            $tab->setSelected();
            $content[] = $tab->getItemConfig();
        }
        return new ContentComponent(
            type   : ContentType::DhtmlxTabBar,
            content: $content,
            events : $events
        );
    }

    private function initTabs(): void
    {
        foreach ($this->tabs as $tab) {
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
        }
    }

    private function setSelectedTab(string $selectedId): void
    {
        $parts = explode('\\', $selectedId);
        $path  = '';
        foreach ($parts as $part) {
            $path .= ($path === '' ? '' : '\\').$part;
            if (isset($this->tabs[$path])) {
                $this->tabs[$path]->setSelected();
                return;
            }
        }

        reset($this->tabs);
        $firstCell = key($this->tabs);
        $this->tabs[$firstCell]->setSelected();
    }
}