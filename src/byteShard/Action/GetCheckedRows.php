<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Internal\Action;

/**
 * convenience action, but limited to single cell
 */
class GetCheckedRows extends GetCellData
{
    public function __construct(string $cell, Action ...$actions)
    {
        $this->fromMasterCheckboxInCell($cell);
        $this->addAction(...$actions);
    }
}
