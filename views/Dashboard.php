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

<script>
let doorStatus = null;
const initialDoorState = <?= (int) $initialDoorState ?>;
let timeoutId = null;
let alarmActive = false;
let dashboardRefreshInProgress = false;
const doorOpenSince = new Map();
const doorColors = <?= json_encode([
  'closed' => \App\Enums\Color::Green->value,
  'open' => \App\Enums\Color::Orange->value,
  'openTooLong' => \App\Enums\Color::Red->value,
], JSON_THROW_ON_ERROR) ?>;

function formatDoorOpenTime(milliseconds) {
  const totalSeconds = Math.floor(milliseconds / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  return minutes > 0
    ? `${minutes}m ${String(seconds).padStart(2, '0')}s`
    : `${seconds} s`;
}

function updateDoorCards() {
  document.querySelectorAll('[data-door-card]').forEach(card => {
    const deviceId = card.dataset.deviceId;
    const rawState = card.dataset.doorState;
    const sensorState = Number(rawState);

    if (!deviceId || !['0', '1', '2'].includes(rawState)) {
      return;
    }

    let displayState = sensorState;
    if (sensorState === 0) {
      doorOpenSince.delete(deviceId);
    } else if (sensorState === 1) {
      if (!doorOpenSince.has(deviceId)) {
        doorOpenSince.set(deviceId, Date.now());
      }
      if (Date.now() - doorOpenSince.get(deviceId) >= 10000) {
        displayState = 2;
      }
    }

    const statusColor = displayState === 0 ? doorColors.closed : (displayState === 1 ? doorColors.open : doorColors.openTooLong);
    const iconFile = displayState === 0 ? 'DoorSensor.svg' : (displayState === 1 ? 'DoorSensorAjar.svg' : 'DoorSensorOpen.svg');
    const statusLabel = { 0: 'Dicht', 1: 'Open', 2: 'Te lang open' }[displayState];

    card.style.borderColor = statusColor;
    const iconImg = card.querySelector('img[src*="/doorSensor/"]');
    if (iconImg) {
      iconImg.src = `/img/sensors/doorSensor/${iconFile}`;
    }
    const statusValue = card.querySelector('[data-door-state-val]');
    if (statusValue) {
      statusValue.textContent = `= ${statusLabel}`;
    }

    const timerValue = card.querySelector('[data-door-open-timer]');
    if (timerValue) {
      const elapsed = sensorState === 0
        ? 0
        : Date.now() - (doorOpenSince.get(deviceId) ?? Date.now());
      timerValue.textContent = `= ${formatDoorOpenTime(elapsed)}`;
    }
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
    pushDoorAlarmState(2);
    return true;
  }

  return false;
}

function startAlarmTimer() {
  clearTimeout(timeoutId);
  timeoutId = setTimeout(() => {
    Alarm();
  }, 10000);
  console.log('Alarm timer started (10s)');
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
    pushDoorAlarmState(0);
    return;
  }

  if (nextStatus === 1) {
    if (doorStatus === 2) {
      return;
    }

    if (doorStatus !== 1) {
      doorStatus = 1;
      startAlarmTimer();
      pushDoorAlarmState(1);
    }
    return;
  }

  if (nextStatus === 2) {
    doorStatus = 2;
    alarmActive = true;
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
  updateDoorCards();
  setInterval(refreshDoorState, 2000);
  setInterval(refreshDashboardData, 1000);
  setInterval(updateDoorCards, 1000);
});

async function refreshDashboardData() {
  if (dashboardRefreshInProgress) {
    return;
  }

  dashboardRefreshInProgress = true;
  try {
    const response = await fetch(`/dashboard/data?_=${Date.now()}`, { cache: 'no-store' });
    if (!response.ok) {
      throw new Error(`Dashboard refresh failed with status ${response.status}`);
    }
    document.getElementById('dashboard-data').innerHTML = await response.text();
    updateDoorCards();
  } catch (error) {
    console.error('Failed to refresh dashboard data:', error);
  } finally {
    dashboardRefreshInProgress = false;
  }
}
</script>
</body>
</html>