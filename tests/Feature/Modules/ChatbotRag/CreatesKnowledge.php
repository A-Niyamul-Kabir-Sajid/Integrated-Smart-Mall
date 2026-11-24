<?php

namespace Tests\Feature\Modules\ChatbotRag;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\MallShopDirectory\Models\Mall;

/**
 * Builds mall administrators and knowledge documents for the ChatbotRag tests. Documents are
 * captured through KnowledgeService, so they have a revision and chunks like real ones.
 */
trait CreatesKnowledge
{
    /** @var array<string, User> */
    private array $knowledgeAdministrators = [];

    protected function administratorOf(Mall $mall): User
    {
        return $this->knowledgeAdministrators[$mall->getKey()] ??= tap(
            User::factory()->create(),
            fn (User $user) => app(MembershipService::class)->grantMallRole($user, $mall, RoleCode::MallAdmin),
        );
    }

    protected function knowledge(Mall $mall, string $title, string $content, string $scope = 'public', string $kind = 'faq'): KnowledgeDocument
    {
        return app(KnowledgeService::class)->create($this->administratorOf($mall), $mall, [
            'title' => $title, 'source_kind' => $kind, 'access_scope' => $scope, 'content' => $content,
        ]);
    }
}
