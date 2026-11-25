<?php
namespace App\Services;

use App\Database\DBConn;
use App\Services\Definer;
use PDO;
use App\Models\DeviceModels\DoorSensor;
use App\Models\DeviceModels\TempSensor;
use App\Models\DeviceModels\MotionSensor;
use App\Models\DeviceModels\PowerSocket;
use App\Models\DeviceModels\SoundLevelSensor;
use App\Models\DeviceModels\IndoorAmbienceMonitoringSensor;

class DBParser {
    public $definer;
    private $pdo;
    private $thresholdSettingService;

    public function __construct() {
        $this->pdo = DBConn::getInstance();
        $this->definer = new Definer();
        $this->thresholdSettingService = new SensorThresholdSettingService();
    }

    public function parseDataToDB($ttn_id, $json) {
        if (empty($json) || !$json) return;
        
        $payload = json_encode($json);

        $id = $this->getOrCreateDevice($ttn_id);

        $stmt = $this->pdo->prepare("
            INSERT INTO activity (device_ID, data, dateTime)
            VALUES (:device_id, :data, NOW())
        ");
       $stmt->bindParam('device_id', $id, PDO::PARAM_INT);
       $stmt->bindParam('data', $payload, PDO::PARAM_STR);
       $stmt->execute();
    }
    
    public function generateCards()
    {
        $devices = $this->definer->getDevices();
        $card_info = [];

        if (!is_array($devices)) return [];
        foreach ($devices as $key => $dev) {

            if ($dev["deviceID"]) {
                $activity = $this->definer->getActivity($dev["deviceID"]);
                if (is_array($activity) && trim($activity["data"]) != "") {
                    $card = $this->getSensorType($dev["deviceID"], json_decode($activity["data"]));

                    if ($card) {
                        $deviceDbId = isset($dev['ID']) ? (int) $dev['ID'] : 0;
                        $card->device_id = $dev["deviceID"];
                        $card->device_db_id = $deviceDbId;
                        $card->status = $this->resolveCardStatus($card, $deviceDbId);
                        array_push($card_info, $card);
                    }
                }
            }
        }
        return $card_info;
    }

    private function resolveCardStatus($card, int $deviceDbId): string {
        if ($deviceDbId <= 0) {
            return 'good';
        }

        $settings = $this->thresholdSettingService->getByDeviceId($deviceDbId);
        if (empty($settings)) {
            return 'good';
        }

        $hasWarning = false;
        foreach ($settings as $setting) {
            if ((int) ($setting['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $metric = (string) ($setting['metric'] ?? '');
            if ($metric === '' || !property_exists($card, $metric)) {
                continue;
            }

            $value = $card->{$metric};
            if (!is_numeric($value)) {
                continue;
            }

            $numericValue = (float) $value;

            $errorMin = $this->toNullableFloat($setting['error_min'] ?? null);
            $errorMax = $this->toNullableFloat($setting['error_max'] ?? null);
            $warningMin = $this->toNullableFloat($setting['warning_min'] ?? null);
            $warningMax = $this->toNullableFloat($setting['warning_max'] ?? null);

            if ($this->isOutsideBounds($numericValue, $errorMin, $errorMax)) {
                return 'error';
            }

            if ($this->isOutsideBounds($numericValue, $warningMin, $warningMax)) {
                $hasWarning = true;
            }
        }

        return $hasWarning ? 'warning' : 'good';
    }

    private function toNullableFloat($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function isOutsideBounds(float $value, ?float $min, ?float $max): bool {
        if ($min === null && $max === null) {
            return false;
        }

        if ($min !== null && $value < $min) {
            return true;
        }

        if ($max !== null && $value > $max) {
            return true;
        }

        return false;
    }

    private function getOrCreateDevice($ttn_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM device WHERE deviceID = :name LIMIT 1");
        $stmt->execute([':name' => $ttn_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device) {
            return (int) $device['id'];
        }

        $insertStmt = $this->pdo->prepare("INSERT INTO device (deviceID) VALUES (:name)");
        $insertStmt->execute([':name' => $ttn_id]);

        return (int) $this->pdo->lastInsertId();
    }  

    public function getSensorType($deviceId, $payload) {
        $deviceArray = explode("-", $deviceId);
        $type = strtolower($deviceArray[0]);

        $abstractClass = null;
        switch($type){
            case "deursensor":
                $abstractClass = DoorSensor::Deserialize(json_encode($payload));
                $abstractClass->setSensorType("doorSensor");
                break;
            case "temphumidity": // Temperatuur
                $abstractClass = TempSensor::Deserialize(json_encode($payload));
                $abstractClass->setSensorType("temperatureSensor");
                break;
            case "motion":
                $abstractClass = MotionSensor::Deserialize(json_encode($payload));
                $abstractClass->setSensorType("motionSensor");
                break;
            case "powersocket": // Powersocket
                $abstractClass = PowerSocket::Deserialize(json_encode($payload));
                $abstractClass->setSensorType("powerSocket");
                break;
            case "soundlevelsensor":
                $abstractClass = SoundLevelSensor::Deserialize(json_encode($payload));
                $abstractClass->setSensorType("soundLevelSensor");
                break;
            case "indoorambiencemonitoringsensor":
                $abstractClass = IndoorAmbienceMonitoringSensor::Deserialize(json_encode($payload));
                $abstractClass->setSensorType("indoorAmbienceMonitoringSensor");
                break;
            }

            return $abstractClass;

        }    
}
