<?php

namespace byteShard\Internal\Traits;

/**
 * Trait Disabled
 * @property array $attributes
 */
trait Image
{
    /**
     * the icon of the button in the enabled state.
     * @API
     */
    public function setImage(string $string): self
    {
        $this->attributes['img'] = $string;
        return $this;
    }
}