<?php
declare(strict_types=1);

final class Validator
{
    private array $errors = [];

    public function required(array $data, string $field, string $label): self
    {
        if (empty(trim((string)($data[$field] ?? '')))) {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function date(array $data, string $field, string $label): self
    {
        $value = $data[$field] ?? '';
        if ($value !== '' && !DateTime::createFromFormat('Y-m-d', $value)) {
            $this->errors[$field] = "$label must be a valid date (YYYY-MM-DD).";
        }
        return $this;
    }

    public function phone(array $data, string $field, string $label): self
    {
        $value = $data[$field] ?? '';
        if ($value !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $value)) {
            $this->errors[$field] = "$label is not a valid phone number.";
        }
        return $this;
    }

    public function maxLength(array $data, string $field, string $label, int $max): self
    {
        $value = $data[$field] ?? '';
        if (mb_strlen($value) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters.";
        }
        return $this;
    }

    public function allowedValues(array $data, string $field, string $label, array $allowed): self
    {
        $value = $data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "$label contains an invalid value.";
        }
        return $this;
    }

    public function alphaNumeric(array $data, string $field, string $label): self
    {
        $value = $data[$field] ?? '';
        if ($value !== '' && !preg_match('/^[a-zA-Z0-9_\- ]+$/', $value)) {
            $this->errors[$field] = "$label may only contain letters, numbers, spaces, hyphens and underscores.";
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
