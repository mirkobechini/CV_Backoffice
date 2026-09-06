<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class IssueIndexRenderTest extends TestCase
{
    use RefreshDatabase;
    public function test_issues_index_renders_without_error(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $response = $this->actingAs($user)->get(route('admin.issues.index'));
        $response->assertOk();
    }
}
