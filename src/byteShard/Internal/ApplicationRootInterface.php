<?php

namespace byteShard\Internal;

use byteShard\Enum\Access;
use byteShard\Internal\Struct\ContentComponent;

interface ApplicationRootInterface
{
    public function getRootParameters(?string $selectedId = null, Access $parentAccess = Access::WRITE): ContentComponent;
}