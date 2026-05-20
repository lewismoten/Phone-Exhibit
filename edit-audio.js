const getAudioId = () => parseInt(document.getElementById('edit-audio-id').value);

onReady(() => {
    load_audio_file();

    document
        .getElementById('edit-audio-form')
        .addEventListener('submit', save_audio_file);
});

async function load_audio_file() {
    const status = document.getElementById('edit-audio-status');

    status.innerHTML = '<p>Loading…</p>';

    const result = await api('audio-file', {
        data: {
            id: getAudioId()
        }
    });

    if (!result.success) {
        status.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to load audio file.')}</div>`;
        return;
    }

    const row = result.audio_file;

    document.getElementById('directory_title').value = row.directory_title || '';
    document.getElementById('requested_phone_number').value = row.requested_phone_number || '';
    document.getElementById('rolodex_title').value = row.rolodex_title || '';
    document.getElementById('rolodex_details').value = row.rolodex_details || '';
    document.getElementById('tty_transcription_text').value = row.tty_transcription_text || '';
    document.getElementById('ai_transcription_opt_in').checked = row.ai_transcription_opt_in === 1;

    render_audio_playback(row);

    status.innerHTML = '';
}

async function save_audio_file(event) {
    event.preventDefault();

    const status = document.getElementById('edit-audio-status');
    const form = document.getElementById('edit-audio-form');

    status.innerHTML = '<p>Saving…</p>';

    const result = await api('audio-file', {
        method: 'POST',
        data: {
            id: getAudioId(),
            csrf_token: form.csrf_token.value,
            directory_title: form.directory_title.value,
            requested_phone_number: form.requested_phone_number.value,
            rolodex_title: form.rolodex_title.value,
            rolodex_details: form.rolodex_details.value,
            tty_transcription_text: form.tty_transcription_text.value,
            ai_transcription_opt_in: form.ai_transcription_opt_in.checked ? '1' : '0'
        }
    });

    if (!result.success) {
        status.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to save changes.')}</div>`;
        return;
    }

    status.innerHTML = '<div class="success">Changes saved.</div>';
}

function render_audio_playback(row) {
    const container = document.getElementById('audio-playback');

    if (!row.playback_url) {
        container.innerHTML = '<p>Audio unavailable.</p>';
        return;
    }

    const label = row.using_converted_audio
        ? 'Playing converted telephone-ready audio'
        : 'Playing original uploaded audio';

    container.innerHTML = `
        <p><strong>${escape_html(label)}</strong></p>

        <audio controls preload="none" style="width:100%;">
            <source src="${escape_html(row.playback_url)}" type="${escape_html(row.playback_mime_type || 'audio/wav')}">
            Your browser does not support audio playback.
        </audio>

        <p style="font-size:13px;color:#666;">
            Original filename: ${escape_html(row.original_filename || '')}
        </p>
    `;
}