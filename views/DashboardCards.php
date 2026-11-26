<?php
$customerVisibleFields = [
    'powersocket-001' => [],
    'temphumidity-001' => ['TempC_DS', 'TempC_SHT'],
    'soundlevelsensor-001' => ['la', 'lamax'],
    'indoorambiencemonitoringsensor' => ['co2', 'humidity'],
    'motion-001' => ['motion', 'count'],
    'deursensor-002' => ['DOOR_OPEN_STATUS', 'DOOR_OPEN_TIMES', 'LAST_DOOR_OPEN_DURATION'],
    'deursensor-003' => ['DOOR_OPEN_STATUS', 'DOOR_OPEN_TIMES', 'LAST_DOOR_OPEN_DURATION'],
];

$logoCardRendered = false;

foreach ($deviceCards as $card):
    [$borderColor, $statusLabel] = $definer->status_color_and_label($card->status);
    $doorState = $card->DOOR_OPEN_STATUS ?? null;

    if ($card->sensorType === 'doorSensor') {
        if (isset($doorStatus) && $doorStatus !== null) {
            if (strtolower((string)$card->device_id) === 'deursensor-002' || (int)$doorState !== 0) {
                $doorState = $doorStatus;
                $card->DOOR_OPEN_STATUS = $doorStatus;
            }
        }
        if (in_array((string) $doorState, ['0', '1', '2'], true)) {
            $borderColor = match ((string) $doorState) {
                '0' => '#22c55e',
                '1' => '#ff8800',
                '2' => '#ff2b2b',
            };
        }
    }
    $visibleFields = $customerVisibleFields[strtolower((string) $card->device_id)] ?? [];
    ?>
    <div class="relative flex min-w-0 flex-col justify-between items-center rounded-xl border-2 overflow-hidden p-5 h-full" style="border-color: <?= $borderColor ?>">
        <button
            type="button"
            class="absolute top-2 right-2 rounded bg-slate-700 p-2 text-white hover:bg-slate-600"
            data-threshold-settings-btn
            data-device-db-id="<?= (int) ($card->device_db_id ?? 0) ?>"
            data-device-id="<?= htmlspecialchars((string) $card->device_id, ENT_QUOTES, 'UTF-8') ?>"
            data-sensor-type="<?= htmlspecialchars((string) $card->sensorType, ENT_QUOTES, 'UTF-8') ?>"
            title="Instellingen"
            aria-label="Instellingen"
        >
            <span aria-hidden="true">⚙️</span>
        </button>
        <div class="<?= in_array($card->sensorType, ['soundLevelSensor', 'doorSensor', 'indoorAmbienceMonitoringSensor'], true) ? 'w-32' : 'w-11' ?> block max-w-full h-auto">
            <?= $definer->getDeviceIcon($card->sensorType, $card->status, isset($card->DOOR_OPEN_STATUS) ? $card->DOOR_OPEN_STATUS : null, isset($card->motion) ? $card->motion : null, isset($card->state) ? $card->state : null, isset($card->la) ? $card->la : null) ?? "<img src='/img/sensors/default/GildeDataCenterIconGood.svg' width=\"64\" class=\"block max-w-full h-auto ml-auto\">" ?>
        </div>
        <div class="flex-grow"></div>
        <ul class="flex flex-col items-center justify-center bg-[#001] overflow-hidden w-full">
            <p class="text-lg font-semibold"><?= htmlspecialchars(match (strtolower((string) $card->device_id)) {
                'powersocket-001' => 'Stopcontact',
                'soundlevelsensor-001' => 'Geluid-Level',
                'deursensor-002' => 'Voordeur',
                'deursensor-003' => 'Achterdeur',
                'motion-001' => 'Beweging',
                'temphumidity-001' => 'Temperatuur',
                'indoorambiencemonitoringsensor' => 'Luchtkwaliteit',
                default => $card->device_id,
            }) ?></p>
            <?php foreach ($card as $key => $value):
                if (!in_array($key, $visibleFields, true)) continue;
                $displayValue = (string) $value;
                if ($card->sensorType === 'doorSensor' && $key === 'DOOR_OPEN_STATUS') {
                    $displayValue = $definer->door_state_label($value);
                }
                ?>
                <li class="grid grid-cols-2 gap-1 w-full">
                    <span class="text-gray-300 text-right break-all text-xs"><?= htmlspecialchars($definer->getLabels($key)) ?></span>
                    <span class="font-medium break-all text-xs" <?= ($card->sensorType === 'doorSensor' && $key === 'DOOR_OPEN_STATUS') ? 'data-door-state-val' : '' ?>>= <?= htmlspecialchars($displayValue) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="text-right text-gray-400 text-sm mt-4"></div>
    </div>
    <?php if (strtolower((string) $card->device_id) === 'deursensor-003'): ?>
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
