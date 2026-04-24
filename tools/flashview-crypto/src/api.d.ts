export class ApiError extends Error {
    status: number;
    errors: Record<string, unknown> | null;
    retryAfter: number | null;
    constructor(message: string, status: number, errors?: Record<string, unknown> | null);
}

export class FlashViewClient {
    baseUrl: string;
    token: string;
    timeout: number;

    constructor(baseUrl: string, token: string, timeout?: number);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    static fetchConfig(baseUrl: string, token?: string | null): Promise<any>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    createSecret(
        encryptedMessage: string,
        expiresIn?: number,
        email?: string | null,
        withVerifiedBadge?: boolean,
    ): Promise<any>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    listSecrets(page?: number): Promise<any>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    getSecretStatus(hashId: string): Promise<any>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    retrieveSecret(hashId: string): Promise<any>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    burnSecret(hashId: string): Promise<any>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    getUser(): Promise<any>;

    prepareFileUpload(): Promise<{
        upload_type: string;
        upload_url: string;
        upload_headers: Record<string, string>;
        token: string;
    }>;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    createSecretWithFileToken(
        fileToken: string,
        encryptedFilename: string,
        fileSize: number,
        fileMimeType: string,
        expiresIn?: number,
        email?: string | null,
        withVerifiedBadge?: boolean,
        encryptedMessage?: string | null,
    ): Promise<any>;

    uploadFile(
        encryptedBuffer: Uint8Array,
        encryptedFilename: string,
        fileSize: number,
        fileMimeType: string,
        expiresIn?: number,
        email?: string | null,
        withVerifiedBadge?: boolean,
        encryptedMessage?: string | null,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ): Promise<any>;

    downloadFile(
        hashId: string,
        onProgress?: ((received: number, total: number) => void) | null,
    ): Promise<Uint8Array>;

    confirmFileDownloaded(hashId: string): Promise<void>;
}
