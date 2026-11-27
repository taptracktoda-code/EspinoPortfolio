<?php

namespace Tests\Feature;

use App\Models\EmailDeliveryLog;
use App\Models\Inquiry;
use App\Models\Media;
use App\Models\PortfolioRecord;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Page;
use InvalidArgumentException;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_portfolio_is_public_and_stateless(): void
    {
        $response = $this->get('/');
        $response->assertOk()->assertHeader('X-Portfolio-Public', 'page')->assertInertia(fn (Page $page) => $page
            ->component('Home')
            ->missing('errors')
            ->where('sections.projects.empty', 'No Projects Available')
            ->has('sections.certificates.items', 0)
            ->has('sections.achievements.items', 0)
            ->where('photo', null));
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->get('/sw.js')->assertOk()->assertSee('BUILD_VERSION');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin/');
    }

    public function test_only_the_owner_can_sign_in_and_manage_content(): void
    {
        $owner = $this->owner();
        $guest = User::factory()->create();
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
        $this->post('/admin/login', ['email' => $guest->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($guest)->get('/admin/profile')->assertForbidden();
        auth()->logout();

        $this->post('/admin/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($owner);
        $this->get('/admin/dashboard')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->get('/register')->assertNotFound();
    }

    public function test_project_details_and_media_follow_publication(): void
    {
        Storage::fake('local');
        $data = [
            'title' => 'Testing portfolio publishing', 'overview' => 'A temporary test fixture.',
            'category' => 'Web', 'status' => 'Completed', 'published' => false,
            'technologies' => 'Laravel, React', 'featured' => true,
        ];
        $this->actingAs($this->owner())->post('/admin/projects', [
            ...$data, 'files' => [UploadedFile::fake()->image('screenshot.png')],
        ])->assertRedirect('/admin/projects');

        $project = PortfolioRecord::forSection('projects')->newQuery()->firstOrFail();
        $media = Media::sole();
        $projectUrl = '/projects/'.$project->slug;
        $imageUrl = '/images/'.$media->public_id;
        $privateUrl = '/admin/media/'.$media->public_id;
        $this->assertDatabaseCount('project_images', 1);
        $this->assertDatabaseCount('project_technology', 2);
        $this->get($privateUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($imageUrl)->assertNotFound();
        $this->get($projectUrl)->assertNotFound();

        $this->post('/admin/projects/'.$project->id, [...$data, 'published' => true])->assertRedirect('/admin/projects');
        $this->get($projectUrl)->assertInertia(fn (Page $page) => $page->component('Project')->where('project.id', $project->id));
        $this->get($imageUrl)->assertOk()->assertHeader('X-Portfolio-Public', 'image');
        $this->get('/sitemap.xml')->assertSee($projectUrl);

        $this->post('/admin/projects/'.$project->id, $data)->assertRedirect('/admin/projects');
        $this->get($projectUrl)->assertNotFound();
        $this->get($imageUrl)->assertNotFound();
        $this->get('/')->assertInertia(fn (Page $page) => $page->has('sections.projects.items', 0));
        $this->delete('/admin/projects/'.$project->id)->assertRedirect();
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseCount('project_images', 0);
    }

    public function test_profile_image_is_optimized_and_accessible_as_a_guest(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner())->post('/admin/profile', [
            'name' => 'Portfolio Owner', 'email' => 'private@example.com', 'hidden_fields' => ['email'],
            'photo' => UploadedFile::fake()->image('avatar.jpg', 900, 750),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $file = Media::sole();
        $this->assertSame('image/webp', $file->mime);
        $size = getimagesize(Storage::disk('local')->path($file->path));
        $this->assertSame([640, 640], [$size[0], $size[1]]);

        auth()->logout();
        $this->get('/images/'.$file->public_id)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get('/admin/media/'.$file->public_id)->assertRedirect('/admin/login');
        $this->get('/')->assertInertia(fn (Page $page) => $page->where('profile.name', 'Portfolio Owner')
            ->missing('profile.email')->missing('profile.hidden_fields')->where('photo', '/images/'.$file->public_id));
    }

    public function test_profile_text_can_be_published_after_a_photo_upload_fails(): void
    {
        $workingDisk = Storage::fake('local');
        $this->actingAs($this->owner())->post('/admin/profile', [
            'name' => 'Original Profile',
            'photo' => UploadedFile::fake()->image('original.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $existingPhoto = Media::sole();
        $failingDisk = $this->mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('put')->once()->andThrow(new InvalidArgumentException('Missing S3 region'));
        Storage::set('local', $failingDisk);

        $this->from('/admin/profile')->post('/admin/profile', [
            'name' => 'Updated Profile',
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertRedirect('/admin/profile')->assertSessionHasErrors('photo');
        $this->get('/')->assertInertia(fn (Page $page) => $page->where('profile.name', 'Original Profile'));
        $this->assertSame($existingPhoto->id, Media::sole()->id);

        $this->post('/admin/profile', ['name' => 'Updated Profile'])->assertRedirect()->assertSessionHasNoErrors();
        Storage::set('local', $workingDisk);
        auth()->logout();

        $this->get('/')->assertInertia(fn (Page $page) => $page->where('profile.name', 'Updated Profile')
            ->where('photo', '/images/'.$existingPhoto->public_id));
        $workingDisk->assertExists($existingPhoto->path);
    }

    public function test_publishing_a_resume_makes_the_previous_file_private(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner());
        foreach (['First version', 'Second version'] as $title) {
            $this->post('/admin/resumes', [
                'title' => $title, 'published' => true,
                'files' => [UploadedFile::fake()->createWithContent('resume.pdf', "%PDF-1.4\n%%EOF")],
            ])->assertRedirect('/admin/resumes')->assertSessionHasNoErrors();
        }
        $files = Media::orderBy('id')->get();
        $this->assertDatabaseHas('resumes', ['title' => 'First version', 'published' => false]);
        $this->assertDatabaseHas('resumes', ['title' => 'Second version', 'published' => true]);
        $this->get('/documents/'.$files[0]->public_id)->assertNotFound();
        $this->get('/documents/'.$files[1]->public_id)->assertOk()->assertDownload('resume.pdf');
        $this->get('/images/'.$files[1]->public_id)->assertNotFound();
    }

    public function test_inquiry_and_reply_record_mail_delivery_and_stay_private(): void
    {
        $owner = $this->owner();
        $inquiry = [
            'name' => 'Test Client', 'email' => 'client@example.com', 'type' => 'Project inquiry',
            'subject' => 'Project details', 'message' => 'Please provide more information about my project.',
        ];
        $this->postJson('/contact', $inquiry)->assertUnprocessable();
        $this->postJson('/contact', [...$inquiry, 'website' => 'spam'])->assertUnprocessable();
        $this->withSession(['contact_started' => time() - 5])->postJson('/contact', $inquiry)->assertCreated();
        $record = Inquiry::sole();
        $this->assertDatabaseHas('email_delivery_logs', ['kind' => 'notification', 'status' => 'Sent', 'attempts' => 1]);
        $this->get('/admin/inquiries/'.$record->id)->assertRedirect('/admin/login');
        $this->get('/')->assertDontSee($inquiry['email']);

        $url = '/admin/inquiries/'.$record->id;
        $this->actingAs($owner)->get($url)->assertInertia(fn (Page $page) => $page->component('Thread', false)->where('inquiry.messages.0.body', $inquiry['message']));
        $this->patch($url.'/status', ['status' => 'Read'])->assertRedirect();
        $this->post($url.'/reply', ['message' => 'Thanks for reaching out.'])->assertRedirect();
        $this->assertSame('Replied', $record->refresh()->status);
        $this->assertSame(2, $record->messages()->count());
        $this->assertSame('Sent', EmailDeliveryLog::where('kind', 'reply')->sole()->status);
    }

    public function test_used_skill_categories_can_be_renamed_and_are_protected_from_deletion(): void
    {
        $this->actingAs($this->owner())->post('/admin/skills', [
            'title' => 'PHP', 'category' => 'Backend', 'published' => true,
        ])->assertRedirect('/admin/skills');

        $url = '/admin/skill-categories/'.\Illuminate\Support\Facades\DB::table('skill_categories')->sole()->id;
        $this->post($url, ['name' => 'Backend development'])->assertRedirect();
        $this->delete($url)->assertSessionHasErrors('category');
        $this->get('/')->assertInertia(fn (Page $page) => $page->where('sections.skills.items.0.details.category', 'Backend development'));
    }

    public function test_owner_login_email_can_change_without_changing_the_password(): void
    {
        $owner = $this->owner();
        $password = $owner->password;
        $originalEmail = $owner->email;
        $occupiedEmail = User::factory()->create()->email;

        $this->artisan('portfolio:owner', ['email' => $occupiedEmail, '--change-email' => true])->assertExitCode(1);
        $this->assertSame($originalEmail, $owner->refresh()->email);

        $this->artisan('portfolio:owner', ['email' => 'new-owner@example.com', '--change-email' => true])->assertExitCode(0);
        $this->assertSame($password, $owner->refresh()->password);
        $this->post('/admin/login', ['email' => $originalEmail, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'new-owner@example.com', 'password' => 'password'])->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($owner);
    }

    private function owner(): User
    {
        return User::factory()->create(['owner_slot' => true]);
    }
}
