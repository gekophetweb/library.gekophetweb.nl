<?php

namespace App\Http\Resources\V1;

use App\Models\CollectionObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectionObject */
class CollectionObjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creator = $this->bookDetail?->artist ?? $this->musicDetail?->artist;
        $ref = fn ($model) => $model ? ['id' => $model->id, 'name' => $model->name] : null;

        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'name_romanized' => $this->name_romanized,
            'name_english' => $this->name_english,
            'category' => $ref($this->category),
            'sub_category' => $ref($this->subCategory),
            'languages' => $this->languages->map(fn ($language) => ['code' => $language->code, 'name' => $language->name])->values(),
            'genres' => $this->genres->map($ref)->values(),
            'series' => $ref($this->serie),
            'volume' => $this->bookDetail?->volume,
            'creator' => $ref($creator),
            'platform' => $ref($this->gameDetail?->platform),
            'publisher' => $ref($this->publisher),
            'release_date' => $this->release_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
