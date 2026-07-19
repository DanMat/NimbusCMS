<?php

declare(strict_types=1);

namespace Panelix;

use Panelix\Auth\Auth;
use Panelix\Config\CmsConfig;
use Panelix\Database\Connection;
use Panelix\Http\Csrf;
use Panelix\Http\Request;
use Panelix\Resource\EntityRepository;
use Panelix\Resource\Resource;
use Panelix\Support\Password;
use Panelix\View\View;

/**
 * The CMS kernel: boots the object graph from config, routes the request,
 * gates it by auth + role, and dispatches generic CRUD actions. Host apps call
 * (new Cms($config))->run() from a single admin entry point.
 */
final class Cms
{
    private Connection $db;
    private Auth $auth;
    private View $view;
    private EntityRepository $entities;

    public function __construct(private CmsConfig $config)
    {
        $this->db       = new Connection($config->db);
        $this->auth     = new Auth($this->db, $config);
        $this->view     = new View(__DIR__ . '/View/templates', $config, $this->auth);
        $this->entities = new EntityRepository($this->db);
    }

    public function run(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        try {
            $this->handle(Request::fromGlobals());
        } catch (\Throwable $e) {
            http_response_code(500);
            echo $this->view->renderBare('error', ['title' => 'Something went wrong', 'message' => $e->getMessage()]);
        }
    }

    private function handle(Request $req): void
    {
        $page = $req->query('p', 'dashboard');

        if ($page === 'login') {
            $this->login($req);
            return;
        }
        if ($page === 'logout') {
            $this->auth->logout();
            $this->redirect('p=login');
        }

        if (!$this->auth->check()) {
            $this->redirect('p=login');
        }

        if ($page === 'dashboard') {
            echo $this->dashboard();
            return;
        }
        if ($page === 'resource') {
            $this->resource($req);
            return;
        }

        http_response_code(404);
        echo $this->view->renderBare('error', ['title' => 'Not found', 'message' => 'Unknown page.']);
    }

    private function login(Request $req): void
    {
        if ($this->auth->check()) {
            $this->redirect('p=dashboard');
        }
        $error = null;
        if ($req->isPost()) {
            if (!Csrf::check($req->input('_token'))) {
                $error = 'Your session expired. Please try again.';
            } elseif ($this->auth->attempt((string) $req->input('username'), (string) $req->input('password'))) {
                $this->redirect('p=dashboard');
            } else {
                $error = 'Invalid username or password.';
            }
        }
        echo $this->view->renderBare('login', ['error' => $error, 'csrf' => Csrf::token()]);
    }

    private function dashboard(): string
    {
        $cards = [];
        foreach ($this->config->resourcesFor($this->auth->role()) as $resource) {
            $cards[] = ['resource' => $resource, 'count' => $this->entities->count($resource)];
        }
        return $this->view->render('dashboard', ['cards' => $cards]);
    }

    private function resource(Request $req): void
    {
        $key      = (string) $req->query('r');
        $resource = $this->config->resource($key);

        if ($resource === null) {
            http_response_code(404);
            echo $this->view->renderBare('error', ['title' => 'Not found', 'message' => 'Unknown resource.']);
            return;
        }
        if (!$this->authorized($resource)) {
            http_response_code(403);
            echo $this->view->renderBare('error', ['title' => 'Forbidden', 'message' => 'You do not have access to ' . $resource->label . '.']);
            return;
        }

        $action = $req->query('a', 'index');
        $id     = (int) $req->query('id', '0');

        if ($req->isPost()) {
            if (!Csrf::check($req->input('_token'))) {
                $this->redirect('p=resource&r=' . $key);
            }
            if ($action === 'create') {
                $this->save($resource, null, $req);
            }
            if ($action === 'update') {
                $this->save($resource, $id, $req);
            }
            if ($action === 'delete') {
                $this->entities->delete($resource, $id);
                $this->redirect('p=resource&r=' . $key . '&msg=deleted');
            }
            $this->redirect('p=resource&r=' . $key);
        }

        if ($action === 'new') {
            echo $this->form($resource, null);
            return;
        }
        if ($action === 'edit') {
            echo $this->form($resource, $this->entities->find($resource, $id));
            return;
        }

        echo $this->view->render('resource/index', [
            'resource' => $resource,
            'rows'     => $this->entities->all($resource, $req->query('q')),
            'labels'   => $this->relationLabels($resource),
            'search'   => $req->query('q'),
            'flash'    => $req->query('msg'),
        ]);
    }

    private function form(Resource $resource, ?array $row): string
    {
        $options = [];
        foreach ($resource->formFields() as $field) {
            if ($field->type === 'belongsTo') {
                $options[$field->name] = $this->entities->options(
                    (string) $field->options['table'],
                    (string) $field->options['key'],
                    (string) $field->options['display']
                );
            }
        }
        return $this->view->render('resource/form', [
            'resource' => $resource,
            'row'      => $row,
            'options'  => $options,
            'csrf'     => Csrf::token(),
        ]);
    }

    private function save(Resource $resource, ?int $id, Request $req): void
    {
        $data = [];
        foreach ($resource->formFields() as $field) {
            $raw = $req->input($field->name);

            switch ($field->type) {
                case 'password':
                    if ($raw === null || $raw === '') {
                        continue 2; // blank => keep existing password
                    }
                    $data[$field->name] = Password::hash($raw);
                    break;
                case 'boolean':
                    $data[$field->name] = $raw ? 1 : 0;
                    break;
                case 'money':
                case 'number':
                    $data[$field->name] = ($raw === null || $raw === '') ? 0 : $raw;
                    break;
                default:
                    $data[$field->name] = $raw ?? '';
            }
        }

        if ($id === null) {
            $this->entities->create($resource, $data);
            $this->redirect('p=resource&r=' . $resource->key . '&msg=created');
        }
        $this->entities->update($resource, $id, $data);
        $this->redirect('p=resource&r=' . $resource->key . '&msg=updated');
    }

    /** @return array<string,array<string,string>> field => (value => label) for belongsTo columns */
    private function relationLabels(Resource $resource): array
    {
        $labels = [];
        foreach ($resource->listFields() as $field) {
            if ($field->type === 'belongsTo') {
                $labels[$field->name] = $this->entities->options(
                    (string) $field->options['table'],
                    (string) $field->options['key'],
                    (string) $field->options['display']
                );
            }
        }
        return $labels;
    }

    private function authorized(Resource $resource): bool
    {
        return $this->auth->isAdmin() || $resource->allowedFor($this->auth->role());
    }

    private function redirect(string $query): never
    {
        header('Location: ' . $this->config->url($query));
        exit;
    }
}
