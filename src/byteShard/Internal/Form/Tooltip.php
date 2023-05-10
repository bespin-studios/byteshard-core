<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Form;

/**
 * Trait Tooltip
 * @package byteShard\Form\Internal
 */
trait Tooltip
{
    /**
     * creates the tooltip for the FormObject
     * @param string $tooltip
     * @return $this
     * @noinspection PhpDocSignatureInspection
     * @API
     */
    public function setTooltip(string $tooltip): self
    {
        if (isset($this->attributes)) {
            $this->attributes['tooltip'] = $tooltip;
        }
        return $this;
    }
}
