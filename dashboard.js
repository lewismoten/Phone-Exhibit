let currentPage = 1;
const RECORD_MAX_SECONDS = 180;
let audioUserFilterReady = false;

let recordStream = null;
let recordRecorder = null;
let recordChunks = [];
let recordBlob = null;
let recordObjectUrl = null;
let recordStartTime = null;
let recordTimer = null;
let recordAudioCtx = null;
let recordAnalyser = null;
let recordIsActive = false;
let uploadPreviewObjectUrl = null;

onReady(async () => {
    init_audio_capture_panel();
    maybe_show_audio_deleted_toast();
    await init_audio_user_filter();

    document
        .getElementById('audio-upload-choose')
        .addEventListener('click', event => {
            event.preventDefault();
            document.getElementById('audio_upload_file').click();
        });

    document
        .getElementById('audio_upload_file')
        .addEventListener('change', update_upload_filename_label);

    document
        .getElementById('audio-upload-form')
        .addEventListener('submit', event => {
            event.preventDefault();
            upload_audio_from_panel();
        });

    document
        .getElementById('audio-upload-submit')
        .addEventListener('click', event => {
            event.preventDefault();
            upload_audio_from_panel();
        });

    document
        .getElementById('audio-search-form')
        .addEventListener('submit', event => {
            event.preventDefault();
            load_audio_files(1);
        });

    document
        .getElementById('audio-search-submit')
        .addEventListener('click', event => {
            event.preventDefault();
            load_audio_files(1);
        });

    load_audio_files();
});

async function init_audio_user_filter() {
    const wrap = document.getElementById('audio-user-filter-wrap');
    const select = document.getElementById('audio-user-filter');

    if (!wrap || !select) {
        audioUserFilterReady = true;
        return;
    }

    const result = await api('audio-users');

    if (!result.success) {
        audioUserFilterReady = true;
        return;
    }

    const users = Array.isArray(result.users) ? result.users : [];

    select.innerHTML = users.map(user => `
        <option value="${escape_html(String(user.id ?? 'all'))}">
            ${escape_html(String(user.username ?? ''))} (${Number(user.file_count ?? 0)})
        </option>
    `).join('');

    select.value = 'all';
    wrap.hidden = !result.is_admin;

    select.addEventListener('change', () => {
        load_audio_files(1);
    });

    audioUserFilterReady = true;
}

function maybe_show_audio_deleted_toast() {
    const url = new URL(window.location.href);

    if (url.searchParams.get('audio_deleted') !== '1') {
        return;
    }

    show_toast('Audio file deleted.');
    url.searchParams.delete('audio_deleted');
    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
}

function init_audio_capture_panel() {
    const uploadReveal = document.getElementById('audio-upload-reveal');
    const uploadClose = document.getElementById('audio-upload-close');
    const reveal = document.getElementById('audio-record-reveal');
    const recordClose = document.getElementById('audio-record-close');
    const toggle = document.getElementById('audio-record-toggle');
    const upload = document.getElementById('audio-record-upload');
    const uploadSubmit = document.getElementById('audio-upload-submit');

    if (!uploadReveal || !uploadClose || !reveal || !recordClose || !toggle || !upload || !uploadSubmit) {
        return;
    }

    uploadReveal.addEventListener('click', event => {
        event.preventDefault();
        show_audio_capture_panel('upload');
    });

    uploadClose.addEventListener('click', event => {
        event.preventDefault();
        collapse_upload_panel();
    });

    reveal.addEventListener('click', async event => {
        event.preventDefault();
        show_audio_capture_panel('record');
        await load_record_devices();
    });

    recordClose.addEventListener('click', event => {
        event.preventDefault();
        collapse_record_panel();
    });

    toggle.addEventListener('click', async event => {
        event.preventDefault();

        if (is_anchor_disabled(toggle)) {
            return;
        }

        if (recordIsActive) {
            stop_recording_from_panel(false);
        } else {
            await start_recording_from_panel();
        }
    });

    upload.addEventListener('click', async event => {
        event.preventDefault();

        if (is_anchor_disabled(upload)) {
            return;
        }

        await upload_recording_from_panel();
    });

    reset_recording_visual();
    set_anchor_enabled(toggle, true);
    set_anchor_enabled(upload, false);
    set_anchor_enabled(uploadSubmit, false);
    render_recording_preview(null, null);
    render_upload_preview(null, null);
}

