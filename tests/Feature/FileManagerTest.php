<?php

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view my files', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage files', 'guard_name' => 'web']);
});

function fileUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view my files', 'manage files']);

    return $user;
}

test('guests are redirected from the file manager page', function () {
    $this->get(route('files.index'))->assertRedirect(route('login'));
});

test('a user can visit their file manager page', function () {
    $user = fileUser();
    $user->givePermissionTo(['view my files', 'manage files']);

    $this->actingAs($user);

    $this->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('files/index')
            ->has('files', 0)
            ->where('folder', null)
            ->has('breadcrumbs', 0));
});

test('the file manager lists folders before files, alphabetically', function () {
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Archive']);
    $file = File::factory()->create(['user_id' => $user->id, 'name' => 'report.pdf']);

    $this->actingAs($user);

    $this->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('files', 2)
            ->where('files.0.id', $folder->id)
            ->where('files.0.type', 'folder')
            ->where('files.1.id', $file->id)
            ->where('files.1.type', 'file'));
});

test('the file manager sends a locale-stable updated label for each file', function () {
    $user = fileUser();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'updated_at' => '2026-08-14 10:00:00',
    ]);

    $this->actingAs($user);

    $this->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('files.0.updated_label', '14/08/2026'));
});

test('the file manager only shows the current user files', function () {
    $user = fileUser();
    $other = User::factory()->create();
    File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('files', 0));
});

test('a user can open their own folder and see its breadcrumbs', function () {
    $user = fileUser();
    $parent = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);
    $child = File::factory()->folder()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Sub']);
    $nestedFile = File::factory()->create(['user_id' => $user->id, 'parent_id' => $child->id, 'name' => 'notes.txt']);

    $this->actingAs($user);

    $this->get(route('files.index', ['folder' => $child->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('files', 1)
            ->where('files.0.id', $nestedFile->id)
            ->where('folder.id', $child->id)
            ->where('breadcrumbs.0.id', $parent->id)
            ->where('breadcrumbs.1.id', $child->id));
});

test('a user cannot open a folder that is not theirs', function () {
    $user = fileUser();
    $other = User::factory()->create();
    $folder = File::factory()->folder()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->get(route('files.index', ['folder' => $folder->id]))->assertNotFound();
});

test('a user can upload files into the root folder', function () {
    Storage::fake('local');
    $user = fileUser();

    $this->actingAs($user);

    $this->post(route('files.store'), [
        'files' => [
            UploadedFile::fake()->create('report.pdf', 100),
            UploadedFile::fake()->image('photo.jpg'),
        ],
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseCount('files', 2);
    $this->assertDatabaseHas('files', ['user_id' => $user->id, 'parent_id' => null, 'type' => 'file', 'name' => 'report.pdf']);
    $this->assertDatabaseHas('files', ['user_id' => $user->id, 'parent_id' => null, 'type' => 'file', 'name' => 'photo.jpg']);
});

test('a user can upload files into one of their folders', function () {
    Storage::fake('local');
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->post(route('files.store'), [
        'files' => [UploadedFile::fake()->create('doc.txt', 10)],
        'parent_id' => $folder->id,
    ])->assertRedirect(route('files.index', ['folder' => $folder->id]));

    $this->assertDatabaseHas('files', ['user_id' => $user->id, 'parent_id' => $folder->id, 'type' => 'file', 'name' => 'doc.txt']);
});

test('a user cannot upload into a folder that is not theirs', function () {
    Storage::fake('local');
    $user = fileUser();
    $other = User::factory()->create();
    $folder = File::factory()->folder()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->post(route('files.store'), [
        'files' => [UploadedFile::fake()->create('doc.txt', 10)],
        'parent_id' => $folder->id,
    ])->assertNotFound();

    $this->assertDatabaseCount('files', 1);
});

test('files are required to upload', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->post(route('files.store'), ['files' => []])->assertSessionHasErrors('files');
});

test('a user can create a folder', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->post(route('files.store-folder'), ['name' => 'Documents'])
        ->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('files', ['user_id' => $user->id, 'parent_id' => null, 'type' => 'folder', 'name' => 'Documents']);
});

test('a user can create a nested folder', function () {
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);

    $this->actingAs($user);

    $this->post(route('files.store-folder'), [
        'name' => 'Archive',
        'parent_id' => $folder->id,
    ])->assertRedirect(route('files.index', ['folder' => $folder->id]));

    $this->assertDatabaseHas('files', ['user_id' => $user->id, 'parent_id' => $folder->id, 'type' => 'folder', 'name' => 'Archive']);
});

