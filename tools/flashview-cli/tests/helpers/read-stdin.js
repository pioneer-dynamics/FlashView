/**
 * Minimal helper that imports readStdin() logic and writes the result to stdout.
 * Used by regression tests to verify stdin content preservation.
 */
const chunks = [];
for await (const chunk of process.stdin) {
    chunks.push(chunk);
}
const result = Buffer.concat(chunks).toString('utf8');
process.stdout.write(result);
