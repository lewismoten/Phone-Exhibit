const getAudioId = () => parseInt(document.getElementById('edit-audio-id').value);
const TTY_LINE_WIDTH = 32;
const ROLODEX_LINE_WIDTH = 40;
const ROLODEX_DETAIL_LINES = 5;
const ROLODEX_ALLOWED_CHARS_REGEX = /[^A-Za-z023456789 \n,.\?:;'&\-()@¢£½¼]/gu;
let currentAudioRow = null;

onReady(() => {
    load_audio_file();

    document
        .getElementById('edit-audio-form')
        .addEventListener('submit', save_audio_file);

    document
        .getElementById('delete-audio-button')
        .addEventListener('click', open_delete_audio_modal);

    document
        .getElementById('delete-audio-cancel')
        .addEventListener('click', close_delete_audio_modal);

    document
        .getElementById('delete-audio-confirm')
        .addEventListener('click', delete_audio_file);

    document
        .getElementById('tty_transcription_text')
        .addEventListener('input', normalize_tty_transcription_field);

    document
        .getElementById('rolodex_title')
        .addEventListener('input', normalize_rolodex_title_field);

    document
        .getElementById('rolodex_details')
        .addEventListener('input', normalize_rolodex_details_field);

    document
        .getElementById('requested_phone_number')
        .addEventListener('input', render_rolodex_preview);

    document
        .getElementById('delete-audio-modal')
        .addEventListener('click', event => {
            if (event.target.id === 'delete-audio-modal') {
                close_delete_audio_modal();
            }
        });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            close_delete_audio_modal();
        }
    });
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
    currentAudioRow = row;

    document.getElementById('directory_title').value = row.directory_title || '';
    document.getElementById('requested_phone_number').value = row.requested_phone_number || '';
    document.getElementById('rolodex_title').value = row.rolodex_title || '';
    document.getElementById('rolodex_details').value = row.rolodex_details || '';
    document.getElementById('transcription_text').value = row.transcription_text || '';
    document.getElementById('transcription_tty_preview').value = row.transcription_tty_preview || '';
    document.getElementById('tty_transcription_text').value = row.tty_transcription_text || '';
    normalize_rolodex_title_field();
    normalize_rolodex_details_field();
    normalize_tty_transcription_field();
    document.getElementById('ai_transcription_opt_in').checked = row.ai_transcription_opt_in === 1;
    document.getElementById('ai-transcription-wrap').style.display =
        row.transcription_text ? 'block' : 'none';
    document.getElementById('ai-transcription-tty-wrap').style.display =
        row.transcription_tty_preview ? 'block' : 'none';

    render_audio_playback(row);
    render_rolodex_preview();

    status.innerHTML = '';
}

async function save_audio_file(event) {
    event.preventDefault();

    const status = document.getElementById('edit-audio-status');
    const form = document.getElementById('edit-audio-form');

    status.innerHTML = '<p>Saving…</p>';
    normalize_rolodex_title_field();
    normalize_rolodex_details_field();
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

    currentAudioRow = result.audio_file || currentAudioRow;
    render_rolodex_preview();
    status.innerHTML = '<div class="success">Changes saved.</div>';
}

function open_delete_audio_modal() {
    const modal = document.getElementById('delete-audio-modal');
    const confirmButton = document.getElementById('delete-audio-confirm');

    if (!modal || !confirmButton) {
        return;
    }

    modal.hidden = false;
    confirmButton.focus();
}

function close_delete_audio_modal() {
    const modal = document.getElementById('delete-audio-modal');
    const deleteButton = document.getElementById('delete-audio-button');

    if (!modal || modal.hidden) {
        return;
    }

    modal.hidden = true;

    if (deleteButton) {
        deleteButton.focus();
    }
}

