<?php

declare(strict_types=1);

namespace Nimbus\Admin;

use Nimbus\Content\Collection;
use Nimbus\Content\CollectionRepository;
use Nimbus\Content\EntryInput;
use Nimbus\Content\EntryRepository;
use Nimbus\Content\EntryService;
use Nimbus\Content\FieldTypeRegistry;
use Nimbus\Content\Permissions;
use Nimbus\Content\RelationRepository;
use Nimbus\Http\Csrf;
use Nimbus\Http\Request;
use Nimbus\Http\Router;
use Nimbus\Support\Str;

/**
 * The Collections engine: define content types + fields (admins), then manage
 * entries against them (anyone the collection's permissions allow). Entry forms
 * are generated from field definitions via the FieldTypeRegistry.
 */
final class CollectionsController extends Controller
{
    private CollectionRepository $collections;
    private EntryRepository $entries;
    private RelationRepository $relations;
    private FieldTypeRegistry $types;
    private EntryService $entryService;

    public function boot(): void
    {
        $this->collections  = new CollectionRepository($this->db);
        $this->entries      = new EntryRepository($this->db);
        $this->relations    = new RelationRepository($this->db);
        $this->types        = new FieldTypeRegistry();
        $this->entryService = new EntryService($this->db, $this->entries, $this->relations, $this->types);
    }

    public function routes(Router $r): void
    {
        $this->boot();

        // ---- collections (structural: admin only) ----
        $r->get('/admin/collections', fn (): string => $this->index());
        $r->get('/admin/collections/new', fn (): string => $this->form(null));
        $r->post('/admin/collections', fn (): string => $this->store());
        $r->get('/admin/collections/{id}/edit', fn (array $p): string => $this->form((int) $p['id']));
        $r->post('/admin/collections/{id}', fn (array $p): string => $this->update((int) $p['id']));
        $r->post('/admin/collections/{id}/delete', fn (array $p): string => $this->destroy((int) $p['id']));

        // ---- entries ----
        $r->get('/admin/collections/{handle}/entries', fn (array $p): string => $this->entriesIndex($p['handle']));
        $r->get('/admin/collections/{handle}/entries/new', fn (array $p): string => $this->entryForm($p['handle'], null));
        $r->post('/admin/collections/{handle}/entries', fn (array $p): string => $this->entryStore($p['handle']));
        $r->get('/admin/collections/{handle}/entries/{id}/edit', fn (array $p): string => $this->entryForm($p['handle'], (int) $p['id']));
        $r->post('/admin/collections/{handle}/entries/{id}', fn (array $p): string => $this->entryUpdate($p['handle'], (int) $p['id']));
        $r->post('/admin/collections/{handle}/entries/{id}/delete', fn (array $p): string => $this->entryDestroy($p['handle'], (int) $p['id']));
    }

    // =========================================================== collections

    private function index(): string
    {
        $this->guard();
        $rows = [];
        foreach ($this->collections->all() as $c) {
            $rows[] = [
                'c'       => $c,
                'fields'  => $this->collections->fieldCount($c->id),
                'entries' => $this->collections->entryCount($c->id),
            ];
        }
        return $this->page('collections/index', 'collections', [
            'rows'    => $rows,
            'isAdmin' => Permissions::isAdmin($this->auth->user()),
            'flash'   => Request::fromGlobals()->query('msg'),
        ]);
    }

    private function form(?int $id): string
    {
        $this->requireAdmin();
        $collection = $id !== null ? $this->collections->find($id) : null;
        if ($id !== null && $collection === null) {
            $this->redirect('/admin/collections');
        }
        $collectionOptions = [];
        foreach ($this->collections->all() as $c) {
            $collectionOptions[$c->handle] = $c->name;
        }
        return $this->page('collections/form', 'collections', [
            'collection'        => $collection,
            'typeChoices'       => $this->types->choices(),
            'choiceTypes'       => $this->choiceTypes(),
            'relationTypes'     => ['relation'],
            'collectionOptions' => $collectionOptions,
            'roles'             => Permissions::ROLES,
            'csrf'              => Csrf::token(),
        ]);
    }

    private function store(): string
    {
        $this->requireAdmin();
        $req = Request::fromGlobals();
        $this->requireCsrf($req);

        $name   = trim((string) $req->input('name'));
        $handle = Str::handle($req->input('handle') ?: $name);
        if ($name === '' || $handle === '' || $this->collections->handleExists($handle)) {
            $this->redirect('/admin/collections/new');
        }

        $id = $this->collections->create($handle, $name, $this->icon($req), (string) $req->input('description'), $this->options($req));
        $this->collections->syncFields($id, $this->fieldDefs($req));
        $this->redirect('/admin/collections?msg=created');
    }

