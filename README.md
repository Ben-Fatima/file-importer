# FileImporter project

Simple CSV importer that streams uploads in chunks, stores the file, and processes it into SQLite.

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
- `GET /products?page=N` → paginated products list