function show_audio_capture_panel(mode) {
    const home = document.getElementById('audio-capture-home');
    const uploadPanel = document.getElementById('audio-upload-panel');
    const recordPanel = document.getElementById('audio-record-panel');

    if (home) {
        home.hidden = true;
    }

    if (uploadPanel) {
        uploadPanel.hidden = mode !== 'upload';
    }

    if (recordPanel) {
        recordPanel.hidden = mode !== 'record';
    }
}

async function upload_audio_from_panel() {
    const form = document.getElementById('audio-upload-form');
    const status = document.getElementById('audio-upload-status');
    const fileInput = document.getElementById('audio_upload_file');
    const submit = document.getElementById('audio-upload-submit');

    if (!form || !status || !fileInput || !submit) {
        return;
    }

    if (!fileInput.files || !fileInput.files.length) {
        status.innerHTML = '<div class="error">Please choose an audio file.</div>';
        return;
    }

    render_upload_progress(status, 'Uploading file…', 0);
    set_anchor_enabled(submit, false);

    const result = await api_upload('upload-audio', {
        method: 'POST',
        data: {
            csrf_token: form.csrf_token.value
        },
        files: {
            audio_file: fileInput.files[0]
        },
        onUploadProgress: event => {
            const percent = event.lengthComputable
                ? Math.round((event.loaded / event.total) * 100)
                : null;
            render_upload_progress(status, 'Uploading file…', percent);
        },
    });

    if (!result.success) {
        status.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to upload audio file.')}</div>`;
        set_anchor_enabled(submit, true);
        return;
    }

    status.innerHTML = `<div class="success">${escape_html(result.message || 'Audio uploaded successfully.')}</div>`;
    show_toast(result.message || 'Audio uploaded successfully.');
    form.reset();
    update_upload_filename_label();
    set_anchor_enabled(submit, true);
    collapse_upload_panel();
    load_audio_files(1);
}

function update_upload_filename_label() {
    const fileInput = document.getElementById('audio_upload_file');
    const submit = document.getElementById('audio-upload-submit');

    if (!fileInput || !submit) {
        return;
    }

    const hasFile = !!(fileInput.files && fileInput.files.length);

    submit.classList.toggle('warn', !hasFile);
    submit.classList.toggle('primary', hasFile);
    set_anchor_enabled(submit, hasFile);

    if (uploadPreviewObjectUrl) {
        URL.revokeObjectURL(uploadPreviewObjectUrl);
        uploadPreviewObjectUrl = null;
    }

    if (hasFile) {
        uploadPreviewObjectUrl = URL.createObjectURL(fileInput.files[0]);
        render_upload_preview(
            uploadPreviewObjectUrl,
            fileInput.files[0].type || 'audio/mpeg',
            fileInput.files[0].name || ''
        );
        return;
    }

    render_upload_preview(null, null, '');
}

function render_upload_preview(url, mimeType, fileName = '') {
    const preview = document.getElementById('audio-upload-preview');

    if (!preview) {
        return;
    }

    preview.title = fileName || '';

    if (!url) {
        preview.className = 'audio-upload-preview audio-record-preview audio-record-preview-disabled';
        preview.innerHTML = `
            <div class="audio-record-preview-row">
                <span class="compact-player compact-player-disabled" aria-hidden="true">
                    <svg class="progress-ring" viewBox="0 0 48 48">
                        <circle class="progress-ring-bg" cx="24" cy="24" r="20"></circle>
                    </svg>
                    <span class="compact-player-button">
                        <span class="compact-player-icon">
                            <svg viewBox="0 0 16 16" class="compact-player-icon-svg">
                                <polygon class="compact-player-play-shape" points="3.5,2 13.5,8 3.5,14"></polygon>
                            </svg>
                        </span>
                    </span>
                </span>
            </div>
        `;
        return;
    }

    preview.className = 'audio-upload-preview audio-record-preview';
    preview.innerHTML = `
        <div class="audio-record-preview-row">
            ${compact_audio_player({
                id: 'upload-panel-preview',
                playback_url: url,
                playback_mime_type: mimeType || 'audio/mpeg',
                file_tooltip: fileName || ''
            })}
        </div>
    `;
}

