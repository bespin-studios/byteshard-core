<?php

namespace byteShard;

use byteShard\Enum\Access;
use byteShard\Enum\ContentType;
use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Permission\NoApplicationPermissionError;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ContentComponent;

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

    public function getRootParameters(?string $selectedId = null, Access $parentAccess = Access::WRITE): ContentComponent
    {
        $type     = ContentType::DhtmlxSideBar;
        $content  = [];
        $events[] = new ClientCellEvent('onSelect', 'doOnSelect');
        $setup    = [
            'config' => ['width' => $this->width],
        ];
        if (isset($this->customHeader)) {
            $setup['customHeader'] = $this->customHeader;
        }
        $this->initSideBarCells();
        $this->setSelectedSideBarCell($selectedId);
        if (!empty($this->sideBarCells)) {
            foreach ($this->sideBarCells as $sideBarCell) {
                $sideBarCell->setParentAccessType($parentAccess);
                if ($sideBarCell->getAccessType() > Access::NONE->value) {
                    $content[] = $sideBarCell->getItemConfig($selectedId);
                }
            }
        } else {
            $tab = new NoApplicationPermissionError();
            if (!$tab->isInitialized()) {
                $tab->defineTabContent();
                $tab->setInitialized();
            }
            $tab->setSelected();
            $type      = ContentType::DhtmlxTabBar;
            $content[] = $tab->getItemConfig();
        }
        return new ContentComponent(
            type   : $type,
            content: $content,
            events : $events,
            setup  : $setup
        );
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