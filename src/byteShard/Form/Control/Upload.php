<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form\Control;

use byteShard\Internal\Form;
use byteShard\Enum;

/**
 * Class Upload
 * @package byteShard\Form\Control
 */
class Upload extends Form\FormObject implements Form\InputWidthInterface
{
    protected string $type = 'upload';
    //TODO: refactor all parameters in the FormObject class and access them from the proxy class like all other parameters
    private array  $fileTypes        = [];
    private string $url              = 'bs/bs_upload.php';
    private string $method           = '';
    private string $targetFilename   = '';
    private string $targetPath       = '';
    private bool   $clearAfterUpload = false;
    private bool   $singleFileMode   = false;
    use Form\ClassName;
    use Form\Disabled;
    use Form\Hidden;
    use Form\InputHeight;
    use Form\InputWidth;
    use Form\Name;
    use Form\OffsetLeft;
    use Form\OffsetTop;
    use Form\Userdata;

    //Uploader-related attributes
    use Form\Mode;

    //use FormObject_titleScreen;
    use Form\TitleText;
    use Form\AutoStart;
    use Form\AutoRemove;

    //use FormObject_autoStart;
    //use FormObject_autoRemove;
    //URL attributes
    use Form\Url;
    use Form\SwfPath;
    use Form\SwfUrl;
    use Form\SlPath;
    use Form\SlUrl;

    //Silverlight-related attributes
    //use FormObject_slXap;
    //use FormObject_slUrl;
    //use FormObject_slLogs;

    /**
     * Set allowed file types for this upload control.
     * The files will be checked with their proper mime type
     *
     * @param Enum\FileType ...$fileTypes
     * @return Upload
     * @API
     */
    public function setAllowedFileTypes(Enum\FileType ...$fileTypes): Upload
    {
        foreach ($fileTypes as $fileType) {
            $this->fileTypes[] = $fileType->value;
        }
        return $this;
    }

    public function setSingleFileMode(): self
    {
        $this->attributes['multiple'] = false;
        $this->singleFileMode = true;
        return $this;
    }

    public function getSingleFileMode(): bool
    {
        return $this->singleFileMode;
    }

    /**
     * @API
     */
    public function setTargetFilename(string $filename): self
    {
        $this->targetFilename = $filename;
        return $this;
    }

    /**
     * @API
     */
    public function setClearUploadImmediately(): self
    {
        $this->clearAfterUpload = true;
        return $this;
    }

    /**
     * @API
     */
    public function setTargetPath(string $path): self
    {
        $this->targetPath = $path;
        return $this;
    }

    //TODO: refactor all the getters in the FormObject proxy class so this class has no more getters
    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTargetFilename(): string
    {
        return $this->targetFilename;
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    public function getClearAfterUpload(): bool
    {
        return $this->clearAfterUpload;
    }
}
