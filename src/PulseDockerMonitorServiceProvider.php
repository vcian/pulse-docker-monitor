<?php

namespace Vcian\Pulse\PulseDockerMonitor;


use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;
use Vcian\Pulse\PulseDockerMonitor\Livewire\PulseDockerMonitor;

class PulseDockerMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'pulse_docker_monitor');

        $this->callAfterResolving('livewire', function (LivewireManager $livewire, Application $app) {
            $livewire->component('pulse_docker_monitor', PulseDockerMonitor::class);
        });
    }
}
