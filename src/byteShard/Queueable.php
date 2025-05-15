<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

abstract class Queueable
{
    private bool $parentConstructCalled;

    /**
     * Queueable constructor.
     * @param mixed $data
     */
    public function __construct(private readonly mixed $data)
    {
        $this->parentConstructCalled = true;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return bool
     */
    public function parentConstructHasBeenCalled(): bool
    {
        return $this->parentConstructCalled;
    }

    /**
     * @param mixed $data
     * @return bool
     */
    abstract public function run(mixed $data): bool;

    /**
     * method is called when all runs failed and there are no tries left or a critical exception ocurred
     */
    public function failureCallback(mixed $data): void
    {
        // implement in child class
    }

    /**
     * method is called on successful run
     */
    public function successCallback(mixed $data, ?\Exception $exception = null): void
    {
        // implement in child class
    }
}
