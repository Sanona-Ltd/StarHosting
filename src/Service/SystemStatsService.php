<?php

namespace App\Service;

class SystemStatsService
{
    public function getStats(): array
    {
        return [
            'cpu' => $this->getCpuUsage(),
            'ram' => $this->getRamUsage(),
            'disk' => $this->getDiskUsage(),
        ];
    }

    private function getCpuUsage(): array
    {
        $load = sys_getloadavg();
        $cpuCount = (int) shell_exec('nproc 2>/dev/null') ?: 1;
        $usage = round(($load[0] / $cpuCount) * 100, 1);

        return ['usage' => min($usage, 100), 'load' => $load[0]];
    }

    private function getRamUsage(): array
    {
        if (!is_readable('/proc/meminfo')) {
            return ['total' => 0, 'used' => 0, 'percent' => 0];
        }

        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

        $totalKb = (int) ($total[1] ?? 0);
        $availableKb = (int) ($available[1] ?? 0);
        $usedKb = $totalKb - $availableKb;

        return [
            'total' => round($totalKb / 1024 / 1024, 2),
            'used' => round($usedKb / 1024 / 1024, 2),
            'percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0,
        ];
    }

    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');

        if ($total === false || $free === false) {
            return ['total' => 0, 'used' => 0, 'percent' => 0];
        }

        $used = $total - $free;

        return [
            'total' => round($total / 1024 / 1024 / 1024, 2),
            'used' => round($used / 1024 / 1024 / 1024, 2),
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }
}
