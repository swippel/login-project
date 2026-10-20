const qr = document.getElementById('qrcode');
if (qr && window.QRCode) {
    new QRCode(qr, { text: qr.dataset.uri, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
}
