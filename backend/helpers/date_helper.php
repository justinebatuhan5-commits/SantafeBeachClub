<?php
declare(strict_types=1);

/** Parse an exact calendar date without PHP's silent date normalization. */
function sf_parse_exact_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false) {
        return null;
    }
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }
    return $date->format('Y-m-d') === $value ? $date : null;
}

