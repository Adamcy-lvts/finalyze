<?php

namespace App\Enums;

enum ProjectTopicStatus: string
{
    case NotStarted = 'not_started';
    case TopicSelection = 'topic_selection';
    case TopicPendingApproval = 'topic_pending_approval';
    case TopicApproved = 'topic_approved';
    case TopicRejected = 'topic_rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::TopicSelection => 'Topic Selection',
            self::TopicPendingApproval => 'Pending Approval',
            self::TopicApproved => 'Topic Approved',
            self::TopicRejected => 'Topic Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-gray-500/10 text-gray-700',
            self::TopicSelection => 'bg-blue-500/10 text-blue-700',
            self::TopicPendingApproval => 'bg-yellow-500/10 text-yellow-700',
            self::TopicApproved => 'bg-green-500/10 text-green-700',
            self::TopicRejected => 'bg-red-500/10 text-red-700',
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
