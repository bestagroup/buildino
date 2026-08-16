<?php

namespace Tests\Feature\Security;

use App\Enums\FileScanStatus;
use App\Models\DocumentRecord;
use App\Models\File as ManagedFile;
use App\Models\FileDownload;
use App\Models\MeetingMinute;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ManagedFileFlowTest extends TestCase
{
    use CreatesBuildingDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('file_management.disk', 'private');
        config()->set('file_management.scan.enabled', false);
        Storage::fake('private');
    }

    public function test_document_file_upload_download_and_delete_are_audited(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->verifiedUser();

        $this->grant(
            $manager,
            $graph['building'],
            [
                'documents.view',
                'files.view',
                'files.create',
                'files.delete',
            ]
        );

        $document = DocumentRecord::query()->create([
            'documentable_type' =>
                $graph['unit']->getMorphClass(),
            'documentable_id' => $graph['unit']->id,
            'title' => 'Ownership document',
            'document_type' => 'ownership',
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->post(
            "/api/v1/documents/{$document->id}/files",
            [
                'file' => UploadedFile::fake()->create(
                    'deed.pdf',
                    64,
                    'application/pdf'
                ),
                'category' => 'ownership',
                'purpose' => 'primary',
                'is_confidential' => true,
            ],
            ['Accept' => 'application/json']
        )
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'deed.pdf')
            ->assertJsonPath('data.category', 'ownership')
            ->assertJsonPath('data.scan_status', 'clean')
            ->assertJsonMissingPath('data.disk')
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.checksum');

        $file = ManagedFile::query()
            ->where('uuid', $response->json('data.uuid'))
            ->firstOrFail();

        Storage::disk('private')->assertExists($file->path);

        $this->get(
            "/api/v1/files/{$file->uuid}/download",
            ['Accept' => 'application/json']
        )
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->assertDatabaseHas('file_downloads', [
            'file_id' => $file->id,
            'user_id' => $manager->id,
        ]);

        $this->deleteJson(
            "/api/v1/files/{$file->uuid}"
        )->assertNoContent();

        $this->assertSoftDeleted('files', [
            'id' => $file->id,
        ]);
        $this->assertDatabaseMissing('file_relations', [
            'file_id' => $file->id,
        ]);
        Storage::disk('private')->assertMissing($file->path);
    }

    public function test_cross_building_file_access_and_unsafe_upload_are_rejected(): void
    {
        $allowed = $this->createBuildingGraph();
        $outside = $this->createBuildingGraph();
        $uploader = $this->verifiedUser();
        $outsideManager = $this->verifiedUser();

        $this->grant(
            $uploader,
            $allowed['building'],
            [
                'documents.view',
                'files.view',
                'files.create',
            ]
        );

        $this->grant(
            $outsideManager,
            $outside['building'],
            [
                'documents.view',
                'files.view',
                'files.create',
            ]
        );

        $document = DocumentRecord::query()->create([
            'documentable_type' =>
                $allowed['building']->getMorphClass(),
            'documentable_id' => $allowed['building']->id,
            'title' => 'Building contract',
            'document_type' => 'contract',
            'created_by' => $uploader->id,
        ]);

        Sanctum::actingAs($uploader);

        $this->post(
            "/api/v1/documents/{$document->id}/files",
            [
                'file' => UploadedFile::fake()->create(
                    'payload.php',
                    4,
                    'application/x-httpd-php'
                ),
                'category' => 'other',
            ],
            ['Accept' => 'application/json']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $upload = $this->post(
            "/api/v1/documents/{$document->id}/files",
            [
                'file' => UploadedFile::fake()->create(
                    'contract.pdf',
                    32,
                    'application/pdf'
                ),
                'category' => 'contract',
            ],
            ['Accept' => 'application/json']
        )->assertCreated();

        $uuid = $upload->json('data.uuid');

        Sanctum::actingAs($outsideManager);

        $this->getJson(
            "/api/v1/files/{$uuid}/download"
        )->assertForbidden();

        $this->post(
            "/api/v1/documents/{$document->id}/files",
            [
                'file' => UploadedFile::fake()->create(
                    'outside.pdf',
                    8,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        )->assertForbidden();
    }

    public function test_expired_or_non_clean_file_cannot_be_downloaded(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->verifiedUser();

        $this->grant(
            $manager,
            $graph['building'],
            [
                'documents.view',
                'files.view',
                'files.create',
            ]
        );

        $document = DocumentRecord::query()->create([
            'documentable_type' =>
                $graph['building']->getMorphClass(),
            'documentable_id' => $graph['building']->id,
            'title' => 'Expirable document',
            'document_type' => 'building',
        ]);

        Sanctum::actingAs($manager);

        $upload = $this->post(
            "/api/v1/documents/{$document->id}/files",
            [
                'file' => UploadedFile::fake()->create(
                    'notice.pdf',
                    8,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        )->assertCreated();

        $file = ManagedFile::query()
            ->where('uuid', $upload->json('data.uuid'))
            ->firstOrFail();

        $file->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson(
            "/api/v1/files/{$file->uuid}/download"
        )->assertStatus(410);

        $file->update([
            'expires_at' => null,
            'scan_status' => FileScanStatus::Infected,
        ]);

        $this->getJson(
            "/api/v1/files/{$file->uuid}/download"
        )->assertStatus(423);

        $this->assertSame(
            0,
            FileDownload::query()->count()
        );
    }

    public function test_meeting_minute_accepts_scoped_file_attachment(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->verifiedUser();

        $this->grant(
            $manager,
            $graph['building'],
            [
                'meeting-minutes.view',
                'files.view',
                'files.create',
            ]
        );

        $meetingMinute = MeetingMinute::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Board meeting',
            'meeting_at' => now(),
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $this->post(
            "/api/v1/meeting-minutes/{$meetingMinute->id}/files",
            [
                'file' => UploadedFile::fake()->create(
                    'minutes.pdf',
                    16,
                    'application/pdf'
                ),
                'category' => 'meeting_minute',
            ],
            ['Accept' => 'application/json']
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.category',
                'meeting_minute'
            );
    }

    private function verifiedUser(): User
    {
        return $this->createUser([
            'mobile_verified_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $permissionNames
     */
    private function grant(
        User $user,
        mixed $scope,
        array $permissionNames
    ): void {
        $role = Role::query()->create([
            'name' => 'file-role-'.uniqid(),
            'display_name' => 'File test role',
            'is_system' => false,
        ]);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => $permissionName,
                    'module' => explode('.', $permissionName)[0],
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);
    }
}
