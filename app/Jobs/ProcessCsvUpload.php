<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessCsvUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fileUpload;

    public function __construct(FileUpload $fileUpload)
    {
        $this->fileUpload = $fileUpload;
    }

    public function handle()
    {
        logger()->info('Starting CSV processing job', ['file_upload_id' => $this->fileUpload->id]);

        $this->fileUpload->update(['status' => 'processing']);

        try {
            $filePath = Storage::path('uploads/' . $this->fileUpload->filename);

            logger()->info('File path resolved', ['path' => $filePath]);

            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // Read file content and remove BOM
            $content = file_get_contents($filePath);
            $content = $this->removeUtf8Bom($content);

            // Create temporary file without BOM
            $tempFilePath = tempnam(sys_get_temp_dir(), 'csv_');
            file_put_contents($tempFilePath, $content);

            $file = fopen($tempFilePath, 'r');
            if (!$file) {
                throw new \Exception("Cannot open file: {$tempFilePath}");
            }

            logger()->info('File opened successfully');

            $headers = fgetcsv($file);
            logger()->info('CSV headers', ['headers' => $headers]);

            if ($headers === false) {
                throw new \Exception("Cannot read CSV headers - file may be empty or invalid");
            }

            // Clean headers - remove any invisible characters and trim
            $headers = array_map(function($header) {
                return trim($header);
            }, $headers);

            logger()->info('Cleaned CSV headers', ['headers' => $headers]);

            // Validate required columns
            $requiredColumns = [
                'UNIQUE_KEY', 'PRODUCT_TITLE', 'PRODUCT_DESCRIPTION',
                'STYLE#', 'SANMAR_MAINFRAME_COLOR', 'SIZE',
                'COLOR_NAME', 'PIECE_PRICE'
            ];

            foreach ($requiredColumns as $column) {
                if (!in_array($column, $headers)) {
                    throw new \Exception("Missing required column: {$column}. Found columns: " . implode(', ', $headers));
                }
            }

            logger()->info('Column validation passed');

            // Count total rows (excluding header)
            $totalRows = 0;
            fseek($file, 0); // Reset to beginning
            fgetcsv($file); // Skip header

            while (($row = fgetcsv($file)) !== false) {
                $totalRows++;
            }

            logger()->info('Total rows counted', ['total_rows' => $totalRows]);
            $this->fileUpload->update(['total_rows' => $totalRows]);

            // Reset file pointer and process data
            fseek($file, 0);
            fgetcsv($file); // Skip header again

            $processed = 0;
            $batchCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                $batchCount++;

                // Log first few rows for debugging
                if ($batchCount <= 3) {
                    logger()->debug('Processing row', ['row_number' => $batchCount, 'row_data' => $row]);
                }

                // Skip empty rows
                if ($row === null || (count($row) === 1 && empty($row[0]))) {
                    logger()->debug('Skipping empty row', ['row_number' => $batchCount]);
                    continue;
                }

                // Validate row has same number of columns as headers
                if (count($row) !== count($headers)) {
                    logger()->warning('Row column count mismatch', [
                        'row_number' => $batchCount,
                        'expected_columns' => count($headers),
                        'actual_columns' => count($row)
                    ]);
                    continue; // Skip malformed rows but continue processing
                }

                try {
                    // Combine headers with row data
                    $record = array_combine($headers, $row);

                    // Clean UTF-8 characters
                    $cleanedRecord = array_map(function ($value) {
                        if (!is_string($value)) {
                            return $value;
                        }
                        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }, $record);

                    // Validate required fields
                    if (empty($cleanedRecord['UNIQUE_KEY'])) {
                        logger()->warning('Skipping row with empty UNIQUE_KEY', ['row_number' => $batchCount]);
                        continue;
                    }

                    // Upsert product data
                    Product::updateOrCreate(
                        ['unique_key' => $cleanedRecord['UNIQUE_KEY']],
                        [
                            'product_title' => $cleanedRecord['PRODUCT_TITLE'] ?? '',
                            'product_description' => $cleanedRecord['PRODUCT_DESCRIPTION'] ?? null,
                            'style_number' => $cleanedRecord['STYLE#'] ?? null,
                            'sanmar_mainframe_color' => $cleanedRecord['SANMAR_MAINFRAME_COLOR'] ?? null,
                            'size' => $cleanedRecord['SIZE'] ?? null,
                            'color_name' => $cleanedRecord['COLOR_NAME'] ?? null,
                            'piece_price' => $this->parsePrice($cleanedRecord['PIECE_PRICE'] ?? null),
                        ]
                    );

                    $processed++;

                    // Update progress every 10 rows
                    if ($processed % 10 === 0) {
                        $this->fileUpload->update(['processed_rows' => $processed]);
                        logger()->debug('Progress update', ['processed' => $processed, 'total' => $totalRows]);
                    }

                } catch (\Exception $e) {
                    logger()->error('Error processing row', [
                        'row_number' => $batchCount,
                        'error' => $e->getMessage(),
                        'row_data' => $row
                    ]);
                    // Continue processing other rows even if one fails
                }
            }

            fclose($file);
            // Clean up temporary file
            unlink($tempFilePath);

            logger()->info('CSV processing completed', [
                'total_processed' => $processed,
                'file_upload_id' => $this->fileUpload->id
            ]);

            $this->fileUpload->update([
                'status' => 'completed',
                'processed_rows' => $processed,
            ]);

        } catch (\Exception $e) {
            logger()->error('CSV processing job failed', [
                'file_upload_id' => $this->fileUpload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->fileUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw to mark job as failed in queue
            throw $e;
        }
    }

    /**
     * Remove UTF-8 BOM from string
     */
    private function removeUtf8Bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    private function parsePrice($price)
    {
        if (empty($price)) {
            return null;
        }

        // Handle numeric strings and clean non-numeric characters
        if (is_numeric($price)) {
            return floatval($price);
        }

        // Remove any non-numeric characters except decimal point and minus
        $cleanPrice = preg_replace('/[^\d.-]/', '', (string)$price);

        if ($cleanPrice === '' || $cleanPrice === '-') {
            return null;
        }

        return floatval($cleanPrice);
    }

    public function failed(\Throwable $exception)
    {
        logger()->error('ProcessCsvUpload job failed completely', [
            'file_upload_id' => $this->fileUpload->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->fileUpload->update([
            'status' => 'failed',
            'error_message' => 'Job failed: ' . $exception->getMessage(),
        ]);
    }
}
