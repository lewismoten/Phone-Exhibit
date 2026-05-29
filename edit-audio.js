const getAudioId = () => parseInt(document.getElementById('edit-audio-id').value);
const TTY_LINE_WIDTH = 32;
const ROLODEX_LINE_WIDTH = 40;
const ROLODEX_DETAIL_LINES = 5;
const ROLODEX_ALLOWED_CHARS_REGEX = /[^A-Za-z023456789 \n,.\?:;'&\-()@¢£½¼]/gu;
let currentAudioRow = null;
let currentRolodexTargetId = 'rolodex_details';

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
        .getElementById('rolodex_title')
        .addEventListener('focus', () => {
            currentRolodexTargetId = 'rolodex_title';
        });

    document
        .getElementById('rolodex_details')
        .addEventListener('focus', () => {
            currentRolodexTargetId = 'rolodex_details';
        });

    document.querySelectorAll('.rolodex-key-button').forEach(button => {
        button.addEventListener('click', () => {
            insert_rolodex_symbol(button.dataset.insert || '');
        });
    });

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

    update_field_value_preserving_selection(
        field,
        normalize_rolodex_title_text(field.value, true)
    );
    render_rolodex_preview();
}

function normalize_rolodex_details_field() {
    const field = document.getElementById('rolodex_details');

    if (!field) {
        return;
    }

    update_field_value_preserving_selection(
        field,
        normalize_rolodex_details_text(field.value, true)
    );
    render_rolodex_preview();
}