async function load_record_devices() {
    const status = document.getElementById('audio-record-status');
    const device = document.getElementById('audio-record-device');

    if (!status || !device) {
        return;
    }

    status.innerHTML = '<p>Allow microphone access to choose a device.</p>';

    try {
        const tmp = await navigator.mediaDevices.getUserMedia({ audio: true });
        tmp.getTracks().forEach(track => track.stop());

        const devices = await navigator.mediaDevices.enumerateDevices();
        const mics = devices.filter(item => item.kind === 'audioinput');

        device.innerHTML = '';

        mics.forEach((mic, index) => {
            const option = document.createElement('option');
            option.value = mic.deviceId;
            option.text = mic.label || `Mic ${index + 1}`;
            device.appendChild(option);
        });

        status.innerHTML = '';
    } catch (error) {
        status.innerHTML = '<div class="error">Microphone access denied.</div>';
    }
}

async function start_recording_from_panel() {
    const device = document.getElementById('audio-record-device');
    const state = document.getElementById('audio-record-state');
    const status = document.getElementById('audio-record-status');
    const toggle = document.getElementById('audio-record-toggle');
    const upload = document.getElementById('audio-record-upload');

    if (!device || !state || !status || !toggle || !upload) {
        return;
    }

    status.innerHTML = '';
    recordChunks = [];
    recordBlob = null;

    if (recordObjectUrl) {
        URL.revokeObjectURL(recordObjectUrl);
        recordObjectUrl = null;
    }

    render_recording_preview(null, null);
    reset_recording_visual();
    set_anchor_enabled(upload, false);

    try {
        recordStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                deviceId: device.value ? { exact: device.value } : undefined
            }
        });

        recordRecorder = new MediaRecorder(recordStream);
        recordRecorder.ondataavailable = event => recordChunks.push(event.data);
            recordRecorder.onstop = () => {
                recordBlob = new Blob(recordChunks, { type: recordRecorder.mimeType });
                recordObjectUrl = URL.createObjectURL(recordBlob);
                render_recording_preview(recordObjectUrl, recordRecorder.mimeType);
                set_anchor_enabled(upload, true);
                recordIsActive = false;
                update_record_toggle_button();

                if (state.textContent === 'Recording') {
                    state.textContent = 'Recorded';
                }
            };

        recordRecorder.start();
        recordStartTime = Date.now();
        recordTimer = setInterval(update_recording_timer, 200);
        begin_recording_meter(recordStream);
        update_recording_visual(0);
        recordIsActive = true;
        update_record_toggle_button();

        state.textContent = 'Recording';
    } catch (error) {
        status.innerHTML = '<div class="error">Recording failed.</div>';
        recordIsActive = false;
        update_record_toggle_button();
    }
}

function stop_recording_from_panel(reachedLimit) {
    const state = document.getElementById('audio-record-state');
    const elapsedSeconds = recordStartTime
        ? Math.min((Date.now() - recordStartTime) / 1000, RECORD_MAX_SECONDS)
        : 0;

    if (recordRecorder && recordRecorder.state !== 'inactive') {
        recordRecorder.stop();
    }

    if (recordStream) {
        recordStream.getTracks().forEach(track => track.stop());
        recordStream = null;
    }

    clearInterval(recordTimer);
    recordTimer = null;
    recordStartTime = null;
    recordIsActive = false;
    update_record_toggle_button();

    document.getElementById('audio-record-duration').textContent = fmt_seconds(elapsedSeconds);
    document.getElementById('audio-record-remaining').textContent = fmt_seconds(Math.max(0, RECORD_MAX_SECONDS - elapsedSeconds));
    update_recording_visual(elapsedSeconds);

    if (state && reachedLimit) {
        state.textContent = 'Recorded (limit reached)';
    }
}

