<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Inquiry extends Model
{
    use HasFactory;

    protected $table = 'inquiries_tb';

    protected $fillable = [
        'tracking_token',
        'fullname',
        'phone',
        'email',
        'location',
        'subject',
        'message',
        'photo_path',
        'photos',
        'status',
        'admin_notes',
        'cancellation_reason',
        'accepted_at',
        'accepted_by',
        'resolved_at',
        'resolved_by',
        'cancelled_at',
        'cancelled_by',
        'updated_by',
    ];

    protected $casts = [
        'photos' => 'array',
        'accepted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'photo_url',
        'photo_urls',
    ];

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!empty($this->photos) && is_array($this->photos) && count($this->photos) > 0) {
            return Storage::url($this->photos[0]);
        }

        if (! $this->photo_path) {
            return null;
        }

        return Storage::url($this->photo_path);
    }

    public function getPhotoUrlsAttribute(): array
    {
        $urls = [];

        if (!empty($this->photos) && is_array($this->photos)) {
            foreach ($this->photos as $path) {
                if ($path) {
                    $urls[] = Storage::url($path);
                }
            }
        } elseif ($this->photo_path) {
            $urls[] = Storage::url($this->photo_path);
        }

        return $urls;
    }
}
