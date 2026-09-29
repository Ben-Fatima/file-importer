import { uploadChunk } from "./api.js";

/**
 * Upload a chunk with retry logic for transient errors.
 * @param {string} uploadId
 * @param {number} offset
 * @param {Blob} chunk
 * @param {number} retries
 * @returns {Promise<object>}
 */
async function uploadChunkWithRetry(uploadId, offset, chunk, retries = 3) {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      return await uploadChunk(uploadId, offset, chunk);
    } catch (e) {
      const status = e?.status;
      const isNetwork = e?.isNetwork === true;
      const is5xx = typeof status === "number" && status >= 500;

      if (attempt === retries || (!isNetwork && !is5xx)) throw e;

      await new Promise((r) => setTimeout(r, 300 * attempt));
    }
  }
}

/**
 * Upload a file in chunks with progress reporting.
 * @param {File} file
 * @param {string} uploadId
 * @param {object} options
 * @param {number} [options.chunkSize]
 * @param {function} [options.onProgress]
 * @returns {Promise<void>}
 */
export async function uploadFileInChunks(
  file,
  uploadId,
  { chunkSize, onProgress } = {}
) {
  const size = file.size;
  const cs = chunkSize ?? 1024 * 1024 * 10;

  let offset = 0;
  while (offset < size) {
    const chunk = file.slice(offset, offset + cs);
    await uploadChunkWithRetry(uploadId, offset, chunk, 3);

    offset += chunk.size;
    const pct = Math.min((offset / size) * 100, 100);
    onProgress?.(pct);
  }
}
