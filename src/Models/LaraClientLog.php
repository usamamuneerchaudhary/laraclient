<?php

namespace Usamamuneerchaudhary\LaraClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaraClientLog extends Model
{
    protected $table = 'laraclient_logs';
    use HasFactory;

    public $fillable = [
        'endpoint',
        'method',
        'request_payload',
        'response_status',
        'response_body'
    ];
}
