<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Action;

use byteShard\Enum\HttpResponseState;
use byteShard\Session;
use byteShard\ID\ID;

class CellActionResult implements ActionResultInterface
{
    private array $commands = [];
    private bool  $error    = false;

    public function __construct(private readonly ActionTargetEnum $type = ActionTargetEnum::Cell)
    {

    }

    public function addCellCommand(array $cells, string $command, mixed $parameters): self
    {
        $this->commands[] = [
            'cells'      => $cells,
            'command'    => $command,
            'parameters' => $parameters
        ];
        return $this;
    }

    public function getResultArray(?ID $containerId): array
    {
        $result = [];
        foreach ($this->commands as $command) {
            $cells = $this->getCells($command['cells'], $containerId);
            foreach ($cells as $cell) {
                $result[$this->type->value][$cell->containerId()][$cell->cellId()][$command['command']] = $command['parameters'];
            }
        }
        $result['state'] = $this->error === false ? HttpResponseState::SUCCESS->value : HttpResponseState::ERROR->value;
        return $result;
    }

    private function getCells(array $cellNames, ?ID $containerId = null): array
    {
        $cells = [];
        if ($containerId?->isTabId() === true) {
            foreach ($cellNames as $cellName) {
                $cells[] = Session::getCell(ID::refactor($cellName, $containerId));
            }
        }
        return array_values(array_filter($cells));
    }
}