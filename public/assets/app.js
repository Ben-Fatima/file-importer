import { assertDom, dom } from "./dom.js";
import { formatBytes } from "./utils.js";
import { initUpload, uploadComplete } from "./api.js";
import {
  setInputDisabled,
  resetUi,
  setFileInfo,
  setUploadProgress,
  setUploadStatus,
  setImportStatus,
  setImportProgress,
  setSummaryText,
  renderErrors,
} from "./ui.js";
import { uploadFileInChunks } from "./uploader.js";
import { pollImport } from "./importer.js";

assertDom();

let isProcessing = false;

dom.fileInput.addEventListener("change", async () => {
  if (isProcessing) return;

  const [file] = dom.fileInput.files;
  if (!file) {
    setFileInfo("");
    setUploadStatus("Waiting for file…");
    return;
  }

  isProcessing = true;
  setInputDisabled(true);

  try {
    resetUi();
    setFileInfo(`${file.name} (${formatBytes(file.size)})`);

    // Upload
    const uploadId = await initUpload(file);
    setUploadStatus("Upload initialized. Starting upload...");

    await uploadFileInChunks(file, uploadId, {
      onProgress: setUploadProgress,
    });

    const complete = await uploadComplete(uploadId, file.name);
    const importId = complete.import_id;
    setUploadStatus(complete.message || "Upload completed.");

    // Import
    setImportStatus("Starting import...");

    const result = await pollImport(importId, file.size, {
      delayMs: 0,
      onProgress: setImportProgress,
    });

    setImportStatus(
      `Import completed. Processed ${result.processedRows} rows.`
    );
    setImportProgress(100);

    setSummaryText(
      `Created: ${result.created}, Updated: ${result.updated}, Failed: ${result.failed}.`
    );

    renderErrors(result.errors, importId, result.failed);
  } catch (e) {
    setUploadStatus(`Failed: ${e?.message || "Unknown error"}`);
  } finally {
    setInputDisabled(false);
    isProcessing = false;
  }
});
