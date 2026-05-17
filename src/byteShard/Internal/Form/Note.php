<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Form;

/**
 * Trait Note
 * @package byteShard\Form\Internal
 * @property array $note
 */
trait Note
{
    /**
     * creates the details block which is placed under the FormObject. If null is passed, there will be a locale lookup
     * @param string $string (the text of the block)
     * @return $this
     * @noinspection PhpDocSignatureInspection
     * @API
     */
    public function setNote(?string $string = null): self
    {
        if (property_exists($this, 'note')) {
            $this->note['text'] = $string;
        }
        return $this;
    }

    /**
     * the width of the note block
     * @param int $width (the width of the note block)
     * @return $this
     * @noinspection PhpDocSignatureInspection
     * @API
     */
    public function setNoteWidth(int $width): self
    {
        if (property_exists($this, 'note')) {
            $this->note['width'] = $width;
        }
        return $this;
    }
}
