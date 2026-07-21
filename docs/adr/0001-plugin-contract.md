# ADR 0001 — The first plugin contract

- **Status:** Accepted (design only — nothing here is implemented yet)
- **Date:** 2026-07-21
- **Supersedes:** nothing
- **Context:** the plugin-readiness milestone (PRs #1–#11)

## Context

NimbusCMS needs an extension mechanism. The core is now stable enough to
freeze one: writes go through services, handlers have a fixed
`Request → Response` contract, field-type lookup is strict, events are
truthful, and CI proves it with 183 tests, static analysis and a smoke test.

The question this record answers is not *whether* to have plugins. It is how
small the first version can be while still being useful.

The failure mode we are avoiding is well documented by every plugin ecosystem
that came before: a surface published early becomes permanent, because
breaking it breaks other people's work. WordPress cannot remove hooks it
regretted in 2005. The cheapest time to say no to an extension point is before
anyone depends on it.

## Decision

### The contract

```php
interface Plugin
{
    public function register(PluginContext $context): void;
}
```

One method. A plugin registers what it provides and returns. It does not get
a lifecycle, a boot order, a priority number, or a dependency graph — those
are all things we can add once a real plugin demonstrates it needs them.

### What PluginContext exposes

Only surfaces that already have a **first-party consumer in the core**. If
nothing in Nimbus itself uses a registry, we cannot know its shape is right,
and publishing it is a guess that becomes a commitment.

| Registry | First-party consumer today |
|----------|---------------------------|
| `FieldTypeRegistry` | nine built-in field types |
| `RouteRegistry` | admin, collections and entries controllers |
| `EventRegistry` | `CoreEvents` entry lifecycle |
| `PermissionRegistry` | per-collection manage roles, admin override |
| `MigrationRegistry` | the three core migrations |
| `AdminNavigationRegistry` | the admin sidebar |

`FieldTypeRegistry` is the one that already exists and already works — it has
been the plugin seam since the collections engine landed. The other five are
described here and built when the reference plugin needs them.

### What PluginContext deliberately does not expose

- **`Application`** — hands a plugin the kernel, and every internal becomes API.
- **Controllers** — internal structure; #11 moved half of one and no plugin
  should have noticed or cared.
- **Mutable session internals** — auth state is core's to own. Plugins that
  need identity get the resolved user, not the session array.
- **Internal repositories** — direct table access bypasses the services that
  own transactions, validation, slug rules and events. This is the invariant
  the entire stabilisation milestone existed to protect.
- **A service locator or arbitrary object container** — `$context->get('anything')`
  is not an API, it is the absence of one. It makes every internal reachable
  and every refactor a breaking change.

The rule: plugins receive **capabilities**, never the objects that implement
them.

### Installation

Composer, for the first iteration. It already handles versioning,
autoloading, dependency resolution and lockfiles, and Nimbus is a Composer
package. Adding a bespoke installer would mean reimplementing all of that
worse.

The future nimbuscms.dev site may **index** Composer packages and add
reviewed marketplace metadata (categories, screenshots, compatibility, download
counts). Indexing is a directory, not an execution path.

**The CMS core will not download and execute arbitrary package code.** An
in-admin "install plugin" button is a remote-code-execution feature wearing a
friendly hat. It needs package signing, a compatibility policy, a rollback
story and a trust model — none of which are designed. Until they are,
installing a plugin is a deliberate act at the command line, by someone with
shell access, recorded in `composer.lock` and reviewable in a diff.

## Consequences

**Good.** The surface is small enough to support indefinitely. Everything in
it is proven by a core consumer rather than imagined. Composer does the hard
parts. Nothing ships that we would have to break later.

**Accepted costs.** Installing a plugin requires shell access, which is worse
UX than WordPress and is the correct trade until the trust model exists.
Plugins cannot do everything at first — deliberately; the answer to "I need
X" is to add X with a first-party consumer, not to open a container. Six
registries is more surface than one, but each has a demonstrated need.

**Open questions**, to be answered by building the reference plugin rather
than by more design:

- Do plugins need a disable path beyond removing the Composer package?
- Does `EventRegistry` need pre-commit (vetoing) events, or is post-commit
  notification enough? (See `CoreEvents` — the current answer is post-commit
  only, and it has not hurt yet.)
- How do plugin migrations interleave with core migrations on upgrade?
- What happens to entries when a plugin providing a field type is removed?
  Core already answers this safely — `MissingType` preserves the data and
  blocks writes — but the admin recovery flow is not designed.

## Next step

Build **one small official reference plugin** against this contract, and let
it prove or break the design before anything is declared stable. A good
candidate uses exactly one registry and has an obvious correct behaviour —
a single field type, for example — so that what it exercises is the contract
itself rather than the plugin's own complexity.
