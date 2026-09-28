/**
 * Starts a camera barcode scan inside the element with the given id and returns a stop function.
 * html5-qrcode is loaded on demand so the main bundle stays small.
 */
export async function startScanner(elementId, onCode) {
    const { Html5Qrcode, Html5QrcodeSupportedFormats: Formats } = await import('html5-qrcode');

    const scanner = new Html5Qrcode(elementId, {
        formatsToSupport: [Formats.EAN_13, Formats.EAN_8, Formats.UPC_A, Formats.UPC_E],
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        verbose: false,
    });

    let handled = false;

    await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 260, height: 150 } },
        (code) => {
            if (!handled) {
                handled = true;
                onCode(code);
            }
        },
        () => {},
    );

    return async () => {
        try {
            await scanner.stop();
            scanner.clear();
        } catch {
            // Already stopped.
        }
    };
}

/** Downscales a photo so the upload stays small; falls back to the original file if the browser cannot decode it. */
export async function shrinkImage(file, maxSide = 1600) {
    try {
        const bitmap = await createImageBitmap(file);
        const ratio = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * ratio);
        canvas.height = Math.round(bitmap.height * ratio);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));

        return blob ? new File([blob], 'meal.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}
