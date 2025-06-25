<?php

namespace byteShard;

use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Permission\NoApplicationPermissionError;

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
                $this->events['onTabClose'] = ['doOnTabClose'];
            }
        }
    }

    public function getRootParameters(?string $selectedId = null): array
    {
        $result                    = [];
        $result['content']['type'] = 'TabBar';
        $this->initTabs();
        $this->setSelectedTab($selectedId);
        if (!empty($this->tabs)) {
            foreach ($this->tabs as $tab) {
                $result['content']['tabs'][] = $tab->getItemConfig($selectedId);
            }
            $result['content']['events']             = $this->events;
            $result['content']['events']['onSelect'] = ['doOnSelect'];
        } else {
            $tab = new NoApplicationPermissionError();
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
            $tab->setSelected();
            $result['content']['tabs'][] = $tab->getItemConfig();
        }
        return $result;
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