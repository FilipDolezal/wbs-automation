<?php declare(strict_types=1);

namespace App\TaskUploader\Redmine;

enum EParentType: string
{
    case INITIATIVE = 'initiative';
    case EPIC = 'epic';

    public function getIssuePrefix(): string
    {
        return match ($this)
        {
            self::INITIATIVE => '[INITIATIVE]',
            self::EPIC => '[EPIC]',
        };
    }
}
