/**
 * UI manipulation functions for the File Importer application.
 */
import { dom } from "./dom.js";

/**
 * Enable or disable the file input.
 * @param {boolean} disabled
 */
export function setInputDisabled(disabled) {
  dom.fileInput.disabled = disabled;
  dom.fileInput.classList.toggle("opacity-60", disabled);
  dom.fileInput.classList.toggle("cursor-not-allowed", disabled);
}

/**
 * Reset the UI to the initial state.
 */
export function resetUi() {
  dom.errorsContainer.innerHTML = "";
  dom.messages.textContent = "";
  dom.uploadBar.style.width = "0%";
  dom.importBar.style.width = "0%";
  dom.uploadStatus.textContent = "Initializing upload...";
  dom.importStatus.textContent = "";
}

/**
 * Set the file info text.
 * @param {string} text
 */
export function setFileInfo(text) {
  dom.fileInfo.textContent = text;
}

/**
 * Update the upload progress bar and status text.
 * @param {number} pct
 */
export function setUploadProgress(pct) {
  dom.uploadBar.style.width = `${pct}%`;
  dom.uploadStatus.textContent = `Uploading... ${pct.toFixed(2)}%`;
}

/**
 * Set the upload status text.
 * @param {string} text
 */
export function setUploadStatus(text) {
  dom.uploadStatus.textContent = text;
}

/**
 * Update the import progress bar and status text.
 * @param {number} pct
 */
export function setImportProgress(pct) {
  dom.importBar.style.width = `${pct}%`;
  dom.importStatus.textContent = `Importing... ${pct.toFixed(2)}%`;
}

/**
 * Set the import status text.
 * @param {string} text
 */
export function setImportStatus(text) {
  dom.importStatus.textContent = text;
}

/**
 * Set the summary text.
 * @param {string} text
 */
export function setSummaryText(text) {
  dom.messages.textContent = text;
}

/**
 * Render import errors in the UI.
 * @param {Array} errors
 * @param {string} importId
 * @param {number} totalFailed
 */
export function renderErrors(errors, importId, totalFailed) {
  dom.errorsContainer.innerHTML = "";

  if (!totalFailed || totalFailed === 0) return;

  const visible = errors.slice(0, 5);

  const details = document.createElement("details");
  details.className = "mt-3 border border-red-200 bg-red-50/80 rounded-md p-3";

  const summary = document.createElement("summary");
  summary.className =
    "cursor-pointer text-sm font-semibold text-red-700 flex items-center justify-between";

  summary.innerHTML = `
    <span>${totalFailed} row${
    totalFailed > 1 ? "s" : ""
  } failed during import</span>
    <span class="text-xs text-red-500">Click to view first ${
      visible.length
    }</span>
  `;

  const body = document.createElement("div");
  body.className = "mt-3 space-y-3";

  const list = document.createElement("ul");
  list.className = "space-y-2 text-xs text-red-800";

  visible.forEach((error) => {
    const li = document.createElement("li");
    li.className = "border border-red-100 rounded px-2 py-1 bg-white/60";

    const msg =
      Array.isArray(error.errors) && error.errors.length
        ? error.errors.join(", ")
        : error.message || "Unknown error";

    li.innerHTML = `
      <div class="font-medium">
        Row ${error.row_number ?? "?"}
        ${error.sku ? `(SKU: ${error.sku})` : ""}
      </div>
      <div class="mt-0.5">${msg}</div>
      ${
        error.raw_row
          ? `<div class="mt-1 text-[10px] text-slate-500 break-words">Raw: ${error.raw_row}</div>`
          : ""
      }
    `;
    list.appendChild(li);
  });

  body.appendChild(list);

  if (totalFailed > visible.length) {
    const link = document.createElement("a");
    link.href = `/import/errors-report?import_id=${encodeURIComponent(
      importId
    )}`;
    link.className =
      "inline-flex items-center text-xs font-medium text-red-900 underline underline-offset-2";
    link.textContent = "Download full errors report (CSV)";
    body.appendChild(link);
  }

  details.appendChild(summary);
  details.appendChild(body);
  dom.errorsContainer.appendChild(details);
}
