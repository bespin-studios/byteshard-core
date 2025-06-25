<?php

namespace byteShard;

use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Permission\NoApplicationPermissionError;

class SideBar implements ApplicationRootInterface
{
    /** @var array<string, SideBarItem> */
    private array  $sideBarCells;
    private string $customHeader;

    public function __construct(SideBarItem ...$sideBarCells)
    {
        foreach ($sideBarCells as $sideBarCell) {
            $this->sideBarCells[$sideBarCell->getId()] = $sideBarCell;
        }
    }

    public function setCustomHeader(string $header): void
    {
        $this->customHeader = $header;
    }

    public function getRootParameters(?string $selectedId = null): array
    {
        $result         = [];
        $result['type'] = 'SideBar';
        $sideBarCells   = $this->initSideBarCells($selectedId);
        if (!empty($sideBarCells)) {
            foreach ($sideBarCells as $sideBarCell) {
                $result['tabs'][] = $sideBarCell->getNavigationData();
                if (isset($this->customHeader)) {
                    $result['customHeader'] = $this->customHeader;
                }
                $cells = $sideBarCell->getCells();
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

    private function initSideBarCells($selectedId): array
    {
        if (empty($this->sideBarCells)) {
            return [];
        }
        foreach ($this->sideBarCells as $sideBarCell) {
            if (!$sideBarCell->isInitialized()) {
                $sideBarCell->defineTabContent();
                $sideBarCell->setInitialized();
            }
        }
        $split           = explode('\\', $selectedId);
        $navigationDepth = count($split);
        $found           = false;
        if ($navigationDepth === 1 && array_key_exists($selectedId, $this->sideBarCells)) {
            $found = true;
            $this->sideBarCells[$selectedId]->setSelected();
        } elseif ($navigationDepth > 1 && array_key_exists($split[0], $this->sideBarCells)) {
            $found = true;
            $this->sideBarCells[$split[0]]->setSelected($selectedId);
        }

        if ($found === false) {
            reset($this->sideBarCells);
            $firstCell = key($this->sideBarCells);
            $this->sideBarCells[$firstCell]->setSelected();
        }
        foreach ($this->sideBarCells as $sideBarCell) {
            $sideBarCell->selectFirstTabIfNoneSelected();
        }
        return $this->sideBarCells;
    }
}