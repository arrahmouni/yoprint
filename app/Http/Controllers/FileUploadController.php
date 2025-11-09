<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\FileUpload;
use Illuminate\Http\JsonResponse;
use App\Services\FileUploadService;
use App\Http\Requests\StoreFileRequest;
use App\Http\Resources\FileUploadResource;

class FileUploadController extends Controller
{
    public function __construct(protected FileUploadService $fileUploadService) {}

    public function index()
    {
        $uploads = FileUpload::latest()
            ->get();

        return view('upload', compact('uploads'));
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        try {
            $fileUpload = $this->fileUploadService->uploadAndProcess($request->file('csv_file'));

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and is being processed',
                'upload' => new FileUploadResource($fileUpload),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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
