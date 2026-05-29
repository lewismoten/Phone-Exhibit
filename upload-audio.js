onReady(() => {
    const form = document.getElementById('upload-audio-form');

    if (!form) {
        return;
    }

    form.addEventListener('submit', upload_audio_file);
});

async function upload_audio_file(event) {
    event.preventDefault();

    const form = document.getElementById('upload-audio-form');
    const status = document.getElementById('upload-audio-status');
    const fileInput = document.getElementById('audio_file');

    if (!form || !status || !fileInput) {
        return;
    }

    if (!fileInput.files || !fileInput.files.length) {
        status.innerHTML = '<div class="error">Please choose an audio file.</div>';
        return;
    }

    status.innerHTML = '<p>Uploading…</p>';

    const result = await api('upload-audio', {
        method: 'POST',
        data: {
            csrf_token: form.csrf_token.value
        },
        files: {
            audio_file: fileInput.files[0]
        }
    });

    if (!result.success) {
        status.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to upload audio file.')}</div>`;
        return;
    }

    status.innerHTML = `<div class="success">${escape_html(result.message || 'Audio uploaded successfully.')}</div>`;
    window.location.href = 'audio-files.php';
}
