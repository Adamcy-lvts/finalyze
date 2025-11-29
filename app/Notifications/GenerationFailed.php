<?php

namespace App\Notifications;

use App\Models\ProjectGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenerationFailed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ProjectGeneration $generation,
        public string $errorMessage,
        public ?string $stage = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->generation->project;
        $stageInfo = $this->stage ? " during {$this->stage}" : '';

        return (new MailMessage)
            ->error()
            ->subject("Project Generation Failed: {$project->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your project generation has failed{$stageInfo}.")
            ->line("**Project:** {$project->title}")
            ->line("**Progress:** {$this->generation->progress}%")
            ->line("**Error:** {$this->errorMessage}")
            ->action('View Project', route('projects.bulk-generation', $project->slug))
            ->line('You can resume the generation from where it left off.')
            ->line('If the problem persists, please contact support.');
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $project = $this->generation->project;

        return [
            'type' => 'generation_failed',
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'project_title' => $project->title,
            'generation_id' => $this->generation->id,
            'progress' => $this->generation->progress,
            'stage' => $this->stage,
            'error_message' => $this->errorMessage,
            'can_resume' => $this->generation->progress > 0,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
