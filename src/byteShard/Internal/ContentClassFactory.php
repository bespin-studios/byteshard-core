<?php

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Container;
use byteShard\Exception;
use byteShard\Form\FormInterface;
use byteShard\Grid\GridInterface;
use byteShard\Internal\Ribbon\RibbonClassInterface;
use byteShard\Internal\Toolbar\ToolbarClassInterface;
use byteShard\Internal\Toolbar\ToolbarContainer;
use byteShard\Popup;
use byteShard\TabNew;

class ContentClassFactory
{

    /**
     * @throws Exception
     */
    public static function getToolbar(ToolbarContainer $toolbarContainer): ToolbarClassInterface
    {
        $toolbarClass = '\\byteShard\\Toolbar';
        if (class_exists($toolbarClass) && is_subclass_of($toolbarClass, ToolbarClassInterface::class)) {
            return new $toolbarClass($toolbarContainer);
        } else {
            throw new Exception('Toolbar class not found or not a subclass of '.ToolbarClassInterface::class);
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

    public static function cellContent(string $contentClass, string $context, Cell $cell): CellContent|Container
    {
        $cellContent = new $contentClass($cell, $context);
        if ($cellContent instanceof CellContent || $cellContent instanceof Container) {
            return $cellContent;
        }
        throw new Exception('('.$contentClass.') Cell content class not found or not a subclass of '.CellContent::class);
    }

    public static function popup(string $popupClass): Popup
    {
        $popup = new $popupClass();
        if ($popup instanceof Popup) {
            return $popup;
        }
        throw new Exception('('.$popupClass.') Popup class not found or not a subclass of '.Popup::class);
    }

    public static function tab(string $tabClass): TabNew
    {
        $tab = new $tabClass();
        if ($tab instanceof TabNew) {
            return $tab;
        }
        throw new Exception('('.$tabClass.') Tab class not found or not a subclass of '.TabNew::class);
    }
}