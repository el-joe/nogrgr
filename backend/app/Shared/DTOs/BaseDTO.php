<?php

namespace App\Shared\DTOs;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Immutable data carrier between Http (FormRequest) and Services.
 *
 * Subclasses declare promoted readonly constructor properties in camelCase;
 * array/request keys may be snake_case or camelCase. Scalar values are cast to
 * backed enums automatically when the property is enum typed.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class BaseDTO implements Arrayable
{
    public static function fromRequest(FormRequest $request): static
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return static::fromArray($validated);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        $arguments = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $snake = Str::snake($name);

            if (array_key_exists($name, $data)) {
                $value = $data[$name];
            } elseif (array_key_exists($snake, $data)) {
                $value = $data[$snake];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[$name] = $parameter->getDefaultValue();

                continue;
            } elseif ($parameter->allowsNull()) {
                $arguments[$name] = null;

                continue;
            } else {
                throw new InvalidArgumentException(sprintf('Missing [%s] for %s.', $snake, static::class));
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType
                && ! $type->isBuiltin()
                && is_subclass_of($type->getName(), BackedEnum::class)
                && (is_int($value) || is_string($value))) {
                /** @var class-string<BackedEnum> $enum */
                $enum = $type->getName();
                $value = $enum::from($value);
            }

            $arguments[$name] = $value;
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * Public properties as a snake_case array (enums → values, nested DTOs → arrays).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($this);

            $result[Str::snake($property->getName())] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof Arrayable => $value->toArray(),
                default => $value,
            };
        }

        return $result;
    }

    /**
     * New instance with some values replaced.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): static
    {
        $current = [];

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $current[$property->getName()] = $property->getValue($this);
        }

        foreach ($changes as $key => $value) {
            $current[Str::camel($key)] = $value;
        }

        return static::fromArray($current);
    }
}
