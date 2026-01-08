<?php

namespace byteShard\Internal\Traits;

trait Value
{
    /**
     * the text value.
     * @API
     */
    public function setValue(string $value): self
    {
        $this->attributes['value'] = $value;
        return $this;
    }
}