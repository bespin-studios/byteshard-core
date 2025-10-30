<?php

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Layout\Enum\Pattern;
use byteShard\TabBar;
use byteShard\TabNew;

trait TabNewDeprecation
{
    /**
     * @deprecated
     * @API
     */
    public function addTab(TabNew ...$tabs): void
    {
        trigger_error('byteShard\TabNew::addTab is deprecated. Please create a new \byteShard\TabBar and add it with byteShard\TabNew::setTabBar', E_USER_DEPRECATED);
        if (!$this->content instanceof TabBar) {
            $this->content = new TabBar();
        }
        $this->content->addTabs(...$tabs);
    }

    /**
     * @return Cell[]
     * @deprecated
     */
    public function getCells(): array
    {
        trigger_error('byteShard\TabNew::getCells is deprecated.', E_USER_DEPRECATED);
        $cells = isset($this->layout) ? $this->layout->getCells() : [];
        foreach ($this->tabs as $tab) {
            foreach ($tab->getCells() as $cell) {
                $cells[] = $cell;
            }
        }
        return $cells;
    }

    /**
     * @param Pattern $pattern
     * @return void
     * @API
     * @deprecated
     */
    public function setPattern(Pattern $pattern): void
    {
        trigger_error('BREAKING: setPattern is deprecated. Use setContent() and provide a Layout instead', E_USER_DEPRECATED);
    }

    /**
     * @param Cell ...$cells
     * @return void
     * @API
     * @deprecated
     */
    public function addCell(Cell ...$cells): void
    {
        trigger_error('BREAKING: setPattern is deprecated. Use setContent() and provide a Layout instead', E_USER_DEPRECATED);
    }

    /**
     * @param TabBar $tabBar
     * @return void
     * @deprecated
     */
    public function setTabBar(TabBar $tabBar): void
    {
        trigger_error('setTabBar is deprecated. Use setContent() instead', E_USER_DEPRECATED);
        $this->content = $tabBar;
    }
}