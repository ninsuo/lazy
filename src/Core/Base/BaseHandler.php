<?php

namespace Lazy\Core\Base;

use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

abstract class BaseHandler extends BaseService
{
    public function validate(Args $args, IO $io, array $constraints)
    {
        $validator  = Validation::createValidator();
        $violations = 0;
        foreach ($constraints as $argument => $constraints) {
            $data   = $args->getArgument($argument);
            $errors = $validator->validate($data, $constraints);
            foreach ($errors as $error) {
                $violations++;
                $this->error($io, 'Error validating <red>%s</red>: %s', $argument, $error->getMessage());
            }
        }
        if ($violations) {
            return 1;
        }
    }
}
