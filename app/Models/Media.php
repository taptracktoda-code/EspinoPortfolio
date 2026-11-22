<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $guarded = [];

    public function viewData(bool $public): array
    {
        $image = str_starts_with($this->mime, 'image/');

        return [
            'id' => $this->id,
            'image' => $image,
            'url' => ($public ? ($image ? '/images/' : '/documents/') : '/admin/media/').$this->public_id,
        ];
    }
}