    private function update(int $id): string
    {
        $this->requireAdmin();
        $req = Request::fromGlobals();
        $this->requireCsrf($req);

        if ($this->collections->find($id) === null) {
            $this->redirect('/admin/collections');
        }
        $name = trim((string) $req->input('name'));
        if ($name === '') {
            $this->redirect("/admin/collections/{$id}/edit");
        }
        $this->collections->update($id, $name, $this->icon($req), (string) $req->input('description'), $this->options($req));
        $this->collections->syncFields($id, $this->fieldDefs($req));
        $this->redirect('/admin/collections?msg=updated');
    }

    private function destroy(int $id): string
    {
        $this->requireAdmin();
        $this->requireCsrf(Request::fromGlobals());
        $this->collections->delete($id);
        $this->redirect('/admin/collections?msg=deleted');
    }

    // =============================================================== entries

    private function entriesIndex(string $handle): string
    {
        $collection = $this->mustFind($handle);
        $this->guard();

        // A singleton has no list — go straight to editing its one entry.
        if ($collection->isSingle()) {
            $this->requireManage($collection);
            $entry = $this->entries->firstForCollection($collection->id);
            return $this->renderEntryForm($collection, $this->modelFromEntry($collection, $entry), [], Request::fromGlobals()->query('msg'));
        }

        return $this->page('entries/index', 'collections', [
            'collection' => $collection,
            'rows'       => $this->entries->forCollection($collection->id, Request::fromGlobals()->query('q')),
            'types'      => $this->types,
            'canManage'  => Permissions::canManage($this->auth->user(), $collection),
            'flash'      => Request::fromGlobals()->query('msg'),
        ]);
    }

    private function entryForm(string $handle, ?int $id): string
    {
        $collection = $this->mustFind($handle);
        $this->requireManage($collection);
        $entry = $id !== null ? $this->entries->find($collection->id, $id) : null;
        if ($id !== null && $entry === null) {
            $this->redirect("/admin/collections/{$handle}/entries");
        }
        return $this->renderEntryForm($collection, $this->modelFromEntry($collection, $entry), []);
    }

    private function entryStore(string $handle): string
    {
        $collection = $this->mustFind($handle);
        $this->requireManage($collection);
        $req = Request::fromGlobals();
        $this->requireCsrf($req);
        return $this->saveEntry($collection, $req, null);
    }

    private function entryUpdate(string $handle, int $id): string
    {
        $collection = $this->mustFind($handle);
        $this->requireManage($collection);
        $req = Request::fromGlobals();
        $this->requireCsrf($req);

        if ($this->entries->find($collection->id, $id) === null) {
            $this->redirect("/admin/collections/{$handle}/entries");
        }
        return $this->saveEntry($collection, $req, $id);
    }

    /** Read request -> input object -> EntryService; render errors or redirect. */
    private function saveEntry(Collection $collection, Request $req, ?int $id): string
    {
        $input  = $this->inputFromRequest($collection, $req);
        $result = $this->entryService->save($collection, $input, $id, $this->auth->user()?->id);

        if (!$result->successful) {
            return $this->renderEntryForm($collection, $this->modelFromInput($input, $id), $result->errors);
        }
        $msg = $id === null ? 'created' : ($collection->isSingle() ? 'saved' : 'updated');
        $this->redirect("/admin/collections/{$collection->handle}/entries?msg={$msg}");
    }

    private function entryDestroy(string $handle, int $id): string
    {
        $collection = $this->mustFind($handle);
        $this->requireManage($collection);
        $this->requireCsrf(Request::fromGlobals());
        // Singletons aren't deleted as entries — there's always exactly one.
        if ($collection->isSingle() || $this->entries->find($collection->id, $id) === null) {
            $this->redirect("/admin/collections/{$handle}/entries");
        }
        $this->entryService->delete($collection, $id);
        $this->redirect("/admin/collections/{$handle}/entries?msg=deleted");
    }

    // =============================================================== helpers

    private function renderEntryForm(Collection $collection, array $model, array $errors, ?string $flash = null): string
    {
        // Relation pickers need their target collection's entries (id => title).
        $relationOptions = [];
        foreach ($collection->fields as $field) {
            if ($field->type === 'relation') {
                $target = (string) $field->option('target', '') !== '' ? $this->collections->findByHandle((string) $field->option('target')) : null;
                $relationOptions[$field->handle] = $target !== null ? $this->entries->titleMap($target->id) : [];
            }
        }
        return $this->page('entries/form', 'collections', [
            'collection'      => $collection,
            'model'           => $model,
            'errors'          => $errors,
            'flash'           => $flash,
            'types'           => $this->types,
            'relationOptions' => $relationOptions,
            'csrf'            => Csrf::token(),
        ]);
    }

