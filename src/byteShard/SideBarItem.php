<?php

namespace byteShard;

abstract class SideBarItem extends TabNew
{
    private string $icon;

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getNavigationData(): array
    {
        $result = parent::getNavigationData();
        if (isset($this->icon)) {
            $result['icon'] = $this->icon;
        }
        return $result;
    }
}