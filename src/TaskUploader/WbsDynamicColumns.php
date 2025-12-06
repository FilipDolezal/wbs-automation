<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\DynamicColumns;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;

/**
 * @phpstan-import-type Attributes from DynamicColumns
 * @extends DynamicColumns<WbsDynamicColumn>
 */
readonly class WbsDynamicColumns extends DynamicColumns
{
    public const string ID_TASK_NAME = 'taskName';
    public const string ID_INITIATIVE = 'initiative';
    public const string ID_EPIC = 'epic';
    public const string ID_REDMINE_ID = 'redmineId';
    private const array IDENTIFIERS = [self::ID_TASK_NAME, self::ID_INITIATIVE, self::ID_EPIC, self::ID_REDMINE_ID];

    /** @var array<string, string> $fields */
    private array $fields;

    /**
     * @param array<string, Attributes> $columnDefinition
     * @param array<string, string> $columnIdentifiers
     * @throws ExcelParserDefinitionException
     */
    public function __construct(array $columnDefinition, private array $columnIdentifiers)
    {
        parent::__construct($columnDefinition);

        foreach (self::IDENTIFIERS as $identifier)
        {
            if (!isset($columnIdentifiers[$identifier]))
            {
                throw new ExcelParserDefinitionException("You must define column for identifier [$identifier].");
            }
        }

        $fields = [];

        foreach ($this->columns as $column => $definition)
        {
            if (!empty($definition->field))
            {
                $fields[$definition->field] = $column;
            }
        }

        $this->fields = $fields;
    }

    public function getCustomFields(): array
    {
        return array_filter($this->columns, static fn (WbsDynamicColumn $c) => $c !== null && $c->custom);
    }

    public function getColumnByIdentifier(string $identifier): ?string
    {
        return $this->columnIdentifiers[$identifier] ?? null;
    }

    /**
     * @throws ExcelParserDefinitionException
     */
    public function getColumn(string $field): string
    {
        if (!isset($this->fields[$field]))
        {
            throw new ExcelParserDefinitionException("Field [$field] not defined.");
        }

        return $this->get($this->fields[$field])->column;
    }

    public static function defineColumn(string $column, array $attributes): WbsDynamicColumn
    {
        $dynamicColumn = parent::defineColumn($column, $attributes);

        $field = $attributes['field'] ?? null;
        $customField = $attributes['custom_field'] ?? null;

        $wbsDynamicColumn = new WbsDynamicColumn();
        $wbsDynamicColumn->column = $dynamicColumn->column;
        $wbsDynamicColumn->type = $dynamicColumn->type;
        $wbsDynamicColumn->nullable = $dynamicColumn->nullable;
        $wbsDynamicColumn->calculated = $dynamicColumn->calculated;
        $wbsDynamicColumn->field = $field ?? $customField;
        $wbsDynamicColumn->custom = $customField !== null;

        return $wbsDynamicColumn;
    }
}