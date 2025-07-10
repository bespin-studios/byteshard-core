<?php

namespace byteShard\Internal\Struct;

use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use JsonSerializable;

class ClientCellComponent implements JsonSerializable, UiComponentInterface
{
    public readonly array $events;

    public function __construct(
        public readonly ContentType         $type,
        public readonly array|object|string $content,
        array                               $events = [],
        public readonly array               $pre = [],
        public readonly array               $post = [],
        public readonly array               $settings = [],
        public readonly ContentFormat       $format = ContentFormat::XML
    )
    {
        $this->events = ClientCellEvent::getUniqueEvents(...$events);
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), fn($value) => !empty($value));
    }
}