<?php

namespace App\Enums;

enum ChapterStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case UnderReview = 'under_review';
    case NeedsRevision = 'needs_revision';
    case Approved = 'approved';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::InProgress => 'In Progress',
            self::PendingReview => 'Pending Review',
            self::UnderReview => 'Under Review',
            self::NeedsRevision => 'Needs Revision',
            self::Approved => 'Approved',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'bg-gray-500/10 text-gray-700',
            self::InProgress => 'bg-blue-500/10 text-blue-700',
            self::PendingReview => 'bg-yellow-500/10 text-yellow-700',
            self::UnderReview => 'bg-purple-500/10 text-purple-700',
            self::NeedsRevision => 'bg-orange-500/10 text-orange-700',
            self::Approved => 'bg-green-500/10 text-green-700',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}