<?php

namespace byteShard\Internal\Traits;

/**
 * Trait Disabled
 * @property array $attributes
 */
trait Disabled
{
    /**
     * disables/enables the item.
     * @API
     */
    public function setDisabled(bool $bool = true): self
    {
        if ($bool === true) {
            $this->attributes['disabled'] = true;
        } elseif (array_key_exists('disabled', $this->attributes)) {
            unset($this->attributes['disabled']);
        }
        return $this;
    }
}