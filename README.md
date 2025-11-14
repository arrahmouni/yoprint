# YoPrint

YoPrint is a small Laravel application to upload CSV files and process them in the background. The UI uses Tailwind + Alpine and Vite for asset bundling. Uploaded files are tracked in the database via the `file_uploads` table and processed by a queued job.

## Features

- Upload CSV files via a web UI ([resources/views/upload.blade.php](resources/views/upload.blade.php))
- Background processing using a queued job ([App\Jobs\ProcessCsvUpload](app/Jobs/ProcessCsvUpload.php))
- Track processing progress with the `FileUpload` model ([App\Models\FileUpload](app/Models/FileUpload.php))
- Validation via a form request ([App\Http\Requests\StoreFileRequest](app/Http/Requests/StoreFileRequest.php))
- API endpoints and controller at the web routes ([routes/web.php](routes/web.php) — controller: [`App\Http\Controllers\FileUploadController`](app/Http/Controllers/FileUploadController.php))

## Quickstart

1. Clone the repository and install PHP dependencies:
   ```sh
   composer install
   ```

2. Install frontend dependencies and build assets:
   ```sh
   npm install
   npm run dev    # or `npm run build` for production
   ```

3. Copy the environment file and set values:
   ```sh
   cp .env.example .env
   # edit .env to set DB_*, QUEUE_CONNECTION, MAIL_*, etc.
   ```

4. Generate the app key:
   ```sh
   php artisan key:generate
   ```

5. Run database migrations:
   ```sh
   php artisan migrate
   ```

6. Create a storage symlink (for public file access):
   ```sh
   php artisan storage:link
   ```

7. Start a queue worker to process uploaded CSVs:
   ```sh
   php artisan queue:work
   ```
   (Alternatively use `php artisan queue:listen` or a supervisor in production.)

8. Serve the application locally:
   ```sh
   php artisan serve
   ```
   Then open http://127.0.0.1:8000 to access the upload UI.

## Endpoints

- GET / — upload UI handled by [`App\Http\Controllers\FileUploadController@index`](app/Http/Controllers/FileUploadController.php) ([routes/web.php](routes/web.php))
- POST /upload — upload CSV file via [`App\Http\Requests\StoreFileRequest`](app/Http/Requests/StoreFileRequest.php) and processed by [`App\Services\FileUploadService`](app/Services/FileUploadService.php)
- GET /upload/status — status polling endpoint for upload progress (see controller)

## Important files & symbols

- [`App\Models\FileUpload`](app/Models/FileUpload.php) — model for uploaded files
- [`App\Services\FileUploadService`](app/Services/FileUploadService.php) — handles storing uploads and dispatching jobs
- [`App\Jobs\ProcessCsvUpload`](app/Jobs/ProcessCsvUpload.php) — job that processes the CSV in the background
- [`App\Http\Controllers\FileUploadController`](app/Http/Controllers/FileUploadController.php) — controller for upload flow
- [`App\Http\Requests\StoreFileRequest`](app/Http/Requests/StoreFileRequest.php) — validation for uploaded files
- [`App\Http\Resources\FileUploadResource`](app/Http/Resources/FileUploadResource.php) — API resource for upload responses
- Database migration for file uploads: [database/migrations/2025_11_08_202012_create_file_uploads_table.php](database/migrations/2025_11_08_202012_create_file_uploads_table.php)
- Upload view: [resources/views/upload.blade.php](resources/views/upload.blade.php)
- Frontend assets: [resources/js/app.js](resources/js/app.js), [resources/css/app.css](resources/css/app.css), [vite.config.js](vite.config.js)
- Project entrypoints: [artisan](artisan), [public/index.php](public/index.php), [bootstrap/app.php](bootstrap/app.php)
- Package manifests: [composer.json](composer.json), [package.json](package.json)
- Example env: [.env.example](.env.example)

## Tests

Run automated tests with:

```sh
php artisan test
# or
vendor/bin/phpunit
```

## Notes

- Ensure your queue connection is configured in `.env` (default is `database`) and migrations for queues (jobs) are run if needed.
- Max upload size is validated in [`App\Http\Requests\StoreFileRequest`](app/Http/Requests/StoreFileRequest.php) (`max:102400`).
- Use `php artisan queue:work --sleep=3 --tries=3` (or Supervisor) in production for reliable job processing.

## Contributing

Pull requests and issues are welcome. Keep changes small and focused.

## License

This project inherits the licensing of included components. See [composer.json](composer.json) for PHP package licensing.
