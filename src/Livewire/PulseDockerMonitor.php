<?php

namespace Vcian\Pulse\PulseDockerMonitor\Livewire;

use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Livewire\Attributes\Lazy;
use Livewire\Livewire;

class PulseDockerMonitor extends Card
{
    use HasPeriod, RemembersQueries;

    #[Lazy]
    public function render()
    {

        $containers = Pulse::values('docker_monitor', ['result'])->first();

        $containers = $containers
            ? json_decode($containers->value, associative: true, flags: JSON_THROW_ON_ERROR)
            : [];

        $containers = $this->filterContainers($containers);
        $containers = collect($containers)->take(10);

        [$graph, $time, $runAt] = $this->remember(fn () => Pulse::graph(
            ['docker_memory','docker_cpu'],
            'avg',
            $this->periodAsInterval(),
        ));

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('container-chart-update', graph: $graph);
        }

        return View::make('pulse_docker_monitor::livewire.pulse_docker_monitor', [
            'allContainers' => $containers,
            'time' => $time,
            'runAt' => $runAt,
            'graph' => $graph
        ]);
    }

    /**
     * @param array $containers
     * @return array
     */
    protected function filterContainers($containers = []): array
    {
        $returnArr = [];

        if ($containers) {
            foreach ($containers as $cnt) {
                if (str_contains($cnt['status'], "Exited") || str_contains($cnt['status'], "exited")) {
                    $cnt['state'] = "Exited";
                    $cnt['status'] = str_replace("Exited (0) ", "", $cnt['status']);
                    $cnt['ports'] = "-";
                } else {
                    $cnt['state'] = "Running";
                }

                $returnArr[] = $cnt;
            }
        }

        return $returnArr;
    }
}
