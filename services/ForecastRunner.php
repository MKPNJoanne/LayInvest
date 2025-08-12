<?php
namespace app\services;

final class ForecastRunner
{
    public function run(string $series, string $startDate, array $history, string $unit, int $weeks = 100): array
    {
        $payload = json_encode([
            'series_name'=>$series, 'start_date'=>$startDate,
            'horizon_weeks'=>$weeks, 'history'=>$history, 'unit'=>$unit
        ]);

        $venv = __DIR__ . '/../../python/.venv/bin/python';
        $script = __DIR__ . '/../../python/forecast.py';
        $cmd = escapeshellcmd("$venv $script");

        $descriptors = [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) throw new \RuntimeException('Failed to start prophet runner');

        fwrite($pipes[0], $payload); fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) throw new \RuntimeException("Prophet error: $err");
        return json_decode($out, true);
    }
}
