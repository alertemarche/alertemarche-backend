<?php

namespace App\Jobs;

use App\Models\Tender;
use App\Models\User;
use App\Services\AlertDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTenderAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'notifications';

    public function __construct(public int $userId, public int $tenderId) {}

    public function handle(AlertDispatcher $dispatcher): void
    {
        $user = User::find($this->userId);
        $tender = Tender::find($this->tenderId);
        if ($user && $tender) {
            $dispatcher->dispatchTender($user, $tender);
        }
    }
}
