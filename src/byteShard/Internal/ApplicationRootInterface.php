<?php

namespace byteShard\Internal;

use byteShard\Internal\Struct\ContentComponent;

interface ApplicationRootInterface
{
    public function getRootParameters(?string $selectedId = null): ContentComponent;
}