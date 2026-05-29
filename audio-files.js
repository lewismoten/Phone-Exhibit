let currentPage = 1;

onReady(() => {
    document
        .getElementById('audio-search-form')
        .addEventListener('submit', event => {
            event.preventDefault();
            load_audio_files(1);
        });

    load_audio_files();
});

async function load_audio_files(page = 1) {
    currentPage = page;

    const q = document.getElementById('q').value.trim();
    const results = document.getElementById('audio-results');
    const pagination = document.getElementById('audio-pagination');

    results.innerHTML = '<p>Loading…</p>';
    pagination.innerHTML = '';

    const result = await api('audio-files', { data: { q, page } });

    if (!result.success) {
        results.innerHTML = `<p class="error">${escape_html(result.error || 'Unable to load audio files.')}</p>`;
        return;
    }

    const rows = Array.isArray(result.rows) ? result.rows : [];

    if (!rows.length) {
        results.innerHTML = '<p>No audio files found.</p>';
        return;
    }

    results.innerHTML = `
        <div class="audio-file-table-wrap">
            <table class="audio-file-table">
                <thead>
                    <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Audio</th>
                        <th scope="col">Type</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(audio_file_row).join('')}
                </tbody>
            </table>
        </div>
    `;

    render_audio_pagination(result.page, result.total_pages);
}

function audio_file_row(row) {

    const phone = format_phone_number(row.phone_number);
    const phoneStatus = row.phone_status || 'unassigned';
    const phoneAssignedIndicator = phoneStatus === 'assigned'
        ? `<span class="audio-file-phone-indicator" title="Assigned phone number" aria-label="Assigned phone number">☎</span>`
        : '';

    const type = row.using_converted_audio
        ? 'Converted'
        : 'Original';

    const player = row.playback_url
        ? compact_audio_player(row)
        : `<span class="muted">Unavailable</span>`;

    return `
        <tr>
            <td class="audio-file-title">
                <a href="edit-audio.php?id=${encodeURIComponent(row.id)}">
                    ${escape_html(row.title)}
                </a>
            </td>

            <td class="audio-file-phone">
                <span class="audio-file-phone-inline">
                    ${phoneAssignedIndicator}
                    <span>${escape_html(phone)}</span>
                </span>
            </td>

            <td class="audio-file-audio">${player}</td>

            <td>${escape_html(type)}</td>

            <td class="muted audio-file-date">
                ${escape_html(format_local_datetime(row.created_at))}
            </td>
        </tr>
    `;
}

function render_audio_pagination(page, totalPages) {
    const pagination = document.getElementById('audio-pagination');

    page = Number(page || 1);
    totalPages = Number(totalPages || 1);

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';

    if (page > 1) {
        html += `<button type="button" onclick="load_audio_files(${page - 1})">« Previous</button> `;
    }

    html += `<span> Page ${page} of ${totalPages} </span>`;

    if (page < totalPages) {
        html += ` <button type="button" onclick="load_audio_files(${page + 1})">Next »</button>`;
    }

    pagination.innerHTML = html;
}
