<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form\Control;

use byteShard\Internal\Form\FormObject;
use byteShard\Internal\Form;

class Pdf extends FormObject
{
    use Form\ClassName;
    use Form\Disabled;
    use Form\Hidden;
    use Form\Label;
    use Form\LabelAlign;
    use Form\LabelHeight;
    use Form\LabelLeft;
    use Form\LabelTop;
    use Form\LabelWidth;
    use Form\Name;

    protected string $type    = 'pdf';
    private bool     $toolbar = true;
    private int      $zoom;
    private int      $page;
    private string   $view;
    private string   $url;


    public function __construct(?string $id, string $url)
    {
        parent::__construct($id);
        $this->url                 = $url;
        $this->attributes['value'] = $this->getValueAttribute();
    }

    public function setValue(string $value): self
    {
        $this->url                 = $value;
        $this->attributes['value'] = $this->getValueAttribute();
        return $this;
    }

    public function useToolbar(bool $value = true): self
    {
        $this->toolbar             = $value;
        $this->attributes['value'] = $this->getValueAttribute();
        return $this;
    }

    public function setView(string $view): self
    {
        $this->view                = $view;
        $this->attributes['value'] = $this->getValueAttribute();
        return $this;
    }

    public function setZoom(int $value): self
    {
        $this->zoom                = $value;
        $this->attributes['value'] = $this->getValueAttribute();
        return $this;
    }

    public function setPage(int $value): self
    {
        $this->page                = $value;
        $this->attributes['value'] = $this->getValueAttribute();
        return $this;
    }

    /**
     * use any valid css value, like "80%" or "100vh" or "40px"
     * @param string $minHeight
     * @return static
     */
    public function setMinHeight(string $minHeight): static
    {
        if (isset($this->attributes)) {
            $this->attributes['minHeight'] = $minHeight;
        }
        return $this;
    }

    /**
     * use any valid css value, like "80%" or "100vh" or "40px"
     * @param string $maxHeight
     * @return static
     */
    public function setMaxHeight(string $maxHeight): static
    {
        if (isset($this->attributes)) {
            $this->attributes['maxHeight'] = $maxHeight;
        }
        return $this;
    }

    private function getValueAttribute(): string
    {
        $url     = $this->url;
        $options = [];
        if ($this->toolbar === false) {
            $options[] = 'toolbar=0';
        }
        if (isset($this->view)) {
            $options[] = 'view='.$this->view;
        }
        if (isset($this->zoom)) {
            $options[] = 'zoom='.$this->zoom;
        }
        if (isset($this->page)) {
            $options[] = 'page='.$this->page;
        }
        if (!empty($options)) {
            $url .= '#'.implode('&', $options);
        }
        return $url;
    }
}
