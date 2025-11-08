<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Base class for all Request DTOs
 *
 * Provides:
 * - Automatic JSON deserialization from Request
 * - Validation with groups support
 * - Type-safe property access
 * - Factory methods for common patterns
 *
 * Usage:
 * ```php
 * class CreateBookingDTO extends AbstractRequestDTO
 * {
 *     #[Assert\NotBlank]
 *     public string $serviceId;
 *
 *     #[Assert\Positive]
 *     public int $durationMin;
 * }
 *
 * // In controller:
 * $dto = CreateBookingDTO::fromRequest($request, $validator);
 * // Automatically validated, throws ValidationFailedException on error
 * ```
 */
abstract class AbstractRequestDTO
{
    /**
     * Create DTO from HTTP Request and validate
     *
     * @param Request $request HTTP request containing JSON body
     * @param ValidatorInterface $validator Symfony validator
     * @param array $groups Validation groups to apply
     * @return static Validated DTO instance
     * @throws ValidationFailedException If validation fails
     */
    public static function fromRequest(
        Request $request,
        ValidatorInterface $validator,
        array $groups = []
    ): static {
        $data = json_decode($request->getContent(), true) ?? [];
        return static::fromArray($data, $validator, $groups);
    }

    /**
     * Create DTO from array and validate
     *
     * @param array $data Input data
     * @param ValidatorInterface $validator Symfony validator
     * @param array $groups Validation groups to apply
     * @return static Validated DTO instance
     * @throws ValidationFailedException If validation fails
     */
    public static function fromArray(
        array $data,
        ValidatorInterface $validator,
        array $groups = []
    ): static {
        $dto = new static();
        $dto->populate($data);
        $dto->validate($validator, $groups);
        return $dto;
    }

    /**
     * Populate DTO properties from array
     *
     * Uses reflection to set public properties.
     * Handles type conversion for common types (int, float, bool, array).
     *
     * @param array $data Input data
     */
    protected function populate(array $data): void
    {
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $data)) {
                continue;
            }

            $value = $data[$propertyName];

            // Handle null values
            if ($value === null && $property->getType()?->allowsNull()) {
                $property->setValue($this, null);
                continue;
            }

            // Type conversion based on property type
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType) {
                $value = $this->convertType($value, $type->getName());
            }

            $property->setValue($this, $value);
        }
    }

    /**
     * Convert value to target type
     */
    private function convertType(mixed $value, string $targetType): mixed
    {
        return match ($targetType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Validate DTO properties
     *
     * @throws ValidationFailedException If validation fails
     */
    protected function validate(ValidatorInterface $validator, array $groups = []): void
    {
        $violations = $validator->validate($this, groups: $groups ?: null);

        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }

    /**
     * Convert DTO to array (useful for logging, debugging)
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $data = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }
}
