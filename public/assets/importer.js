import { importStep } from "./api.js";
import { sleep } from "./utils.js";

/**
 * Poll the import status until completion.
 * @param {string} importId
 * @param {number} fileSize
 * @param {number} [options.delayMs]
 * @param {function} [options.onProgress]
 * @returns {Promise<object>}
 */
export async function pollImport(
  importId,
  fileSize,
  { delayMs = 0, onProgress } = {}
) {
  let created = 0,
    updated = 0,
    failed = 0;
  const collectedErrors = [];

  while (true) {
    const data = await importStep(importId);

    const pct = Math.min((data.bytes_processed / fileSize) * 100, 100);
    onProgress?.(pct);

    created += data.created;
    updated += data.updated;
    failed += data.failed;

    if (data.errors?.length) collectedErrors.push(...data.errors);

    if (data.done) {
      return {
        created,
        updated,
        failed,
        processedRows: data.processed_rows,
        errors: collectedErrors,
      };
    }

    if (delayMs > 0) await sleep(delayMs);
  }
}
