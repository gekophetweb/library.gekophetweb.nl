<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuggestionCommentRequest;
use App\Http\Requests\StoreSuggestionRequest;
use App\Http\Resources\V1\SuggestionCommentResource;
use App\Http\Resources\V1\SuggestionResource;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Support\Suggestions\SuggestionMailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The suggestion box for the phone app (plan suggestion-api): the same rules as the web
 * pages — `Suggestion::isVisibleTo()` gates everything, a hidden suggestion is a 404.
 * Admin triage (status, reply, board) stays on the web.
 */
class SuggestionController extends Controller
{
    /** The caller's own suggestions, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        return SuggestionResource::collection(
            $this->query($request)->whereBelongsTo($request->user())->latest()->latest('id')->paginate(50)
        );
    }

    /** The public board, most votes first. */
    public function board(Request $request): AnonymousResourceCollection
    {
        return SuggestionResource::collection(
            $this->query($request)->onBoard()->orderByDesc('votes_count')->latest()->latest('id')->paginate(50)
        );
    }

    public function store(StoreSuggestionRequest $request, SuggestionMailer $mailer): JsonResponse
    {
        $suggestion = $request->user()->suggestions()->create($request->validated());
        $mailer->created($suggestion);

        return (new SuggestionResource($this->fresh($request, $suggestion)))->response()->setStatusCode(201);
    }

    public function show(Request $request, Suggestion $suggestion): SuggestionResource
    {
        abort_unless($suggestion->isVisibleTo($request->user()), 404);

        return new SuggestionResource($this->fresh($request, $suggestion)->load(['comments.user', 'comments.suggestion']));
    }

    public function vote(Request $request, Suggestion $suggestion): SuggestionResource
    {
        abort_unless($suggestion->is_public, 404);
        abort_unless($suggestion->canBeVotedOnBy($request->user()), 403, 'You cannot vote on your own suggestion.');

        $suggestion->voters()->syncWithoutDetaching([$request->user()->id]);

        return new SuggestionResource($this->fresh($request, $suggestion));
    }

    public function unvote(Request $request, Suggestion $suggestion): SuggestionResource
    {
        abort_unless($suggestion->is_public, 404);

        $suggestion->voters()->detach($request->user()->id);

        return new SuggestionResource($this->fresh($request, $suggestion));
    }

    public function storeComment(StoreSuggestionCommentRequest $request, Suggestion $suggestion, SuggestionMailer $mailer): JsonResponse
    {
        abort_unless($suggestion->isVisibleTo($request->user()), 404);

        $comment = new SuggestionComment($request->validated());
        $comment->user()->associate($request->user());
        $suggestion->comments()->save($comment);
        $mailer->commented($comment);

        return (new SuggestionCommentResource($comment->load(['user', 'suggestion'])))->response()->setStatusCode(201);
    }

    public function destroyComment(Request $request, Suggestion $suggestion, SuggestionComment $comment): Response
    {
        abort_unless($suggestion->isVisibleTo($request->user()), 404);
        abort_unless($comment->canBeDeletedBy($request->user()), 403);

        $comment->delete();

        return response()->noContent();
    }

    /** @return Builder<Suggestion> */
    private function query(Request $request): Builder
    {
        return Suggestion::query()->withVotesFor($request->user())->withCount('comments');
    }

    private function fresh(Request $request, Suggestion $suggestion): Suggestion
    {
        return $this->query($request)->findOrFail($suggestion->id);
    }
}
