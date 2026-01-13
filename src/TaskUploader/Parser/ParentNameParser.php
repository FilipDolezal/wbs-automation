<?php

namespace App\TaskUploader\Parser;

/**
 * Parses and formats parent issue names with embedded Redmine IDs.
 *
 * Format: "[12345] Initiative Name"
 * - [12345] is the Redmine issue ID (any number of digits)
 * - Optional whitespace after the bracket
 * - Remaining text is the name
 */
class ParentNameParser
{
    private const string PATTERN = '/^\[(\d+)]\s*(.*)$/';

    /**
     * Parses an input string for a Redmine ID prefix.
     *
     * @param string $input The raw input string (e.g., "[12345] Initiative Name" or "Initiative Name")
     * @return ParsedParentName Contains the parsed ID (if found) and the name
     */
    public static function parse(string $input): ParsedParentName
    {
        $input = trim($input);

        if (preg_match(self::PATTERN, $input, $matches))
        {
            return new ParsedParentName(
                redmineId: (int) $matches[1],
                name: $matches[2] !== '' ? $matches[2] : null
            );
        }

        return new ParsedParentName(redmineId: null, name: $input);
    }

    /**
     * Formats a Redmine ID and name into the standard format.
     *
     * @param int $redmineId The Redmine issue ID
     * @param string $name The issue name
     * @return string Formatted as "[12345] Name"
     */
    public static function format(int $redmineId, string $name): string
    {
        return "[$redmineId] $name";
    }
}
