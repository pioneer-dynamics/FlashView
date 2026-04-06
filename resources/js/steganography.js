const MAGIC = 'FVSTEG';
const MAGIC_BITS = MAGIC.length * 8;
const LENGTH_BITS = 32;
const HEADER_BITS = MAGIC_BITS + LENGTH_BITS;

function textToBits(text) {
    return [...new TextEncoder().encode(text)]
        .flatMap((byte) => Array.from({ length: 8 }, (_, i) => (byte >> (7 - i)) & 1));
}

function bitsToText(bits) {
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(bits.slice(i, i + 8).reduce((acc, b, j) => acc | (b << (7 - j)), 0));
    }
    return new TextDecoder().decode(new Uint8Array(bytes));
}

function intTo32Bits(n) {
    return Array.from({ length: 32 }, (_, i) => (n >> (31 - i)) & 1);
}

function bits32ToInt(bits) {
    // Use unsigned right-shift (>>> 0) to ensure a non-negative 32-bit integer
    return bits.reduce((acc, b, i) => (acc | (b << (31 - i))) >>> 0, 0);
}

/**
 * Returns the maximum number of payload bytes that can be stored in an image of the given dimensions.
 */
export function getImageCapacityBytes(width, height) {
    const totalBits = width * height * 3; // 1 LSB per R, G, B channel
    return Math.floor((totalBits - HEADER_BITS) / 8);
}

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = reject;
        img.src = url;
    });
}

/**
 * Embeds text into the LSBs of an image file's pixel data.
 * Returns a PNG Blob with the hidden payload.
 */
export async function embedText(imageFile, text) {
    const img = await loadImage(imageFile);
    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = imageData.data; // RGBA flat array

    const payload = new TextEncoder().encode(text);
    const capacity = getImageCapacityBytes(canvas.width, canvas.height);

    if (payload.length > capacity) {
        throw new Error(
            `Image too small to carry this message. Maximum capacity is ${capacity} bytes for this image. ` +
            'Try uploading a larger image or shortening your message.'
        );
    }

    const magicBits = textToBits(MAGIC);
    const lengthBits = intTo32Bits(payload.length);
    const payloadBits = textToBits(text);
    const allBits = [...magicBits, ...lengthBits, ...payloadBits];

    let bitIndex = 0;
    for (let i = 0; i < pixels.length && bitIndex < allBits.length; i++) {
        if ((i + 1) % 4 === 0) {
            continue; // skip alpha channel
        }
        pixels[i] = (pixels[i] & 0xfe) | allBits[bitIndex++];
    }

    ctx.putImageData(imageData, 0, 0);

    return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}

/**
 * Extracts hidden text from a stego PNG file.
 * Throws if no valid payload is found.
 */
export async function extractText(imageFile) {
    const img = await loadImage(imageFile);
    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);

    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

    const bits = [];
    for (let i = 0; i < pixels.length; i++) {
        if ((i + 1) % 4 === 0) {
            continue; // skip alpha channel
        }
        bits.push(pixels[i] & 1);
    }

    const magic = bitsToText(bits.slice(0, MAGIC_BITS));
    if (magic !== MAGIC) {
        throw new Error('No hidden message found in this image.');
    }

    const length = bits32ToInt(bits.slice(MAGIC_BITS, MAGIC_BITS + LENGTH_BITS));

    // Bounds-check: reject garbage length values from non-stego images
    if (HEADER_BITS + length * 8 > bits.length) {
        throw new Error('No hidden message found in this image.');
    }

    const payloadBits = bits.slice(HEADER_BITS, HEADER_BITS + length * 8);
    return bitsToText(payloadBits);
}
