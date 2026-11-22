<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InquiryMessage extends Model
{
    protected $guarded = [];

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function delivery()
    {
        return $this->hasOne(EmailDeliveryLog::class);
    }
}
