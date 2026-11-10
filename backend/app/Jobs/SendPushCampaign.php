<?php

namespace App\Jobs;

use App\Models\PushCampaign;
use App\Services\PushCampaignSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushCampaign implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $campaignId) {}

    public function handle(PushCampaignSender $sender): void
    {
        $campaign = PushCampaign::query()->find($this->campaignId);
        if ($campaign && $campaign->status === 'scheduled') {
            $sender->send($campaign);
        }
    }
}
