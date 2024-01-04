<?php

namespace Vcian\Pulse\PulseDockerMonitor\Recorders;

use Exception;
use Illuminate\Config\Repository;
use JetBrains\PhpStorm\Pure;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Ignores;
use Laravel\Pulse\Recorders\Concerns\Sampling;
use Laravel\Pulse\Recorders\Concerns\Thresholds;
use RuntimeException;
use function Pest\Expectations\json;

class PulseDockerMonitorRecorder
{
    use Ignores, Sampling, Thresholds;

    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = SharedBeat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config
    )
    {
        //
    }

    /**
     * @param SharedBeat $event
     */
    public function record(SharedBeat $event): void
    {
        if ($event->time->second % 1 !== 0) {
            return;
        }

        $allContainers = match (PHP_OS_FAMILY) {
            'Darwin' => `docker ps -a --format '{"name": "{{.Names}}", "status": "{{.Status}}", "ports": "{{.Ports}}", "id": "{{.ID}}"}'`,
            'Linux' => `docker ps -a --format '{"name": "{{.Names}}", "status": "{{.Status}}", "ports": "{{.Ports}}", "id": "{{.ID}}"}'`,
            'Windows' => `docker ps -a --format '{"name": "{{.Names}}", "status": "{{.Status}}", "ports": "{{.Ports}}", "id": "{{.ID}}"}'`,
            default => throw new RuntimeException('The pulse:check command does not currently support ' . PHP_OS_FAMILY),
        };

        $allContainers = '[' . rtrim(str_replace("\n", ",", $allContainers), ',') . ']';

        $containerStat = match (PHP_OS_FAMILY) {
            'Darwin' => `docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemPerc}}"`,
            'Linux' => `docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemPerc}}"`,
            'Windows' => `docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemPerc}}"`,
            default => throw new RuntimeException('The pulse:check command does not currently support ' . PHP_OS_FAMILY),
        };

        $containerStat = $this->filterStringToArray($containerStat);
        $allContainers = $this->filterContainers($allContainers,$containerStat);

        if ($containerStat) {
            foreach ($containerStat as $key => $stat) {
                $this->pulse->record('docker_memory', $stat['Name'], $stat['MEM'], $event->time)->avg()->onlyBuckets();
                $this->pulse->record('docker_cpu', $stat['Name'], $stat['CPU'], $event->time)->avg()->onlyBuckets();
            }
        }

        $this->pulse->set('docker_monitor', 'result', $allContainers, $event->time);

    }


    /**
     *
     * @param $allContainers
     * @param $containerStat
     */
    protected function filterContainers($allContainers, $containerStat)
    {
        $filteredContainers = [];

        if ($allContainers) {
            $decodedContainers = json_decode($allContainers, true);
            $containerStat = array_column($containerStat, NULL, 'Name'); // Set id as array key
            if ($decodedContainers !== null) {
                foreach ($decodedContainers as $key => $container) {
                    $container['cpu'] = $containerStat[$container['name']]['CPU'] ?? 0;
                    $container['memory'] = $containerStat[$container['name']]['MEM'] ?? 0;
                    if (isset($container['ports'])) {
                        preg_match_all('/\d+\/tcp/', $container['ports'], $matches);
                        $container['ports'] = implode(', ', array_unique($matches[0]));
                        $filteredContainers[] = $container;
                    }
                }
            }
        }

        return json_encode($filteredContainers);
    }

    /**
     * @param string|null $string
     * @return array
     */
    protected function filterStringToArray(?string $string = null): array
    {
        if (empty($string)) {
            return [];
        }

        // Split the input string into an array of lines
        $lines = array_filter(explode("\n", $string));

        // Process each line (skip the header)
        $containerArray = array_map(function ($line) {
            // Split each line into columns
            $columns = preg_split('/\s+/', trim($line));

            // Extract container information
            return [
                'Name' => $columns[0],
                'CPU' => $this->percentageToDecimal($columns[1]),
                'MEM' => $this->percentageToDecimal($columns[2]),
            ];
        }, array_slice($lines, 1)); // Skip the header

        return $containerArray;
    }

    /**
     * @param $percentage
     * @return string
     */
    #[Pure] protected function percentageToDecimal($percentage): string
    {
        // Remove the percentage sign and trim any whitespace
        return trim($percentage, '%');
    }
}
