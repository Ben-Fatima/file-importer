/**
 * References to DOM elements.
 */
export const dom = {
  fileInput: document.getElementById("file-input"),
  fileInfo: document.getElementById("file-info"),
  uploadStatus: document.getElementById("upload-status"),
  importStatus: document.getElementById("import-status"),
  uploadBar: document.getElementById("upload-progress-bar"),
  importBar: document.getElementById("import-progress-bar"),
  messages: document.getElementById("messages"),
  errorsContainer: document.getElementById("errors"),
};

export function assertDom() {
  for (const [key, el] of Object.entries(dom)) {
    if (!el) throw new Error(`Missing DOM element: ${key}`);
  }
}
