<?php
namespace app\services;

use app\config\DashboardConfig;

class ValidationService {
    public function validateBusinessLogic(array $metrics): array {
        $warnings = [];

        // Laying rate > 100% sanity check
        if (isset($metrics['laying_rate']) && $metrics['laying_rate'] > 100) {
            $warnings[] = 'Laying rate exceeds 100% — check data accuracy';
        }

        // Feed consumption alerts (real g/bird/day)
        if (isset($metrics['feed_per_bird'])) {
            $feed = $metrics['feed_per_bird'];
            if ($feed < DashboardConfig::FEED_G_PER_BIRD_MIN || $feed > DashboardConfig::FEED_G_PER_BIRD_MAX) {
                $warnings[] = "Feed value ({$feed} g) outside absolute range";
            }
            elseif ($feed < DashboardConfig::FEED_ALERT_MIN) {
                $warnings[] = "Feed consumption low ({$feed} g)";
            }
            elseif ($feed > DashboardConfig::FEED_ALERT_MAX) {
                $warnings[] = "Feed consumption high ({$feed} g)";
            }
        }

        // Mortality alerts (weekly % per 100 birds)
        if (isset($metrics['mortality'])) {
            $mortality = $metrics['mortality'];
            if ($mortality > DashboardConfig::MORTALITY_DANGER) {
                $warnings[] = "Mortality critical ({$mortality}%)";
            }
            elseif ($mortality > DashboardConfig::MORTALITY_WARN) {
                $warnings[] = "Mortality warning ({$mortality}%)";
            }
        }

        return $warnings;
    }
}
