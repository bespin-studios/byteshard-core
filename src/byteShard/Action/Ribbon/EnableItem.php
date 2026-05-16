<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Ribbon;

use byteShard\Internal\Action\Ribbon\ModifyRibbonControl;
use byteShard\Internal\Ribbon\RibbonControl;

class EnableItem extends ModifyRibbonControl
{

    public function __construct(string $cell, RibbonControl ...$controls) {
        parent::__construct($cell, 'enableItem', ...$controls);
        $this->modificationValue = 'true';
    }
}
