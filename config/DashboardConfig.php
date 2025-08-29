<?php
namespace app\config;

final class DashboardConfig {
    public const HOUSING_SQFT_PER_BIRD = 2.5;
    public const SQFT_TO_SQM           = 0.092903;
    public const LAY_START_WEEK        = 18;   // per farm policy
    public const LAY_END_WEEK          = 100;  // culling starts after 100
    public const DAYS_PER_WEEK         = 7;
    public const CHART_WINDOW_WEEKS    = 30;

    // Absolute data sanity bounds
    public const FEED_G_PER_BIRD_MIN   = 50;   // g/bird/day
    public const FEED_G_PER_BIRD_MAX   = 200;  // g/bird/day

    // Operational alert bounds (from week 18–100 data)
    public const FEED_ALERT_MIN        = 100;  // p5
    public const FEED_ALERT_MAX        = 125;  // widened from p95 for safety

    // Mortality weekly % thresholds
    public const MORTALITY_WARN        = 0.13; // p95
    public const MORTALITY_DANGER      = 0.20; // red alert
}
