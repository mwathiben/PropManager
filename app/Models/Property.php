<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        'name',
        'type',
        'address',
    ];

    // --- RELATIONSHIPS ---

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
