export function generatePassphrase(): string;

export function encryptMessage(
    message: string,
    passphrase?: string | null,
): Promise<{ passphrase: string; secret: string }>;

export function decryptMessage(
    ciphertextString: string,
    passphrase: string,
): Promise<string>;

export function encryptBuffer(
    buffer: Uint8Array,
    passphrase?: string | null,
): Promise<{ passphrase: string; encrypted: Uint8Array }>;

export function decryptBuffer(
    encryptedBuffer: Uint8Array,
    passphrase: string,
): Promise<Uint8Array>;
