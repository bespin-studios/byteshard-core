<?php

namespace byteShard\Internal\Traits\Action;

use byteShard\Cell;
use byteShard\Internal\ContentClassFactory;

trait MethodCallback
{
    private function getNewValueByCallbackMethod(Cell $cell, string $method, mixed $methodParameter, string $newValueProperty = 'newValue', string $nestedProperty = 'runNested'): null|string|object
    {
        $contentClassName = $cell->getContentClass();
        if (!(class_exists($contentClassName) && method_exists($contentClassName, $method))) {
            return null;
        }

        $call   = ContentClassFactory::cellContent($contentClassName, '', $cell);
        $params = is_iterable($methodParameter)
            ? $methodParameter
            : [$methodParameter];
        $result = $call->{$method}(...$params);

        $hasNestedProperty = match (true) {
            is_array($result) && array_key_exists($nestedProperty, $result) => $result[$nestedProperty],
            is_object($result) && property_exists($result, $nestedProperty) => $result->{$nestedProperty},
            default                                                         => null
        };
        if (is_bool($hasNestedProperty) && method_exists($this, 'setRunNested')) {
            $this->setRunNested($hasNestedProperty);
        }

        return match (true) {
            is_array($result) && isset($result[$newValueProperty])            => self::extractValue($result[$newValueProperty]),
            is_object($result) && property_exists($result, $newValueProperty) => self::extractValue($result->{$newValueProperty}),
            is_string($result), is_object($result)                            => $result,
            is_numeric($result)                                               => (string)$result,
            default                                                           => null
        };
    }

    private static function extractValue(mixed $value): null|string|object
    {
        return match (true) {
            is_numeric($value)                   => (string)$value,
            is_string($value), is_object($value) => $value,
            default                              => null
        };
    }
}