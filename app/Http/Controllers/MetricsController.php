<?php

namespace App\Http\Controllers;

use App\Services\EstateStats;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = config('patchmon.metrics.token');

        if ($token === null || $token === '') {
            return response('Metrics endpoint not configured.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response('Forbidden.', Response::HTTP_FORBIDDEN);
        }

        return response($this->exposition(new EstateStats))
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    /**
     * Hand-rolled Prometheus text exposition, reusing the same EstateStats the
     * dashboard and weekly overview use so the numbers always agree. Label values
     * are escaped according to Prometheus text exposition rules.
     */
    private function exposition(EstateStats $stats): string
    {
        $teamMetrics = [
            ['patchmon_servers_total', 'Monitored servers per team.', 'total'],
            ['patchmon_servers_overdue', 'Overdue (non-silenced) servers per team.', 'overdue'],
            ['patchmon_servers_silenced', 'Currently silenced servers per team.', 'silenced'],
            ['patchmon_servers_patched_recently', 'Servers patched in the last 30 days, per team.', 'patched_30d'],
        ];

        $teamRows = $stats->teamRows();
        $lines = [];

        foreach ($teamMetrics as [$name, $help, $key]) {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} gauge";

            foreach ($teamRows as $row) {
                $lines[] = sprintf('%s{team="%s"} %d', $name, $this->labelValue($row['team']->name), $row[$key]);
            }
        }

        $lines[] = '# HELP patchmon_servers_never_checked_in Live servers (any team) that have never reported a patch.';
        $lines[] = '# TYPE patchmon_servers_never_checked_in gauge';
        $lines[] = sprintf('patchmon_servers_never_checked_in %d', $stats->neverCheckedInCount());

        return implode("\n", $lines)."\n";
    }

    /**
     * Escape a Prometheus label value. Backslashes are escaped first so the
     * backslashes added for quotes and newlines are not escaped again.
     */
    private function labelValue(string $value): string
    {
        return str_replace(
            ['\\', "\n", '"'],
            ['\\\\', '\\n', '\\"'],
            $value,
        );
    }
}
