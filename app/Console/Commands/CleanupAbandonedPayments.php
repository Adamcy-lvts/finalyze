<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:cleanup-abandoned
                            {--minutes=30 : Number of minutes before marking payment as abandoned}
                            {--dry-run : Show what would be cleaned up without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark pending payments as abandoned if they have been pending for too long';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = $this->option('dry-run');

        $this->info("Looking for payments pending for more than {$minutes} minutes...");

        $cutoffTime = now()->subMinutes($minutes);

        $query = Payment::where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<', $cutoffTime);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No abandoned payments found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$count} abandoned payment(s).");

        if ($dryRun) {
            $this->table(
                ['ID', 'User ID', 'Amount (NGN)', 'Reference', 'Created At'],
                $query->get()->map(fn ($p) => [
                    $p->id,
                    $p->user_id,
                    number_format($p->amount / 100, 2),
                    $p->paystack_reference,
                    $p->created_at->format('Y-m-d H:i:s'),
                ])
            );

            $this->info('Dry run complete. No payments were modified.');

            return self::SUCCESS;
        }

        if ($this->confirm("Mark {$count} payment(s) as abandoned?", true)) {
            $updated = $query->update(['status' => Payment::STATUS_ABANDONED]);

            $this->info("Successfully marked {$updated} payment(s) as abandoned.");

            Log::info('Abandoned payments cleanup completed', [
                'count' => $updated,
                'minutes_threshold' => $minutes,
            ]);

            return self::SUCCESS;
        }

        $this->warn('Cleanup cancelled by user.');

        return self::FAILURE;
    }
}
