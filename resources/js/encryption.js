import { encryptMessage as sharedEncrypt, decryptMessage as sharedDecrypt, generatePassphrase, encryptBuffer, decryptBuffer } from '@pioneer-dynamics/flashview-crypto';

export class encryption {

    validatePassphrase(passphrase) {
        if (passphrase != null) {
            if (passphrase.length < 8) {
                throw new Error('Passphrase must be at least 8 characters');
            }
        }
    }

    arrayBufferToHex(buffer) {
        return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, '0')).join('');
    }

    hexToArrayBuffer(hex) {
        const bytes = new Uint8Array(hex.length / 2);
        for (let i = 0; i < hex.length; i += 2) {
            bytes[i / 2] = parseInt(hex.slice(i, i + 2), 16);
        }
        return bytes.buffer;
    }

    async generateRandomString(length) {
        const bytes = new Uint8Array(length);
        globalThis.crypto.getRandomValues(bytes);
        return this.arrayBufferToHex(bytes.buffer);
    }

    generatePasssphrase() {
        return generatePassphrase();
    }

    async encryptMessage(message, passphrase = null) {
        this.validatePassphrase(passphrase);
        return sharedEncrypt(message, passphrase);
    }

    async decryptMessage(ciphertext, passphrase) {
        try {
            this.validatePassphrase(passphrase);
            const decoded = await sharedDecrypt(ciphertext, passphrase);
            if (decoded?.length > 0) {
                return decoded;
            } else {
                throw new Error();
            }
        } catch (error) {
            throw new Error('Could not decrypt message. Password might be wrong. Message destroyed.');
        }
    }

    async encryptFile(file, passphrase = null) {
        const buffer = await file.arrayBuffer();
        const { encrypted, passphrase: resolvedPassphrase } = await encryptBuffer(new Uint8Array(buffer), passphrase);
        return { encryptedBuffer: encrypted, passphrase: resolvedPassphrase };
    }

    async decryptFile(encryptedUint8Array, passphrase) {
        this.validatePassphrase(passphrase);
        return decryptBuffer(encryptedUint8Array, passphrase);
    }
}
