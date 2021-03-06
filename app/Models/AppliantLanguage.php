<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppliantLanguage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    
    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];

    /**
     * Obtiene los documentos requeridos del expediente.
     *
     * @return BelongsToMany
     */
    public function requiredDocuments(): BelongsToMany
    {
        return $this->belongsToMany(RequiredDocument::class)->withPivot('location')->where('type', 'language');
    }

    /**
     * Obtiene los documentos personales requeridos del expediente.
     *
     * @return BelongsToMany
     */
    public function languageDocuments(): BelongsToMany
    {
        return $this->requiredDocuments()->where('type', 'language');
    }

    /**
     * Obtiene los documentos requeridos del expediente.
     *
     * @return void
     */
    public function archive()
    {
        return $this->belongsTo(Archive::class);
    }
}
