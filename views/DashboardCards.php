<?php
$visibleFieldsBySensorType = [
    'powerSocket' => [],
    'temperatureSensor' => ['TempC_DS', 'TempC_SHT'],
    'soundLevelSensor' => ['la', 'lamax'],
    'indoorAmbienceMonitoringSensor' => ['co2', 'humidity'],
    'motionSensor' => ['motion', 'count'],
    'doorSensor' => ['DOOR_OPEN_STATUS', 'DOOR_OPEN_TIMES', 'LAST_DOOR_OPEN_DURATION'],
];

$sensorTemplates = [
    'powerSocket' => 'stopcontact.php',
    'temperatureSensor' => 'temperatuur.php',
    'soundLevelSensor' => 'geluidlevel.php',
    'indoorAmbienceMonitoringSensor' => 'luchtkwaliteit.php',
    'motionSensor' => 'beweging.php',
    'doorSensor' => 'deursensor.php',
];

$deviceNames = [
    'powersocket-001' => 'Stopcontact',
    'soundlevelsensor-001' => 'Geluid-Level',
    'deursensor-002' => 'Voordeur',
    'deursensor-003' => 'Achterdeur',
    'motion-001' => 'Beweging',
    'temphumidity-001' => 'Temperatuur',
    'indoorambiencemonitoringsensor' => 'Luchtkwaliteit',
];

$logoCardRendered = false;

foreach ($deviceCards as $card):
    [$borderColor] = $definer->status_color_and_label($card->status);
    $doorState = $card->DOOR_OPEN_STATUS ?? null;

    if ($card->sensorType === 'doorSensor' && in_array((string) $doorState, ['0', '1', '2'], true)) {
        $borderColor = match ((string) $doorState) {
            '0' => \App\Enums\Color::Green->value,
            '1' => \App\Enums\Color::Orange->value,
            '2' => \App\Enums\Color::Red->value,
        };
    }

    $visibleFields = $visibleFieldsBySensorType[$card->sensorType] ?? [];
    $template = $sensorTemplates[$card->sensorType] ?? 'algemeen.php';
    $deviceId = strtolower((string) $card->device_id);
    $displayName = $deviceNames[$deviceId] ?? (string) $card->device_id;
    $iconHtml = $definer->getDeviceIcon(
        $card->sensorType,
        $card->status,
        $doorState,
        $card->motion ?? null,
        $card->state ?? null,
        $card->la ?? null
    ) ?? "<img src='/img/sensors/default/GildeDataCenterIconGood.svg' width=\"64\" class=\"block max-w-full h-auto ml-auto\">";

    require __DIR__ . '/' . $template;

    if ($deviceId === 'deursensor-003'):
        ?>
        <div class="flex min-w-0 flex-col items-center justify-center rounded-xl border-2 border-gray-500 overflow-hidden p-5 h-full">
            <img src="/img/GildeLogoDatacenter.svg" alt="Gilde Opleidingen Datacenter" class="block w-full max-w-[250px] h-auto">
        </div>
        <?php $logoCardRendered = true; ?>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!$logoCardRendered): ?>
    <div class="flex min-w-0 flex-col items-center justify-center rounded-xl border-2 border-gray-500 overflow-hidden p-5 h-full">
        <img src="/img/GildeLogoDatacenter.svg" alt="Gilde Opleidingen Datacenter" class="block w-full max-w-[250px] h-auto">
    </div>
<?php endif; ?>
