<?php

namespace byteShard;

use byteShard\Enum\Access;
use byteShard\Enum\ContentType;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Layout\Enum\Pattern;
use byteShard\Layout\Separator;

class Layout
{
    /** @var array<LayoutCell> */
    private array $cells = [];
    /** @var Separator[] */
    private array $separators               = [];
    private bool  $eventOnPanelResizeFinish = true;
    private bool  $eventOnExpand            = true;
    private bool  $eventOnCollapse          = true;

    public function __construct(private readonly Pattern $pattern)
    {
        for ($i = 0; $i < $pattern->numberOfCells(); $i++) {
            $char          = chr(($i % 26) + 97);
            $this->cells[] = new LayoutCell($char);
        }
    }

    public function setContentContainerId(\byteShard\ID\ID $id): void
    {
        foreach ($this->cells as $cell) {
            $cell->setContentContainerId($id);
        }
    }

    /**
     * @return array<LayoutCell>
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * @API
     */
    public function setSeparators(Separator ...$separators): self
    {
        foreach ($separators as $separator) {
            if (!in_array($separator, $this->separators)) {
                $this->separators[] = $separator;
            }
        }
        return $this;
    }

    private function registerLegacyCellInSession(?Access $parentAccess = null, ?\byteShard\ID\ID $cellId = null): void
    {
        $cell = new Cell();
        $cell->init($cellId?->getPatternCellId(), $cellId);
        if ($parentAccess !== null) {
            $cell->setParentAccessType($parentAccess);
        }
        Session::registerCell($cell);
    }

    public function getItemConfig(?Access $parentAccess = null): ContentComponent
    {
        $content = [];
        $events  = [];
        $setup   = [];

        $setup['pattern'] = $this->pattern->value;
        foreach ($this->separators as $separator) {
            $setup['separatorSize'][] = $separator->getSeparatorSize();
        }
        if (!empty($this->cells)) {
            $horizontal = [];
            $vertical   = [];
            foreach ($this->cells as $cell) {
                $cellId    = $cell->getId();
                $patternId = $cellId->getPatternCellId();
                $this->registerLegacyCellInSession($parentAccess, $cellId);
                $content[] = $cell->getItemConfig($patternId);
                if ($cell->getHorizontalAutoSize()) {
                    $horizontal[] = $patternId;
                }
                if ($cell->getVerticalAutoSize()) {
                    $vertical[] = $patternId;
                }
            }
            if (!empty($horizontal)) {
                $setup['autoSize']['horizontal'] = implode(';', $horizontal);
            }
            if (!empty($vertical)) {
                $setup['autoSize']['vertical'] = implode(';', $vertical);
            }
            if ($this->eventOnCollapse === true) {
                $events[] = new ClientCellEvent('onCollapse', 'doOnCollapse');
            }
            if ($this->eventOnExpand === true) {
                $events[] = new ClientCellEvent('onExpand', 'doOnExpand');
            }
            if ($this->eventOnPanelResizeFinish) {
                $events[] = new ClientCellEvent('onPanelResizeFinish', 'doOnPanelResizeFinish');
            }
        }
        return new ContentComponent(
            type   : ContentType::DhtmlxLayout,
            content: $content,
            events : $events,
            setup  : $setup
        );
    }

    public function bubble(): int
    {
        $bubble = 0;
        foreach ($this->cells as $cell) {
            $className = '\\App\\Cell\\'.$cell->getId()->getCellId();
            if (class_exists($className)) {
                $interfaces = class_implements($className);
                if (isset($interfaces[Cell\Bubble::class])) {
                    $layoutCell = new $className(new Cell());
                    try {
                        $bubble += $layoutCell->bubble();
                    } catch (\Exception) {
                        //TODO: log error
                    }
                }
            }
        }
        return $bubble;
    }
}