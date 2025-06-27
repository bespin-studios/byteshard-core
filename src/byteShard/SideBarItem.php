<?php

namespace byteShard;

abstract class SideBarItem extends TabNew
{
    private string $icon;

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getItemConfig(string $selectedId = ''): array
    {
        $result = parent::getItemConfig($selectedId);
        if (isset($this->icon)) {
            $result['icon'] = $this->icon;
        }
        return $result;
    }
}