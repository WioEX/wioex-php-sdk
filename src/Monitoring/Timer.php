<?php

declare(strict_types=1);

namespace Wioex\SDK\Monitoring;

use Wioex\SDK\Enums\MetricType;

class Timer
{
    private Metrics $metrics;
    private MetricType $type;
    private string $name;
    private array $tags;
    private float $startTime;
    private bool $stopped = false;

    public function __construct(Metrics $metrics, MetricType $type, string $name, array $tags = [])
    {
        $this->metrics = $metrics;
        $this->type = $type;
        $this->name = $name;
        $this->tags = $tags;
        $this->startTime = microtime(true);
    }

    public function stop(): float
    {
        if ($this->stopped) {
            return 0.0;
        }

        $this->stopped = true;
        $duration = (microtime(true) - $this->startTime) * 1000; // Convert to milliseconds

        if ($this->type->isTimeBasedMetric()) {
            $this->metrics->histogram($this->type, $this->name, $duration, $this->tags);
        }

        return $duration;
    }

    public function __destruct()
    {
        if (!$this->stopped) {
            $this->stop();
        }
    }

    public static function measure(Metrics $metrics, MetricType $type, string $name, callable $callback, array $tags = [])
    {
        $timer = new self($metrics, $type, $name, $tags);

        try {
            $result = $callback();
            $timer->stop();
            return $result;
        } catch (\Throwable $e) {
            $timer->stop();
            $metrics->recordError($name, get_class($e), array_merge($tags, ['exception' => $e->getMessage()]));
            throw $e;
        }
    }
}
