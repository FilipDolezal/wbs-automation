<?php

namespace App\Common\ExcelParser\WorksheetTableParser\Structures;

use App\Common\ExcelParser\ExcelParserError;
use App\Common\ExcelParser\WorksheetTableParser\WorksheetTableStructure;
use ParseError;

class WbsDevelopmentStructure implements WorksheetTableStructure
{
    public const string SHEET_NAME = 'WBS - vývoj';

    /** @var string Název úkolu */
    public const string COLUMN_TASK_NAME = 'A';

    /** @var string INITIATIVE */
    public const string COLUMN_INITIATIVE = 'B';

    /** @var string EPIC */
    public const string COLUMN_EPIC = 'C';

    /** @var string RM ID */
    public const string COLUMN_REDMINE_ID = 'D';

    /** @var string Komplexita / riziko */
    public const string COLUMN_COMPLEXITY_RISK = 'E';

    /** @var string Odhad vývojář (h) */
    public const string COLUMN_ESTIMATED_DEV_HOURS = 'F';

    /** @var string Režie na dodání úpravy (h) */
    public const string COLUMN_OVERHEAD_HOURS = 'H';

    /** @var string Odhad celkem (h) */
    public const string COLUMN_ESTIMATED_TOTAL_HOURS = 'J';

    /** @var string Odhad celkem final (h) */
    public const string COLUMN_ESTIMATED_FINAL_HOURS = 'K';

    /** @var string Popis */
    public const string COLUMN_DESCRIPTION = 'L';

    /** @var string Akceptační kritéria */
    public const string COLUMN_ACCEPTANCE_CRITERIA = 'M';

    public const array COLUMNS = [
        self::COLUMN_TASK_NAME,
        self::COLUMN_INITIATIVE,
        self::COLUMN_EPIC,
        self::COLUMN_REDMINE_ID,
        self::COLUMN_COMPLEXITY_RISK,
        self::COLUMN_ESTIMATED_DEV_HOURS,
        self::COLUMN_OVERHEAD_HOURS,
        self::COLUMN_ESTIMATED_TOTAL_HOURS,
        self::COLUMN_ESTIMATED_FINAL_HOURS,
        self::COLUMN_DESCRIPTION,
        self::COLUMN_ACCEPTANCE_CRITERIA
    ];

    public const array VALIDATORS = [
        self::COLUMN_TASK_NAME => [[self::class, 'validateNotNull']],
    ];

    public function getSheetName(): string
    {
        return self::SHEET_NAME;
    }

    public function getColumns(): array
    {
        return self::COLUMNS;
    }

    public function validate(mixed $value, string $column): ?ExcelParserError
    {
        foreach (self::VALIDATORS[$column] ?? [] as $validator)
        {
            $error = $validator($value);

            if ($error !== null)
            {
                return $error;
            }
        }

        return null;
    }

    private static function validateNotNull(mixed $value): ?ExcelParserError
    {
        return $value === null ? ExcelParserError::UnexpectedNull : null;
    }
}
