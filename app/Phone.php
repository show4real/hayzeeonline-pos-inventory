<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Concerns\SerializesDatesForApi;

class Phone extends Model
{
    use SerializesDatesForApi;

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }
}
