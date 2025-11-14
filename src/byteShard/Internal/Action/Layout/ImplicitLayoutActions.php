<?php

namespace byteShard\Internal\Action\Layout;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Environment;
use byteShard\Layout\Enum\Pattern;

class ImplicitLayoutActions
{
    /**
     * $tabName = $tab->getNewId()->getTabId();
     * $cellId = clone $tab->getNewId();
     *
     */
    public static function onPanelResizeFinish(string $resizeDirection, array $resizeData, string $tabName, Environment $environment): array
    {
        if (isset($resizeData['autoSizes'], $resizeData['cells'])) {
            $autoSizes = $resizeData['autoSizes'];
            $cellSizes = $resizeData['cells'];
            if (is_array($autoSizes) && is_array($cellSizes)) {
                if ($resizeDirection === 'w' && array_key_exists(0, $autoSizes)) {
                    $cells = explode(';', $autoSizes[0]);
                    foreach ($cells as $cell) {
                        if (array_key_exists($cell, $cellSizes)) {
                            unset($cellSizes[$cell]);
                        }
                    }
                } elseif ($resizeDirection === 'h' && array_key_exists(1, $autoSizes)) {
                    $cells = explode(';', $autoSizes[1]);
                    foreach ($cells as $cell) {
                        if (array_key_exists($cell, $cellSizes)) {
                            unset($cellSizes[$cell]);
                        }
                    }
                }

                foreach ($cellSizes as $cellName => $cellSize) {
                    if ($resizeDirection === 'w' && array_key_exists('width', $cellSize)) {
                        $environment->storeUserSetting($tabName, $cellName, Cell::WIDTH, 'Cell', round($cellSize['width']));
                    } elseif ($resizeDirection === 'h' && array_key_exists('height', $cellSize)) {
                        $environment->storeUserSetting($tabName, $cellName, Cell::HEIGHT, 'Cell', round($cellSize['height']));
                    }
                }
            }
        }
        return ['state' => HttpResponseState::SUCCESS->value];
    }

    public static function getResizeDirection(string $cells, Pattern $pattern): string
    {
        //TODO: patterns with 5++ cells
        $tmp = explode(',', $cells);
        foreach ($tmp as $val) {
            $cell[$val] = true;
        }
        switch ($pattern) {
            case Pattern::PATTERN_2E:
            case Pattern::PATTERN_3E:
            case Pattern::PATTERN_4E:
            case Pattern::PATTERN_5E:
                return 'h';
            case Pattern::PATTERN_2U:
            case Pattern::PATTERN_3W:
            case Pattern::PATTERN_4W:
            case Pattern::PATTERN_5W:
            case Pattern::PATTERN_6W:
                return 'w';
            case Pattern::PATTERN_4A:
            case Pattern::PATTERN_3J:
                if (isset($cell['c'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_3L:
                if (isset($cell['a'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_3T:
            case Pattern::PATTERN_4T:
                if (isset($cell['a'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_3U:
            case Pattern::PATTERN_4F:
                if (isset($cell['c'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4C:
                if (!isset($cell['a'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4G:
                if (isset($cell['d'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_4H:
                if (!isset($cell['a']) && !isset($cell['d'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4I:
                if (!isset($cell['a']) && !isset($cell['d'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_4J:
                if (isset($cell['b'])) {
                    return 'h';
                }
                return 'w';
            case Pattern::PATTERN_4L:
                if (isset($cell['b'])) {
                    return 'w';
                }
                return 'h';
            case Pattern::PATTERN_4U:
                if (isset($cell['d'])) {
                    return 'h';
                }
                return 'w';
            default:
                return '';
        }
    }
}