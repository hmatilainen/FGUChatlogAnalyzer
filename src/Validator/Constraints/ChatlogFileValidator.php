<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validator for chatlog file uploads, checking extension and size.
 *
 * @package App\Validator\Constraints
 */
class ChatlogFileValidator extends ConstraintValidator
{
    /**
     * Validate the uploaded file against the ChatlogFile constraint.
     *
     * @param mixed $value
     * @param Constraint $constraint
     * @return void
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ChatlogFile) {
            throw new UnexpectedTypeException($constraint, ChatlogFile::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof UploadedFile) {
            throw new UnexpectedTypeException($value, UploadedFile::class);
        }

        // Check file extension
        $extension = strtolower(pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension !== 'html') {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check file size
        $maxSize = $this->parseSize($constraint->maxSize);
        if ($value->getSize() > $maxSize) {
            $this->context->buildViolation('The file is too large. Maximum size is {{ max_size }}.')
                ->setParameter('{{ max_size }}', $constraint->maxSize)
                ->addViolation();
        }
    }

    /**
     * Parse a human-readable size string (e.g., 2M, 500K) into bytes.
     *
     * @param string $size
     * @return int
     */
    private function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
} 