<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use FromGripp;

    protected $guarded = ['id'];
}
