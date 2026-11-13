<?php

namespace App\Http\Resources\V1;

use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A suggestion as the phone app sees it (plan suggestion-api). Never the sender's
 * identity — only whether it is the caller's own — so board suggestions stay anonymous
 * here as on the web, admins included (admin triage is web-only).
 *
 * @mixin Suggestion
 */
class SuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'admin_reply' => $this->admin_reply,
            'on_board' => $this->is_public,
            'is_mine' => $this->user_id === $user->id,
            'can_vote' => $this->canBeVotedOnBy($user),
            'votes_count' => $this->whenCounted('votes_count', fn () => (int) $this->votes_count),
            'voted' => $this->whenHas('voted', fn () => (bool) $this->voted),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => SuggestionCommentResource::collection($this->whenLoaded('comments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
