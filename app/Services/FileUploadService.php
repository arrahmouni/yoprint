<?php

namespace App\Services;

use App\Models\FileUpload;
use App\Jobs\ProcessCsvUpload;
use Illuminate\Http\UploadedFile;
use Exception;

class FileUploadService
{
    /**
     * Handle file upload and dispatch processing job.
     *
     * @param UploadedFile $file
     * @return FileUpload
     * @throws Exception
     */
    public function uploadAndProcess(UploadedFile $file): FileUpload
    {
        try {
            // Generate unique filename
            $filename = time() . '_' . $file->getClientOriginalName();

            // Store file
            $file->storeAs('uploads', $filename);

            // Save record in database
            $fileUpload = FileUpload::create([
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
            ]);

            // Dispatch processing job
            ProcessCsvUpload::dispatch($fileUpload);

            return $fileUpload;
        } catch (Exception $e) {
            logger()->error('File upload failed: ' . $e->getMessage());
            throw new Exception('File upload failed. Please try again.');
        }
    }
}
