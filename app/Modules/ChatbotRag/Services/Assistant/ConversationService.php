<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\DTOs\AssistantReplyData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\MessageRole;
use App\Modules\ChatbotRag\Models\ChatMessage;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\ChatbotRag\Models\MessageCitation;
use App\Modules\ChatbotRag\Models\MessageRecommendation;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores conversations and decides who may read one.
 *
 * A conversation belongs to the server-side session that started it and, when the visitor is signed
 * in, to that account. The session holds the conversation's id; an id sent by the browser is never
 * read, so knowing or guessing an id opens nothing. An administrators' conversation always belongs
 * to one account. Conversations are deleted when they expire, or at once when their owner clears them.
 */
class ConversationService
{
    /**
     * The visitor's current conversation with this mall's assistant, if there is one.
     */
    public function current(Mall $mall, AccessScope $scope, ?User $user, Session $session): ?Conversation
    {
        if ($scope === AccessScope::MallAdmin && $user === null) {
            return null;
        }

        $open = fn () => Conversation::query()->whereBelongsTo($mall)->where('access_scope', $scope->value)->unexpired();
        $remembered = $session->get($this->sessionKey((string) $mall->getKey(), $scope));

        if (is_string($remembered)) {
            $conversation = $open()->whereKey($remembered)->first();

            if ($conversation !== null && $this->mayRead($conversation, $scope, $user)) {
                return $conversation;
            }
        }

        if ($user === null) {
            return null;
        }

        $conversation = $open()->where('user_id', $user->getKey())->orderByDesc('created_at')->orderByDesc('conversation_id')->first();

        if ($conversation !== null) {
            $session->put($this->sessionKey((string) $mall->getKey(), $scope), $conversation->getKey());
        }

        return $conversation;
    }

    public function currentOrNew(Mall $mall, AccessScope $scope, ?User $user, Session $session): Conversation
    {
        $conversation = $this->current($mall, $scope, $user, $session);

        if ($conversation !== null) {
            return $conversation;
        }

        $conversation = Conversation::query()->create([
            'mall_id' => $mall->getKey(),
            'user_id' => $user?->getKey(),
            'access_scope' => $scope,
            'created_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config($user === null ? 'assistant.retention_days.guest' : 'assistant.retention_days.account'))),
        ]);

        $session->put($this->sessionKey((string) $mall->getKey(), $scope), $conversation->getKey());

        return $conversation;
    }

    /**
     * Appends the question and its answer, with the chunks the answer cites and the listings it
     * recommends, in one transaction.
     */
    public function record(Conversation $conversation, AssistantReplyData $reply): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $reply): ChatMessage {
            Conversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();
            $next = ChatMessage::query()->where('conversation_id', $conversation->getKey())->count();

            ChatMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sequence_no' => $next,
                'role' => MessageRole::User,
                'content' => $reply->question,
                'created_at' => now(),
            ]);

            $answer = ChatMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sequence_no' => $next + 1,
                'role' => MessageRole::Assistant,
                'content' => $reply->text,
                'model_label' => $reply->modelLabel,
                'meta' => $reply->meta,
                'created_at' => now(),
            ]);

            foreach ($reply->citations as $citation) {
                MessageCitation::query()->insert([
                    'message_id' => $answer->getKey(),
                    'chunk_id' => $citation['chunk_id'],
                    'citation_no' => $citation['number'],
                    'retrieval_score' => $citation['score'],
                    'quoted_excerpt' => $citation['excerpt'],
                ]);
            }

            foreach ($reply->recommendations as $index => $recommendation) {
                MessageRecommendation::query()->insert([
                    'message_id' => $answer->getKey(),
                    'shop_product_id' => $recommendation['listing_id'],
                    'rank_no' => $index + 1,
                    'reason' => $recommendation['reason'],
                ]);
            }

            return $answer;
        });
    }

    /**
     * The latest messages of the conversation, oldest first, with what their answers rest on.
     *
     * @return Collection<int, ChatMessage>
     */
    public function messages(Conversation $conversation): Collection
    {
        return ChatMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->whereIn('role', [MessageRole::User->value, MessageRole::Assistant->value])
            ->with(['citations.chunk.revision.document', 'recommendations'])
            ->orderByDesc('sequence_no')
            ->limit((int) config('assistant.history_limit', 30))
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Deletes the conversation with its messages now, at its owner's request.
     */
    public function clear(Conversation $conversation, Session $session): void
    {
        $session->forget($this->sessionKey((string) $conversation->mall_id, $conversation->access_scope));
        $conversation->delete();
    }

    /**
     * Deletes every conversation past its retention time.
     */
    public function purgeExpired(): int
    {
        return Conversation::query()->where('expires_at', '<=', now())->delete();
    }

    private function mayRead(Conversation $conversation, AccessScope $scope, ?User $user): bool
    {
        if ($scope === AccessScope::MallAdmin) {
            return $user !== null && $conversation->user_id === $user->getKey();
        }

        return $conversation->user_id === null || ($user !== null && $conversation->user_id === $user->getKey());
    }

    private function sessionKey(string $mallId, AccessScope $scope): string
    {
        return 'assistant.conversations.'.$scope->value.'.'.$mallId;
    }
}
