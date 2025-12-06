<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\ColumnDefinition;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;

/**
 * Extends ColumnDefinition to support WBS-specific logic.
 *
 * This class validates the presence of required identifier columns (Task Name, Initiative, etc.)
 * and maps Excel columns to specific Redmine fields (standard or custom).
 *
 * @phpstan-import-type Attributes from ColumnDefinition
 * @extends ColumnDefinition<WbsDynamicColumn>
 */
readonly class WbsColumnDefinition extends ColumnDefinition
{
    public const string ID_TASK_NAME = 'taskName';
    public const string ID_INITIATIVE = 'initiative';
    public const string ID_EPIC = 'epic';
    public const string ID_REDMINE_ID = 'redmineId';
    private const array IDENTIFIERS = [self::ID_TASK_NAME, self::ID_INITIATIVE, self::ID_EPIC, self::ID_REDMINE_ID];

    /** @var array<string, string> $fields Map of Field Name => Column Letter */
    private array $fields;

    /**
     * @param array<string, Attributes> $columnDefinition Raw definition from config.
     * @param array<string, string> $columnIdentifiers Map of logical ID => Column Letter.
     * @throws ExcelParserDefinitionException If required identifiers are missing.
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

    /**
     * Returns all columns defined as Custom Fields.
     *
     * @return array<string, WbsDynamicColumn>
     */
    public function getCustomFields(): array
    {
        return array_filter($this->columns, static fn (WbsDynamicColumn $c) => $c !== null && $c->custom);
    }

    /**
     * Retrieves the column letter associated with a specific logical identifier.
     *
     * @param string $identifier One of the ID_* constants.
     * @return string|null The column letter or null if not found.
     */
    public function getColumnByIdentifier(string $identifier): ?string
    {
        return $this->columnIdentifiers[$identifier] ?? null;
    }

    /**
     * Retrieves the column letter mapped to a specific Redmine field name.
     *
     * @param string $field The field name (e.g., 'subject', 'description').
     * @return string The column letter.
     * @throws ExcelParserDefinitionException If the field is not mapped.
     */
    public function getField(string $field): string
    {
        if (!isset($this->fields[$field]))
        {
            throw new ExcelParserDefinitionException("Field [$field] not defined.");
        }

        return $this->fields[$field];
    }

    /**
     * Factory method to create WbsDynamicColumn instances.
     *
     * Handles WBS-specific attributes like 'field' and 'custom_field'.
     *
     * @inheritDoc
     * @return WbsDynamicColumn
     * @throws ExcelParserDefinitionException
     */
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