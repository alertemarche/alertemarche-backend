<?php

namespace App\Jobs;

use App\Models\ArtisanNeed;
use App\Models\User;
use App\Services\AlertDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNeedAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId, public int $needId)
    {
        $this->onQueue('notifications');
    }

    public function handle(AlertDispatcher $dispatcher): void
    {
        $user = User::find($this->userId);
        $need = ArtisanNeed::find($this->needId);
        if ($user && $need) {
            $dispatcher->dispatchNeed($user, $need);
        }
    }
}
