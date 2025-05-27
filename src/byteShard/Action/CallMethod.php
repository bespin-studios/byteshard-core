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
        parent::__construct();
        $this->method    = $methodName;
        $this->parameter = $methodParameter;
        $this->addUniqueID($methodName, $methodParameter);
    }

    /**
     * @throws ReflectionException|Exception
     */
    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        $result    = ['state' => HttpResponseState::SUCCESS->value];
        $className = null;
        if ($container instanceof Cell) {
            $className = $container->getContentClass();
        } elseif ($container instanceof Tab) {
            $className = $container->getToolbarClass();
        }
        if ($className !== null && $className !== '' && class_exists($className) && method_exists($className, $this->method)) {
            $argumentTest       = new ReflectionClass($className);
            $numberOfParameters = $argumentTest->getMethod($this->method)->getNumberOfParameters();
            if ($numberOfParameters >= 0 && $numberOfParameters <= 2) {
                $clientData     = $this->getClientData();
                $getData        = $this->getGetData();
                $clientTimeZone = $this->getClientTimeZone();
                $call           = new $className($container);
                if ($call instanceof CellContent) {
                    $call->setProcessedClientData($clientData);
                    $call->setClientTimeZone($clientTimeZone);
                    $cell = $call->getCell();
                }
                $methodReturns = null;
                switch ($numberOfParameters) {
                    case 0:
                        $methodReturns = $call->{$this->method}();
                        break;
                    case 1:
                        $methodReturns = $call->{$this->method}($id);
                        break;
                    case 2:
                        $methodReturns = $call->{$this->method}($id, $this->parameter);
                        break;
                }
                if ($methodReturns instanceof Action) {
                    $methodReturns = [$methodReturns];
                }
                if (is_array($methodReturns)) {
                    $mergeArray       = [];
                    $objectProperties = $this->getObjectProperties();
                    foreach ($methodReturns as $returnIndex => $methodReturn) {
                        if ($methodReturn instanceof Action) {
                            if (!isset($cell)) {
                                if ($call instanceof CellContent) {
                                    $cell = $call->getCell();
                                } else {
                                    $cell = new Cell();
                                }
                            }
                            $mergeArray[] = ActionCollector::initializeAction($methodReturn, null, $cell, null, '', '', $clientData, $getData, $clientTimeZone, $objectProperties)->getResult($cell, $id);
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
            } else {
                throw new Exception(__METHOD__.': Any method that will be called by '.self::class.' needs to have no more than two parameters');
            }
        }
        return $result === null ? new Action\ActionResult() : new Action\ActionResultMigrationHelper($result);
    }
}
