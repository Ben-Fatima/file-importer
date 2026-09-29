/**
 * API functions for the File Importer application.
 */

/**
 * Helper function to make a fetch request and handle errors.
 * @param {string} url
 * @param {object} options
 * @returns {Promise<any>}
 */
async function requestJson(url, options = {}) {
  let res;

  try {
    res = await fetch(url, options);
  } catch (_) {
    const e = new Error("Network error.");
    e.isNetwork = true;
    throw e;
  }

  const ct = res.headers.get("content-type") || "";
  const isJson = ct.includes("application/json");

  if (!res.ok) {
    let details = "";
    try {
      details = isJson
        ? (await res.json())?.error || (await res.json())?.message || ""
        : await res.text();
    } catch (_) {}

    const e = new Error(details || `Server responded with ${res.status}`);
    e.status = res.status;
    throw e;
  }

  return isJson ? await res.json() : null;
}

/**
 * Initialize an upload session.
 * @param {File} file
 * @returns {Promise<string>} upload ID
 */
export async function initUpload(file) {
  const data = await requestJson("/upload/init", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ filename: file.name, fileSizeBytes: file.size }),
  });
  return data.upload_id;
}

/**
 * Upload a chunk of a file.
 * @param {string} uploadId
 * @param {number} offset
 * @param {Blob} chunk
 * @returns {Promise<object>}
 */
export async function uploadChunk(uploadId, offset, chunk) {
  return requestJson(
    `/upload/chunk?upload_id=${encodeURIComponent(uploadId)}&offset=${offset}`,
    { method: "POST", body: chunk }
  );
}

/**
 * Finalize the upload after all chunks are sent.
 * @param {string} uploadId
 * @param {string} filename
 * @returns {Promise<object>}
 */
export async function uploadComplete(uploadId, filename) {
  return requestJson(
    `/upload/complete?upload_id=${encodeURIComponent(
      uploadId
    )}&original_name=${encodeURIComponent(filename)}`,
    { method: "POST" }
  );
}

/**
 * Perform an import step.
 * @param {string} importId
 * @returns {Promise<object>}
 */
export async function importStep(importId) {
  return requestJson(`/import?id=${encodeURIComponent(importId)}`, {
    method: "POST",
  });
}
