<?php

namespace App\Common\ExcelParser;

use App\Common\ExcelParser\Attribute\ExcelColumn;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

trait ExcelMappingTrait
{
    /**
     * @return array<string, array{property: ReflectionProperty, type: string, nullable: bool, calculated: bool}>
     */
    public static function getMapping(): array
    {
        $mapping = [];
        $rc = new ReflectionClass(static::class);

        foreach ($rc->getProperties() as $property)
        {
            $attributes = $property->getAttributes(ExcelColumn::class);
            if (empty($attributes))
            {
                continue;
            }

            /** @var ExcelColumn $attr */
            $attr = $attributes[0]->newInstance();

            $type = $property->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'string';

            $mapping[$attr->col] = [
                'property' => $property,
                'type' => $typeName,
                'nullable' => !$type || $type->allowsNull(),
                'calculated' => $attr->calculated,
            ];
        }

        return $mapping;
    }

    public function afterConstruct(): void
    {
        // Default empty implementation
    }
}
