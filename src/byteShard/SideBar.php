<?php

namespace byteShard;

use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Permission\NoApplicationPermissionError;

class SideBar implements ApplicationRootInterface
{
    /** @var array<string, SideBarItem> */
    private array  $sideBarCells;
    private string $customHeader;
    private int    $width = 250;

    public function __construct(SideBarItem ...$sideBarCells)
    {
        foreach ($sideBarCells as $sideBarCell) {
            $this->sideBarCells[$sideBarCell->getId()] = $sideBarCell;
        }
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function setCustomHeader(string $header): void
    {
        $this->customHeader = $header;
    }

    public function getRootParameters(?string $selectedId = null): array
    {
        $result = [
            'content' => [
                'type'   => 'SideBar',
                'config' => [
                    'width' => $this->width,
                ],
                'events' => [
                    'onSelect' => ['doOnSelect']
                ]
            ]
        ];
        if (isset($this->customHeader)) {
            $result['content']['customHeader'] = $this->customHeader;
        }
        $this->initSideBarCells();
        $this->setSelectedSideBarCell($selectedId);
        if (!empty($this->sideBarCells)) {
            foreach ($this->sideBarCells as $sideBarCell) {
                $result['content']['cells'][] = $sideBarCell->getItemConfig($selectedId);
            }
        } else {
            $tab = new NoApplicationPermissionError();
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
            $tab->setSelected();
            $result['content']['type']   = 'TabBar';
            $result['content']['tabs'][] = $tab->getItemConfig();
        }
        return $result;
    }

    private function initSideBarCells(): void
    {
        foreach ($this->sideBarCells as $sideBarCell) {
            if (!$sideBarCell->isInitialized()) {
                $sideBarCell->defineTabContent();
                $sideBarCell->setInitialized();
            }
        }
    }

    private function setSelectedSideBarCell(string $selectedId): void
    {
        $parts = explode('\\', $selectedId);
        $path  = '';
        foreach ($parts as $part) {
            $path .= ($path === '' ? '' : '\\').$part;
            if (isset($this->sideBarCells[$path])) {
                $this->sideBarCells[$path]->setSelected();
                return;
            }
        }

        reset($this->sideBarCells);
        $firstCell = key($this->sideBarCells);
        $this->sideBarCells[$firstCell]->setSelected();
    }
}