test('a folder name is required within a parent', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->post(route('files.store-folder'), ['name' => ''])->assertSessionHasErrors('name');
});

test('a folder name must be unique within its parent', function () {
    $user = fileUser();
    File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);

    $this->actingAs($user);

    $this->post(route('files.store-folder'), ['name' => 'Docs'])->assertSessionHasErrors('name');
});

test('the same folder name is allowed in a different parent', function () {
    $user = fileUser();
    $a = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);
    $b = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Pictures']);

    $this->actingAs($user);

    $this->post(route('files.store-folder'), ['name' => 'Docs', 'parent_id' => $b->id])
        ->assertRedirect(route('files.index', ['folder' => $b->id]));
});

test('a user cannot create a folder inside a foreign folder', function () {
    $user = fileUser();
    $other = User::factory()->create();
    $folder = File::factory()->folder()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->post(route('files.store-folder'), ['name' => 'Docs', 'parent_id' => $folder->id])->assertNotFound();
});

test('a user can rename their own file or folder', function () {
    $user = fileUser();
    $file = File::factory()->create(['user_id' => $user->id, 'name' => 'old.pdf']);

    $this->actingAs($user);

    $this->patch(route('files.update', $file), ['name' => 'new.pdf'])
        ->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('files', ['id' => $file->id, 'name' => 'new.pdf']);
});

test('a user cannot rename a foreign file', function () {
    $user = fileUser();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->patch(route('files.update', $file), ['name' => 'stolen.pdf'])->assertForbidden();
});

test('a rename must be unique within its parent', function () {
    $user = fileUser();
    $a = File::factory()->create(['user_id' => $user->id, 'name' => 'keep.pdf']);
    File::factory()->create(['user_id' => $user->id, 'name' => 'other.pdf']);

    $this->actingAs($user);

    $this->patch(route('files.update', $a), ['name' => 'other.pdf'])->assertSessionHasErrors('name');
});

test('a user can delete their own file', function () {
    Storage::fake('local');
    $user = fileUser();
    $file = File::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->delete(route('files.destroy', $file))->assertRedirect(route('files.index'));

    $this->assertDatabaseMissing('files', ['id' => $file->id]);
});

test('deleting a folder removes its descendants from the database and disk', function () {
    Storage::fake('local');
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id]);
    $childFile = File::factory()->create(['user_id' => $user->id, 'parent_id' => $folder->id]);
    $nested = File::factory()->folder()->create(['user_id' => $user->id, 'parent_id' => $folder->id]);
    $deepFile = File::factory()->create(['user_id' => $user->id, 'parent_id' => $nested->id]);

    Storage::disk('local')->put($childFile->path, 'content');
    Storage::disk('local')->put($deepFile->path, 'content');

    $this->actingAs($user);

    $this->delete(route('files.destroy', $folder))->assertRedirect(route('files.index'));

    $this->assertDatabaseMissing('files', ['id' => $folder->id]);
    $this->assertDatabaseMissing('files', ['id' => $childFile->id]);
    $this->assertDatabaseMissing('files', ['id' => $nested->id]);
    $this->assertDatabaseMissing('files', ['id' => $deepFile->id]);
    Storage::disk('local')->assertMissing($childFile->path);
    Storage::disk('local')->assertMissing($deepFile->path);
});

test('a user cannot delete a foreign file', function () {
    $user = fileUser();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->delete(route('files.destroy', $file))->assertForbidden();

    $this->assertDatabaseHas('files', ['id' => $file->id]);
});

test('a user can download their own file', function () {
    Storage::fake('local');
    $user = fileUser();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'report.pdf',
        'path' => 'files/report.pdf',
    ]);

    Storage::disk('local')->put('files/report.pdf', 'contents');

    $this->actingAs($user);

    $this->get(route('files.download', $file))
        ->assertOk()
        ->assertDownload('report.pdf');
});

test('a user can preview their own file inline', function () {
    Storage::fake('local');
    $user = fileUser();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'report.pdf',
        'path' => 'files/report.pdf',
    ]);

    Storage::disk('local')->put('files/report.pdf', 'contents');

    $this->actingAs($user);

    $response = $this->get(route('files.preview', $file));

    $response->assertOk();
    $this->assertStringContainsString(
        'inline',
        $response->headers->get('Content-Disposition'),
    );
});

test('a user cannot preview a foreign file', function () {
    Storage::fake('local');
    $user = fileUser();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->get(route('files.preview', $file))->assertForbidden();
});