async function upload_recording_from_panel() {
    const form = document.getElementById('audio-upload-form');
    const status = document.getElementById('audio-record-status');
    const upload = document.getElementById('audio-record-upload');
    const state = document.getElementById('audio-record-state');
    const toggle = document.getElementById('audio-record-toggle');

    if (!form || !status || !upload || !state || !toggle || !recordBlob) {
        return;
    }

    const name = 'recording';
    let extension = 'webm';

    if ((recordBlob.type || '').includes('ogg')) {
        extension = 'ogg';
    }

    const file = new File([recordBlob], `${name}.${extension}`, {
        type: recordBlob.type || 'audio/webm'
    });

    render_upload_progress(status, 'Uploading recording…', 0);
    set_anchor_enabled(upload, false);
    set_anchor_enabled(toggle, false);
    state.textContent = 'Uploading';

    const result = await api_upload('upload-audio', {
        method: 'POST',
        data: {
            csrf_token: form.csrf_token.value
        },
        files: {
            audio_file: file
        },
        onUploadProgress: event => {
            const percent = event.lengthComputable
                ? Math.round((event.loaded / event.total) * 100)
                : null;
            render_upload_progress(status, 'Uploading recording…', percent);
        },
    });

    if (!result.success) {
        status.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to upload audio file.')}</div>`;
        set_anchor_enabled(upload, true);
        set_anchor_enabled(toggle, true);
        state.textContent = 'Recorded';
        return;
    }

    status.innerHTML = `<div class="success">${escape_html(result.message || 'Audio uploaded successfully.')}</div>`;
    state.textContent = 'Uploaded';
    show_toast(result.message || 'Audio uploaded successfully.');
    collapse_record_panel();
    load_audio_files(1);
}

function render_upload_progress(container, message, percent = null) {
    if (!container) {
        return;
    }

    const clampedPercent = Number.isFinite(percent)
        ? Math.max(0, Math.min(100, percent))
        : null;
    const label = clampedPercent === null
        ? escape_html(message)
        : `${escape_html(message)} ${clampedPercent}%`;
    const width = clampedPercent === null ? 100 : clampedPercent;

    container.innerHTML = `
        <div class="upload-progress" role="status" aria-live="polite">
            <div class="upload-progress-label">${label}</div>
            <div class="upload-progress-track">
                <div class="upload-progress-fill" style="width:${width}%"></div>
            </div>
        </div>
    `;
}

function render_recording_preview(url, mimeType) {
    const preview = document.getElementById('audio-record-preview');

    if (!preview) {
        return;
    }

    if (!url) {
        preview.className = 'audio-record-preview audio-record-preview-disabled';
        preview.innerHTML = `
            <div class="audio-record-preview-row">
                <span class="compact-player compact-player-disabled" aria-hidden="true">
                    <svg class="progress-ring" viewBox="0 0 48 48">
                        <circle class="progress-ring-bg" cx="24" cy="24" r="20"></circle>
                    </svg>
                    <span class="compact-player-button">
                        <span class="compact-player-icon">
                            <svg viewBox="0 0 16 16" class="compact-player-icon-svg">
                                <polygon class="compact-player-play-shape" points="3.5,2 13.5,8 3.5,14"></polygon>
                            </svg>
                        </span>
                    </span>
                </span>
            </div>
        `;
        return;
    }

    preview.className = 'audio-record-preview';
    preview.innerHTML = `
        <div class="audio-record-preview-row">
            ${compact_audio_player({
                id: 'record-panel-preview',
                playback_url: url,
                playback_mime_type: mimeType || 'audio/webm',
                file_tooltip: 'recording'
            })}
        </div>
    `;
}

function update_recording_timer() {
    if (!recordStartTime) {
        return;
    }

    const elapsedSeconds = (Date.now() - recordStartTime) / 1000;
    document.getElementById('audio-record-duration').textContent = fmt_seconds(Math.min(elapsedSeconds, RECORD_MAX_SECONDS));
    document.getElementById('audio-record-remaining').textContent = fmt_seconds(Math.max(0, RECORD_MAX_SECONDS - elapsedSeconds));
    update_recording_visual(Math.min(elapsedSeconds, RECORD_MAX_SECONDS));

    if (elapsedSeconds >= RECORD_MAX_SECONDS) {
        stop_recording_from_panel(true);
    }
}

