<?php

namespace App\Http\Resources\V1;

use App\Models\SuggestionComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment in a suggestion's thread. `author` is the label the caller would see on the
 * web page ("You", "Admin", "Sender", "Another user"; names for admins).
 *
 * @mixin SuggestionComment
 */
class SuggestionCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->authorLabelFor($request->user()),
            'is_mine' => $this->user_id === $request->user()->id,
            'can_delete' => $this->canBeDeletedBy($request->user()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
