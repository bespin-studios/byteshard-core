<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Tab;
use byteShard\TabNew;

interface TabParentInterface {
    public function addTab(Tab|TabNew ...$tabs);

    public function addNavigationItem(NavigationItem ...$navigationItems): void;

    public function setCustomHeader(string $header): void;
}
