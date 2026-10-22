<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Concerns\SerializesDatesForApi;

class InvoiceItem extends Model
{
    use SerializesDatesForApi;
    use SoftDeletes;
    protected $table='invoiceitems';
    protected $fillable=['invoice_id','name','description','quantity','rate','unit','amount'];
}