async function delete_audio_file() {
    const status = document.getElementById('edit-audio-status');
    const form = document.getElementById('edit-audio-form');
    const button = document.getElementById('delete-audio-button');
    const confirmButton = document.getElementById('delete-audio-confirm');
    const cancelButton = document.getElementById('delete-audio-cancel');

    status.innerHTML = '<p>Deleting…</p>';
    button.disabled = true;
    confirmButton.disabled = true;
    cancelButton.disabled = true;

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
        confirmButton.disabled = false;
        cancelButton.disabled = false;
        return;
    }

    status.innerHTML = `<div class="success">${escape_html(result.message || 'Audio file deleted.')}</div>`;
    close_delete_audio_modal();
    window.location.href = result.redirect_url || '/dashboard.php?audio_deleted=1';
}

function normalize_tty_transcription_field() {
    const field = document.getElementById('tty_transcription_text');

    if (!field) {
        return;
    }

    field.value = normalize_tty_text(field.value, true);
}

function normalize_rolodex_title_field() {
    const field = document.getElementById('rolodex_title');

    if (!field) {
        return;
    }

    field.value = normalize_rolodex_title_text(field.value);
    render_rolodex_preview();
}

function normalize_rolodex_details_field() {
    const field = document.getElementById('rolodex_details');

    if (!field) {
        return;
    }

    field.value = normalize_rolodex_details_text(field.value);
    render_rolodex_preview();
}

