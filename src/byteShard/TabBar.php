<?php

namespace byteShard;

use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Permission\NoApplicationPermissionError;

class TabBar implements ApplicationRootInterface
{
    /** @var array<string, TabNew> */
    private array $tabs = [];

    public function __construct(TabNew ...$tabs)
    {
        foreach ($tabs as $tab) {
            $this->tabs[$tab->getId()] = $tab;
        }
    }

    public function getRootParameters(?string $selectedId = null): array
    {
        $result         = [];
        $result['type'] = 'TabBar';
        $tabs           = $this->getTabs($selectedId);
        if (!empty($tabs)) {
            foreach ($tabs as $tab) {
                $result['tabs'][] = $tab->getNavigationData();
                $cells            = $tab->getCells();
                foreach ($cells as $cell) {
                    Session::registerCell($cell);
                }
            }
        } else {
            $tab = new NoApplicationPermissionError();
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
            $tab->setSelected();
            $result['tabs'][] = $tab->getNavigationData();
        }
        return $result;
    }

    private function getTabs(?string $selectedId = null): array
    {
        if (empty($this->tabs)) {
            return [];
        }
        foreach ($this->tabs as $tab) {
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
        }
        $split    = explode('\\', $selectedId);
        $tabDepth = count($split);
        $found    = false;
        if ($tabDepth === 1 && array_key_exists($selectedId, $this->tabs)) {
            $found = true;
            $this->tabs[$selectedId]->setSelected();
        } elseif ($tabDepth > 1 && array_key_exists($split[0], $this->tabs)) {
            $found = true;
            $this->tabs[$split[0]]->setSelected($selectedId);
        }

        if ($found === false) {
            reset($this->tabs);
            $firstTab = key($this->tabs);
            $this->tabs[$firstTab]->setSelected();
        }
        foreach ($this->tabs as $tab) {
            $tab->selectFirstTabIfNoneSelected();
        }
        return $this->tabs;
    }
}