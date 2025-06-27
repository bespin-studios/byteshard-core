<?php

namespace byteShard\Internal;

interface ApplicationRootInterface
{
    public function getRootParameters(?string $selectedId = null): array;
}