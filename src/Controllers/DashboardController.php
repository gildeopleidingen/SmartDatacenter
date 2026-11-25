<?php
namespace App\Controllers;

use App\Services\DBParser;
use App\Services\Definer;
use App\Services\DeviceStateService;
use App\Services\SensorThresholdSettingService;
use InvalidArgumentException;

class DashboardController {
    public function index() {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $parser = new DBParser();
        $definer = new Definer();
        $deviceStateService = new DeviceStateService();

        $deviceCards = $parser->generateCards();
        $fields = $definer->getFields();
        $initialDoorState = (int) $deviceStateService->getDoorState();
        $doorAlarmDelaySeconds = (int) $deviceStateService->getDoorAlarmDelaySeconds();
        $doorStatus = $initialDoorState;

        require __DIR__ . '/../../views/Dashboard.php';
    }

    public function dashboardData() {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $parser = new DBParser();
        $definer = new Definer();
        $deviceCards = $parser->generateCards();
        $doorStatus = isset($_GET['door_status']) ? (int) $_GET['door_status'] : null;

        require __DIR__ . '/../../views/DashboardCards.php';
    }

    public function doorState() {
        $deviceStateService = new DeviceStateService();
        $state = (int) $deviceStateService->getDoorState();

        header('Content-Type: application/json');
        echo json_encode(['door_status' => $state]);
    }

    public function doorAlarm() {
        $payload = json_decode(file_get_contents('php://input'), true);
        $state = (int) ($payload['door_status'] ?? 0);

        $deviceStateService = new DeviceStateService();
        $deviceStateService->updateBusylight($state);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'door_status' => $state]);
    }

    public function getSensorThresholdSettings() {
        header('Content-Type: application/json');

        try {
            $deviceId = isset($_GET['device_id']) ? (int) $_GET['device_id'] : 0;
            $service = new SensorThresholdSettingService();
            $rows = $service->getByDeviceId($deviceId);

            echo json_encode([
                'status' => 'ok',
                'device_id' => $deviceId,
                'thresholds' => $rows,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to load threshold settings']);
        }
    }

    public function saveSensorThresholdSettings() {
        header('Content-Type: application/json');

        try {
            $payload = json_decode(file_get_contents('php://input'), true);

            if (!is_array($payload)) {
                throw new InvalidArgumentException('Invalid JSON payload');
            }

            $deviceId = isset($payload['device_id']) ? (int) $payload['device_id'] : 0;
            $thresholds = $payload['thresholds'] ?? null;

            if (!is_array($thresholds)) {
                throw new InvalidArgumentException('thresholds must be an array');
            }

            $service = new SensorThresholdSettingService();
            $count = $service->saveForDevice($deviceId, $thresholds);

            echo json_encode([
                'status' => 'ok',
                'device_id' => $deviceId,
                'saved_count' => $count,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save threshold settings']);
        }
    }
}
