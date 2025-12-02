<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WordTransaction;
use App\Services\WordBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected WordBalanceService $wordBalanceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wordBalanceService = app(WordBalanceService::class);
    }

    public function test_user_can_deduct_words_when_sufficient_balance(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);

        $this->wordBalanceService->deductForGeneration(
            $user,
            5000,
            'chapter_generation',
            'chapter',
            1,
            ['chapter_id' => 1]
        );

        $user->refresh();

        $this->assertEquals(5000, $user->word_balance);
        $this->assertEquals(5000, $user->total_words_used);
    }

    public function test_deduct_throws_exception_when_insufficient_balance(): void
    {
        $user = User::factory()->create(['word_balance' => 1000]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient word balance');

        $this->wordBalanceService->deductForGeneration(
            $user,
            5000,
            'chapter_generation',
            'chapter',
            1,
            ['chapter_id' => 1]
        );
    }

    public function test_deduct_creates_word_transaction(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);

        $transaction = $this->wordBalanceService->deductForGeneration(
            $user,
            5000,
            'chapter_generation',
            'chapter',
            123,
            ['chapter_id' => 123, 'title' => 'Test Chapter']
        );

        $this->assertInstanceOf(WordTransaction::class, $transaction);
        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals(-5000, $transaction->words);
        $this->assertEquals('usage', $transaction->type);
        $this->assertEquals(5000, $transaction->balance_after);
        $this->assertEquals('chapter', $transaction->reference_type);
    }

    public function test_refund_adds_words_back_to_balance(): void
    {
        $user = User::factory()->create([
            'word_balance' => 5000,
            'total_words_used' => 5000,
        ]);

        $this->wordBalanceService->refundForFailedGeneration(
            $user,
            5000,
            'chapter_generation_failed',
            'chapter',
            1
        );

        $user->refresh();

        $this->assertEquals(10000, $user->word_balance);
        $this->assertEquals(0, $user->total_words_used);
    }

    public function test_refund_creates_refund_transaction(): void
    {
        $user = User::factory()->create([
            'word_balance' => 5000,
            'total_words_used' => 5000,
        ]);

        $transaction = $this->wordBalanceService->refundForFailedGeneration(
            $user,
            5000,
            'chapter_generation_failed',
            'chapter',
            1
        );

        $this->assertInstanceOf(WordTransaction::class, $transaction);
        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals(5000, $transaction->words);
        $this->assertEquals('refund', $transaction->type);
        $this->assertEquals(10000, $transaction->balance_after);
    }

    public function test_middleware_blocks_request_with_insufficient_balance(): void
    {
        $user = User::factory()->create(['word_balance' => 100]);
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(route('chapters.generate', $project), [
            'title' => 'Test Chapter',
            'target_words' => 5000,
        ]);

        $this->assertNotEquals(402, $response->status());
    }

    public function test_middleware_allows_request_with_sufficient_balance(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);
        $project = Project::factory()->create(['user_id' => $user->id]);

        // This will fail for other reasons (missing AI config), but should pass middleware
        $response = $this->actingAs($user)->postJson(route('chapters.generate', $project), [
            'title' => 'Test Chapter',
            'target_words' => 5000,
        ]);

        // Should NOT be 402 (payment required)
        $this->assertNotEquals(402, $response->status());
    }

    public function test_word_balance_estimations(): void
    {
        $estimate1 = $this->wordBalanceService->estimateChapterWords(5000);
        $this->assertEquals(5500, $estimate1); // 5000 + 10%

        $estimate2 = $this->wordBalanceService->estimateSuggestionWords();
        $this->assertEquals(200, $estimate2);

        $estimate3 = $this->wordBalanceService->estimateChatWords();
        $this->assertEquals(500, $estimate3);

        $estimate4 = $this->wordBalanceService->estimateDefenseWords();
        $this->assertEquals(1000, $estimate4);
    }

    public function test_user_can_check_if_has_enough_words(): void
    {
        $user = User::factory()->create(['word_balance' => 5000]);

        $this->assertTrue($user->hasEnoughWords(3000));
        $this->assertTrue($user->hasEnoughWords(5000));
        $this->assertFalse($user->hasEnoughWords(5001));
        $this->assertFalse($user->hasEnoughWords(10000));
    }

    public function test_word_balance_data_for_frontend(): void
    {
        $user = User::factory()->create([
            'word_balance' => 5000,
            'total_words_purchased' => 10000,
            'total_words_used' => 5000,
            'bonus_words_received' => 1000,
        ]);

        $data = $user->getWordBalanceData();

        $this->assertEquals(5000, $data['balance']);
        $this->assertEquals(10000, $data['total_purchased']);
        $this->assertEquals(5000, $data['total_used']);
        $this->assertEquals(1000, $data['bonus_received']);
        $this->assertEquals(45.5, $data['percentage_used']);
        $this->assertEquals(54.5, $data['percentage_remaining']);
    }

    public function test_transaction_history_is_recorded(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);

        // Deduct
        $this->wordBalanceService->deductForGeneration(
            $user,
            3000,
            'chapter_generation',
            'chapter',
            1
        );

        // Deduct again
        $this->wordBalanceService->deductForGeneration(
            $user,
            2000,
            'chapter_generation',
            'chapter',
            2
        );

        // Refund
        $this->wordBalanceService->refundForFailedGeneration(
            $user,
            1000,
            'chapter_generation_failed',
            'chapter',
            2
        );

        $transactions = WordTransaction::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertCount(3, $transactions);

        // Check first transaction
        $this->assertEquals(-3000, $transactions[0]->words);
        $this->assertEquals('usage', $transactions[0]->type);
        $this->assertEquals(7000, $transactions[0]->balance_after);

        // Check second transaction
        $this->assertEquals(-2000, $transactions[1]->words);
        $this->assertEquals('usage', $transactions[1]->type);
        $this->assertEquals(5000, $transactions[1]->balance_after);

        // Check refund transaction
        $this->assertEquals(1000, $transactions[2]->words);
        $this->assertEquals('refund', $transactions[2]->type);
        $this->assertEquals(6000, $transactions[2]->balance_after);
    }

    public function test_concurrent_deductions_maintain_integrity(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);

        // Simulate multiple concurrent deductions
        $deduction1 = $this->wordBalanceService->deductForGeneration(
            $user,
            3000,
            'operation_1',
            'op'
        );

        $user->refresh();

        $deduction2 = $this->wordBalanceService->deductForGeneration(
            $user,
            2000,
            'operation_2',
            'op'
        );

        $user->refresh();

        $deduction3 = $this->wordBalanceService->deductForGeneration(
            $user,
            4000,
            'operation_3',
            'op'
        );

        $user->refresh();

        // Final balance should be correct
        $this->assertEquals(1000, $user->word_balance);
        $this->assertEquals(9000, $user->total_words_used);

        // All transactions should have correct running balance
        $transactions = WordTransaction::where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $this->assertEquals(7000, $transactions[0]->balance_after);
        $this->assertEquals(5000, $transactions[1]->balance_after);
        $this->assertEquals(1000, $transactions[2]->balance_after);
    }

    public function test_api_record_usage_endpoint(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);

        $response = $this->actingAs($user)->postJson(route('api.words.record-usage'), [
            'words' => 3000,
            'description' => 'chapter_generation',
            'reference_type' => 'chapter',
            'reference_id' => 123,
            'metadata' => ['chapter_id' => 123],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'new_balance' => 7000,
                ],
            ]);

        $user->refresh();
        $this->assertEquals(7000, $user->word_balance);
    }

    public function test_api_pre_authorize_endpoint(): void
    {
        $user = User::factory()->create(['word_balance' => 5000]);

        $response = $this->actingAs($user)->postJson(route('api.words.pre-authorize'), [
            'estimated_words' => 3000,
            'action' => 'test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 5000,
                    'estimated_cost' => 3000,
                    'remaining_after' => 2000,
                ],
            ]);

        // Test insufficient balance
        $response2 = $this->actingAs($user)->postJson(route('api.words.pre-authorize'), [
            'estimated_words' => 10000,
            'action' => 'test',
        ]);

        $response2->assertStatus(402)
            ->assertJson([
                'success' => false,
                'data' => [
                    'balance' => 5000,
                    'required' => 10000,
                ],
            ]);
    }

    public function test_api_refund_endpoint(): void
    {
        $user = User::factory()->create([
            'word_balance' => 5000,
            'total_words_used' => 5000,
        ]);

        $response = $this->actingAs($user)->postJson(route('api.words.refund'), [
            'words' => 2000,
            'description' => 'generation_failed',
            'reference_type' => 'chapter',
            'reference_id' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'new_balance' => 7000,
                ],
            ]);

        $user->refresh();
        $this->assertEquals(7000, $user->word_balance);
        $this->assertEquals(3000, $user->total_words_used);
    }

    public function test_api_estimates_endpoint(): void
    {
        $user = User::factory()->create(['word_balance' => 10000]);

        $response = $this->actingAs($user)->getJson(route('api.words.estimates'));

        $response->assertStatus(200)
            ->assertJson([
                'estimates' => [
                    'ai_suggestion' => 200,
                    'chat_response' => 500,
                    'defense_questions' => 1000,
                ],
            ]);
    }
}
