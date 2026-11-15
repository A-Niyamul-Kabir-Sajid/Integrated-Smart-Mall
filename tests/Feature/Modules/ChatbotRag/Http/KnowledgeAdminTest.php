<?php

namespace Tests\Feature\Modules\ChatbotRag\Http;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Models\DocumentRevision;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Modules\ChatbotRag\CreatesKnowledge;
use Tests\TestCase;

class KnowledgeAdminTest extends TestCase
{
    use CreatesKnowledge, RefreshDatabase;

    private Mall $mall;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->admin = $this->administratorOf($this->mall);
    }

    /**
     * @return array<string, string>
     */
    private function input(array $overrides = []): array
    {
        return ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => "Q: Is there Wi-Fi?\nA: Yes, free.", ...$overrides];
    }

    public function test_an_administrator_adds_a_document_and_sees_what_the_assistant_can_quote(): void
    {
        $this->actingAs($this->admin)->get(route('assistant.admin.knowledge.index', $this->mall))->assertOk()->assertSee('No document matches.');
        $this->get(route('assistant.admin.knowledge.create', $this->mall))->assertOk()->assertSee('Frequently asked questions')->assertSee('Mall administrators only');

        $response = $this->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['source_reference' => 'Visitor handbook, page 4']));

        $document = KnowledgeDocument::query()->sole();
        $response->assertRedirect(route('assistant.admin.knowledge.show', [$this->mall, $document]));
        $this->assertSame('Visitor handbook, page 4', $document->source_locator);
        $this->get(route('assistant.admin.knowledge.show', [$this->mall, $document]))
            ->assertOk()
            ->assertSeeInOrder(['Visitor FAQ', 'Everyone', 'Visitor handbook, page 4', 'Piece 1', 'Is there Wi-Fi?', 'Yes, free.', 'current']);
        $this->get(route('assistant.admin.knowledge.index', $this->mall))->assertSee('Visitor FAQ')->assertSee('Written by administrators');
    }

    public function test_an_uploaded_text_file_becomes_the_text_of_the_document(): void
    {
        $file = UploadedFile::fake()->createWithContent('rules.md', "# Pets\nPets are not allowed inside.");

        $this->actingAs($this->admin)->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['content' => '', 'source_kind' => 'mall_guide', 'file' => $file]))->assertSessionHasNoErrors();

        $this->assertSame("# Pets\nPets are not allowed inside.", DocumentRevision::query()->sole()->content_text);
    }

    public function test_an_upload_that_is_not_utf8_text_is_refused(): void
    {
        $file = UploadedFile::fake()->createWithContent('rules.txt', "Caf\xE9 rules in Latin-1");

        $this->actingAs($this->admin)->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['content' => '', 'file' => $file]))
            ->assertSessionHasErrors(['file' => 'The file is not UTF-8 text. Save it as UTF-8 and upload it again.']);

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_a_file_of_another_type_is_refused(): void
    {
        $file = UploadedFile::fake()->create('rules.pdf', 20, 'application/pdf');

        $this->actingAs($this->admin)->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['content' => '', 'file' => $file]))
            ->assertSessionHasErrors(['file' => 'Upload a plain-text (.txt) or Markdown (.md) file.']);

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_missing_and_invalid_fields_are_refused_with_messages(): void
    {
        $this->actingAs($this->admin)->post(route('assistant.admin.knowledge.store', $this->mall), ['title' => '', 'source_kind' => 'shop_profile', 'access_scope' => 'everyone', 'source_reference' => 'system:floors'])
            ->assertSessionHasErrors([
                'title', 'source_kind', 'access_scope',
                'content' => 'Write the text the assistant may quote, or upload a text file.',
                'source_reference' => 'A source reference cannot start with "system:".',
            ]);

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_text_with_nothing_to_quote_is_sent_back_with_the_business_message(): void
    {
        $this->actingAs($this->admin)->from(route('assistant.admin.knowledge.create', $this->mall))
            ->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['content' => '# Heading only']))
            ->assertRedirect(route('assistant.admin.knowledge.create', $this->mall))
            ->assertSessionHasErrors(['business' => 'Write the text the assistant may quote.']);

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_saving_new_text_tells_the_administrator_a_revision_was_captured(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', "Q: Is there Wi-Fi?\nA: Yes, free.");

        $this->actingAs($this->admin)->get(route('assistant.admin.knowledge.edit', [$this->mall, $document]))->assertOk()->assertSee('A: Yes, free.');

        $this->put(route('assistant.admin.knowledge.update', [$this->mall, $document]), $this->input(['content' => "Q: Is there Wi-Fi?\nA: Yes, on every floor."]))
            ->assertRedirect(route('assistant.admin.knowledge.show', [$this->mall, $document]))
            ->assertSessionHas('status', 'The document was saved as a new revision. The assistant quotes the new text from now on.');
        $this->put(route('assistant.admin.knowledge.update', [$this->mall, $document]), $this->input(['content' => "Q: Is there Wi-Fi?\nA: Yes, on every floor."]))
            ->assertSessionHas('status', 'The document was saved. The text did not change, so no new revision was captured.');

        $this->assertSame(2, $document->revisions()->count());
        $this->get(route('assistant.admin.knowledge.show', [$this->mall, $document]))->assertSee('Yes, on every floor.')->assertDontSee('Yes, free.');
    }

    public function test_an_administrator_withdraws_restores_and_deletes_a_document(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', 'Text.');

        $this->actingAs($this->admin)->post(route('assistant.admin.knowledge.withdraw', [$this->mall, $document]))->assertRedirect();
        $this->assertSame(DocumentStatus::Withdrawn, $document->refresh()->status);
        $this->get(route('assistant.admin.knowledge.show', [$this->mall, $document]))->assertSee('Withdrawn')->assertSee('Delete for good');

        $this->post(route('assistant.admin.knowledge.restore', [$this->mall, $document]))->assertRedirect();
        $this->assertSame(DocumentStatus::Active, $document->refresh()->status);

        $this->from(route('assistant.admin.knowledge.show', [$this->mall, $document]))
            ->delete(route('assistant.admin.knowledge.destroy', [$this->mall, $document]))
            ->assertSessionHasErrors(['business' => 'Withdraw the document before deleting it.']);
        $this->assertModelExists($document);

        $this->post(route('assistant.admin.knowledge.withdraw', [$this->mall, $document]));
        $this->delete(route('assistant.admin.knowledge.destroy', [$this->mall, $document]))->assertRedirect(route('assistant.admin.knowledge.index', $this->mall));
        $this->assertModelMissing($document);
    }

    public function test_reading_the_mall_records_captures_them_and_they_cannot_be_edited_by_hand(): void
    {
        Shop::factory()->for($this->mall)->create(['name' => 'Cafe Brew']);

        $this->actingAs($this->admin)->from(route('assistant.admin.knowledge.index', $this->mall))
            ->post(route('assistant.admin.knowledge.sync', $this->mall))
            ->assertRedirect(route('assistant.admin.knowledge.index', $this->mall))
            ->assertSessionHas('status', 'Mall records read: 2 added, 0 updated, 0 unchanged, 0 withdrawn.');

        $profile = KnowledgeDocument::query()->where('source_kind', 'shop_profile')->sole();
        $this->get(route('assistant.admin.knowledge.index', [$this->mall, 'origin' => 'captured']))->assertSee('Cafe Brew')->assertSee('Captured from mall records');
        $this->get(route('assistant.admin.knowledge.show', [$this->mall, $profile]))->assertOk()->assertSee('Change the shop, product or facility instead.')->assertDontSee('Withdraw');
        $this->get(route('assistant.admin.knowledge.edit', [$this->mall, $profile]))->assertNotFound();
        $this->from(route('assistant.admin.knowledge.show', [$this->mall, $profile]))
            ->put(route('assistant.admin.knowledge.update', [$this->mall, $profile]), $this->input())
            ->assertSessionHasErrors('business');
        $this->assertSame('Cafe Brew', $profile->refresh()->title);
    }

    public function test_the_list_filters_by_title_kind_status_and_origin(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', 'Text one.');
        $rota = $this->knowledge($this->mall, 'Security 100% rota', 'Text two.', kind: 'admin_report');
        app(KnowledgeService::class)->withdraw($this->admin, $rota);

        $list = fn (array $query) => $this->actingAs($this->admin)->get(route('assistant.admin.knowledge.index', [$this->mall, ...$query]));

        $list(['q' => 'visitor'])->assertSee('Visitor FAQ')->assertDontSee('Security 100% rota');
        $list(['q' => '100%'])->assertSee('Security 100% rota')->assertDontSee('Visitor FAQ');
        $list(['q' => '%'])->assertSee('Security 100% rota')->assertDontSee('Visitor FAQ');
        $list(['kind' => 'admin_report'])->assertSee('Security 100% rota')->assertDontSee('Visitor FAQ');
        $list(['status' => 'active'])->assertSee('Visitor FAQ')->assertDontSee('Security 100% rota');
        $list(['origin' => 'captured'])->assertSee('No document matches.');
        $list(['kind' => 'novel'])->assertSessionHasErrors('kind');
    }

    public function test_titles_and_text_are_escaped_on_the_screens(): void
    {
        $document = $this->knowledge($this->mall, 'Rules <img src=x onerror=alert(1)>', "Q: \"Quoted\" & more?\nA: Use <script>alert('x')</script> never.");

        $this->actingAs($this->admin)->get(route('assistant.admin.knowledge.show', [$this->mall, $document]))
            ->assertOk()
            ->assertSee('Rules &lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertDontSee('<script>alert', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function peopleWithoutTheMall(): array
    {
        return ['customer' => ['customer'], 'shop owner in the mall' => ['owner'], 'administrator of another mall' => ['other-admin']];
    }

    #[DataProvider('peopleWithoutTheMall')]
    public function test_only_administrators_of_the_mall_may_open_or_change_its_knowledge(string $who): void
    {
        $user = User::factory()->create();

        match ($who) {
            'owner' => app(MembershipService::class)->grantShopRole($user, Shop::factory()->for($this->mall)->create(), RoleCode::ShopOwner),
            'other-admin' => app(MembershipService::class)->grantMallRole($user, Mall::factory()->create(), RoleCode::MallAdmin),
            default => null,
        };
        $document = $this->knowledge($this->mall, 'Visitor FAQ', 'Text.');

        $this->actingAs($user)->get(route('assistant.admin.knowledge.index', $this->mall))->assertForbidden();
        $this->get(route('assistant.admin.knowledge.show', [$this->mall, $document]))->assertForbidden();
        $this->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['title' => 'Sneaky']))->assertForbidden();
        $this->put(route('assistant.admin.knowledge.update', [$this->mall, $document]), $this->input(['title' => 'Sneaky']))->assertForbidden();
        $this->post(route('assistant.admin.knowledge.withdraw', [$this->mall, $document]))->assertForbidden();
        $this->delete(route('assistant.admin.knowledge.destroy', [$this->mall, $document]))->assertForbidden();
        $this->post(route('assistant.admin.knowledge.sync', $this->mall))->assertForbidden();

        $this->assertSame(['Visitor FAQ'], KnowledgeDocument::query()->pluck('title')->all());
        $this->assertSame(DocumentStatus::Active, $document->refresh()->status);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('assistant.admin.knowledge.index', $this->mall))->assertRedirect(route('login'));
        $this->post(route('assistant.admin.knowledge.store', $this->mall), $this->input())->assertRedirect(route('login'));

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_a_document_of_another_mall_is_not_found_through_this_mall(): void
    {
        $otherMall = Mall::factory()->create();
        $foreign = $this->knowledge($otherMall, 'Their FAQ', 'Their text.');

        $this->actingAs($this->admin)->get(route('assistant.admin.knowledge.show', [$this->mall, $foreign]))->assertNotFound();
        $this->put(route('assistant.admin.knowledge.update', [$this->mall, $foreign]), $this->input())->assertNotFound();
        $this->post(route('assistant.admin.knowledge.withdraw', [$this->mall, $foreign]))->assertNotFound();
        $this->get(route('assistant.admin.knowledge.show', [$this->mall, 'not-a-uuid']))->assertNotFound();

        $this->assertSame('Their FAQ', $foreign->refresh()->title);
        $this->assertSame(DocumentStatus::Active, $foreign->status);
    }

    public function test_a_platform_operator_may_manage_any_malls_knowledge(): void
    {
        $operator = User::factory()->create();
        app(RoleAssignmentService::class)->assignPlatformRole($operator, RoleCode::PlatformAdmin);

        $this->actingAs($operator)->post(route('assistant.admin.knowledge.store', $this->mall), $this->input(['access_scope' => 'mall_admin']))->assertSessionHasNoErrors();

        $this->assertSame(AccessScope::MallAdmin, KnowledgeDocument::query()->sole()->access_scope);
    }

    public function test_the_sync_service_is_not_reachable_for_another_mall_through_the_url(): void
    {
        $otherMall = Mall::factory()->create();
        Shop::factory()->for($otherMall)->create();

        $this->actingAs($this->admin)->post(route('assistant.admin.knowledge.sync', $otherMall))->assertForbidden();

        $this->assertSame(0, KnowledgeDocument::query()->count());
        $this->assertSame(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'withdrawn' => 0], app(KnowledgeSyncService::class)->syncMall($otherMall));
    }
}
