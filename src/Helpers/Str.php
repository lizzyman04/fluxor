<?php
/**
 * Fluxor - String Helpers
 */

namespace Fluxor\Helpers;

class Str
{
    private string $value = '';

    /**
     * Constructor - can be called with or without a string
     */
    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    /**
     * Static factory method
     */
    public static function of(string $value = ''): self
    {
        return new static($value);
    }

    /**
     * Convert to uppercase
     */
    public function upper(): self
    {
        $this->value = strtoupper($this->value);
        return $this;
    }

    /**
     * Convert to lowercase
     */
    public function lower(): self
    {
        $this->value = strtolower($this->value);
        return $this;
    }

    /**
     * Convert to camelCase
     */
    public function camel(): self
    {
        $this->value = lcfirst($this->studly()->value);
        return $this;
    }

    /**
     * Convert to StudlyCase
     */
    public function studly(): self
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $this->value));
        $this->value = str_replace(' ', '', $value);
        return $this;
    }

    /**
     * Convert to snake_case
     */
    public function snake(string $delimiter = '_'): self
    {
        if (!ctype_lower($this->value)) {
            $value = preg_replace('/\s+/u', '', ucwords($this->value));
            $this->value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $this;
    }

    /**
     * Check if string contains a substring
     */
    public function contains(string $needle): bool
    {
        return str_contains($this->value, $needle);
    }

    /**
     * Check if string starts with a substring
     */
    public function startsWith(string $needle): bool
    {
        return str_starts_with($this->value, $needle);
    }

    /**
     * Check if string ends with a substring
     */
    public function endsWith(string $needle): bool
    {
        return str_ends_with($this->value, $needle);
    }

    /**
     * Limit the number of characters
     */
    public function limit(int $limit = 100, string $end = '...'): self
    {
        if (mb_strwidth($this->value, 'UTF-8') <= $limit) {
            return $this;
        }
        $this->value = rtrim(mb_strimwidth($this->value, 0, $limit, '', 'UTF-8')) . $end;
        return $this;
    }

    /**
     * Generate a random string
     */
    public static function random(int $length = 16): string
    {
        $string = '';
        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytes = random_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }
        return $string;
    }

    /**
     * Get the string value
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Magic method to return string when object is treated as string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}