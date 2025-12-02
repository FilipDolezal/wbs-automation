<?php

namespace App\TaskUploader\Parser;

class WbsStructure
{
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

    public const string SHEET_NAME = 'WBS - vývoj';

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
}
