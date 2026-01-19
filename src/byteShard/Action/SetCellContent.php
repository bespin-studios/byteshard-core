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
use byteShard\Internal\ContentClassFactory;
use byteShard\Internal\Export\ExportInterface;
use byteShard\Tab;
use ReflectionClass;

/**
 * @exceptionId 00005
 * Class SetCellContent
 * @package byteShard\Action
 */
class SetCellContent extends Action
{
    private string $cell;
    private string $className = '';
    private string $method;
    private mixed  $methodParameter;

    /**
     * SetCellContent constructor.
     * @param string $cell
     * @param string $methodName
     * @param null $methodParameter
     */
    public function __construct(string $cell, string $methodName = '', mixed $methodParameter = null)
    {
        $this->cell            = Cell::getContentCellName($cell);
        $this->method          = $methodName;
        $this->methodParameter = $methodParameter;
    }

    /**
     * @param string $cellContentClassName
     * @throws Exception
     * @API
     */
    public function setCellContentClassName(string $cellContentClassName): void
    {
        if ($cellContentClassName !== '') {
            $this->className = '\\App\\Cell\\'.trim($cellContentClassName, '\\');

            if (!is_subclass_of($this->className, CellContent::class)) {
                $e = new Exception('cell_content_class_name class must be subclass of CellContent.', 100005002);
                $e->setLocaleToken('byteShard.action.setCellContent.invalidArgument.__construct.cell_content_class_name');
                throw $e;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $container       = $this->getActionInitDTO()->cell;
        $result['state'] = HttpResponseState::WARNING->value;
        $cells           = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            if (!empty($this->method)) {
                $methodClassName = null;
                if ($container instanceof Cell) {
                    $methodClassName = $container->getContentClass();
                } elseif ($container instanceof Tab) {
                    $methodClassName = $container->getToolbarClass();
                }
                if ($methodClassName !== '' && class_exists($methodClassName) && method_exists($methodClassName, $this->method)) {
                    $argumentTest       = new ReflectionClass($methodClassName);
                    $numberOfParameters = $argumentTest->getMethod($this->method)->getNumberOfParameters();
                    switch ($numberOfParameters) {
                        case 1:
                            $call = ContentClassFactory::cellContent($methodClassName, null, $cell);
                            $cell = $call->getCell();
                            if ($call instanceof ExportInterface) {
                                $call->setProcessedClientData($this->getClientData());
                            }
                            $methodResult = $call->{$this->method}($cell);
                            if ($methodResult instanceof CellContent) {
                                $result[Action\ActionTargetEnum::Layout->value][$cell->containerId()][$cell->cellId()]['setCellContent'] = $methodResult->getCellContent();
                            } elseif ($methodResult !== null) {
                                throw new Exception('Any method that will be called by byteShard\Action\SetCellContent needs to return an object of type CellContent', 100005003);
                            }
                            break;
                        case 2:
                            $call = ContentClassFactory::cellContent($methodClassName, null, $cell);
                            $cell = $call->getCell();
                            if ($call instanceof ExportInterface) {
                                $call->setProcessedClientData($this->getClientData());
                            }
                            $methodResult = $call->{$this->method}($cell, $this->methodParameter);
                            if ($methodResult instanceof CellContent) {
                                $result[Action\ActionTargetEnum::Layout->value][$cell->containerId()][$cell->cellId()]['setCellContent'] = $methodResult->getCellContent();
                            } elseif ($methodResult !== null) {
                                throw new Exception('Any method that will be called by byteShard\Action\SetCellContent needs to return an object of type CellContent', 100005004);
                            }
                            break;
                        default:
                            throw new Exception('Any method that will be called by byteShard\Action\SetCellContent needs to have one or two parameters', 100005005);
                    }
                }
            } elseif (!empty($this->className)) {
                $contentClass = ContentClassFactory::cellContent($this->className, null, $cell);
                $cell = $contentClass->getCell();
                $result[Action\ActionTargetEnum::Layout->value][$cell->containerId()][$cell->cellId()]['setCellContent'] = $contentClass->getCellContent();
            }
            $result['state'] = HttpResponseState::SUCCESS->value;
        }
        return new Action\ActionResultMigrationHelper($result);
    }
}
