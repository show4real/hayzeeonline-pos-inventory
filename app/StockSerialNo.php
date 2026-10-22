<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Concerns\SerializesDatesForApi;
class StockSerialNo extends Model
{
    use SerializesDatesForApi;
    use SoftDeletes;
    protected $table='stock_serial_no';
    protected $fillable = ["serial_no",'stock_id'];
}
