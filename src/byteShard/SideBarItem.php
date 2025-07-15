<?php

namespace byteShard;

use byteShard\Enum\ContentType;
use byteShard\Internal\Struct\ContentComponent;

abstract class SideBarItem extends TabNew
{
    private string $icon;

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getItemConfig(string $selectedId = ''): ContentComponent
    {
        $result = parent::getItemConfig($selectedId);
        if (isset($this->icon)) {
            $result->setup['icon'] = $this->icon;
        }
        $result->type = ContentType::DhtmlxSideBarCell;
        return $result;
    }
}