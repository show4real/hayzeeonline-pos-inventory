<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Concerns\SerializesDatesForApi;

class ProductImage extends Model
{
    use SerializesDatesForApi;

    protected $table="productimages";
    protected $fillable=['product_id','url'];
    //
}