function normalize_rolodex_title_text(value, preserveTrailingSpace = false) {
    const hadTrailingSpace = preserveTrailingSpace && / $/.test(String(value || ''));
    const text = normalize_rolodex_base_text(value)
        .replace(/\n+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const normalized = text.slice(0, ROLODEX_LINE_WIDTH);
    if (hadTrailingSpace && normalized.length < ROLODEX_LINE_WIDTH) {
        return `${normalized} `;
    }

    return normalized;
}

function normalize_rolodex_details_text(value, preserveEditing = false) {
    const text = normalize_rolodex_base_text(value);
    const hadTrailingSpace = preserveEditing && / $/.test(text);
    const hadTrailingNewline = preserveEditing && /\n$/.test(text);
    const wrappedLines = [];

    for (const rawLine of text.split('\n')) {
        wrappedLines.push(...wrap_rolodex_line(rawLine, ROLODEX_LINE_WIDTH));
    }

    const normalizedLines = wrappedLines
        .slice(0, ROLODEX_DETAIL_LINES)
        .map(line => preserveEditing ? line : line.replace(/\s+$/g, ''));

    if (preserveEditing && hadTrailingSpace && normalizedLines.length) {
        const lastIndex = normalizedLines.length - 1;
        if (normalizedLines[lastIndex].length < ROLODEX_LINE_WIDTH) {
            normalizedLines[lastIndex] = `${normalizedLines[lastIndex]} `;
        }
    }

    while (!preserveEditing && normalizedLines.length && normalizedLines[normalizedLines.length - 1] === '') {
        normalizedLines.pop();
    }

    return normalizedLines.join('\n');
}

function update_field_value_preserving_selection(field, nextValue) {
    const normalizedValue = String(nextValue ?? '');
    const selectionStart = field.selectionStart ?? field.value.length;
    const selectionEnd = field.selectionEnd ?? field.value.length;

    if (field.value === normalizedValue) {
        return;
    }

    field.value = normalizedValue;

    const nextStart = Math.min(selectionStart, normalizedValue.length);
    const nextEnd = Math.min(selectionEnd, normalizedValue.length);
    field.setSelectionRange(nextStart, nextEnd);
}

function normalize_rolodex_base_text(value) {
    return String(value || '')
        .replace(/\r\n?/g, '\n')
        .replace(/\t/g, ' ')
        .replace(/1\/2/g, '½')
        .replace(/1\/4/g, '¼')
        .replace(ROLODEX_ALLOWED_CHARS_REGEX, '');
}

function insert_rolodex_symbol(symbol) {
    const target = document.getElementById(currentRolodexTargetId) || document.getElementById('rolodex_details');

    if (!target || !symbol) {
        return;
    }

    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? start;
    target.value = `${target.value.slice(0, start)}${symbol}${target.value.slice(end)}`;
    const nextCursor = start + symbol.length;
    target.focus();
    target.setSelectionRange(nextCursor, nextCursor);

    if (target.id === 'rolodex_title') {
        normalize_rolodex_title_field();
        return;
    }

    normalize_rolodex_details_field();
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
        <svg class="rolodex-preview-svg" viewBox="0 0 88.9 50.8" role="img" aria-label="Rolodex card preview">
            <defs>
                <linearGradient id="rolodex-card-fill" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#f8efdc"></stop>
                    <stop offset="100%" stop-color="#ebdbc0"></stop>
                </linearGradient>
            </defs>
            <rect x="0" y="39.2" width="88.9" height="11.6" fill="#2f2a27"></rect>
            <path
                d="M 4.2751912 0.03824056 L 4.2751912 0.066145833 A 4.3817983 4.3817983 0 0 0 0 4.4462402 A 4.3817983 4.3817983 0 0 0 0.0010335286 4.5335734 L 0 4.5335734 L 0 46.418355 L 0 46.615759 L 0.0046508789 46.615759 A 4.3817983 4.3817983 0 0 0 4.3816447 50.8 A 4.3817983 4.3817983 0 0 0 4.9748901 50.759692 L 31.334005 50.759692 A 0.7070092 0.7070092 0 0 0 31.80426 50.137508 L 31.80426 46.78009 L 31.556213 46.78009 A 1.0795734 1.0795734 0 0 1 31.501436 46.781641 A 1.0795734 1.0795734 0 0 1 31.479732 46.78009 L 31.464746 46.78009 C 31.461446 46.78009 31.458344 46.77964 31.455444 46.77854 A 1.0795734 1.0795734 0 0 1 30.421916 45.731576 A 1.0795734 1.0795734 0 0 1 30.421916 45.728475 L 30.421916 45.724858 A 1.0795734 1.0795734 0 0 1 30.421916 45.701603 A 1.0795734 1.0795734 0 0 1 30.421916 45.678866 L 30.421916 40.683305 C 30.421916 40.682405 30.421833 40.681565 30.421916 40.680721 C 30.421907 40.680421 30.421931 40.67939 30.421916 40.679171 L 30.421916 40.678654 L 30.421916 40.678137 A 1.0795734 1.0795734 0 0 1 30.421916 40.645064 A 1.0795734 1.0795734 0 0 1 31.458545 39.566577 C 31.460045 39.566275 31.461596 39.56572 31.463196 39.565544 C 31.463496 39.565514 31.46397 39.565536 31.464229 39.565544 L 31.464746 39.565544 L 31.485933 39.565544 L 31.516939 39.565544 L 35.06866 39.565544 L 35.099666 39.565544 L 35.120854 39.565544 C 35.122954 39.565544 35.125055 39.566097 35.127055 39.566577 A 1.0795734 1.0795734 0 0 1 36.163684 40.645064 A 1.0795734 1.0795734 0 0 1 36.163167 40.678654 A 1.0795734 1.0795734 0 0 1 36.163167 40.681238 C 36.163217 40.681938 36.163167 40.682632 36.163167 40.683305 L 36.163167 45.662329 A 1.0795734 1.0795734 0 0 1 36.163684 45.701603 A 1.0795734 1.0795734 0 0 1 36.163167 45.709871 L 36.163167 45.728475 C 36.163167 45.731775 36.162717 45.734877 36.161617 45.737777 A 1.0795734 1.0795734 0 0 1 35.129639 46.77854 C 35.126939 46.779638 35.123954 46.78009 35.120854 46.78009 L 35.107935 46.78009 A 1.0795734 1.0795734 0 0 1 35.084163 46.781641 A 1.0795734 1.0795734 0 0 1 35.029386 46.78009 L 34.78134 46.78009 L 34.78134 50.064644 L 34.781856 50.064644 A 0.7070092 0.7070092 0 0 0 34.78134 50.079114 L 34.78134 50.105469 A 0.7070092 0.7070092 0 0 0 35.253145 50.759692 L 53.648405 50.759692 A 0.7070092 0.7070092 0 0 0 54.11866 50.137508 L 54.11866 46.78009 L 53.870614 46.78009 A 1.0795734 1.0795734 0 0 1 53.815837 46.781641 A 1.0795734 1.0795734 0 0 1 53.794132 46.78009 L 53.779146 46.78009 C 53.775846 46.78009 53.772745 46.77964 53.769845 46.77854 A 1.0795734 1.0795734 0 0 1 52.736316 45.731576 A 1.0795734 1.0795734 0 0 1 52.736316 45.728475 L 52.736316 45.724858 A 1.0795734 1.0795734 0 0 1 52.736316 45.701603 A 1.0795734 1.0795734 0 0 1 52.736316 45.678866 L 52.736316 40.683305 C 52.736316 40.682405 52.736233 40.681565 52.736316 40.680721 C 52.736307 40.680521 52.736331 40.67939 52.736316 40.679171 L 52.736316 40.678654 L 52.736316 40.678137 A 1.0795734 1.0795734 0 0 1 52.736316 40.645064 A 1.0795734 1.0795734 0 0 1 53.772945 39.566577 C 53.774445 39.566275 53.775996 39.56572 53.777596 39.565544 C 53.777896 39.565514 53.77837 39.565536 53.77863 39.565544 L 53.779146 39.565544 L 53.800334 39.565544 L 53.83134 39.565544 L 57.383061 39.565544 L 57.414067 39.565544 L 57.435254 39.565544 C 57.437354 39.565544 57.439455 39.566097 57.441455 39.566577 A 1.0795734 1.0795734 0 0 1 58.478084 40.645064 A 1.0795734 1.0795734 0 0 1 58.477568 40.678654 A 1.0795734 1.0795734 0 0 1 58.477568 40.681238 C 58.477618 40.681938 58.477568 40.682632 58.477568 40.683305 L 58.477568 45.662329 A 1.0795734 1.0795734 0 0 1 58.478084 45.701603 A 1.0795734 1.0795734 0 0 1 58.477568 45.709871 L 58.477568 45.728475 C 58.477568 45.731775 58.477634 45.734877 58.476534 45.737777 A 1.0795734 1.0795734 0 0 1 57.444556 46.77854 C 57.441856 46.779638 57.438354 46.78009 57.435254 46.78009 L 57.422335 46.78009 A 1.0795734 1.0795734 0 0 1 57.398564 46.781641 A 1.0795734 1.0795734 0 0 1 57.343787 46.78009 L 57.09574 46.78009 L 57.09574 50.064644 L 57.096257 50.064644 A 0.7070092 0.7070092 0 0 0 57.09574 50.079114 L 57.09574 50.105469 A 0.7070092 0.7070092 0 0 0 57.567546 50.759692 L 84.200545 50.759692 A 4.3817983 4.3817983 0 0 0 84.572616 50.775195 A 4.3817983 4.3817983 0 0 0 88.950126 46.590955 L 88.954777 46.590955 L 88.954777 46.393551 L 88.954777 4.5087687 L 88.953743 4.5087687 A 4.3817983 4.3817983 0 0 0 88.954777 4.4214355 A 4.3817983 4.3817983 0 0 0 84.572616 0.039790853 A 4.3817983 4.3817983 0 0 0 84.557113 0.039790853 L 84.557113 0.03824056 L 4.2751912 0.03824056 z"
                fill="url(#rolodex-card-fill)"
                stroke="#877156"
                stroke-width="0.36"
            ></path>
            <path d="M4.6 1.3H84.3" stroke="rgba(255,255,255,0.3)" stroke-width="0.28"></path>
            <path d="M8.2 11.8H79.6" stroke="#dccdae" stroke-width="0.22"></path>
            <path d="M8.2 15.55H79.6" stroke="#e5d7bf" stroke-width="0.18"></path>
            <path d="M8.2 19.3H79.6" stroke="#e5d7bf" stroke-width="0.18"></path>
            <path d="M8.2 23.05H79.6" stroke="#e5d7bf" stroke-width="0.18"></path>
            <path d="M8.2 26.8H79.6" stroke="#e5d7bf" stroke-width="0.18"></path>
            <path d="M8.2 30.55H79.6" stroke="#e5d7bf" stroke-width="0.18"></path>
            <path d="M8.2 34.3H79.6" stroke="#e5d7bf" stroke-width="0.18"></path>
            ${rolodex_preview_line_svg(title, 9.2, 10.1, 'title')}
            ${rolodex_preview_phone_line_svg(phoneLine, ttyPhone, 9.2, 13.85)}
            ${rolodex_preview_line_svg(detailLines[0], 9.2, 17.6, 'detail-0')}
            ${rolodex_preview_line_svg(detailLines[1], 9.2, 21.35, 'detail-1')}
            ${rolodex_preview_line_svg(detailLines[2], 9.2, 25.1, 'detail-2')}
            ${rolodex_preview_line_svg(detailLines[3], 9.2, 28.85, 'detail-3')}
            ${rolodex_preview_line_svg(detailLines[4], 9.2, 32.6, 'detail-4')}
        </svg>
    `;
}

function rolodex_preview_phone_line_svg(phone, ttyPhone, leftX, y) {
    const rightAlignedX = 77.8 - (ttyPhone.length * 1.34);

    return [
        rolodex_preview_line_svg(phone, leftX, y, 'phone'),
        ttyPhone
            ? rolodex_preview_line_svg(ttyPhone, Math.max(leftX + 28, rightAlignedX), y, 'tty')
            : '',
    ].join('');
}

function rolodex_preview_line_svg(text, startX, y, seedKey) {
    const content = String(text || '').slice(0, ROLODEX_LINE_WIDTH);
    const charWidth = 1.34;
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

    return `<text font-family="'Courier Prime', 'Courier New', Courier, monospace" font-size="2.72" xml:space="preserve">${pieces.join('')}</text>`;
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
