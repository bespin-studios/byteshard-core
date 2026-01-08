<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Action\DTO\ClassMapDTO;
use byteShard\Cell;
use byteShard\ID;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use stdClass;

/**
 * Class SetRelatedCellClassNameBySelectedID
 * @package byteShard\Action
 */
class SetRelatedCellClassNameBySelectedID extends Action
{
    private string $cell;
    private array  $map;

    /**
     * @param string $cell
     * @param ClassMapDTO|stdClass ...$idClassMap
     */
    public function __construct(string $cell, ClassMapDTO|stdClass ...$idClassMap)
    {
        $this->cell = Cell::getContentCellName($cell);
        if (count($idClassMap) === 1 && !($idClassMap[0] instanceof ClassMapDTO)) {
            // deprecated
            $idClassArray = (array)$idClassMap[0];
            foreach ($idClassArray as $id => $class) {
                if ($id === 'default') {
                    $this->map['default'] = $class;
                } else {
                    $arr = [];
                    foreach (ID::explode($id) as $idName => $idValue) {
                        if ($idName !== 'ID') {
                            $arr[$idName] = $idValue;
                        }
                    }
                    ksort($arr);
                    $this->map[json_encode($arr)] = $class;
                }
            }
        } else {
            foreach ($idClassMap as $classMapDTO) {
                if ($classMapDTO instanceof ClassMapDTO) {
                    $this->map[$classMapDTO->getIdString()] = $classMapDTO->className;
                }
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $id      = $this->getLegacyId();
        $map     = [];
        $default = '';
        foreach ($this->map as $ids => $class) {
            if ($ids === 'default') {
                $default = $class;
            } else {
                $idArray = json_decode($ids, true);
                ksort($idArray);
                $map[] = [
                    'id'    => (array)json_decode($ids),
                    'jid'   => json_encode($idArray),
                    'class' => $class
                ];
            }
        }
        $cells = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            $matches      = [];
            $selectedId   = [];
            $matchedClass = '';
            if (is_array($id)) {
                ksort($id);
                $selectedId = json_encode($id);
                foreach ($map as $item) {
                    if (count($id) !== count($item['id'])) {
                        $selectedIdElements = [];
                        foreach ($id as $selectedIdElementKey => $selectedIdElementValue) {
                            if (array_key_exists($selectedIdElementKey, $item['id'])) {
                                $selectedIdElements[$selectedIdElementKey] = $selectedIdElementValue;
                            }
                        }
                        $selectedId = json_encode($selectedIdElements);
                    }
                    if ($selectedId === $item['jid']) {
                        $matchedClass = $item['class'];
                    }
                }
                if (array_key_exists($selectedId, $map)) {
                    $matchedClass = $map;
                }
            } else {
                foreach (ID::explode($id) as $idName => $idValue) {
                    if ($idName !== 'ID') {
                        $selectedId[$idName] = $idValue;
                    }
                }
                foreach ($map as $testAgainst) {
                    if (empty(array_diff_assoc($testAgainst['id'], $selectedId))) {
                        $matches[count($testAgainst['id'])][] = $testAgainst['class'];
                    }
                }
                if (!empty($matches)) {
                    $matchedClass = $this->map->{$id};
                }
            }

            if ($matchedClass !== '') {
                $cell->setContentClassName($matchedClass);
            } elseif ($default !== '') {
                $cell->setContentClassName($default);
            } else {
                $cell->revertCustomContentClassName();
            }
        }
        return new Action\ActionResult();
    }
}
