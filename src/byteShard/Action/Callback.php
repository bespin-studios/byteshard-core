<?php

namespace byteShard\Action;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use Closure;

class Callback extends Action
{

    public function __construct(private readonly Closure $callback)
    {
    }

    protected function runAction(): ActionResultInterface
    {
        $result      = ['state' => HttpResponseState::SUCCESS->value];
        $returnValue = ($this->callback)($this);

        if ($returnValue instanceof Action) {
            $returnValue = [$returnValue];
        }

        if (is_array($returnValue)) {
            $mergeArray = [];
            foreach ($returnValue as $key => $value) {
                if ($value instanceof Action) {
                    $value->initializeAction($this->getActionInitDTO());
                    $mergeArray[] = $value->getResult();
                } else {
                    $result[$key] = $value;
                }
            }
            if (!empty($mergeArray)) {
                $result = array_merge_recursive($result, ...$mergeArray);
            }
        } elseif ($returnValue !== null) {
            $result = $returnValue;
        }

        return new Action\ActionResultMigrationHelper($result);
    }
}