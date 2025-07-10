<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Form;

use byteShard\Exception;
use byteShard\Internal\Validation;

/**
 * Trait Validate
 * @package byteShard\Form\Internal
 * @property array $validations
 */
trait Validate
{
    protected array $validations    = [];
    protected array $newValidations = [];

    /**
     * adds validations to the form object and enables live validation
     * @param Validation|Validation\Validation ...$validations
     * @return self
     * @throws Exception
     * @noinspection PhpDocSignatureInspection
     * @API
     */
    public function addValidations(Validation|Validation\Validation ...$validations): self
    {
        foreach ($validations as $validation) {
            if (get_class($validation) === \byteShard\Form\Validation::class) {
                throw new Exception(__METHOD__.': Method only accepts either a single (deprecated) Validation or multiple subclasses of Validation');
            }
            if ($validation instanceof Validation\Validation) {
                $this->newValidations[] = $validation;
            } else {
                $this->validations[] = $validation;
            }
        }
        return $this;
    }
}
