<?php

namespace byteShard\Internal;

interface NavigationItem
{
    public function getId(): ?string;
    public function getAccessType(): int;
}