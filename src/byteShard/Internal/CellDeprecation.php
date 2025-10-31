<?php

namespace byteShard\Internal;

use byteShard\Enum\ContentType;
use byteShard\ID\ID;

abstract class CellDeprecation
{
    private ?string $refactorCellCollapsedLabel = null;
    protected bool  $refactorCellUserWidth      = false;
    protected bool  $refactorCellUserHeight     = false;
    protected bool  $refactorCellCollapsed      = false;
    private ?int    $refactorCellWidth          = null;
    private ?int    $refactorCellHeight         = null;
    private bool    $refactorCellUseFixedWidth  = false;
    private bool    $refactorCellUseFixedHeight = false;
    private bool    $refactorCellHideHeader     = false;
    private bool    $refactorCellHideArrow      = false;
    private string  $cssClass                   = '';

    /**
     * add a css class to the layoutCell
     */
    public function setCssClass(string $class): self
    {
        $this->cssClass = $class;
        return $this;
    }

    /**
     * This will set the initial width of the cell which can later be changed by the user (unless otherwise specified)
     * On any subsequent login the width is currently evaluated from the cookie
     * On any subsequent reload the width is evaluated from the framework
     * The width will only be set the first time this method is called.
     */
    public function setWidth(int $int): self
    {
        if ($this->refactorCellUserWidth === false) {
            $this->refactorCellWidth = $int;
        }
        return $this;
    }

    /**
     * @internal store the cell width in the session after the user resized a cell
     */
    public function setWidthOnResize(int $int): self
    {
        $this->refactorCellWidth = $int;
        return $this;
    }

    /**
     * This will set the initial height of the cell which can later be changed by the user (unless otherwise specified)
     * On any subsequent login the height is currently evaluated from the cookie
     * On any subsequent reload the height is evaluated from the framework
     * The height will only be set the first time this method is called.
     */
    public function setHeight(int $int): self
    {
        if ($this->refactorCellUserHeight === false) {
            $this->refactorCellHeight = $int;
        }
        return $this;
    }

    /**
     * @internal store the cell height in the session after the user resized a cell
     */
    public function setHeightOnResize(int $height): self
    {
        $this->refactorCellHeight = $height;
        return $this;
    }

    /**
     * This will hide the header row of a cell
     * Note: collapse / expand buttons and meta information in certain CellContents will not be available
     */
    public function setHideHeader(bool $bool = true): self
    {
        $this->refactorCellHideHeader = $bool;
        return $this;
    }

    /**
     * @API
     */
    public function setHideArrow(): self
    {
        $this->refactorCellHideArrow = true;
        return $this;
    }

    /**
     * This will make the cell not resizable horizontally
     * (works only in a Layout with at least 2 columns and at least one column must be auto sizable)
     *
     * @API
     */
    public function setFixedWidth(bool $bool = true): self
    {
        $this->refactorCellUseFixedWidth = $bool;
        return $this;
    }

    /**
     * This will make the cell not resizable vertically
     * (works only in a Layout with at least 2 rows and at least one row must be auto sizable)
     *
     * @API
     */
    public function setFixedHeight(bool $bool = true): self
    {
        $this->refactorCellUseFixedHeight = $bool;
        return $this;
    }

    /**
     * @API
     */
    public function setCollapsedLabel(string $collapsedLabel): self
    {
        $this->refactorCellCollapsedLabel = $collapsedLabel;
        return $this;
    }

    public function setCollapsed(bool $bool = true): self
    {
        $this->refactorCellCollapsed = $bool;
        return $this;
    }

    public function getHorizontalAutoSize(): bool
    {
        return $this->refactorCellWidth === null;
    }

    public function getVerticalAutoSize(): bool
    {
        return $this->refactorCellHeight === null;
    }

    abstract public function getNewId(): ?ID;

    abstract public function getLabel(): string;

    public function getItemConfig(string $patternId): Struct\ContentComponent
    {
        $id    = $this->getNewId();
        $setup = [
            'ID'        => $id->getEncryptedCellId(),
            'EID'       => $id->getEncryptedCellIdForEvent(),
            'patternId' => $patternId
        ];
        if ($this->refactorCellCollapsedLabel !== null) {
            $setup['collapsedLabel'] = $this->refactorCellCollapsedLabel;
        }
        if ($this->refactorCellCollapsed === true) {
            $setup['collapsed'] = true;
        }
        if ($this->refactorCellWidth !== null) {
            $setup['width'] = $this->refactorCellWidth;
        }
        if ($this->refactorCellHeight !== null) {
            $setup['height'] = $this->refactorCellHeight;
        }
        if ($this->refactorCellUseFixedWidth === true) {
            $setup['fixSize']['width'] = true;
        }
        if ($this->refactorCellUseFixedHeight === true) {
            $setup['fixSize']['height'] = true;
        }
        if ($this->refactorCellHideHeader === true) {
            $setup['hideHeader'] = true;
            $setup['label']      = '';
        } else {
            $setup['label'] = $this->getLabel();
        }
        if ($this->refactorCellHideArrow === true) {
            $setup['hideArrow'] = true;
        }
        if ($this->cssClass !== '') {
            $setup['class'] = $this->cssClass;
        }
        return new Struct\ContentComponent(
            type   : ContentType::DhtmlxLayoutCell,
            content: [],
            setup  : $setup,
        );
    }
}