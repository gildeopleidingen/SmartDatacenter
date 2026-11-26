<?php
namespace App\Services;

use App\Database\DBConn;
use PDO;
use InvalidArgumentException;

class SensorThresholdSettingService {
    private const DOOR_DELAY_METRIC = 'DOOR_OPEN_SECONDS';
    private const DEFAULT_DOOR_DELAY_SECONDS = 10.0;
    private const MIN_DOOR_DELAY_SECONDS = 1;
    private const MAX_DOOR_DELAY_SECONDS = 3600;

    private $pdo;

    public function __construct() {
        $this->pdo = DBConn::getInstance();
    }

    public function getByDeviceId(int $deviceId): array {
        $this->assertValidDeviceId($deviceId);

        $stmt = $this->pdo->prepare(
            "SELECT device_ID, metric, warning_min, warning_max, error_min, error_max, is_enabled, createdAt, updatedAt
             FROM sensor_threshold_setting
             WHERE device_ID = :device_id
             ORDER BY metric ASC"
        );
        $stmt->bindValue(':device_id', $deviceId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveForDevice(int $deviceId, array $thresholds): int {
        $this->assertValidDeviceId($deviceId);
        $this->assertDeviceExists($deviceId);

        if (empty($thresholds)) {
            throw new InvalidArgumentException('thresholds must contain at least one metric');
        }

        $sql = "
            INSERT INTO sensor_threshold_setting
            (device_ID, metric, warning_min, warning_max, error_min, error_max, is_enabled)
            VALUES
            (:device_id, :metric, :warning_min, :warning_max, :error_min, :error_max, :is_enabled)
            ON DUPLICATE KEY UPDATE
                warning_min = VALUES(warning_min),
                warning_max = VALUES(warning_max),
                error_min = VALUES(error_min),
                error_max = VALUES(error_max),
                is_enabled = VALUES(is_enabled),
                updatedAt = CURRENT_TIMESTAMP
        ";

        $stmt = $this->pdo->prepare($sql);
        $saved = 0;

        foreach ($thresholds as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('each threshold entry must be an object');
            }

            $metric = $this->normalizeMetric($row['metric'] ?? null);
            $warningMin = $this->normalizeNumber($row['warning_min'] ?? null);
            $warningMax = $this->normalizeNumber($row['warning_max'] ?? null);
            $errorMin = $this->normalizeNumber($row['error_min'] ?? null);
            $errorMax = $this->normalizeNumber($row['error_max'] ?? null);
            $isEnabled = $this->normalizeBooleanToInt($row['is_enabled'] ?? 1);

            if ($metric === self::DOOR_DELAY_METRIC) {
                // Door "threshold" is a single delay in seconds, not a min/max bound.
                $warningMin = null;
                $warningMax = $this->normalizeDoorDelay($warningMax);
                $errorMin = null;
                $errorMax = null;
            }

            $this->assertRangeOrder($warningMin, $warningMax, 'warning');
            $this->assertRangeOrder($errorMin, $errorMax, 'error');

            $stmt->execute([
                ':device_id' => $deviceId,
                ':metric' => $metric,
                ':warning_min' => $this->formatNullableDecimal($warningMin),
                ':warning_max' => $this->formatNullableDecimal($warningMax),
                ':error_min' => $this->formatNullableDecimal($errorMin),
                ':error_max' => $this->formatNullableDecimal($errorMax),
                ':is_enabled' => $isEnabled,
            ]);
            $saved++;
        }

        return $saved;
    }

    private function assertValidDeviceId(int $deviceId): void {
        if ($deviceId <= 0) {
            throw new InvalidArgumentException('device_id must be a positive integer');
        }
    }

    private function assertDeviceExists(int $deviceId): void {
        $stmt = $this->pdo->prepare('SELECT ID FROM device WHERE ID = :id LIMIT 1');
        $stmt->bindValue(':id', $deviceId, PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new InvalidArgumentException('device_id does not exist');
        }
    }

    private function normalizeMetric($value): string {
        $metric = trim((string) $value);

        if ($metric === '') {
            throw new InvalidArgumentException('metric is required');
        }

        if (strlen($metric) > 100) {
            throw new InvalidArgumentException('metric length must be <= 100');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $metric)) {
            throw new InvalidArgumentException('metric may only contain letters, numbers, and underscore');
        }

        return $metric;
    }

    private function normalizeNumber($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('bound values must be numeric or null');
        }

        return (float) $value;
    }

    private function normalizeBooleanToInt($value): int {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return 1;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return 0;
        }

        throw new InvalidArgumentException('is_enabled must be boolean-like');
    }

    private function assertRangeOrder(?float $min, ?float $max, string $label): void {
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException($label . '_min cannot be greater than ' . $label . '_max');
        }
    }

    private function normalizeDoorDelay(?float $seconds): float {
        if ($seconds === null) {
            return self::DEFAULT_DOOR_DELAY_SECONDS;
        }

        return (float) max(
            self::MIN_DOOR_DELAY_SECONDS,
            min(self::MAX_DOOR_DELAY_SECONDS, (int) round($seconds))
        );
    }

    private function formatNullableDecimal(?float $value): ?string {
        if ($value === null) {
            return null;
        }

        return number_format($value, 2, '.', '');
    }
}
