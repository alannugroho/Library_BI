<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogBook extends Model
{
    use HasFactory;

    protected $table = 'catalog_books';

    protected $guarded = [];
}
