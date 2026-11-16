<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterTandaTangan extends Model
{
    use HasFactory;

    protected $table = 'master_tanda_tangan';

    protected $fillable = [
        'nrp',
        'nama',
        'position',
        'department',
        'division',
        'signature_url',
        'drive_file_id',
        'signature_base64',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'nrp', 'nrp');
    }
}
