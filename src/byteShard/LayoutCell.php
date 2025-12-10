<?php

namespace byteShard;

use byteShard\Enum\ContentType;
use byteShard\ID\CellIDElement;
use byteShard\ID\PatternIDElement;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Utils\Strings;

class LayoutCell
{
    use PermissionImplementation;

    public const HEIGHT    = 'CellHeight';
    public const WIDTH     = 'CellWidth';
    public const COLLAPSED = 'Collapsed';
    private ?string           $collapsedLabel = null;
    private bool              $collapsed      = false;
    private ?int              $width          = null;
    private ?int              $height         = null;
    private bool              $fixedWidth     = false;
    private bool              $fixedHeight    = false;
    private bool              $hideHeader     = false;
    private bool              $hideArrow      = false;
    private ?string           $cssClass       = null;
    private ?\byteShard\ID\ID $id             = null;
    private string            $context;

    public function __construct(private readonly string $patternId, private readonly string $contentClass = '')
    {

    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function getId(): ?\byteShard\ID\ID
    {
        return $this->id;
    }

    public function setContentContainerId(\byteShard\ID\ID $contentContainerId): void
    {
        $this->id     = clone $contentContainerId;
        $contentClass = '';
        if ($contentContainerId->isPopupId()) {
            if ($this->contentClass !== '') {
                $contentClass = $this->contentClass;
            } else {
                $contentClass = trim($contentContainerId->getPopupId(), '\\').'\\'.$this->patternId;
            }
        } else if ($contentContainerId->isTabId()) {
            $contentClass = trim($contentContainerId->getTabId(), '\\').'\\'.$this->patternId;
        }
        $this->id->addIdElement(new CellIDElement($contentClass), new PatternIDElement($this->patternId));
    }

    public function setCollapsedLabel(?string $collapsedLabel): self
    {
        $this->collapsedLabel = $collapsedLabel;
        return $this;
    }

    public function setCollapsed(bool $collapsed = true): self
    {
        $this->collapsed = $collapsed;
        return $this;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;
        return $this;
    }

    public function setFixedWidth(bool $fixedWidth = true): self
    {
        $this->fixedWidth = $fixedWidth;
        return $this;
    }

    public function setFixedHeight(bool $fixedHeight = true): self
    {
        $this->fixedHeight = $fixedHeight;
        return $this;
    }

    public function setHideHeader(bool $hideHeader = true): self
    {
        $this->hideHeader = $hideHeader;
        return $this;
    }

    public function setHideArrow(bool $hideArrow = true): self
    {
        $this->hideArrow = $hideArrow;
        return $this;
    }

    public function setCssClass(?string $cssClass): self
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    public function getHorizontalAutoSize(): bool
    {
        return $this->width === null;
    }

    public function getVerticalAutoSize(): bool
    {
        return $this->height === null;
    }

    public function getLabel(): string
    {
        $contentContainerId = '';
        if ($this->id->isPopupId()) {
            $contentContainerId = $this->id->getPopupId();
        } else if ($this->id->isTabId()) {
            $contentContainerId = $this->id->getTabId();
        }
        $token = str_replace('\\', '_', $contentContainerId).'::'.'Cell.'.$this->patternId.'.Label';
        return Strings::purify(Locale::get($token));
    }

    public function getItemConfig(): ContentComponent
    {
        $size  = Session::getSizeData($this->id->getCellId());
        $setup = [
            'ID'        => $this->id->getEncryptedCellId(),
            'EID'       => $this->id->getEncryptedCellIdForEvent(),
            'patternId' => $this->patternId
        ];
        if (isset($this->context)) {
            $setup['context'] = Session::encrypt($this->context);
        }
        if ($this->collapsedLabel !== null) {
            $setup['collapsedLabel'] = $this->collapsedLabel;
        }
        if ($this->collapsed === true || array_key_exists(self::COLLAPSED, $size)) {
            $setup['collapsed'] = true;
        }
        if ($this->width !== null) {
            $setup['width'] = $this->width;
        } else if (array_key_exists(self::WIDTH, $size)) {
            $setup['width'] = $size[self::WIDTH];
        }
        if ($this->height !== null) {
            $setup['height'] = $this->height;
        } else if (array_key_exists(self::HEIGHT, $size)) {
            $setup['height'] = $size[self::HEIGHT];
        }
        if ($this->fixedWidth === true) {
            $setup['fixSize']['width'] = true;
        }
        if ($this->fixedHeight === true) {
            $setup['fixSize']['height'] = true;
        }
        if ($this->hideHeader === true) {
            $setup['hideHeader'] = true;
            $setup['label']      = '';
        } else {
            $setup['label'] = $this->getLabel();
        }
        if ($this->hideArrow === true) {
            $setup['hideArrow'] = true;
        }
        if ($this->cssClass !== '') {
            $setup['class'] = $this->cssClass;
        }
        return new ContentComponent(
            type   : ContentType::DhtmlxLayoutCell,
            content: [],
            setup  : $setup,
        );
    }
}