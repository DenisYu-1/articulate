<?php

namespace Articulate\Utils;

use BackedEnum;
use InvalidArgumentException;
use ReflectionEnum;
use UnitEnum;

/**
 * Converts PHP enums to/from their database scalar representation.
 *
 * Backed enums persist their backing value (int or string). Pure (unit) enums
 * persist the case name as a string. Bound to a single enum class so it can
 * reconstruct instances coming back from the database.
 */
class EnumTypeConverter implements TypeConverterInterface {
    /** @param class-string<UnitEnum> $enumClass */
    public function __construct(private readonly string $enumClass)
    {
        if (!enum_exists($this->enumClass)) {
            throw new InvalidArgumentException("Not an enum: {$this->enumClass}");
        }
    }

    public function convertToDatabase(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    public function convertToPHP(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $value;
        }

        if (is_subclass_of($this->enumClass, BackedEnum::class)) {
            // DB drivers may hand back int backing values as numeric strings — cast
            // to the declared backing type so ::from() matches.
            $backingType = (string) (new ReflectionEnum($this->enumClass))->getBackingType();
            $scalar = $backingType === 'int' ? (int) $value : (string) $value;

            /** @var class-string<BackedEnum> $enumClass */
            $enumClass = $this->enumClass;

            return $enumClass::from($scalar);
        }

        foreach (($this->enumClass)::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Unknown enum case '{$value}' for {$this->enumClass}");
    }
}