function update_recording_visual(elapsedSeconds) {
    const clamped = Math.max(0, Math.min(elapsedSeconds, RECORD_MAX_SECONDS));
    const progress = clamped / RECORD_MAX_SECONDS;
    const remainingProgress = 1 - progress;
    const topMaxHeight = 19;
    const bottomMaxHeight = 19;
    const topHeight = Math.max(0, topMaxHeight * remainingProgress);
    const bottomHeight = Math.max(0, bottomMaxHeight * progress);
    const tick = Math.floor(clamped * 8);

    document.getElementById('audio-record-progress').style.width = `${progress * 100}%`;
    document.getElementById('audio-record-hourglass-top-clip-rect').setAttribute('y', String(14 + (topMaxHeight - topHeight)));
    document.getElementById('audio-record-hourglass-top-clip-rect').setAttribute('height', String(topHeight));
    document.getElementById('audio-record-hourglass-bottom-clip-rect').setAttribute('y', String(82 - bottomHeight));
    document.getElementById('audio-record-hourglass-bottom-clip-rect').setAttribute('height', String(bottomHeight));
    document.getElementById('audio-record-hourglass-bottom-sand').style.opacity = String(Math.max(0.25, progress));
    document.getElementById('audio-record-hourglass-stream').style.opacity = progress > 0 && progress < 1 ? '1' : '0';
    document.getElementById('audio-record-hourglass-stream-dot-1').setAttribute('cx', String(31.4 + (tick % 2) * 0.7));
    document.getElementById('audio-record-hourglass-stream-dot-1').setAttribute('cy', String(39 + (tick % 6)));
    document.getElementById('audio-record-hourglass-stream-dot-2').setAttribute('cx', String(32.6 - (tick % 2) * 0.8));
    document.getElementById('audio-record-hourglass-stream-dot-2').setAttribute('cy', String(44 + ((tick + 2) % 6)));
    document.getElementById('audio-record-hourglass-stream-dot-3').setAttribute('cx', String(31.2 + ((tick + 1) % 3) * 0.5));
    document.getElementById('audio-record-hourglass-stream-dot-3').setAttribute('cy', String(49 + ((tick + 4) % 6)));
    document.getElementById('audio-record-hourglass-stream-dot-4').setAttribute('cx', String(32.8 - ((tick + 1) % 3) * 0.4));
    document.getElementById('audio-record-hourglass-stream-dot-4').setAttribute('cy', String(54 + ((tick + 3) % 6)));
}

function reset_recording_visual() {
    document.getElementById('audio-record-state').textContent = 'Idle';
    document.getElementById('audio-record-duration').textContent = '0:00';
    document.getElementById('audio-record-remaining').textContent = fmt_seconds(RECORD_MAX_SECONDS);
    document.getElementById('audio-record-progress').style.width = '0%';
    document.getElementById('audio-record-level').style.height = '0%';
    update_recording_visual(0);
}

function begin_recording_meter(stream) {
    recordAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
    recordAnalyser = recordAudioCtx.createAnalyser();
    const src = recordAudioCtx.createMediaStreamSource(stream);
    src.connect(recordAnalyser);

    const data = new Uint8Array(recordAnalyser.fftSize);

    function tick() {
        if (!recordStream || !recordAnalyser) {
            return;
        }

        recordAnalyser.getByteTimeDomainData(data);
        let peak = 0;

        for (const value of data) {
            const normalized = Math.abs((value - 128) / 128);
            if (normalized > peak) {
                peak = normalized;
            }
        }

        document.getElementById('audio-record-level').style.height = `${Math.min(100, peak * 140)}%`;
        requestAnimationFrame(tick);
    }

    tick();
}

function set_anchor_enabled(element, enabled) {
    if (!element) {
        return;
    }

    element.classList.toggle('is-disabled', !enabled);
    element.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    element.tabIndex = enabled ? 0 : -1;
}

function is_anchor_disabled(element) {
    return !element || element.classList.contains('is-disabled') || element.getAttribute('aria-disabled') === 'true';
}

function update_record_toggle_button() {
    const toggle = document.getElementById('audio-record-toggle');

    if (!toggle) {
        return;
    }

    toggle.textContent = recordIsActive ? 'Stop' : 'Record';
}

function collapse_record_panel() {
    const home = document.getElementById('audio-capture-home');
    const panel = document.getElementById('audio-record-panel');
    const status = document.getElementById('audio-record-status');
    const device = document.getElementById('audio-record-device');

    if (recordRecorder && recordRecorder.state !== 'inactive') {
        recordRecorder.stop();
    }

    if (recordStream) {
        recordStream.getTracks().forEach(track => track.stop());
    }

    if (recordAudioCtx) {
        recordAudioCtx.close();
    }

    if (home) {
        home.hidden = false;
    }

    if (panel) {
        panel.hidden = true;
    }

    if (device) {
        device.innerHTML = '';
    }

    if (recordObjectUrl) {
        URL.revokeObjectURL(recordObjectUrl);
        recordObjectUrl = null;
    }

    recordStream = null;
    recordRecorder = null;
    recordChunks = [];
    recordBlob = null;
    recordStartTime = null;
    recordAudioCtx = null;
    recordAnalyser = null;
    clearInterval(recordTimer);
    recordTimer = null;
    recordIsActive = false;
    update_record_toggle_button();
    reset_recording_visual();
    render_recording_preview(null, null);
    set_anchor_enabled(document.getElementById('audio-record-upload'), false);

    if (status) {
        status.innerHTML = '';
    }
}

