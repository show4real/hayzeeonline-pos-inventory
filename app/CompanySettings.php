<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Concerns\SerializesDatesForApi;

class CompanySettings extends Model
{
    use SerializesDatesForApi;

    protected $table = "company_settings";
    protected $fillable = ['logo_url', 'name','country', 'city', 'email', 'address',
    'invoice_footer_one', 'invoice_footer_two', 'invoice_footer_three', 'phone_one','phone_two'];

    protected $casts = ['sell_by_serial_no' => 'integer', 'cashier_daily_filter' => 'integer'];
    //
}
