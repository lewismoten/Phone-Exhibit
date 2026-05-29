<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

html_header('Record Audio');
?>

<h1>Record audio</h1>
<p>Select a microphone, record audio, preview it, then upload.</p>

<div id="error" class="error" style="display:none;"></div>
<div id="success" class="success" style="display:none;"></div>

<form style="border:1px solid #ddd;padding:20px;border-radius:12px;">
    <label>Microphone</label>
    <select id="device"></select>

    <label style="margin-top:10px;">File name</label>
    <input id="filename" value="recording" />

    <div style="margin-top:15px;">
        <button type="button" id="refresh">Refresh</button>
        <button type="button" id="start">Start</button>
        <button type="button" id="stop" disabled>Stop</button>
        <button type="button" id="upload" disabled>Upload</button>
    </div>

    <p>
        <strong>Status:</strong> <span id="status">Idle</span><br>
        <strong>Duration:</strong> <span id="duration">0:00</span><br>
        <strong>Remaining:</strong> <span id="remaining">3:00</span>
    </p>

    <div style="display:flex;align-items:center;gap:16px;margin:12px 0 8px;">
        <svg id="hourglass" viewBox="0 0 64 96" width="54" height="81" aria-hidden="true">
            <defs>
                <clipPath id="hourglass-top-clip">
                    <rect id="hourglass-top-clip-rect" x="20" y="14" width="24" height="19"></rect>
                </clipPath>
                <clipPath id="hourglass-bottom-clip">
                    <rect id="hourglass-bottom-clip-rect" x="20" y="63" width="24" height="19"></rect>
                </clipPath>
            </defs>
            <path d="M14 6h36v8c0 12-8 21-18 28c10 7 18 16 18 28v20H14V70c0-12 8-21 18-28C22 35 14 26 14 14V6z" fill="none" stroke="#4a3c2e" stroke-width="4" stroke-linejoin="round"/>
            <path d="M20 14h24c0 8-5 14-12 19c-7-5-12-11-12-19z" fill="#d8c08b"/>
            <path id="hourglass-top-sand" d="M20 14h24c0 8-5 14-12 19c-7-5-12-11-12-19z" fill="#c49b52" clip-path="url(#hourglass-top-clip)"/>
            <path d="M20 82h24c0-8-5-14-12-19c-7 5-12 11-12 19z" fill="#d8c08b"/>
            <path id="hourglass-bottom-sand" d="M20 82h24c0-8-5-14-12-19c-7 5-12 11-12 19z" fill="#c49b52" opacity="0.25" clip-path="url(#hourglass-bottom-clip)"/>
            <g id="hourglass-stream" opacity="0">
                <circle id="hourglass-stream-dot-1" cx="32" cy="40" r="1.2" fill="#d8c08b"></circle>
                <circle id="hourglass-stream-dot-2" cx="31" cy="46" r="1" fill="#c49b52"></circle>
                <circle id="hourglass-stream-dot-3" cx="33" cy="52" r="1.1" fill="#e6cf97"></circle>
                <circle id="hourglass-stream-dot-4" cx="32" cy="58" r="0.9" fill="#c49b52"></circle>
            </g>
        </svg>
        <div id="recording-progress-text" class="muted">3 minutes maximum</div>
    </div>

    <div style="background:#eee;height:12px;border-radius:10px;">
        <div id="recording-progress" style="height:12px;width:0;background:#b78a42;border-radius:10px;"></div>
    </div>

    <div style="background:#eee;height:12px;border-radius:10px;margin-top:8px;">
        <div id="level" style="height:12px;width:0;background:#444;border-radius:10px;"></div>
    </div>

    <audio id="playback" controls style="margin-top:15px;display:none;"></audio>
</form>

