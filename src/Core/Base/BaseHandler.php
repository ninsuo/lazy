<?php

namespace Lazy\Core\Base;

use Lazy\Core\Exception\StopExecutionException;

abstract class BaseHandler extends BaseService
{
    public function validate($name, $value, $constraints)
    {
        if (!is_array($constraints)) {
            $constraints = [$constraints];
        }

        $validator  = Validation::createValidator();
        $violations = 0;
        $errors = $validator->validate($value, $constraints);
        foreach ($errors as $error) {
            $violations++;
            $this->error('Error validating value <red>%s</red> for %s: %s', $value, $name, $error->getMessage());
        }

        if ($violations) {
            throw new StopExecutionException();
        }
    }
}
