<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #001; }
        .body_wrapper {
            width: min(100%, 1240px);
            min-height: 100vh;
            margin: 0 auto;
            padding: 1rem;
            box-sizing: border-box;
        }
        .card-dot { width: 12px; height: 12px; border-radius: 9999px; background: #1E40AF; border: 3px solid white; }
    </style>
    <link rel="icon" href="/img/favicon.svg" sizes="any" type="image/svg+xml">
</head>
<body class="min-h-screen text-white">
<div class="body_wrapper">
    <div class="main_wrapper">
        <section class="px-2 py-4 sm:px-4 lg:px-6">
            <div class="container mx-auto w-full max-w-[1240px]">
                <div id="dashboard-data" class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <?php require __DIR__ . '/DashboardCards.php'; ?>
                </div>

            </div>
        </section>
    </div>
    <div class="main_footer"></div>
</div>

<div id="threshold-settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
    <div class="w-full max-w-xl rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-lg">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold">Sensor instellingen</h2>
            <button type="button" id="threshold-modal-close" class="rounded bg-slate-700 px-2 py-1 text-sm hover:bg-slate-600">Sluiten</button>
        </div>
        <p id="threshold-modal-device-label" class="mb-3 text-sm text-slate-300"></p>
        <form id="threshold-settings-form" class="space-y-3">
            <div id="threshold-metric-rows" class="max-h-[50vh] space-y-3 overflow-y-auto pr-1"></div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" id="threshold-modal-cancel" class="rounded bg-slate-700 px-3 py-2 text-sm hover:bg-slate-600">Annuleren</button>
                <button type="submit" class="rounded bg-blue-600 px-3 py-2 text-sm font-semibold hover:bg-blue-500">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<script>
let doorStatus = null;
const initialDoorState = <?= (int) $initialDoorState ?>;
const doorAlarmDelaySeconds = <?= (int) ($doorAlarmDelaySeconds ?? 10) ?>;
let timeoutId = null;
let alarmActive = false;
let dashboardRefreshInProgress = false;
let thresholdModalState = {
  deviceDbId: null,
  deviceId: '',
  sensorType: ''
};

const thresholdMetricConfig = {
  temperatureSensor: [
    { metric: 'TempC_SHT', label: 'Temp SHT (C)' },
    { metric: 'TempC_DS', label: 'Temp DS (C)' },
    { metric: 'Hum_SHT', label: 'Humidity (%)' }
  ],
  soundLevelSensor: [
    { metric: 'la', label: 'Huidig (dB)' },
    { metric: 'lamax', label: 'Hoogste (dB)' }
  ],
  indoorAmbienceMonitoringSensor: [
    { metric: 'co2', label: 'CO2 (ppm)' },
    { metric: 'humidity', label: 'Humidity (%)' },
    { metric: 'temperature', label: 'Temperature (C)' }
  ],
  motionSensor: [
    { metric: 'count', label: 'Counter' }
  ],
  powerSocket: [
    { metric: 'voltage', label: 'Voltage (V)' },
    { metric: 'current', label: 'Current (mA)' },
    { metric: 'power', label: 'Power (W)' }
  ]
};

function updateDoorUi(status) {
  const statusNum = Number(status);
  const statusColor = statusNum === 0 ? '#22c55e' : (statusNum === 1 ? '#ff8800' : '#ff2b2b');
  const iconFile = statusNum === 0 ? 'DoorSensor.svg' : (statusNum === 1 ? 'DoorSensorAjar.svg' : 'DoorSensorOpen.svg');
  const labelMap = {
    0: 'Dicht',
    1: 'Open',
    2: 'Te lang open'
  };
  const statusLabel = labelMap[statusNum] ?? statusNum;

  document.querySelectorAll('[data-door-card]').forEach(card => {
    card.style.borderColor = statusColor;
    const iconImg = card.querySelector('img[src*="doorSensor"]');
    if (iconImg) {
      iconImg.src = `/img/sensors/doorSensor/${iconFile}`;
    }
  });

  document.querySelectorAll('[data-door-state-val]').forEach(valSpan => {
    valSpan.textContent = `= ${statusLabel}`;
  });
}

async function pushDoorAlarmState(state) {
  try {
    await fetch('/api/door-alarm', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ door_status: state })
    });
  } catch (error) {
    console.error('Failed to push door alarm state:', error);
  }
}