test('a folder cannot be previewed', function () {
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(route('files.preview', $folder))->assertNotFound();
});

test('a user cannot download a foreign file', function () {
    Storage::fake('local');
    $user = fileUser();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->get(route('files.download', $file))->assertForbidden();
});

test('a folder can be downloaded as a zip archive', function () {
    Storage::fake('local');
    $user = fileUser();
    $folder = File::factory()->folder()->create([
        'user_id' => $user->id,
        'name' => 'Docs',
    ]);
    $child = File::factory()->create([
        'user_id' => $user->id,
        'parent_id' => $folder->id,
        'name' => 'report.pdf',
        'path' => 'files/report.pdf',
    ]);
    $nestedFolder = File::factory()->folder()->create([
        'user_id' => $user->id,
        'parent_id' => $folder->id,
        'name' => 'Sub',
    ]);
    $deepFile = File::factory()->create([
        'user_id' => $user->id,
        'parent_id' => $nestedFolder->id,
        'name' => 'notes.txt',
        'path' => 'files/notes.txt',
    ]);

    Storage::disk('local')->put($child->path, 'pdf contents');
    Storage::disk('local')->put($deepFile->path, 'note contents');

    $this->actingAs($user);

    $response = $this->get(route('files.download', $folder))
        ->assertOk()
        ->assertDownload('Docs.zip');

    $zipPath = sys_get_temp_dir().'/download-'.uniqid().'.zip';
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->getFromName('Docs/report.pdf'))->toBe('pdf contents')
        ->and($zip->getFromName('Docs/Sub/notes.txt'))->toBe('note contents');
    $zip->close();
    @unlink($zipPath);
});

test('an empty folder can be downloaded as a valid zip archive', function () {
    Storage::fake('local');
    $user = fileUser();
    $folder = File::factory()->folder()->create([
        'user_id' => $user->id,
        'name' => 'Empty',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('files.download', $folder))
        ->assertOk()
        ->assertDownload('Empty.zip');

    $zipPath = sys_get_temp_dir().'/empty-download-'.uniqid().'.zip';
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->numFiles)->toBe(1)
        ->and($zip->getNameIndex(0))->toBe('Empty/');
    $zip->close();
    @unlink($zipPath);
});

test('a user can bulk download their files as a zip archive', function () {
    Storage::fake('local');
    $user = fileUser();
    $fileA = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'a.txt',
        'path' => 'files/a.txt',
    ]);
    $fileB = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'b.txt',
        'path' => 'files/b.txt',
    ]);

    Storage::disk('local')->put($fileA->path, 'AAA');
    Storage::disk('local')->put($fileB->path, 'BBB');

    $this->actingAs($user);

    $response = $this->get(route('files.bulk-download', [
        'ids' => [$fileA->id, $fileB->id],
    ]))->assertOk()
        ->assertDownload('files.zip');

    $zipPath = sys_get_temp_dir().'/bulk-'.uniqid().'.zip';
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->getFromName('a.txt'))->toBe('AAA')
        ->and($zip->getFromName('b.txt'))->toBe('BBB');
    $zip->close();
    @unlink($zipPath);
});

test('bulk download ignores ids that belong to other users', function () {
    Storage::fake('local');
    $user = fileUser();
    $other = User::factory()->create();
    $ownFile = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'mine.txt',
        'path' => 'files/mine.txt',
    ]);
    $foreignFile = File::factory()->create([
        'user_id' => $other->id,
        'name' => 'theirs.txt',
        'path' => 'files/theirs.txt',
    ]);

    Storage::disk('local')->put($ownFile->path, 'mine');
    Storage::disk('local')->put($foreignFile->path, 'theirs');

    $this->actingAs($user);

    $response = $this->get(route('files.bulk-download', [
        'ids' => [$ownFile->id, $foreignFile->id],
    ]))->assertOk()
        ->assertDownload('files.zip');

    $zipPath = sys_get_temp_dir().'/bulk-'.uniqid().'.zip';
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->getFromName('mine.txt'))->toBe('mine')
        ->and($zip->getFromName('theirs.txt'))->toBeFalse();
    $zip->close();
    @unlink($zipPath);
});

test('bulk download requires an ids array', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->get(route('files.bulk-download'))->assertSessionHasErrors('ids');
});

