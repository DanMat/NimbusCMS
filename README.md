# Panelix

A super-lightweight, config-driven PHP admin CMS. Point it at your existing
database, declare a few resources, and get a clean admin panel — auth, roles,
and full CRUD — with no per-entity boilerplate and no schema changes.

Built to be dropped into legacy PHP apps: it reads your current users table and
upgrades legacy `md5` password hashes to bcrypt on login.

## Highlights

- **Config-driven** — declare a `Resource` (table + fields + roles); Panelix
  generates list / create / edit / delete screens from it.
- **Layered & safe** — `Connection → Repository → Service/Kernel → View`. Every
  query is a prepared statement; output is escaped; writes are CSRF-protected.
- **Roles** — gate resources per role; an `adminRoles` list sees everything.
- **Zero dependencies** — PHP 8.1+ and PDO. Use via Composer, or the bundled
  `autoload.php`.

## Install

```bash
composer require danmat/panelix
```

Or, without Composer, `require __DIR__ . '/path/to/panelix/autoload.php';`.

## Usage

Create one admin entry point (e.g. `admin/index.php`):

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Panelix\Cms;
use Panelix\Config\CmsConfig;
use Panelix\Resource\Resource;
use Panelix\Resource\Field;

$config = new CmsConfig(
    db: [
        'host' => '127.0.0.1', 'name' => 'shop',
        'user' => 'root', 'pass' => 'secret',
    ],
    // map to your existing users table
    users: [
        'table' => 'employee', 'id' => 'id',
        'username' => 'username', 'password' => 'pwd', 'role' => 'role',
    ],
    resources: [
        Resource::make('item', 'Menu items', 'item', 'itemid')
            ->field(Field::text('desc', 'Name')->required())
            ->field(Field::belongsTo('catid', 'Category', 'category', 'catId', 'catDesc'))
            ->field(Field::money('price', 'Price'))
            ->roles(['admin', 'manager']),

        Resource::make('category', 'Categories', 'category', 'catId')
            ->field(Field::text('catDesc', 'Name')->required())
            ->roles(['admin', 'manager']),
    ],
    brand: 'My Shop',
    basePath: '/admin',
    adminRoles: ['admin'],
);

(new Cms($config))->run();
```

That's the whole integration. Serve `admin/` and sign in with a user from your
table.

## Field types

`text` · `textarea` · `number` · `money` · `email` · `boolean` · `password`
(write-only) · `select` (static options) · `belongsTo` (FK dropdown).

## License

MIT © DanMat
