<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Chart\Legend\Legend;
use byteShard\Chart\Axis\Axis;
use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use byteShard\Internal\CellContent;
use byteShard\Internal\Chart\View;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellComponent;

class Chart extends CellContent
{
    /** @var array<int,object> */
    private array $data = [];
    /** @var View[] */
    private array  $views = [];
    private Legend $legend;
    private Axis   $xAxis;
    private Axis   $yAxis;
    private int    $origin;
    private string $label;

    /**
     * @param mixed[] $content
     * @return array<string,string|array<string, array<string, bool|int|string>|int|string>>
     */
    public function getCellContent(array $content = []): array
    {
        switch ($this->getAccessType()) {
            case Enum\AccessType::NONE:
                //TODO: return a dhtmlxForm with a no permission label
                break;
            case Enum\AccessType::R:
            case Enum\AccessType::RW:
                $this->defineCellContent();
                break;
        }
        $components   = parent::getComponents();
        $components[] = new ClientCellComponent(
            type   : ContentType::DhtmlxChart,
            content: $this->getContent(),
            format : ContentFormat::JSON
        );
        $result       = new ClientCell(
            ...$components
        );
        return $result->getArray();
    }

    /** @API */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @param array<int,object> $data
     * @return $this
     * @API
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /** @API */
    public function setLegend(Legend $legend): self
    {
        $this->legend = $legend;
        return $this;
    }

    /** @API */
    public function setXAxis(Axis $xAxis): self
    {
        $this->xAxis = $xAxis;
        return $this;
    }

    /** @API */
    public function setYAxis(Axis $yAxis): self
    {
        $this->yAxis = $yAxis;
        return $this;
    }

    /** @API */
    public function addView(View $view): self
    {
        $this->views[] = $view;
        return $this;
    }

    /** @API */
    public function setOrigin(int $origin): self
    {
        $this->origin = $origin;
        return $this;
    }

    /**
     * @return array<string, array<int|string, array<mixed>|bool|int|object|string>|int|string>
     */
    private function getContent(): array
    {
        $views = [];
        foreach ($this->views as $view) {
            $views[] = $view->getView();
        }
        $result = [
            'data'  => $this->data,
            'views' => $views
        ];
        if (isset($this->legend)) {
            $result['legend'] = $this->legend->getLegend();
        }
        if (isset($this->xAxis)) {
            $result['xAxis'] = $this->xAxis->getAxis();
        }
        if (isset($this->yAxis)) {
            $result['yAxis'] = $this->yAxis->getAxis();
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin;
        }
        if (isset($this->label)) {
            $result['label'] = $this->label;
        }
        return $result;
    }
}
