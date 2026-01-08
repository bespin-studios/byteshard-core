<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Exception;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\ActionCollector;
use byteShard\Internal\CellContent;
use byteShard\Tab;
use ReflectionClass;
use ReflectionException;

/**
 * calls a method with the $method_name in the current cell
 * that method can have up to two parameters
 * the first parameter is the event request body
 * the second parameter can be passed as the second constructor argument
 *
 * Class CallMethod
 * @package byteShard\Action
 */
class CallMethod extends Action
{
    /**
     * part of action uid
     * @var string
     */
    private string $method;

    /**
     * part of action uid
     * @var null
     */
    private mixed $parameter;

    /**
     * CallMethod constructor.
     * @param string $methodName
     * @param mixed $methodParameter
     */
    public function __construct(string $methodName, mixed $methodParameter = null)
    {
        $this->method    = $methodName;
        $this->parameter = $methodParameter;
    }

    /**
     * @throws ReflectionException|Exception
     */
    protected function runAction(): ActionResultInterface
    {
        $container = $this->getActionInitDTO()->eventContainer;
        $result    = ['state' => HttpResponseState::SUCCESS->value];
        if (method_exists($container, $this->method)) {
            $params        = is_iterable($this->parameter) ? $this->parameter : [$this->parameter];
            $methodReturns = $container->{$this->method}(...$params);
            if ($methodReturns instanceof Action) {
                $methodReturns = [$methodReturns];
            }
            if (is_array($methodReturns)) {
                $mergeArray = [];
                foreach ($methodReturns as $returnIndex => $methodReturn) {
                    if ($methodReturn instanceof Action) {
                        $methodReturn->initializeAction($this->getActionInitDTO());
                        $mergeArray[] = $methodReturn->getResult();
                    } else {
                        $result[$returnIndex] = $methodReturn;
                    }
                }
                if (!empty($mergeArray)) {
                    $result = array_merge_recursive($result, ...$mergeArray);
                }
            } else {
                $result = $methodReturns;
            }
            if (isset($result['run_nested']) && is_bool($result['run_nested'])) {
                $this->setRunNested($result['run_nested']);
                unset($result['run_nested']);
            }
        }
        return $result === null ? new Action\ActionResult() : new Action\ActionResultMigrationHelper($result);
    }
}
