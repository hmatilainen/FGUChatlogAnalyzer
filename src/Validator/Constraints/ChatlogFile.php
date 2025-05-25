<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ChatlogFile extends Constraint
{
    public $message = 'Please upload a valid Fantasy Grounds chatlog file (.html)';
    public $maxSize = '15M';
} 