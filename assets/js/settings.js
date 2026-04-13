const form = document.getElementById('settings-form');
const statusMessage = document.getElementById('status-message');

function setStatus(message, isError = false) {
    statusMessage.textContent = message;
    statusMessage.style.color = isError ? '#b42318' : '#52606d';
}

async function loadSettings() {
    try {
        const response = await fetch('api/settings.php');
        if (!response.ok) {
            throw new Error('Failed to load settings');
        }

        const settings = await response.json();
        form.lighthouseApiToken.value = settings.lighthouseApiToken ?? '';
        form.lighthouseMonitorEndpoints.value = Array.isArray(settings.lighthouseMonitorEndpoints)
            ? (settings.lighthouseMonitorEndpoints[0] ?? '')
            : (settings.lighthouseMonitorEndpoints ?? '');
        form.lighthouseRunsEndpoint.value = settings.lighthouseRunsEndpoint ?? '';
        form.lighthouseRunsValueEndpoint.value = settings.lighthouseRunsValueEndpoint ?? '';
        form.scoreAmber.value = settings.scoreAmber ?? 50;
        form.scoreRed.value = settings.scoreRed ?? 25;
    } catch (error) {
        setStatus('Unable to load settings.', true);
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    setStatus('Saving settings...');

    const payload = {
        lighthouseApiToken: form.lighthouseApiToken.value.trim(),
        lighthouseMonitorEndpoints: form.lighthouseMonitorEndpoints.value.trim(),
        lighthouseRunsEndpoint: form.lighthouseRunsEndpoint.value.trim(),
        lighthouseRunsValueEndpoint: form.lighthouseRunsValueEndpoint.value.trim(),
        scoreAmber: Number(form.scoreAmber.value) || 50,
        scoreRed: Number(form.scoreRed.value) || 25
    };

    try {
        const response = await fetch('api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Failed to save settings');
        }

        setStatus('Settings saved successfully.');
    } catch (error) {
        setStatus(error.message || 'Unable to save settings.', true);
    }
});

loadSettings();
