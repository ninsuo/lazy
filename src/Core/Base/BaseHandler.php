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
        $violations = 0;
        $errors = $validator->validate($value, $constraints);
        foreach ($errors as $error) {

            echo "there are errors\n";

            $violations++;
            $this->error('Error validating value <red>%s</red> for %s: %s', $value, $name, $error->getMessage());
        }

        if ($violations) {
            throw new StopExecutionException();
        }
    }
}