function Alarm() {
  if (doorStatus === 1) {
    doorStatus = 2;
    alarmActive = true;
    console.log('Alarm triggered');
    updateDoorUi(2);
    pushDoorAlarmState(2);
    refreshDashboardData();
    return true;
  }

  return false;
}

function startAlarmTimer() {
  clearTimeout(timeoutId);
  timeoutId = setTimeout(() => {
    Alarm();
  }, doorAlarmDelaySeconds * 1000);
  console.log(`Alarm timer started (${doorAlarmDelaySeconds}s)`);
}

function TimerStop() {
  clearTimeout(timeoutId);
  timeoutId = null;
  alarmActive = false;
  console.log('Alarm timer stopped');
}

function setDoorStatus(newStatus) {
  const nextStatus = Number(newStatus);

  if (nextStatus === 0) {
    if (doorStatus === 0) {
      return;
    }
    doorStatus = 0;
    TimerStop();
    updateDoorUi(0);
    pushDoorAlarmState(0);
    refreshDashboardData();
    return;
  }

  if (nextStatus === 1) {
    if (doorStatus === 2) {
      return;
    }

    if (doorStatus !== 1) {
      doorStatus = 1;
      updateDoorUi(1);
      startAlarmTimer();
      pushDoorAlarmState(1);
      refreshDashboardData();
    }
    return;
  }

  if (nextStatus === 2) {
    doorStatus = 2;
    alarmActive = true;
    updateDoorUi(2);
    refreshDashboardData();
  }
}

async function refreshDoorState() {
  try {
    const response = await fetch(`/api/door-state?_=${Date.now()}`, { cache: 'no-store' });
    const data = await response.json();
    const nextStatus = Number(data.door_status);

    if (nextStatus === 0) {
      if (doorStatus !== 0) {
        setDoorStatus(0);
      }
      return;
    }

    if (nextStatus === 1) {
      if (doorStatus !== 1 && doorStatus !== 2) {
        setDoorStatus(1);
      }
      return;
    }
  } catch (error) {
    console.error('Failed to fetch door state:', error);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  setDoorStatus(initialDoorState);
  setInterval(refreshDoorState, 2000);
  setInterval(refreshDashboardData, 2000);
  initThresholdSettingsUi();
});

async function refreshDashboardData() {
  if (dashboardRefreshInProgress) {
    return;
  }

  dashboardRefreshInProgress = true;
  try {
    const response = await fetch(`/dashboard/data?door_status=${encodeURIComponent(doorStatus)}&_=${Date.now()}`, { cache: 'no-store' });
    if (!response.ok) {
      throw new Error(`Dashboard refresh failed with status ${response.status}`);
    }
    document.getElementById('dashboard-data').innerHTML = await response.text();
  } catch (error) {
    console.error('Failed to refresh dashboard data:', error);
  } finally {
    dashboardRefreshInProgress = false;
  }
}

function initThresholdSettingsUi() {
  const modal = document.getElementById('threshold-settings-modal');
  const closeBtn = document.getElementById('threshold-modal-close');
  const cancelBtn = document.getElementById('threshold-modal-cancel');
  const form = document.getElementById('threshold-settings-form');

  document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-threshold-settings-btn]');
    if (!btn) {
      return;
    }

    const sensorType = String(btn.dataset.sensorType || '');
    const metricConfig = thresholdMetricConfig[sensorType] || [];
    if (metricConfig.length === 0) {
      alert('Geen instelbare drempels voor deze sensor.');
      return;
    }

    thresholdModalState = {
      deviceDbId: Number(btn.dataset.deviceDbId || 0),
      deviceId: String(btn.dataset.deviceId || ''),
      sensorType
    };

    if (!thresholdModalState.deviceDbId) {
      alert('Ongeldig device ID.');
      return;
    }

    openThresholdModal();
    try {
      await loadThresholdRows();
    } catch (error) {
      console.error(error);
      alert('Kon drempels niet laden.');
      closeThresholdModal();
    }
  });

  closeBtn.addEventListener('click', closeThresholdModal);
  cancelBtn.addEventListener('click', closeThresholdModal);

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeThresholdModal();
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await saveThresholdRows();
    } catch (error) {
      console.error(error);
      alert(error.message || 'Opslaan mislukt.');
    }
  });
}

