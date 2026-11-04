# FileImporter

A chunked CSV importer in plain PHP + SQLite — plus two measured debugging stories:
why a "chunked" import still ran out of memory, and why deep product pages got
slower the further you paged.

## What it does

- Upload large CSVs in resumable chunks; the browser drives `POST /import`
  repeatedly until the import reports `done`
- Each request processes 1,000 rows and checkpoints a **byte position**, so the
  next request resumes exactly where the reader stopped — the whole file is
  never loaded into memory
- Products list paginates by **cursor** (`WHERE id > :after`), not OFFSET

## Setup

- `cd FileImporter`
- Create the database: `php init_db.php`
- Start the dev server: `php -S localhost:8000 -t public`
- Open the uploader at http://localhost:8000/upload

## CSV format

Headers (four columns, comma-separated):

```
sku,name,price,stock
```

Example row:

```
SKU123,Example product,9.99,10
```

## Routes

- `GET /` → redirects to `/upload`
- `GET /upload` → upload form
- `POST /upload/init` → start a chunked upload
- `POST /upload/chunk` → append a chunk (query: `upload_id`, `offset`)
- `POST /upload/complete` → finalize upload (query: `upload_id`, `original_name`), returns `import_id`
- `POST /import?id=IMPORT_ID` → process the next chunk of the file
- `GET /import/status?id=IMPORT_ID` → current import progress
- `GET /import/errors-report?import_id=IMPORT_ID` → CSV of failed rows
- `GET /products?after=ID` → product list, 50 rows after the given id (`after=0` = first page)

---

## Case study 1 — the "chunked" importer that still ran out of memory

**Symptom.** Importing a 43 MB / 1.2M-row CSV crashed with
`Allowed memory size exhausted` — even though each request only processed 1,000 rows.

**Root cause.** The chunk limit was per-request, but `file($path)` loaded *all*
1.2M lines into an array before slicing the requested chunk. Memory scaled with
total file size, not chunk size — the chunking was cosmetic.

**Fix.** Stream instead: `fopen` the file, `fseek` to the saved byte position,
`fgetcsv` one row at a time, and checkpoint `ftell` after each chunk so the next
request resumes exactly where this one stopped.

**Measured.** Peak memory **157 MB → 2 MB** on the same file; the full
1,200,000-row import completes through the browser UI driving the chunks.

| | |
|---|---|
| Before | `memory/start` branch |
| After | `solution/streaming-import` branch |
| Verify | `php -d memory_limit=128M -S localhost:8000 -t public` (crashes on before), then the commands in `memory-check.txt` |

## Case study 2 — deep pages got slower the further you paged

**Symptom.** `/products` page 1 answered instantly; `?page=20000` made you wait —
same 50-row page, only the depth changed.

**Root cause.** `OFFSET N` doesn't skip — it tells the database to read N+50 rows
and throw the first N away. Work grows with the page number, not the page size.

**Fix.** Keyset pagination: `WHERE id > :after ORDER BY id LIMIT 50` uses the
primary-key index to jump straight to the next page. The controller reads the
last row's id into `nextCursor`, and the Next link carries it as `?after=`.

**Trade-off (honest).** No Previous button and no random page access — forward
only, or "First" to go home. Right trade for a scroll-style list; a
numbered-page UI would need a different approach.

| | |
|---|---|
| Before | `task/keyset-start` branch |
| After | `solution/keyset-pagination` branch |
| Verify | `pagination-check.txt` — SQLite `.timer` on OFFSET vs `WHERE id >`, plus end-to-end curl timing |

## Branches

- `main` — finished state (both fixes applied)
- `memory/start` / `solution/streaming-import` — memory fix, before / after
- `task/keyset-start` / `solution/keyset-pagination` — pagination fix, before / after

Each pair is a complete before/after with its verification commands — runnable
as a self-contained reproduction.
