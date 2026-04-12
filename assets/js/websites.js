const websitesList = document.getElementById('websites-list');
const websitesStatus = document.getElementById('websites-status');
const exportButton = document.getElementById('export-csv');

function renderStatus(message, isError = false) {
    websitesStatus.textContent = message;
    websitesStatus.classList.toggle('is-error', isError);
}

async function loadWebsites() {
    renderStatus('Loading websites...');

    try {
        const response = await fetch('api/fetch-websites.php', { method: 'GET' });
        const rawResponse = await response.text();

        let result;
        try {
            result = JSON.parse(rawResponse);
        } catch {
            const snippet = rawResponse.slice(0, 180).replace(/\s+/g, ' ').trim();
            throw new Error(`Server returned non-JSON response: ${snippet || 'empty response'}`);
        }

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Failed to load websites');
        }

        renderWebsites(result.websites);
        renderStatus(result.count === 0 ? 'No websites found. Fetch monitors first.' : '');
        exportButton.disabled = result.count === 0;
    } catch (err) {
        renderStatus(err.message, true);
    }
}

function renderWebsites(websites) {
    if (!websites || websites.length === 0) {
        websitesList.innerHTML = '<tr><td class="websites-empty" colspan="3">No websites found.</td></tr>';
        return;
    }

    websitesList.innerHTML = websites.map((row, index) =>
        `<tr>
            <td class="websites-index">${index + 1}</td>
            <td class="websites-url"><a href="${escapeHtml(row.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(row.url)}</a></td>
            <td class="websites-region">${escapeHtml(row.region || '')}</td>
        </tr>`
    ).join('');
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

exportButton.addEventListener('click', () => {
    window.location.href = 'api/fetch-websites.php?format=csv';
});

loadWebsites();