function openThresholdModal() {
  const modal = document.getElementById('threshold-settings-modal');
  const label = document.getElementById('threshold-modal-device-label');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  label.textContent = `Device: ${thresholdModalState.deviceId}`;
}

function closeThresholdModal() {
  const modal = document.getElementById('threshold-settings-modal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

function renderThresholdRows(existingRows) {
  const container = document.getElementById('threshold-metric-rows');
  const metricConfig = thresholdMetricConfig[thresholdModalState.sensorType] || [];
  const byMetric = new Map((existingRows || []).map((row) => [String(row.metric), row]));

  container.innerHTML = metricConfig.map(({ metric, label }) => {
    const row = byMetric.get(metric) || {};
    const warningMin = row.warning_min ?? '';
    const warningMax = row.warning_max ?? '';
    const errorMin = row.error_min ?? '';
    const errorMax = row.error_max ?? '';
    const enabledChecked = Number(row.is_enabled ?? 1) === 1 ? 'checked' : '';

    return `
      <fieldset class="rounded border border-slate-700 p-3" data-threshold-row data-metric="${metric}">
        <legend class="px-1 text-sm font-semibold">${label}</legend>
        <label class="mb-3 flex items-center gap-2 text-xs text-slate-300">
          <input type="checkbox" data-input="is_enabled" ${enabledChecked}>
          Ingeschakeld
        </label>
        <div class="grid grid-cols-2 gap-2">
          <label class="text-xs">Warning min
            <input type="number" step="0.01" data-input="warning_min" value="${warningMin}" class="mt-1 w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-sm">
          </label>
          <label class="text-xs">Warning max
            <input type="number" step="0.01" data-input="warning_max" value="${warningMax}" class="mt-1 w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-sm">
          </label>
          <label class="text-xs">Error min
            <input type="number" step="0.01" data-input="error_min" value="${errorMin}" class="mt-1 w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-sm">
          </label>
          <label class="text-xs">Error max
            <input type="number" step="0.01" data-input="error_max" value="${errorMax}" class="mt-1 w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-sm">
          </label>
        </div>
      </fieldset>
    `;
  }).join('');
}

async function loadThresholdRows() {
  const response = await fetch(`/api/sensor-thresholds?device_id=${encodeURIComponent(thresholdModalState.deviceDbId)}`, { cache: 'no-store' });
  if (!response.ok) {
    throw new Error(`Failed to load thresholds: ${response.status}`);
  }

  const data = await response.json();
  renderThresholdRows(Array.isArray(data.thresholds) ? data.thresholds : []);
}

function collectThresholdRowsFromForm() {
  const rows = [];
  document.querySelectorAll('[data-threshold-row]').forEach((rowEl) => {
    const metric = rowEl.getAttribute('data-metric');
    const warningMin = rowEl.querySelector('[data-input="warning_min"]').value.trim();
    const warningMax = rowEl.querySelector('[data-input="warning_max"]').value.trim();
    const errorMin = rowEl.querySelector('[data-input="error_min"]').value.trim();
    const errorMax = rowEl.querySelector('[data-input="error_max"]').value.trim();
    const isEnabled = rowEl.querySelector('[data-input="is_enabled"]').checked ? 1 : 0;

    rows.push({
      metric,
      warning_min: warningMin === '' ? null : Number(warningMin),
      warning_max: warningMax === '' ? null : Number(warningMax),
      error_min: errorMin === '' ? null : Number(errorMin),
      error_max: errorMax === '' ? null : Number(errorMax),
      is_enabled: isEnabled
    });
  });
  return rows;
}

async function saveThresholdRows() {
  const payload = {
    device_id: thresholdModalState.deviceDbId,
    thresholds: collectThresholdRowsFromForm()
  };

  const response = await fetch('/api/sensor-thresholds', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  const data = await response.json();
  if (!response.ok || data.status !== 'ok') {
    const message = data && data.message ? data.message : 'Opslaan mislukt';
    throw new Error(message);
  }

  closeThresholdModal();
  await refreshDashboardData();
}
</script>
</body>
</html>