<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FileUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'status',
        'processed_rows',
        'total_rows',
        'error_message',
    ];

    protected $casts = [
        'processed_rows' => 'integer',
        'total_rows' => 'integer',
    ];

    protected $appends = ['progress'];

    public function progress(): Attribute
    {
        return Attribute::get(
            function () {
                if (empty($this->total_rows) || $this->total_rows <= 0) {
                    return 0;
                }

                $processed = $this->processed_rows ?? 0;

                return ($processed / $this->total_rows) * 100;
            }
        );
    }
}