function collapse_upload_panel() {
    const home = document.getElementById('audio-capture-home');
    const panel = document.getElementById('audio-upload-panel');
    const form = document.getElementById('audio-upload-form');
    const status = document.getElementById('audio-upload-status');
    const submit = document.getElementById('audio-upload-submit');

    if (home) {
        home.hidden = false;
    }

    if (panel) {
        panel.hidden = true;
    }

    if (form) {
        form.reset();
    }

    if (uploadPreviewObjectUrl) {
        URL.revokeObjectURL(uploadPreviewObjectUrl);
        uploadPreviewObjectUrl = null;
    }

    update_upload_filename_label();

    if (status) {
        status.innerHTML = '';
    }

    set_anchor_enabled(submit, true);
}

function fmt_seconds(sec) {
    sec = Math.floor(sec);
    return `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`;
}

async function load_audio_files(page = 1) {
    currentPage = page;

    const searchInput = document.getElementById('q');
    const userFilter = document.getElementById('audio-user-filter');
    const results = document.getElementById('audio-results');
    const pagination = document.getElementById('audio-pagination');
    const q = searchInput ? searchInput.value.trim() : '';
    const userId = userFilter ? userFilter.value : 'all';

    if (!audioUserFilterReady) {
        results.innerHTML = '<p>Loading…</p>';
        pagination.innerHTML = '';
        return;
    }

    results.innerHTML = '<p>Loading…</p>';
    pagination.innerHTML = '';

    const result = await api('audio-files', { data: { q, page, user_id: userId } });

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
    const player = row.playback_url
        ? compact_audio_player(row)
        : `<span class="muted">Unavailable</span>`;
    const classificationLabel = row.paper_classification_label || '';
    const classificationColor = row.paper_classification_color || '';
    const classificationDescription = row.paper_classification_description || '';
    const statusLabel = ({
        assigned: 'Assigned',
        requested: 'Requested',
        unassigned: 'Unassigned',
    })[phoneStatus] || 'Unassigned';
    const categorizationLabel = classificationLabel
        ? `${classificationLabel}${classificationDescription ? ` - ${classificationDescription}` : ''}`
        : 'None';
    const tooltip = `Status: ${statusLabel}\nCategorization: ${categorizationLabel}`;
    const swatch = `
        <span
            class="audio-file-classification-swatch${phoneStatus === 'assigned' ? ' audio-file-classification-swatch-assigned' : ''}${classificationColor ? '' : ' audio-file-classification-swatch-empty'}"
            ${classificationColor ? `style="background:${escape_html(classificationColor)};"` : ''}
            title="${escape_html(tooltip)}"
            aria-label="${escape_html(tooltip)}"
        >${phoneStatus === 'assigned' ? '<span class="audio-file-phone-indicator" aria-hidden="true">☎</span>' : ''}</span>
    `;

    return `
        <tr>
            <td class="audio-file-title">
                <a href="edit-audio.php?id=${encodeURIComponent(row.id)}">
                    ${escape_html(row.title)}
                </a>
            </td>

            <td class="audio-file-phone">
                <span class="audio-file-phone-inline" title="${escape_html(tooltip)}" aria-label="${escape_html(tooltip)}">
                    ${swatch}
                    <span>${escape_html(phone)}</span>
                </span>
            </td>

            <td class="audio-file-audio">${player}</td>

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
        html += `<a class="button" href="#" onclick="load_audio_files(${page - 1}); return false;">« Previous</a> `;
    }

    html += `<span class="audio-pagination-status">Page ${page} of ${totalPages}</span>`;

    if (page < totalPages) {
        html += ` <a class="button" href="#" onclick="load_audio_files(${page + 1}); return false;">Next »</a>`;
    }

    pagination.innerHTML = html;
}
