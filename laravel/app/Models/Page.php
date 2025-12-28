<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_title',
        'page_meta_descr',
        'page_meta_key',
        'page_content',
        'page_url',
    ];
}
