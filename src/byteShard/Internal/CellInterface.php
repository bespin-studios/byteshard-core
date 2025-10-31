<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

interface CellInterface
{
    /**
     * @session write (Cell, LayoutContainer, Toolbar)
     * @session none (Node, CellContent, SharedParent, Column, ToolbarObject)
     * @param int $accessType
     * @return $this
     * @internal
     */
    public function setParentAccessType(int $accessType): self;

    public function getHorizontalAutoSize(): bool;

    public function getVerticalAutoSize(): bool;
}
