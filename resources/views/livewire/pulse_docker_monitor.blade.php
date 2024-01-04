<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Docker Monitor"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <x-pulse_docker_monitor::icons.docker/>
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if($allContainers->count() <= 0)
            <x-pulse::no-results/>
        @else
            <div class="flex flex-col gap-6">
                <div>
                    <x-pulse::table>
                        <colgroup>
                            <col width="15%"/>
                            <col width="0%"/>
                            <col width="0%"/>
                            <col width="40%"/>
                            <col width="40%"/>
                        </colgroup>
                        <x-pulse::thead>
                            <tr>
                                <x-pulse::th class="text-base font-bold text-gray-600 dark:text-gray-300">Name
                                </x-pulse::th>
                                <x-pulse::th class="text-base font-bold text-gray-600 dark:text-gray-300">Status
                                </x-pulse::th>
                                <x-pulse::th class="text-base font-bold text-gray-600 dark:text-gray-300">Port
                                </x-pulse::th>
                                <x-pulse::th class="text-base font-bold text-gray-600 dark:text-gray-300">Cpu
                                </x-pulse::th>
                                <x-pulse::th class="text-base font-bold text-gray-600 dark:text-gray-300">Memory
                                </x-pulse::th>
                            </tr>
                        </x-pulse::thead>
                        <tbody>
                        @foreach ($allContainers as $key => $container)
                            <tr wire:key="{{ $loop->index }}-spacer" class="h-2 first:h-0"></tr>
                            <tr wire:key="{{ $loop->index }}-row">
                                <x-pulse::td>
                                    <code class="text-gray-700 dark:text-gray-300 font-bold truncate"
                                          title="{{ $container['name'] }}">
                                        {{ $container['name'] }}
                                        <p class="text-xs text-gray-400 dark:text-gray-600 font-medium truncate">{{ $container['id'] }}</p>
                                    </code>
                                </x-pulse::td>
                                <x-pulse::td class="text-gray-700 dark:text-gray-300 font-bold">
                                    {{ $container['state'] }}
                                    <p class="text-xs text-gray-400 dark:text-gray-600 font-medium truncate">{{ $container['status'] }}</p>
                                </x-pulse::td>
                                <x-pulse::td class="text-gray-700 dark:text-gray-300 font-bold">
                                    {{ $container['ports'] }}
                                </x-pulse::td>
                                <x-pulse::td>
                                    <div class="mt-3 relative flex">
                                        <div wire:key="docker-cpu" class="">
                                            <div
                                                class="text-xl font-bold text-gray-700 dark:text-gray-200 w-14 whitespace-nowrap tabular-nums">
                                                {{ round($container['cpu']) ?? 0 }}%
                                            </div>
                                        </div>
                                        <div wire:ignore class="h-14 w-full"
                                             x-data="cpuChart({
                                                                                        graph: '{{ $graph }}',
                                                                                        readings: @js($graph),
                                                                                        sampleRate: 1,
                                                                                        containerName: '{{ $container['name'] }}',
                                                                                        containerIndex: {{ $loop->index }},
                                                                                })">
                                            <canvas x-ref="canvas"
                                                    class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                                        </div>
                                    </div>
                                </x-pulse::td>
                                <x-pulse::td>
                                    <div class="mt-3 relative flex">
                                        <div wire:key="docker-memory" class="  ">
                                            <div
                                                class="text-xl font-bold text-gray-700 dark:text-gray-200 w-14 whitespace-nowrap tabular-nums">
                                                {{ round($container['memory']) ?? 0 }}%
                                            </div>
                                        </div>
                                        <div wire:ignore class="h-14 w-full"
                                             x-data="memoryChart({
                                                                                        graph: '{{ $graph }}',
                                                                                        readings: @js($graph),
                                                                                        sampleRate: 1,
                                                                                        containerName: '{{ $container['name'] }}',
                                                                                        containerIndex: {{ $loop->index }},
                                                                                })">
                                            <canvas x-ref="canvas"
                                                    class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                                        </div>
                                    </div>
                                </x-pulse::td>
                            </tr>
                        @endforeach
                        </tbody>
                    </x-pulse::table>
                </div>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>

@script
<script>
    const chartConfig = {
        maintainAspectRatio: false,
        layout: {
            autoPadding: false,
            padding: {
                top: 1,
            },
        },
        datasets: {
            line: {
                borderWidth: 2,
                borderCapStyle: 'round',
                pointHitRadius: 10,
                pointStyle: false,
                tension: 0.2,
                spanGaps: false,
                segment: {
                    borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                },
            },
        },
        scales: {
            x: {
                display: false,
            },
            y: {
                display: false,
                min: 0,
                max: 1, // Adjust the max value as needed
            },
        },
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                mode: 'index',
                position: 'nearest',
                intersect: false,
                callbacks: {
                    beforeBody: (context) => context
                        .map(item => `${item.dataset.label}: ${1 < 1 ? '~' : ''}${item.formattedValue}%`)
                        .join(', '),
                    label: () => null,
                },
            },
        },
    };

    function createChart(type, label, color, data, options) {
        return new Chart(
            this.$refs.canvas,
            {
                type: type,
                data: {
                    labels: this.labels(data),
                    datasets: [
                        {
                            label: label,
                            borderColor: color,
                            data: this.scale(data),
                            order: 1,
                        },
                    ],
                },
                options: {...chartConfig, ...options},
            }
        );
    }

    function updateChart(chart, graph, containerName, dataKey) {
        if (chart === undefined) {
            return;
        }

        if (graph === undefined && chart) {
            chart.destroy();
            chart = undefined;
            return;
        }

        chart.data.labels = this.labels(graph[containerName]);
        chart.options.scales.y.max = this.highest(graph[containerName]);
        chart.data.datasets[0].data = this.scale(graph[containerName][dataKey]);
        chart.update();
    }

    Alpine.data('cpuChart', (config) => ({
        containerName: config.containerName,
        containerIndex: config.containerIndex,
        init() {
            let chart = createChart.call(this, 'line', 'CPU', '#d5e80d', config.readings[config.containerName].docker_cpu);

            Livewire.on('container-chart-update', ({graph}) => {
                updateChart.call(this, chart, graph, config.containerName, 'docker_cpu');
            });
        },
        labels(readings) {
            return Object.keys(readings);
        },
        scale(data) {
            return data;
        },
        highest(readings) {
            return Math.max(...Object.values(readings).map(dataset => Math.max(...Object.values(dataset)))) * (1 / 1);
        },
    }));

    Alpine.data('memoryChart', (config) => ({
        containerName: config.containerName,
        containerIndex: config.containerIndex,
        init() {
            let memoryChart = createChart.call(this, 'line', 'MEMORY', '#31D70D', config.readings[config.containerName].docker_memory);

            Livewire.on('container-chart-update', ({graph}) => {
                updateChart.call(this, memoryChart, graph, config.containerName, 'docker_memory');
            });
        },
        labels(readings) {
            return Object.keys(readings);
        },
        scale(data) {
            return data;
        },
        highest(readings) {
            return Math.max(...Object.values(readings).map(dataset => Math.max(...Object.values(dataset)))) * (1 / 1);
        },
    }));

</script>
@endscript