test('a user can bulk delete their own files', function () {
    Storage::fake('local');
    $user = fileUser();
    $fileA = File::factory()->create(['user_id' => $user->id]);
    $fileB = File::factory()->create(['user_id' => $user->id]);

    Storage::disk('local')->put($fileA->path, 'a');
    Storage::disk('local')->put($fileB->path, 'b');

    $this->actingAs($user);

    $this->delete(route('files.bulk-destroy'), [
        'ids' => [$fileA->id, $fileB->id],
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseMissing('files', ['id' => $fileA->id]);
    $this->assertDatabaseMissing('files', ['id' => $fileB->id]);
    Storage::disk('local')->assertMissing($fileA->path);
    Storage::disk('local')->assertMissing($fileB->path);
});

test('bulk delete ignores ids that belong to other users', function () {
    Storage::fake('local');
    $user = fileUser();
    $other = User::factory()->create();
    $ownFile = File::factory()->create(['user_id' => $user->id]);
    $foreignFile = File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->delete(route('files.bulk-destroy'), [
        'ids' => [$ownFile->id, $foreignFile->id],
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseMissing('files', ['id' => $ownFile->id]);
    $this->assertDatabaseHas('files', ['id' => $foreignFile->id]);
});

test('bulk delete requires an ids array', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->delete(route('files.bulk-destroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});

test('a user can move files and folders into another folder', function () {
    $user = fileUser();
    $folderA = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);
    $folderB = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Archive']);
    $file = File::factory()->create(['user_id' => $user->id, 'name' => 'report.pdf']);

    $this->actingAs($user);

    $this->post(route('files.move'), [
        'ids' => [$folderA->id, $file->id],
        'folder_id' => $folderB->id,
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('files', ['id' => $folderA->id, 'parent_id' => $folderB->id]);
    $this->assertDatabaseHas('files', ['id' => $file->id, 'parent_id' => $folderB->id]);
});

test('a user can move items back to the root folder', function () {
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);
    $file = File::factory()->create(['user_id' => $user->id, 'parent_id' => $folder->id, 'name' => 'notes.txt']);

    $this->actingAs($user);

    $this->post(route('files.move'), [
        'ids' => [$file->id],
        'folder_id' => '',
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('files', ['id' => $file->id, 'parent_id' => null]);
});

test('moving a mix of own and foreign ids only moves the user items', function () {
    $user = fileUser();
    $other = User::factory()->create();
    $folder = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);
    $ownFile = File::factory()->create(['user_id' => $user->id]);
    $foreignFile = File::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->post(route('files.move'), [
        'ids' => [$ownFile->id, $foreignFile->id],
        'folder_id' => $folder->id,
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('files', ['id' => $ownFile->id, 'parent_id' => $folder->id]);
    $this->assertDatabaseHas('files', ['id' => $foreignFile->id, 'parent_id' => null]);
});

test('a user cannot move into a folder that is not theirs', function () {
    $user = fileUser();
    $other = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $foreignFolder = File::factory()->folder()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->post(route('files.move'), [
        'ids' => [$file->id],
        'folder_id' => $foreignFolder->id,
    ])->assertNotFound();
});

test('a folder cannot be moved into itself or one of its descendants', function () {
    $user = fileUser();
    $parent = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Parent']);
    $child = File::factory()->folder()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Child']);
    $grandchild = File::factory()->folder()->create(['user_id' => $user->id, 'parent_id' => $child->id, 'name' => 'Grandchild']);

    $this->actingAs($user);

    $this->post(route('files.move'), [
        'ids' => [$parent->id],
        'folder_id' => $child->id,
    ])->assertRedirect(route('files.index'));

    $this->post(route('files.move'), [
        'ids' => [$parent->id],
        'folder_id' => $grandchild->id,
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('files', ['id' => $parent->id, 'parent_id' => null]);
    $this->assertDatabaseHas('files', ['id' => $child->id, 'parent_id' => $parent->id]);
    $this->assertDatabaseHas('files', ['id' => $grandchild->id, 'parent_id' => $child->id]);
});

test('the file manager sends disk storage information', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('storage')
            ->whereType('storage.total', 'integer')
            ->whereType('storage.used', 'integer')
            ->whereType('storage.free', 'integer'));
});

test('the file manager sends the user folders for the move dialog', function () {
    $user = fileUser();
    $folder = File::factory()->folder()->create(['user_id' => $user->id, 'name' => 'Docs']);

    $this->actingAs($user);

    $this->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('folders', 1)
            ->where('folders.0.id', $folder->id)
            ->where('folders.0.parent_id', null));
});

test('move requires an ids array', function () {
    $user = fileUser();

    $this->actingAs($user);

    $this->post(route('files.move'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
