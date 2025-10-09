<?php

namespace byteShard\Internal\Traits;

/**
 * Class Label
 * @property array $attributes
 */
trait Label
{
    /**
     * the text label.
     * @API
     */
    public function setLabel(string $label): self
    {
        $this->attributes['label'] = $label;
        return $this;
    }
}