<script>
(() => {
    const device = document.getElementById('device');
    const start = document.getElementById('start');
    const stop = document.getElementById('stop');
    const upload = document.getElementById('upload');
    const refresh = document.getElementById('refresh');
    const playback = document.getElementById('playback');
    const status = document.getElementById('status');
    const duration = document.getElementById('duration');
    const remaining = document.getElementById('remaining');
    const level = document.getElementById('level');
    const recordingProgress = document.getElementById('recording-progress');
    const recordingProgressText = document.getElementById('recording-progress-text');
    const hourglassTopClipRect = document.getElementById('hourglass-top-clip-rect');
    const hourglassBottomClipRect = document.getElementById('hourglass-bottom-clip-rect');
    const hourglassBottomSand = document.getElementById('hourglass-bottom-sand');
    const hourglassStream = document.getElementById('hourglass-stream');
    const hourglassStreamDot1 = document.getElementById('hourglass-stream-dot-1');
    const hourglassStreamDot2 = document.getElementById('hourglass-stream-dot-2');
    const hourglassStreamDot3 = document.getElementById('hourglass-stream-dot-3');
    const hourglassStreamDot4 = document.getElementById('hourglass-stream-dot-4');
    const filename = document.getElementById('filename');
    const errorEl = document.getElementById('error');
    const successEl = document.getElementById('success');
    const MAX_RECORDING_SECONDS = 180;

    let stream, recorder, chunks = [], blob;
    let startTime, timer, audioCtx, analyser;

    function error(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        successEl.style.display = 'none';
    }

    function success(msg) {
        successEl.textContent = msg;
        successEl.style.display = 'block';
        errorEl.style.display = 'none';
    }

    function clearMsg() {
        errorEl.style.display = 'none';
        successEl.style.display = 'none';
    }

    function fmt(sec) {
        sec = Math.floor(sec);
        return Math.floor(sec/60)+":"+String(sec%60).padStart(2,'0');
    }

    function updateTime() {
        if (!startTime) return;
        const elapsedSeconds = (Date.now() - startTime) / 1000;
        duration.textContent = fmt(Math.min(elapsedSeconds, MAX_RECORDING_SECONDS));
        remaining.textContent = fmt(Math.max(0, MAX_RECORDING_SECONDS - elapsedSeconds));
        updateRecordingVisual(Math.min(elapsedSeconds, MAX_RECORDING_SECONDS));

        if (elapsedSeconds >= MAX_RECORDING_SECONDS) {
            stopRecording(true);
        }
    }

    function updateRecordingVisual(elapsedSeconds) {
        const clamped = Math.max(0, Math.min(elapsedSeconds, MAX_RECORDING_SECONDS));
        const progress = clamped / MAX_RECORDING_SECONDS;
        const remainingProgress = 1 - progress;

        recordingProgress.style.width = `${progress * 100}%`;
        recordingProgressText.textContent = `${fmt(MAX_RECORDING_SECONDS - clamped)} remaining`;

        const topMaxHeight = 19;
        const bottomMaxHeight = 19;
        const topHeight = Math.max(0, topMaxHeight * remainingProgress);
        const bottomHeight = Math.max(0, bottomMaxHeight * progress);
        const tick = Math.floor(clamped * 8);

        hourglassTopClipRect.setAttribute('y', String(14 + (topMaxHeight - topHeight)));
        hourglassTopClipRect.setAttribute('height', String(topHeight));

        hourglassBottomClipRect.setAttribute('y', String(82 - bottomHeight));
        hourglassBottomClipRect.setAttribute('height', String(bottomHeight));

        hourglassBottomSand.style.opacity = String(Math.max(0.25, progress));
        hourglassStream.style.opacity = progress > 0 && progress < 1 ? '1' : '0';

        // Offset the falling grains so the stream sparkles instead of reading as a rigid line.
        hourglassStreamDot1.setAttribute('cx', String(31.4 + (tick % 2) * 0.7));
        hourglassStreamDot1.setAttribute('cy', String(39 + (tick % 6)));
        hourglassStreamDot2.setAttribute('cx', String(32.6 - (tick % 2) * 0.8));
        hourglassStreamDot2.setAttribute('cy', String(44 + ((tick + 2) % 6)));
        hourglassStreamDot3.setAttribute('cx', String(31.2 + ((tick + 1) % 3) * 0.5));
        hourglassStreamDot3.setAttribute('cy', String(49 + ((tick + 4) % 6)));
        hourglassStreamDot4.setAttribute('cx', String(32.8 - ((tick + 1) % 3) * 0.4));
        hourglassStreamDot4.setAttribute('cy', String(54 + ((tick + 3) % 6)));
    }

    function resetRecordingVisual() {
        duration.textContent = '0:00';
        remaining.textContent = fmt(MAX_RECORDING_SECONDS);
        recordingProgress.style.width = '0%';
        recordingProgressText.textContent = '3 minutes maximum';
        updateRecordingVisual(0);
    }

    function stopRecording(reachedLimit = false) {
        const elapsedSeconds = startTime ? Math.min((Date.now() - startTime) / 1000, MAX_RECORDING_SECONDS) : 0;

        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }

        if (stream) {
            stream.getTracks().forEach(t => t.stop());
        }

        clearInterval(timer);
        timer = null;
        startTime = null;
        start.disabled = false;
        stop.disabled = true;
        duration.textContent = fmt(elapsedSeconds);
        remaining.textContent = fmt(Math.max(0, MAX_RECORDING_SECONDS - elapsedSeconds));
        updateRecordingVisual(elapsedSeconds);

        if (reachedLimit) {
            status.textContent = 'Recorded (3 minute limit reached)';
        }
    }

    async function loadDevices() {
        clearMsg();
        try {
            const tmp = await navigator.mediaDevices.getUserMedia({audio:true});
            tmp.getTracks().forEach(t=>t.stop());

            const devices = await navigator.mediaDevices.enumerateDevices();
            const mics = devices.filter(d=>d.kind==='audioinput');

            device.innerHTML='';
            mics.forEach((m,i)=>{
                const o = document.createElement('option');
                o.value = m.deviceId;
                o.text = m.label || `Mic ${i+1}`;
                device.appendChild(o);
            });
        } catch(e) {
            error("Microphone access denied.");
        }
    }

    function meter(s) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioCtx.createAnalyser();
        const src = audioCtx.createMediaStreamSource(s);
        src.connect(analyser);

        const data = new Uint8Array(analyser.fftSize);

        function tick() {
            if (!stream || !analyser) {
                return;
            }
            analyser.getByteTimeDomainData(data);
            let peak=0;
            for (let v of data) {
                let n = Math.abs((v-128)/128);
                if (n>peak) peak=n;
            }
            level.style.width = Math.min(100, peak*140)+"%";
            requestAnimationFrame(tick);
        }
        tick();
    }

    start.onclick = async () => {
        clearMsg();
        chunks = [];
        blob = null;
        playback.style.display = 'none';
        playback.removeAttribute('src');
        upload.disabled = true;
        resetRecordingVisual();

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: { deviceId: device.value ? {exact:device.value}:undefined }
            });

            recorder = new MediaRecorder(stream);
            recorder.ondataavailable = e => chunks.push(e.data);

            recorder.onstop = () => {
                blob = new Blob(chunks, {type: recorder.mimeType});
                playback.src = URL.createObjectURL(blob);
                playback.style.display = 'block';
                upload.disabled = false;

                if (status.textContent === 'Recording') {
                    status.textContent = 'Recorded';
                }
            };

            recorder.start();
            startTime = Date.now();
            timer = setInterval(updateTime, 200);
            meter(stream);
            updateRecordingVisual(0);

            status.textContent = 'Recording';
            start.disabled = true;
            stop.disabled = false;

        } catch(e) {
            error("Recording failed.");
        }
    };

    stop.onclick = () => {
        stopRecording(false);
    };

    upload.onclick = async () => {
    clearMsg();
    if (!blob) {
        error("Nothing recorded.");
        return;
    }

    const name = (filename.value || "recording").trim() || "recording";

    let extension = "webm";
    if ((blob.type || "").includes("ogg")) {
        extension = "ogg";
    }

    const file = new File([blob], name + "." + extension, { type: blob.type || "audio/webm" });

    const fd = new FormData();
    fd.append('audio_file', file);
    fd.append('csrf_token', <?= json_encode(csrf_token()) ?>);

    status.textContent = 'Uploading...';
    upload.disabled = true;

    try {
        const res = await fetch('upload-audio.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const contentType = res.headers.get('content-type') || '';
        let message = 'Upload failed.';

        if (contentType.includes('application/json')) {
            const data = await res.json();
            message = data.message || message;

            if (!res.ok || !data.success) {
                throw new Error(message);
            }

            success(message);
            status.textContent = 'Done';
            return;
        }

        const text = await res.text();
        if (!res.ok) {
            throw new Error(text || message);
        }

        success('Uploaded successfully.');
        status.textContent = 'Done';

    } catch (e) {
        error(e.message || "Upload failed.");
        status.textContent = 'Recorded';
        upload.disabled = false;
    }
  };

    refresh.onclick = loadDevices;

    resetRecordingVisual();
    loadDevices();
})();
</script>

<?php html_footer(); ?>
