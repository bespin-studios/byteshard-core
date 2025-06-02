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
use byteShard\Internal\CellContent;
use byteShard\Session;
use ReflectionException;
use ReflectionClass;

/**
 * Class PollMethod
 */
class PollMethod extends Action
{
    private string  $id;
    private string  $name;
    private ?string $method;
    private mixed   $data;
    private string  $queue;

    /**
     * PollMethod constructor.
     * @param string $id
     * @param ?string $methodName
     * @param null $data
     */
    public function __construct(string $id, ?string $methodName = null, mixed $data = null)
    {
        parent::__construct();
        $this->method = $methodName === null ? $id : $methodName;
        $this->data   = $data;
        $this->name   = $id;
        $this->id     = md5($id);
        $this->queue  = md5(microtime());
    }

    /**
     * new queue is used to end the current poll queue after the next call
     * @param $id
     * @param $cell
     * @param int $time
     * @param bool $newQueue
     * @return array
     * @API
     */
    public static function getResponseArray($id, $cell, int $time = 200, bool $newQueue = false): array
    {
        $result['state'] = HttpResponseState::SUCCESS->value;
        if ($cell instanceof CellContent) {
            $cell = $cell->getCell();
        }
        if ($cell instanceof Cell) {
            $actions = $cell->getContentActions('onPoll');
            if (!empty($actions)) {
                foreach ($actions as $action) {
                    if (($action instanceof PollMethod) && $action->getName() === $id) {
                        if ($newQueue === true) {
                            $action->createNewQueue();
                        }
                        $result['layout'][$cell->containerId()][$cell->cellId()]['poll']['interval'] = $time;
                        $result['layout'][$cell->containerId()][$cell->cellId()]['poll']['id']       = $action->getId();
                    }
                }
            }
        } //TODO: else trigger exception
        return $result;
    }

    /**
     * stops existing polling queue on the next call.
     * can be used to update poll time for example
     */
    public function createNewQueue()
    {
        $this->queue = md5(microtime());
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return Session::encrypt(
            json_encode([
                'id'        => $this->name,
                'queueTime' => microtime()
            ])
        );
    }

    /**
     * @param $cell
     * @param int $time
     */
    public function poll($cell, int $time = 500)
    {
    }

    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        $result    = [];
        if (($container instanceof Cell) && $this->method !== null) {
            $className = $container->getContentClass();
            if (class_exists($className)) {
                if (method_exists($className, $this->method)) {
                    try {
                        $argumentTest       = new ReflectionClass($className);
                        $numberOfParameters = $argumentTest->getMethod($this->method)->getNumberOfParameters();
                    } catch (ReflectionException $exception) {
                        throw new Exception($exception->getMessage());
                    }
                    if ($numberOfParameters === 1) {
                        $call   = new $className($container);
                        $result = $call->{$this->method}($id);
                        if (isset($result['run_nested']) && is_bool($result['run_nested'])) {
                            $this->setRunNested($result['run_nested']);
                            unset($result['run_nested']);
                        }
                    } elseif ($numberOfParameters === 2) {
                        $call   = new $className($container);
                        $result = $call->{$this->method}($id, $this->data);
                        if (isset($result['run_nested']) && is_bool($result['run_nested'])) {
                            $this->setRunNested($result['run_nested']);
                            unset($result['run_nested']);
                        }
                    } else {
                        throw new Exception('Any method that will be called by Action\PollMethod needs to have exactly one parameter');
                    }
                } else {
                    throw new Exception('Method '.$this->method.' not defined in Class '.$className);
                }
            }
        }
        return new Action\ActionResultMigrationHelper($result);
    }
}
