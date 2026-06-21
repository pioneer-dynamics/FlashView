import {
    encryptMessage as sharedEncrypt, decryptMessage as sharedDecrypt,
    generatePassphrase, encryptBuffer, decryptBuffer,
    encryptToBlob, decryptFromBlob,
    encryptFileToBuffer, decryptFileFromBuffer,
    generateFileKey, wrapFileKey, unwrapFileKey,
    deriveAuthKey, computeVerifier, generateChallenge, deriveUpdateToken,
    deriveSigningKeypair, signChallenge,
    deriveKeyFromFile, combineLockerKeyMaterials,
    generateCallEphemeralKeypair, generateCallSessionAesKey,
    wrapCallSessionKey, unwrapCallSessionKey,
    deriveCallKeyPair, signCallChallenge,
} from '@pioneer-dynamics/flashview-crypto';
export { LockerBlobVersionError, LockerDecryptionError } from '@pioneer-dynamics/flashview-crypto';

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
        this.validatePassphrase(passphrase);
        const buffer = await file.arrayBuffer();
        const { encrypted, passphrase: resolvedPassphrase } = await encryptBuffer(new Uint8Array(buffer), passphrase);
        return { encryptedBuffer: encrypted, passphrase: resolvedPassphrase };
    }

    async decryptFile(encryptedUint8Array, passphrase) {
        this.validatePassphrase(passphrase);
        return decryptBuffer(encryptedUint8Array, passphrase);
    }

    async encryptLockerContent(content, passphrase) {
        return encryptToBlob(content, passphrase);
    }

    async decryptLockerContent(blob, passphrase) {
        return decryptFromBlob(blob, passphrase);
    }

    async encryptLockerFileToBuffer(buffer, options) {
        return encryptFileToBuffer(buffer, options);
    }

    async decryptLockerFileFromBuffer(buffer, options) {
        return decryptFileFromBuffer(buffer, options);
    }

    generateLockerFileKey() {
        return generateFileKey();
    }

    async wrapLockerFileKey(dek, passphrase, accountId) {
        return wrapFileKey(dek, passphrase, accountId);
    }

    async unwrapLockerFileKey(wrappedKey, passphrase, accountId) {
        return unwrapFileKey(wrappedKey, passphrase, accountId);
    }

    async deriveLockerAuthKey(passphrase, accountId) {
        return deriveAuthKey(passphrase, accountId);
    }

    async computeLockerVerifier(authKey, challenge) {
        return computeVerifier(authKey, challenge);
    }

    generateLockerChallenge() {
        return generateChallenge();
    }

    async deriveLockerUpdateToken(passphrase, accountId) {
        return deriveUpdateToken(passphrase, accountId);
    }

    async deriveLockerSigningKeypair(passphrase, accountId) {
        return deriveSigningKeypair(passphrase, accountId);
    }

    async signLockerChallenge(privateKey, challengeHex) {
        return signChallenge(privateKey, challengeHex);
    }

    async deriveLockerKeyFromFile(fileBuffer) {
        return deriveKeyFromFile(fileBuffer);
    }

    async combineLockerKeyMaterials(materials) {
        return combineLockerKeyMaterials(materials);
    }

    async deriveCallKeyPair(password, saltBase64) {
        return deriveCallKeyPair(password, saltBase64);
    }

    signCallChallenge(privateKeyBytes, challengeHex) {
        return signCallChallenge(privateKeyBytes, challengeHex);
    }

    async generateCallEphemeralKeypair() {
        return generateCallEphemeralKeypair();
    }

    generateCallSessionAesKey() {
        return generateCallSessionAesKey();
    }

    async wrapCallSessionKey(sessionKeyBase64, peerPublicKeyBase64, ownPrivateKeyBase64) {
        return wrapCallSessionKey(sessionKeyBase64, peerPublicKeyBase64, ownPrivateKeyBase64);
    }

    async unwrapCallSessionKey(wrappedKeyBase64, ownPrivateKeyBase64, peerPublicKeyBase64) {
        return unwrapCallSessionKey(wrappedKeyBase64, ownPrivateKeyBase64, peerPublicKeyBase64);
    }
}
