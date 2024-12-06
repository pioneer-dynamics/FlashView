import OpenCrypto from 'opencrypto'
import { generate, count } from "random-words";

export class encryption {

    validatePassphrase(passphrase) {
        if(passphrase != null) {
            if(passphrase.length < 8) {
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
        const crypt = new OpenCrypto();

        const bytes =  await crypt.getRandomBytes(length);

        const string = this.arrayBufferToHex(bytes);

        return string;
    }

    generatePasssphrase() { 
        return generate({ exactly: 8, join: '-' });
    }

    async encryptMessage(message, passphrase = null)
    {
        this.validatePassphrase(passphrase);

        const crypt = new OpenCrypto();

        passphrase = passphrase ? passphrase : this.generatePasssphrase();

        const salt = await crypt.getRandomBytes(8);

        const derivedKey = await crypt.derivePassphraseKey(passphrase, salt, 64000);

        var enc = new TextEncoder();

        const ciphertext = await crypt.encrypt(derivedKey, enc.encode(message));

        const secret = this.arrayBufferToHex(salt) + ciphertext;

        return({passphrase, secret});
    }

    async decryptMessage(ciphertext, passphrase)
    {
        this.validatePassphrase(passphrase);
     
        const crypt = new OpenCrypto()

        const salt = this.hexToArrayBuffer(ciphertext.slice(0, 16));

        ciphertext = ciphertext.slice(16);

        const derivedKey = await crypt.derivePassphraseKey(passphrase, salt, 64000);

        var enc = new TextDecoder();

        const message = await crypt.decrypt(derivedKey, ciphertext);

        const decodedMessage = enc.decode(message);

        if(decodedMessage.length > 0)
            return decodedMessage;
        else
            throw new Error('Could not decrypt message. Password might be wrong. Message destroyed.');
    }

    // async encryptFile(file, passphrase = null)
    // {
    //     this.validatePassphrase(passphrase);
        
    //     const crypt = new OpenCrypto()

    //     passphrase = passphrase ? passphrase : this.generatePasssphrase();

    //     const salt = await crypt.getRandomBytes(8);

    //     const derivedKey = await crypt.derivePassphraseKey(passphrase, salt, 64000);

    //     var plaintextbytes = await readfile(file)
    //                         .catch(function(err){
    //                             console.error(err);
    //                         });	

	// 	var plaintextbytes = new Uint8Array(plaintextbytes);

    //     const cypherBytes = await crypt.encrypt(derivedKey, plaintextbytes);

    //     cyphertext = new Uint8Array(cypherBytes);

	// 	var resultbytes=new Uint8Array(cyphertext.length+16);
		
    //     resultbytes.set(new TextEncoder("utf-8").encode('Salted__'));

	// 	resultbytes.set(await crypt.getRandomBytes(8), 8);

	// 	resultbytes.set(cyphertext, 16);

    //     var blob=new Blob([resultbytes], {type: 'application/download'});
		
    //     var blobUrl = URL.createObjectURL(blob);

    //     const saltEncoded = this.arrayBufferToHex(salt);

    //     return({passphrase, saltEncoded + blobUrl});
    // }

    // async decryptFile(file, passphrase)
    // {
    //     this.validatePassphrase(passphrase);
        
    //     const crypt = new OpenCrypto()

    //     const derivedKey = await crypt.derivePassphraseKey(passphrase, salt, 64000);

    //     var enc = new TextDecoder();

    //     const message = await crypt.decrypt(derivedKey, file);

    //     return enc.decode(message);
    // }
}