<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Action;

use byteShard\Enum\Export\ExportType;
use byteShard\ID\ID;
use byteShard\Internal\Action;
use byteShard\Session;

abstract class ExportAction extends Action implements ExportInterface
{
    private string     $name;
    private string     $contentId;
    private bool       $useDateInFilename    = true;
    private bool       $useAppNameInFilename = true;
    private int        $timeout;
    private ExportType $type;
    private ?ID        $containerId          = null;
    private ?string    $eventId              = null;


    public function __construct(ExportType $type, int $timeout)
    {
        $this->type    = $type;
        $this->timeout = $timeout;
    }

    /**
     * @internal
     */
    public function setEventId(?ID $id, string $eventId): void
    {
        $this->containerId = $id;
        $this->eventId     = $eventId;
    }

    protected function resetEventId(): void
    {
        $this->containerId = null;
        $this->eventId     = null;
    }

    protected function getXID(): ?string
    {
        return $this->containerId?->getEncryptedCellId();
    }

    protected function getEventId(): string
    {
        return Session::encrypt(json_encode(['i' => $this->eventId]));
    }

    public function getType(): ExportType
    {
        return $this->type;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    public function setUseDateInFilename(bool $bool = true): self
    {
        $this->useDateInFilename = $bool;
        return $this;
    }

    public function setUseApplicationNameInFilename(bool $bool = true): self
    {
        $this->useAppNameInFilename = $bool;
        return $this;
    }

    public function getUseDateInFilename(): bool
    {
        return $this->useDateInFilename;
    }

    public function getUseApplicationNameInFilename(): bool
    {
        return $this->useAppNameInFilename;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getContentId(): ?string
    {
        return $this->contentId ?? null;
    }

    /**
     * @API
     */
    public function setContentId(string $contentId): self
    {
        $this->contentId = $contentId;
        return $this;
    }
}