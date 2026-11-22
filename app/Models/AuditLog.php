<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    public static function record(string $action, string $subject, ?int $id = null): void
    {
        self::create([
            'user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'subject_id' => $id,
        ]);
    }
}
