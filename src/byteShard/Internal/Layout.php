<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Accordion;
use byteShard\Cell;
use byteShard\Enum\Access;
use byteShard\Enum\AccessType;
use byteShard\Enum\ContentType;
use byteShard\Exception;
use byteShard\ID;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Layout\Enum\Pattern;
use byteShard\Layout\Separator;

/**
 * Class Layout
 * @package byteShard\Internal
 */
class Layout
{
    /** @var Cell[] */
    private array $cells = [];
    /** @var Separator[] */
    private array    $separators               = [];
    private string   $name;
    private string   $id;
    private ?Pattern $pattern                  = null;
    private bool     $eventOnPanelResizeFinish = true;
    private bool     $eventOnExpand            = true;
    private bool     $eventOnCollapse          = true;
    private ?ID\ID   $parentId;

    /**
     * Layout constructor.
     * @param string $id
     * @param string $name
     * @param ID\ID|null $parentId
     */
    public function __construct(string $id, string $name, ?ID\ID $parentId = null)
    {
        $this->name = $name;
        $this->setID($id);
        $this->parentId = $parentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param Cell $cell
     * @return $this
     * @throws Exception
     */
    public function addCell(Cell $cell): self
    {
        $count = count($this->cells);
        if ($count <= 7) {
            $char = chr(($count % 26) + 97);
            if ($this->parentId !== null) {
                $cellId           = clone $this->parentId;
                $cellContentClass = $cell->getContentClass();
                if ($cellContentClass === '' || !str_starts_with(strtolower($cellContentClass), 'app\\cell')) {
                    $cellId->addIdElement(new ID\CellIDElement(trim($this->name, '\\').'\\'.$char), new ID\PatternIDElement($char));
                } else {
                    $cellId->addIdElement(new ID\CellIDElement(substr($cellContentClass, 9)), new ID\PatternIDElement($char));
                }
                $cell->init($char, $cellId);
            } else {
                $cell->setID($char);
                if (!empty($this->id)) {
                    $cell->setContainerID($this->id);
                }
                $cell->setNamespace($this->name);
            }
            $this->cells[$char] = $cell;
        } elseif ($cell instanceof Accordion) {
            $char               = chr(($count % 26) + 97);
            $this->cells[$char] = $cell;
        } else {
            $e = new Exception(__METHOD__.': More Cells added than DHTMLX Layout is capable of.');
            $e->setLocaleToken('byteShard.layout.logic.addCell.nrOfCells');
            throw $e;
        }
        return $this;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setID(string $id): self
    {
        $this->id = $id;
        foreach ($this->cells as $cell) {
            $cell->setContainerID($id);
        }
        return $this;
    }

    /**
     * @param string $id
     * @return Cell|null
     */
    public function getCell(string $id): ?Cell
    {
        if (array_key_exists($id, $this->cells)) {
            return $this->cells[$id];
        }
        return null;
    }

    public function getCells(): array
    {
        return $this->cells;
    }

    public function setPattern(Pattern $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function setSeparators(Separator ...$separators): self
    {
        foreach ($separators as $separator) {
            if (!in_array($separator, $this->separators)) {
                $this->separators[] = $separator;
            }
        }
        return $this;
    }

    /**
     * called by LayoutContainer to pass the access type to every cell in it.
     * no accessType is stored in Layout since a Layout can never be directly accessed
     * @param int $accessRight
     * @return Layout
     * @throws Exception
     * @internal
     */
    public function setParentAccessType(int $accessRight): self
    {
        if (AccessType::is_enum($accessRight)) {
            foreach ($this->cells as $cell) {
                $cell->setParentAccessType($accessRight);
            }
        }
        return $this;
    }

    public function getItemConfig(?Access $parentAccess = null): ContentComponent
    {
        $content = [];
        $events  = [];
        $setup   = [];
        // TODO:: choose layout depending on permissions
        if ($this->pattern === null) {
            $count                         = count($this->cells);
            $setup['pattern'] = match ($count) {
                2       => Pattern::PATTERN_2E->value,
                3       => Pattern::PATTERN_3E->value,
                4       => Pattern::PATTERN_4A->value,
                5       => Pattern::PATTERN_5C->value,
                6       => Pattern::PATTERN_6A->value,
                7       => Pattern::PATTERN_7H->value,
                default => Pattern::PATTERN_1C->value,
            };
        } else {
            $setup['pattern'] = $this->pattern->value;
        }
        foreach ($this->separators as $separator) {
            $setup['separatorSize'][] = $separator->getSeparatorSize();
        }
        if (!empty($this->cells)) {
            $horizontal = '';
            $vertical   = '';
            foreach ($this->cells as $id => $cell) {
                if ($parentAccess !== null) {
                    $cell->setParentAccessType($parentAccess);
                }
                $content[] = $cell->getItemConfig($id);
                if ($cell->getHorizontalAutoSize()) {
                    if ($horizontal === '') {
                        $horizontal = $id;
                    } else {
                        $horizontal .= ';'.$id;
                    }
                }
                if ($cell->getVerticalAutoSize()) {
                    if ($vertical === '') {
                        $vertical = $id;
                    } else {
                        $vertical .= ';'.$id;
                    }
                }
            }
            if ($horizontal !== '') {
                $setup['autoSize']['horizontal'] = $horizontal;
            }
            if ($vertical !== '') {
                $setup['autoSize']['vertical'] = $vertical;
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

    public function getNavigationData(): array
    {
        trigger_error('getNavigationData is deprecated, refactor to getItemConfig', E_USER_DEPRECATED);
        // TODO:: choose layout depending on permissions
        if ($this->pattern === null) {
            $count             = count($this->cells);
            $result['pattern'] = match ($count) {
                2       => Pattern::PATTERN_2E->value,
                3       => Pattern::PATTERN_3E->value,
                4       => Pattern::PATTERN_4A->value,
                5       => Pattern::PATTERN_5C->value,
                6       => Pattern::PATTERN_6A->value,
                7       => Pattern::PATTERN_7H->value,
                default => Pattern::PATTERN_1C->value,
            };
        } else {
            $result['pattern'] = $this->pattern->value;
        }
        foreach ($this->separators as $separator) {
            $result['separatorSize'][] = $separator->getSeparatorSize();
        }
        if (!empty($this->cells)) {
            $horizontal = '';
            $vertical   = '';
            foreach ($this->cells as $id => $cell) {
                $result['cells'][$id] = $cell->getNavigationData();
                if ($cell->getHorizontalAutoSize()) {
                    if ($horizontal === '') {
                        $horizontal = $id;
                    } else {
                        $horizontal .= ';'.$id;
                    }
                }
                if ($cell->getVerticalAutoSize()) {
                    if ($vertical === '') {
                        $vertical = $id;
                    } else {
                        $vertical .= ';'.$id;
                    }
                }
            }
            if ($horizontal !== '') {
                $result['autoSize']['horizontal'] = $horizontal;
            }
            if ($vertical !== '') {
                $result['autoSize']['vertical'] = $vertical;
            }
            if ($this->eventOnCollapse === true) {
                $result['events']['onCollapse'][] = 'doOnCollapse';
            }
            if ($this->eventOnExpand === true) {
                $result['events']['onExpand'][] = 'doOnExpand';
            }
            if ($this->eventOnPanelResizeFinish) {
                $result['events']['onPanelResizeFinish'][] = 'doOnPanelResizeFinish';
            }
        }
        return $result;
    }

    public function bubble(): int
    {
        $bubble = 0;
        foreach ($this->cells as $cell) {
            $className = $cell->getContentClass();
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

    public function getLocale(): array
    {
        $result = [];
        foreach ($this->cells as $id => $cell) {
            $result[$id]['reload']   = true;
            $result[$id]['setLabel'] = $cell->getLabel();
        }
        return $result;
    }

    public function getResizeDirection(string $cells): string
    {
        //TODO: patterns with 5++ cells
        $tmp = explode(',', $cells);
        foreach ($tmp as $val) {
            $cell[$val] = true;
        }
        switch ($this->pattern) {
            case Pattern::PATTERN_2E:
            case Pattern::PATTERN_3E:
            case Pattern::PATTERN_4E:
            case Pattern::PATTERN_5E:
                return 'h';
            case Pattern::PATTERN_2U:
            case Pattern::PATTERN_3W:
            case Pattern::PATTERN_4W:
            case Pattern::PATTERN_5W:
            case Pattern::PATTERN_6W:
                return 'w';
            case Pattern::PATTERN_4A:
            case Pattern::PATTERN_3J:
                if (isset($cell['c'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_3L:
                if (isset($cell['a'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_3T:
            case Pattern::PATTERN_4T:
                if (isset($cell['a'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_3U:
            case Pattern::PATTERN_4F:
                if (isset($cell['c'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4C:
                if (!isset($cell['a'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4G:
                if (isset($cell['d'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_4H:
                if (!isset($cell['a']) && !isset($cell['d'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4I:
                if (!isset($cell['a']) && !isset($cell['d'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_4J:
                if (isset($cell['b'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4L:
                if (isset($cell['b'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_4U:
                if (isset($cell['d'])) {
                    return 'h';
                }
                return 'w';
            default:
                return '';
        }
    }
}
