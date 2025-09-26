<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Setup = 'setup';
    case Planning = 'planning';
    case TopicSelection = 'topic_selection';
    case TopicPendingApproval = 'topic_pending_approval';
    case TopicApproved = 'topic_approved';
    case Guidance = 'guidance';
    case Writing = 'writing';
    case Review = 'review';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Setup => 'Setup',
            self::Planning => 'Planning',
            self::TopicSelection => 'Topic Selection',
            self::TopicPendingApproval => 'Topic Pending Approval',
            self::TopicApproved => 'Topic Approved',
            self::Guidance => 'Guidance',
            self::Writing => 'Writing',
            self::Review => 'Under Review',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-500/10 text-gray-700',
            self::Setup => 'bg-blue-500/10 text-blue-700',
            self::Planning => 'bg-purple-500/10 text-purple-700',
            self::TopicSelection => 'bg-indigo-500/10 text-indigo-700',
            self::TopicPendingApproval => 'bg-yellow-500/10 text-yellow-700',
            self::TopicApproved => 'bg-emerald-500/10 text-emerald-700',
            self::Guidance => 'bg-purple-500/10 text-purple-700',
            self::Writing => 'bg-blue-500/10 text-blue-700',
            self::Review => 'bg-yellow-500/10 text-yellow-700',
            self::Completed => 'bg-green-500/10 text-green-700',
            self::OnHold => 'bg-orange-500/10 text-orange-700',
            self::Archived => 'bg-gray-500/10 text-gray-700',
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
