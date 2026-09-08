<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['latitude', 'longitude', 'accuracy', 'speed', 'recorded_at', 'received_at', 'verified'])]
class TripLocation extends Model
{
    public $timestamps = false;
}
