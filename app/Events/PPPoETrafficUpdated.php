<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PPPoETrafficUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customerId;
    public $trafficData;

    /**
     * Create a new event instance.
     */
    public function __construct($customerId, array $trafficData)
    {
        $this->customerId = $customerId;
        $this->trafficData = $trafficData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('pppoe-traffic-updates'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'pppoe.traffic.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customerId,
            'traffic' => $this->trafficData,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

