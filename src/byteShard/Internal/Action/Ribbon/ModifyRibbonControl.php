<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Action\Ribbon;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Ribbon\RibbonControl;

abstract class ModifyRibbonControl extends Action
{
    protected string $cell;
    protected array  $controls          = [];
    protected string $modification;
    protected string $modificationValue = '';

    public function __construct(string $cell, string $modification, RibbonControl ...$controls)
    {
        $this->cell         = Cell::getContentCellName($cell);
        $this->modification = $modification;
        foreach ($controls as $control) {
            if ($control !== '') {
                $this->controls[$control->getObjectName()] = $control;
            }
        }
    }

    protected function runAction(): Action\ActionResultInterface
    {
        $cells = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            $nonce = $cell->getNonce();
            foreach ($this->controls as $control) {
                $controlId = $control->generateEncryptedId($nonce);
                if ($controlId !== '') {
                    $action['rb'][$cell->containerId()][$cell->cellId()][$controlId][$this->modification] = $this->modificationValue;
                }
            }
        }
        $action['state'] = 2;
        return new Action\ActionResultMigrationHelper($action);
    }
}
