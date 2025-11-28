<?php

namespace App\Enums;

enum ChapterStatus: string
{
    case NotStarted = 'not_started';
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Draft => 'Draft',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-gray-500/10 text-gray-700',
            self::Draft => 'bg-blue-500/10 text-blue-700',
            self::InReview => 'bg-yellow-500/10 text-yellow-700',
            self::Approved => 'bg-green-500/10 text-green-700',
            self::Completed => 'bg-green-500/10 text-green-700',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->toArray();
    }
}
