<?php

namespace byteShard\Internal\Traits;

/**
 * Trait Disabled
 * @property array $attributes
 */
trait Big
{
    /**
     * defines whether the button is big or small, false by default.
     * @API
     */
    public function setBig(bool $bool = true): self
    {
        if ($bool === true) {
            $this->attributes['isbig'] = 'true';
        } elseif (array_key_exists('isbig', $this->attributes)) {
            unset($this->attributes['isbig']);
        }
        return $this;
    }
}