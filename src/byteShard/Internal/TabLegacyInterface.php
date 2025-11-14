<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

interface TabLegacyInterface
{
    public function setSelected(string $name = ''): bool;

    public function selectFirstTab(): void;

    public function getCells(): array;
    
    public function selectFirstTabIfNoneSelected(): void;

    public function getSelected(): bool;
}