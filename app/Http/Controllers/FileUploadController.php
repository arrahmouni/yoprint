<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCsvUpload;
use App\Models\FileUpload;
use Illuminate\Http\Request;
use App\Http\Resources\FileUploadResource;

class FileUploadController extends Controller
{
    public function index()
    {
        $uploads = FileUpload::latest()
            ->get();

        return view('upload', compact('uploads'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:102400'
            ]);

            $file = $request->file('csv_file');
            $filename = time() . '_' . $file->getClientOriginalName();

            $file->storeAs('uploads', $filename);

            $fileUpload = FileUpload::create([
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
            ]);

            ProcessCsvUpload::dispatch($fileUpload);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and is being processed',
                'upload' => new FileUploadResource($fileUpload)
            ]);

        } catch (\Exception $e) {
            logger()->error('Upload error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file. Please try again.'
            ], 500);
        }
    }

    public function status()
    {
        try {
            $uploads = FileUpload::latest()->get();

            return response()->json([
                'success' => true,
                'uploads' => FileUploadResource::collection($uploads)
            ]);

        } catch (\Exception $e) {
            logger()->error('Status check error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upload status',
                'uploads' => []
            ], 500);
        }
    }
}
