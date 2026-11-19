<?php
namespace App\Models\DeviceModels;

use App\Models\Sensor;
use App\Database\DBConn;
use PDO;

class DoorSensor extends Sensor
{
    public $ALARM;
    
    public $BAT_V;
    
    public $DOOR_OPEN_STATUS;
    
    public $DOOR_OPEN_TIMES;

    public $LAST_DOOR_OPEN_DURATION;

    public $MOD;

    public static function getLatestActivities(): array {
        $db = DBConn::getInstance();

        $sql = "
            SELECT a.data
            FROM device d
            JOIN activity a ON a.device_ID = d.ID
            WHERE LOWER(d.deviceID) LIKE 'deursensor-%'
              AND a.ID = (
                  SELECT latest.ID
                  FROM activity latest
                  WHERE latest.device_ID = d.ID
                  ORDER BY latest.dateTime DESC, latest.ID DESC
                  LIMIT 1
              )
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getDoorState() {
        foreach (self::getLatestActivities() as $activity) {
            $data = json_decode($activity['data'], true);
            if (is_array($data) && isset($data['DOOR_OPEN_STATUS']) && (int) $data['DOOR_OPEN_STATUS'] !== 0) {
                return 1;
            }
        }

        return 0;
    }
}