function normalize_rolodex_title_text(value) {
    const text = normalize_rolodex_base_text(value)
        .replace(/\n+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return text.slice(0, ROLODEX_LINE_WIDTH);
}

function normalize_rolodex_details_text(value) {
    const text = normalize_rolodex_base_text(value);
    const wrappedLines = [];

    for (const rawLine of text.split('\n')) {
        wrappedLines.push(...wrap_rolodex_line(rawLine, ROLODEX_LINE_WIDTH));
    }

    const normalizedLines = wrappedLines
        .slice(0, ROLODEX_DETAIL_LINES)
        .map(line => line.replace(/\s+$/g, ''));

    while (normalizedLines.length && normalizedLines[normalizedLines.length - 1] === '') {
        normalizedLines.pop();
    }

    return normalizedLines.join('\n');
}

function normalize_rolodex_base_text(value) {
    return String(value || '')
        .replace(/\r\n?/g, '\n')
        .replace(/\t/g, ' ')
        .replace(/1\/2/g, '½')
        .replace(/1\/4/g, '¼')
        .replace(ROLODEX_ALLOWED_CHARS_REGEX, '');
}

function wrap_rolodex_line(line, width) {
    const trimmed = String(line || '').replace(/^\s+/, '').replace(/\s+$/, '');

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

    return wrapped.length ? wrapped : [''];
}

function render_rolodex_preview() {
    const container = document.getElementById('rolodex-preview');
    const titleField = document.getElementById('rolodex_title');
    const detailsField = document.getElementById('rolodex_details');
    const requestedPhoneField = document.getElementById('requested_phone_number');

    if (!container || !titleField || !detailsField || !requestedPhoneField) {
        return;
    }

    const title = normalize_rolodex_title_text(titleField.value);
    const detailLines = normalize_rolodex_details_text(detailsField.value)
        .split('\n')
        .slice(0, ROLODEX_DETAIL_LINES);

    while (detailLines.length < ROLODEX_DETAIL_LINES) {
        detailLines.push('');
    }

    const exhibitPhone = currentAudioRow && currentAudioRow.exhibit_phone_number
        ? format_phone_number(currentAudioRow.exhibit_phone_number)
        : '';
    const requestedPhone = requestedPhoneField.value
        ? format_phone_number(requestedPhoneField.value)
        : '';
    const phoneLine = exhibitPhone || requestedPhone || '';
    const ttyPhone = currentAudioRow && currentAudioRow.tty_phone_number
        ? `TTY ${format_phone_number(currentAudioRow.tty_phone_number)}`
        : '';

    container.innerHTML = `
        <svg class="rolodex-preview-svg" viewBox="0 0 576 324" role="img" aria-label="Rolodex card preview">
            <defs>
                <linearGradient id="rolodex-card-fill" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#f8efdc"></stop>
                    <stop offset="100%" stop-color="#ebdbc0"></stop>
                </linearGradient>
            </defs>
            <path d="M28 26Q28 10 44 10H532Q548 10 548 26V252Q548 268 532 268H358V322H328V268H248V322H218V268H44Q28 268 28 252Z" fill="url(#rolodex-card-fill)" stroke="#877156" stroke-width="2.6"></path>
            <path d="M43 28Q43 19 52 19H524Q533 19 533 28V246Q533 255 524 255H52Q43 255 43 246Z" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.2"></path>
            <path d="M52 78H524" stroke="#dccdae" stroke-width="1.2"></path>
            <path d="M52 102H524" stroke="#e5d7bf" stroke-width="1.05"></path>
            <path d="M52 126H524" stroke="#e5d7bf" stroke-width="1.05"></path>
            <path d="M52 150H524" stroke="#e5d7bf" stroke-width="1.05"></path>
            <path d="M52 174H524" stroke="#e5d7bf" stroke-width="1.05"></path>
            <path d="M52 198H524" stroke="#e5d7bf" stroke-width="1.05"></path>
            <path d="M52 222H524" stroke="#e5d7bf" stroke-width="1.05"></path>
            ${rolodex_preview_line_svg(title, 56, 63, 'title')}
            ${rolodex_preview_phone_line_svg(phoneLine, ttyPhone, 56, 87)}
            ${rolodex_preview_line_svg(detailLines[0], 56, 111, 'detail-0')}
            ${rolodex_preview_line_svg(detailLines[1], 56, 135, 'detail-1')}
            ${rolodex_preview_line_svg(detailLines[2], 56, 159, 'detail-2')}
            ${rolodex_preview_line_svg(detailLines[3], 56, 183, 'detail-3')}
            ${rolodex_preview_line_svg(detailLines[4], 56, 207, 'detail-4')}
        </svg>
    `;
}

function rolodex_preview_phone_line_svg(phone, ttyPhone, leftX, y) {
    const rightAlignedX = 516 - (ttyPhone.length * 8.65);

    return [
        rolodex_preview_line_svg(phone, leftX, y, 'phone'),
        ttyPhone
            ? rolodex_preview_line_svg(ttyPhone, Math.max(leftX + 190, rightAlignedX), y, 'tty')
            : '',
    ].join('');
}

function rolodex_preview_line_svg(text, startX, y, seedKey) {
    const content = String(text || '').slice(0, ROLODEX_LINE_WIDTH);
    const charWidth = 8.65;
    const pieces = [];

    for (let index = 0; index < content.length; index += 1) {
        const char = content[index];
        const opacity = rolodex_char_opacity(char, `${seedKey}-${index}`);
        const fill = (char === '.' || char === ',') ? '#111111' : '#1f1a17';
        const escapedChar = char === ' '
            ? '&#160;'
            : svg_escape(char);

        pieces.push(
            `<tspan x="${(startX + (index * charWidth)).toFixed(1)}" y="${y}" fill="${fill}" fill-opacity="${opacity}">${escapedChar}</tspan>`
        );
    }

    return `<text font-family="'Courier Prime', 'Courier New', Courier, monospace" font-size="17.6" xml:space="preserve">${pieces.join('')}</text>`;
}

function rolodex_char_opacity(char, seed) {
    if (char === '.' || char === ',') {
        return '1';
    }

    let total = 0;
    const text = String(seed);

    for (let i = 0; i < text.length; i += 1) {
        total = (total + (text.charCodeAt(i) * (i + 3))) % 997;
    }

    return (0.68 + ((total % 27) / 100)).toFixed(2);
}

function svg_escape(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
