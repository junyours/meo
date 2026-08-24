<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentScanner extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'document_scanner_tb';

    protected $fillable = [
        'document_name',
        'document_type',
        'file_path',
        'file_hash',
        'file_size',
        'page_number',
        'page_count',
        'resolution',
        'color_mode',
        'metadata',
        'extracted_text',
        'ocr_confidence',
        'ocr_language',
        'ocr_processed',
        'ai_classification',
        'ai_confidence',
        'ai_tags',
        'ai_entities',
        'ai_processed',
        'blockchain_tx_hash',
        'blockchain_verified_at',
        'blockchain_verified',
        'digital_signature',
        'signed_by',
        'signed_at',
        'version',
        'parent_document_id',
        'processing_status',
        'processing_error',
        'scan_device',
        'scan_software',
        'scan_ip',
        'scan_location',
        'access_permissions',
        'is_public',
        'expires_at',
        'project_id',
        'techprep_id',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ai_tags' => 'array',
        'ai_entities' => 'array',
        'access_permissions' => 'array',
        'ocr_confidence' => 'decimal:2',
        'ai_confidence' => 'decimal:2',
        'ocr_processed' => 'boolean',
        'ai_processed' => 'boolean',
        'blockchain_verified' => 'boolean',
        'is_public' => 'boolean',
        'blockchain_verified_at' => 'datetime',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }

    public function techprep()
    {
        return $this->belongsTo(Techprep::class, 'techprep_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parentDocument()
    {
        return $this->belongsTo(DocumentScanner::class, 'parent_document_id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentScanner::class, 'parent_document_id');
    }

    // Scopes
    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByTechprep($query, $techprepId)
    {
        return $query->where('techprep_id', $techprepId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('blockchain_verified', true);
    }

    // Methods
    public function isAccessibleBy($userId): bool
    {
        if ($this->is_public) return true;
        
        $permissions = $this->access_permissions ?? [];
        return in_array($userId, $permissions);
    }

    public function grantAccess($userId): void
    {
        $permissions = $this->access_permissions ?? [];
        if (!in_array($userId, $permissions)) {
            $permissions[] = $userId;
            $this->access_permissions = $permissions;
            $this->save();
        }
    }

    public function revokeAccess($userId): void
    {
        $permissions = $this->access_permissions ?? [];
        $permissions = array_filter($permissions, fn($id) => $id != $userId);
        $this->access_permissions = array_values($permissions);
        $this->save();
    }

    public function markAsProcessing(): void
    {
        $this->processing_status = 'processing';
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->processing_status = 'completed';
        $this->save();
    }

    public function markAsFailed($error = null): void
    {
        $this->processing_status = 'failed';
        $this->processing_error = $error;
        $this->save();
    }

    public function calculateFileHash(): string
    {
        return hash_file('sha256', storage_path('app/' . $this->file_path));
    }

    public function verifyIntegrity(): bool
    {
        return $this->file_hash === $this->calculateFileHash();
    }

    public function createNewVersion(): self
    {
        $newVersion = $this->replicate();
        $newVersion->parent_document_id = $this->id;
        $newVersion->version = $this->version + 1;
        $newVersion->processing_status = 'pending';
        $newVersion->ocr_processed = false;
        $newVersion->ai_processed = false;
        $newVersion->blockchain_verified = false;
        $newVersion->save();
        
        return $newVersion;
    }
}
