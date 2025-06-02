<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Action;

use byteShard\Enum\HttpResponseState;
use byteShard\ID\ID;

class ActionResult implements ActionResultInterface
{
    public function __construct(private bool $error = false)
    {
    }

    public function setError(): void
    {
        $this->error = true;
    }

    public function getResultArray(?ID $containerId): array
    {
        return ['state' => $this->error === false ? HttpResponseState::SUCCESS->value : HttpResponseState::ERROR->value];
    }
}