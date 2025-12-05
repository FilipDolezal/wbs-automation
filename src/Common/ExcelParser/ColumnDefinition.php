<?php

namespace App\Common\ExcelParser;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

class ColumnDefinition
{
    private function __construct(
        public ReflectionProperty $property,
        public string $col,
        public string $type,
        public bool $nullable,
        public bool $calculated
    ) {}

    /**
     * @param class-string $className
     * @return array<string, self>
     * @throws ReflectionException
     */
    public static function fromEntity(string $className): array
    {
        $definitions = [];
        $rc = new ReflectionClass($className);

        foreach ($rc->getProperties() as $property) {
            $attributes = $property->getAttributes(ExcelColumn::class);
            if (empty($attributes)) {
                continue;
            }

            /** @var ExcelColumn $attr */
            $attr = $attributes[0]->newInstance();

            $type = $property->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'string';

            $definitions[$attr->col] = new self(
                $property,
                $attr->col,
                $typeName,
                !$type || $type->allowsNull(),
                $attr->calculated
            );
        }

        return $definitions;
    }
}
