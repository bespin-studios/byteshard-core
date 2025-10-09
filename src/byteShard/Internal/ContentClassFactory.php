<?php

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Exception;
use byteShard\Form\FormInterface;
use byteShard\Grid\GridInterface;
use byteShard\Internal\Ribbon\RibbonClassInterface;
use byteShard\Toolbar\ToolbarInterface;

class ContentClassFactory
{

    /**
     * @throws Exception
     */
    public static function getToolbar(Cell $cell): ToolbarInterface
    {
        $toolbarClass = '\\byteShard\\Toolbar';
        if (class_exists($toolbarClass) && is_subclass_of($toolbarClass, ToolbarInterface::class)) {
            return new $toolbarClass($cell);
        } else {
            throw new Exception('Toolbar class not found or not a subclass of '.ToolbarInterface::class);
        }
    }

    /**
     * @throws Exception
     */
    public static function getRibbon(Cell $cell): RibbonClassInterface
    {
        $ribbonClass = '\\byteShard\\Ribbon';
        if (class_exists($ribbonClass) && is_subclass_of($ribbonClass, RibbonClassInterface::class)) {
            return new $ribbonClass($cell);
        } else {
            throw new Exception('Ribbon class not found or not a subclass of '.RibbonClassInterface::class);
        }
    }

    /**
     * @throws Exception
     */
    public static function getGrid(Cell $cell): GridInterface
    {
        return new (self::getGridClass())($cell);
    }

    /**
     * @throws Exception
     */
    public static function getGridClass(): string
    {
        $gridClass = '\\byteShard\\Grid';
        if (class_exists($gridClass) && is_subclass_of($gridClass, GridInterface::class)) {
            return $gridClass;
        } else {
            throw new Exception('Grid class not found or not a subclass of '.GridInterface::class);
        }
    }

    /**
     * @throws Exception
     */
    public static function getForm(Cell $cell): FormInterface
    {
        return new (self::getFormClass())($cell);
    }

    /**
     * @throws Exception
     */
    public static function getFormClass(): string
    {
        $formClass = '\\byteShard\\Form';
        if (class_exists($formClass) && is_subclass_of($formClass, FormInterface::class)) {
            return $formClass;
        } else {
            throw new Exception('Form class not found or not a subclass of '.FormInterface::class);
        }
    }
}