<?php

namespace Lazy\Core\Base;

use Lazy\Core\Exception\StopExecutionException;
use Symfony\Component\Validator\Validation;

abstract class BaseHandler extends BaseService
{
    public function validate($name, $value, $constraints)
    {
        if (!is_array($constraints)) {
            $constraints = [$constraints];
        }

        $validator  = Validation::createValidator();
        $errors = $validator->validate($value, $constraints);
        foreach ($errors as $error) {
            throw new StopExecutionException('Error validating value <red>%s</red> for %s: %s', $value, $name, $error->getMessage());
        }
    }
}
