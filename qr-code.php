<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'phone.codejamboree.com';
$defaultUrl = $scheme . '://' . $host;

html_header('QR Code Generator');
?>

<h1>QR Code Generator</h1>

<p>
    Generate customizable QR codes for the exhibit, flyers, TTY pages,
    phone numbers, or contribution links.
</p>

<div style="display:grid;grid-template-columns:minmax(300px,500px) 1fr;gap:24px;align-items:start;">

    <div>

        <label for="qrText"><strong>Content</strong></label><br>
        <textarea
            id="qrText"
            rows="4"
            style="width:100%;"
        ><?= e($defaultUrl) ?></textarea>

        <br><br>

        <label for="width"><strong>Width</strong></label><br>
        <input id="width" type="number" value="320" min="64" max="2048">

        <br><br>

        <label for="height"><strong>Height</strong></label><br>
        <input id="height" type="number" value="320" min="64" max="2048">

        <br><br>

        <label for="correction"><strong>Error Correction</strong></label><br>
        <select id="correction">
            <option value="L">L - Low (~7%)</option>
            <option value="M">M - Medium (~15%)</option>
            <option value="Q">Q - Quartile (~25%)</option>
            <option value="H" selected>H - High (~30%)</option>
        </select>

        <br><br>

        <label for="foreground"><strong>Foreground Color</strong></label><br>
        <input id="foreground" type="color" value="#000000">

        <br><br>

        <label for="background"><strong>Background Color</strong></label><br>
        <input id="background" type="color" value="#ffffff">

        <br><br>

        <label for="margin"><strong>Padding</strong></label><br>
        <input id="margin" type="number" value="16" min="0" max="128">

        <br><br>

        <button type="button" id="downloadPng">
            Download PNG
        </button>

    </div>

    <div>

        <div
            id="qrWrapper"
            style="display:inline-block;background:#fff;border:1px solid #ddd;"
        >
            <div id="qrcode"></div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
(function () {

    const qrContainer = document.getElementById('qrcode');
    const qrWrapper = document.getElementById('qrWrapper');

    const qrText = document.getElementById('qrText');
    const width = document.getElementById('width');
    const height = document.getElementById('height');
    const correction = document.getElementById('correction');
    const foreground = document.getElementById('foreground');
    const background = document.getElementById('background');
    const margin = document.getElementById('margin');

    const downloadPng = document.getElementById('downloadPng');

    let qr = null;

    const correctionMap = {
        L: QRCode.CorrectLevel.L,
        M: QRCode.CorrectLevel.M,
        Q: QRCode.CorrectLevel.Q,
        H: QRCode.CorrectLevel.H
    };

    function generateQr() {

        qrContainer.innerHTML = '';

        qrWrapper.style.padding = margin.value + 'px';
        qrWrapper.style.background = background.value;

        qr = new QRCode(qrContainer, {
            text: qrText.value.trim(),
            width: parseInt(width.value, 10),
            height: parseInt(height.value, 10),
            colorDark: foreground.value,
            colorLight: background.value,
            correctLevel: correctionMap[correction.value]
        });
    }

    [
        qrText,
        width,
        height,
        correction,
        foreground,
        background,
        margin
    ].forEach(el => {
        el.addEventListener('input', generateQr);
        el.addEventListener('change', generateQr);
    });

    downloadPng.addEventListener('click', function () {

        const canvas = qrContainer.querySelector('canvas');
        const img = qrContainer.querySelector('img');

        let dataUrl = null;

        if (canvas) {
            dataUrl = canvas.toDataURL('image/png');
        } else if (img) {
            dataUrl = img.src;
        }

        if (!dataUrl) {
            alert('QR code not ready.');
            return;
        }

        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = 'qr-code.png';
        a.click();
    });

    generateQr();

})();
</script>

<?php html_footer(); ?>