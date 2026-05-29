const getAudioId = () => parseInt(document.getElementById('edit-audio-id').value);
const TTY_LINE_WIDTH = 32;

onReady(() => {
    load_audio_file();

    document
        .getElementById('edit-audio-form')
        .addEventListener('submit', save_audio_file);

    document
        .getElementById('delete-audio-button')
        .addEventListener('click', delete_audio_file);

    document
        .getElementById('tty_transcription_text')
        .addEventListener('input', normalize_tty_transcription_field);
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
    document.getElementById('transcription_text').value = row.transcription_text || '';
    document.getElementById('transcription_tty_preview').value = row.transcription_tty_preview || '';
    document.getElementById('tty_transcription_text').value = row.tty_transcription_text || '';
    normalize_tty_transcription_field();
    document.getElementById('ai_transcription_opt_in').checked = row.ai_transcription_opt_in === 1;
    document.getElementById('ai-transcription-wrap').style.display =
        row.transcription_text ? 'block' : 'none';
    document.getElementById('ai-transcription-tty-wrap').style.display =
        row.transcription_tty_preview ? 'block' : 'none';

    render_audio_playback(row);

    status.innerHTML = '';
}

async function save_audio_file(event) {
    event.preventDefault();

    const status = document.getElementById('edit-audio-status');
    const form = document.getElementById('edit-audio-form');

    status.innerHTML = '<p>Saving…</p>';
    normalize_tty_transcription_field();

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

async function delete_audio_file() {
    if (!window.confirm('Delete this audio file? This cannot be undone.')) {
        return;
    }

    const status = document.getElementById('edit-audio-status');
    const form = document.getElementById('edit-audio-form');
    const button = document.getElementById('delete-audio-button');

    status.innerHTML = '<p>Deleting…</p>';
    button.disabled = true;

    const result = await api('delete-audio', {
        method: 'POST',
        data: {
            id: getAudioId(),
            csrf_token: form.csrf_token.value,
        }
    });

    if (!result.success) {
        status.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to delete audio file.')}</div>`;
        button.disabled = false;
        return;
    }

    status.innerHTML = `<div class="success">${escape_html(result.message || 'Audio file deleted.')}</div>`;
    window.location.href = result.redirect_url || '/audio-files.php';
}

function normalize_tty_transcription_field() {
    const field = document.getElementById('tty_transcription_text');

    if (!field) {
        return;
    }

    field.value = normalize_tty_text(field.value, true);
}

function normalize_tty_text(value, preserveTrailingSpaces = false) {
    let text = String(value || '');
    const hadTrailingSpace = preserveTrailingSpaces && /[ \t]$/.test(text);

    text = text
        .replace(/&/g, ' AND ')
        .replace(/%/g, ' PERCENT ')
        .replace(/'/g, '')
        .toUpperCase()
        .replace(/\r\n?/g, '\n')
        .replace(/[^A-Z0-9 \n\.,?!:;\-()/"]+/g, ' ')
        .replace(/[ \t]+/g, ' ');

    const wrappedLines = [];

    for (const rawLine of text.split('\n')) {
        const line = preserveTrailingSpaces
            ? rawLine.replace(/^[ \t]+/, '')
            : rawLine.trim();

        if (!line) {
            wrappedLines.push('');
            continue;
        }

        wrappedLines.push(...wrap_tty_line(line, TTY_LINE_WIDTH));
    }

    const normalized = preserveTrailingSpaces
        ? wrappedLines.join('\n')
        : wrappedLines.join('\n').trim();

    return hadTrailingSpace ? `${normalized} ` : normalized;
}

function wrap_tty_line(line, width) {
    const trimmed = String(line || '').trim();

    if (!trimmed) {
        return [''];
    }

    const words = trimmed.split(/ +/).filter(Boolean);
    const wrapped = [];
    let current = '';

    for (const word of words) {
        if (word.length > width) {
            if (current) {
                wrapped.push(current);
                current = '';
            }

            for (let i = 0; i < word.length; i += width) {
                wrapped.push(word.slice(i, i + width));
            }
            continue;
        }

        const candidate = current ? `${current} ${word}` : word;
        if (candidate.length <= width) {
            current = candidate;
            continue;
        }

        if (current) {
            wrapped.push(current);
        }

        current = word;
    }

    if (current) {
        wrapped.push(current);
    }

    return wrapped;
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
