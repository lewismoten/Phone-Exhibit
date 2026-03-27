<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();

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
        <strong>Duration:</strong> <span id="duration">0:00</span>
    </p>

    <div style="background:#eee;height:12px;border-radius:10px;">
        <div id="level" style="height:12px;width:0;background:#444;"></div>
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
    const level = document.getElementById('level');
    const filename = document.getElementById('filename');
    const errorEl = document.getElementById('error');
    const successEl = document.getElementById('success');

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
        duration.textContent = fmt((Date.now()-startTime)/1000);
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
                status.textContent = 'Recorded';
            };

            recorder.start();
            startTime = Date.now();
            timer = setInterval(updateTime, 200);
            meter(stream);

            status.textContent = 'Recording';
            start.disabled = true;
            stop.disabled = false;

        } catch(e) {
            error("Recording failed.");
        }
    };

    stop.onclick = () => {
        recorder.stop();
        stream.getTracks().forEach(t=>t.stop());
        clearInterval(timer);
        startTime = null;
        start.disabled = false;
        stop.disabled = true;
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

    loadDevices();
})();
</script>

<?php html_footer(); ?>