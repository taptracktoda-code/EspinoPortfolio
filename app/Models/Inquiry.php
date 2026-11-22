<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $guarded = [];

    public function messages()
    {
        return $this->hasMany(InquiryMessage::class)->orderBy('id');
    }
}
