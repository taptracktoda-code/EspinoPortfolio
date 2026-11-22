<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliveryLog extends Model
{
    protected $guarded = [];

    public function message()
    {
        return $this->belongsTo(InquiryMessage::class, 'inquiry_message_id');
    }
}
