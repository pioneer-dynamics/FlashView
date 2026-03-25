/**
 * Remap `hash_id` to `message_id` in an object or array of objects.
 *
 * @param {object|object[]} obj
 * @returns {object|object[]}
 */
export function renameHashIdKey(obj) {
    if (Array.isArray(obj)) {
        return obj.map(renameHashIdKey);
    }
    if (obj && typeof obj === 'object') {
        const { hash_id, ...rest } = obj;
        const result = { ...rest };
        if (hash_id !== undefined) {
            result.message_id = hash_id;
        }
        return result;
    }
    return obj;
}