    /** Editing/new: build the form model from a stored entry (or field defaults). */
    private function modelFromEntry(Collection $collection, ?array $entry): array
    {
        if ($entry !== null) {
            $values = is_array($entry['data']) ? $entry['data'] : [];
            foreach ($collection->fields as $field) {
                if ($field->type === 'relation') {
                    $values[$field->handle] = $this->relations->targets((int) $entry['id'], $field->id);
                }
            }
            return [
                'id'     => (int) $entry['id'],
                'title'  => (string) $entry['title'],
                'slug'   => (string) $entry['slug'],
                'status' => (string) $entry['status'],
                'values' => $values,
            ];
        }
        $values = [];
        foreach ($collection->fields as $field) {
            $values[$field->handle] = $field->type === 'relation' ? [] : $field->option('default', '');
        }
        return ['id' => null, 'title' => '', 'slug' => '', 'status' => 'draft', 'values' => $values];
    }

    /** Build the typed input object from the request (with normalized values). */
    private function inputFromRequest(Collection $collection, Request $req): EntryInput
    {
        $posted = $req->all()['f'] ?? [];
        $values = [];
        foreach ($collection->fields as $field) {
            $raw = is_array($posted) ? ($posted[$field->handle] ?? null) : null;
            $values[$field->handle] = $this->types->get($field->type)->normalize($raw);
        }
        return new EntryInput(
            trim((string) $req->input('title')),
            trim((string) $req->input('slug')),
            in_array($req->input('status'), ['draft', 'published'], true) ? (string) $req->input('status') : 'draft',
            $values,
        );
    }

    /** Re-render the form after a failed save, preserving what the user typed. */
    private function modelFromInput(EntryInput $input, ?int $id): array
    {
        return ['id' => $id, 'title' => $input->title, 'slug' => $input->slug, 'status' => $input->status, 'values' => $input->values];
    }

    /**
     * @return array<int,array{handle:string,label:string,type:string,required:bool,options:array}>
     */
    private function fieldDefs(Request $req): array
    {
        $defs   = [];
        $fields = $req->all()['fields'] ?? [];
        if (!is_array($fields)) {
            return $defs;
        }
        foreach ($fields as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type   = ($row['type'] ?? 'text');
            $type   = $this->types->has($type) ? $type : 'text';
            $handle = Str::handle(($row['handle'] ?? '') !== '' ? $row['handle'] : $label);

            $options = [];
            if ($this->types->get($type)->hasChoices()) {
                $choices = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($row['choices'] ?? '')) ?: [])));
                if ($choices !== []) {
                    $options['choices'] = $choices;
                }
            }
            foreach (['default', 'placeholder', 'help'] as $opt) {
                $val = trim((string) ($row[$opt] ?? ''));
                if ($val !== '') {
                    $options[$opt] = $val;
                }
            }
            if ($type === 'relation') {
                $options['target']   = trim((string) ($row['target'] ?? ''));
                $options['multiple'] = !empty($row['multiple']);
            }
            $defs[] = ['handle' => $handle, 'label' => $label, 'type' => $type, 'required' => !empty($row['required']), 'options' => $options];
        }
        return $defs;
    }

    /** @return array<string,mixed> collection options (kind + permissions) */
    private function options(Request $req): array
    {
        $roles = $req->all()['roles'] ?? [];
        $roles = is_array($roles) ? array_values(array_intersect(Permissions::ROLES, $roles)) : [];
        $kind  = $req->input('kind') === 'single' ? 'single' : 'collection';
        return ['kind' => $kind, 'permissions' => ['manage' => $roles]];
    }

    private function icon(Request $req): string
    {
        $icon = trim((string) $req->input('icon'));
        return $icon !== '' ? mb_substr($icon, 0, 4) : '❑';
    }

    /** @return string[] field types that use the choices builder */
    private function choiceTypes(): array
    {
        $out = [];
        foreach (array_keys($this->types->choices()) as $type) {
            if ($this->types->get($type)->hasChoices()) {
                $out[] = $type;
            }
        }
        return $out;
    }

    private function mustFind(string $handle): Collection
    {
        $collection = $this->collections->findByHandle($handle);
        if ($collection === null) {
            $this->redirect('/admin/collections');
        }
        return $collection;
    }

    private function requireAdmin(): void
    {
        $this->guard();
        if (!Permissions::isAdmin($this->auth->user())) {
            $this->redirect('/admin/collections');
        }
    }

    private function requireManage(Collection $collection): void
    {
        $this->guard();
        if (!Permissions::canManage($this->auth->user(), $collection)) {
            $this->redirect("/admin/collections/{$collection->handle}/entries");
        }
    }

    private function requireCsrf(Request $req): void
    {
        if (!Csrf::check($req->input('_token'))) {
            $this->redirect('/admin/collections');
        }
    }
}
