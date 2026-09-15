# Orphan detection (dead code)

Extracted from the README, which had grown to carry this subsystem's full
manual — suppression rules, tags, scan-root guarding and the framework caveats —
inside a file people open to learn how to run a clone detector. It is a
subsystem, so it gets a document.

`phpcpd --orphans src/` is the whole entry point; everything below is what the
flag means and when to trust it.

Clones are duplicated code; **orphans are unreachable code** — a class, interface, trait, enum, or
global function that nothing references. Same token engine, no parser, no AST, no runtime dependency.

Two modes:

- **Default run** — orphans ride along with the clone scan as an **advisory**: they're reported, but
  only clones set the exit code. (Safe for framework-heavy projects where dynamic dispatch causes
  false positives — see the Laravel note below.)
- **`--orphans`** — orphans **only**, and they **gate CI**: a *definite* orphan makes the run exit
  non-zero, exactly like a clone.

```bash
phpcpd --orphans src/
```

```text
Found 1 orphaned symbol(s):

  - Class App\Legacy\UnusedReport
    src/Legacy/UnusedReport.php:14
    → never referenced
    ⤷ whole file is unwired — no symbol declared here is referenced

  - Class App\Billing\InvoiceLegacy
    src/Billing/Ledger.php:120
    → never referenced
    ⤷ looks like a superseded copy of App\Billing\Invoice (src/Billing/Ledger.php:14)

Found 2 possible orphan(s) — review before removing:

  - Interface App\Contract\PaymentGateway
    src/Contract/PaymentGateway.php:9
    → never referenced (interface — may be implemented outside the scanned set)

  - Class App\Support\Dynamic
    src/Support/Dynamic.php:11
    → never referenced in code; name appears in a string literal (possible dynamic use)
    ⤷ name appears at src/Support/Registry.php:23

Planned, not yet wired — 1

  - Class App\Console\Components\Spinner
    src/Console/Components/Spinner.php:39
    → Wired by the console rework.

Suppressed (30): conditional 12 · fixtures 9 · config 8 · namespace 1
  → --explain to list them

Scanned symbols (829), files (764); orphaned (2), possible (2), suppressed (30), planned (1).
```

Findings are grouped by cause rather than listed flat, so the group worth reading — the symbols
nothing explains — is visible instead of buried. A string-literal demotion cites **where** the name
appears, which is the whole verification for most entries; without it every demotion costs a grep.

Each finding is **explained**, not just listed:

- **`whole file is unwired`** — *every* symbol in that file is an orphan, so the whole file is dead
  (phpunused's "unreferenced file", at symbol granularity).
- **`looks like a superseded copy of X`** — the orphan's body duplicates a **live** symbol, so it's
  almost certainly the stale copy a refactor replaced and forgot to delete. This reuses the clone
  engine — the orphan × clone synergy that a pure dead-code linter can't offer.

**Four result tiers**, so the tool never nags you into deleting live code:

| Tier | Meaning | Gates CI |
|------|---------|----------|
| **Definite** | Referenced nowhere; safe to delete. | Yes (default) |
| **Possible** | A contract (interface / `abstract` / `trait`) an out-of-tree package may implement, extend, or `use`, or a name that appears only in a **string literal** (a candidate for `new $class` / a DI-container id). | No |
| **Suppressed** | A rule structurally accounts for it (see below). Counted and listed on request. | No |
| **Planned** | Marked `@phpcpd-planned`: knowingly written ahead of the code that will wire it. | No |

Widen the gate with `--fail-on=dead,planned` — shipping a release with staged, unwired components
should be a decision someone makes on purpose.

### Suppression rules

A symbol nothing references is not automatically a finding. Each rule below recognises a *structural*
reason the symbol is fine — a guard statement, a namespace the project doesn't own, a manifest entry,
a fixture path — never a name pattern or a guess about intent.

| Rule | Recognises |
|------|------------|
| `conditional` | Declared inside `if (!function_exists('x'))` / `class_exists` / `interface_exists` / `trait_exists` / `enum_exists` — a polyfill or compatibility shim, by definition declared for a caller this scan cannot see. |
| `namespace` | Declared outside every `psr-4`/`psr-0` prefix the project's own `composer.json` declares — code published under another package's namespace so someone else's call resolves to it. |
| `manifest` | Named in `composer.json`: an `autoload.files` entry point, or an FQN under `extra` (Laravel providers and aliases, and anything shaped like a class name). |
| `config` | Named in a `.neon`, `.yaml`, `.yml`, `.xml`, or `.dist` file — PHPStan rules, Symfony DI, Doctrine mapping — **or in a `config/*.php` array**, which is Laravel's native config format. See below. |
| `template` | Named in a `.blade.php`, `.twig`, `.latte`, or `.tpl` file. In a Laravel application a view is a primary call site, so a class called only from a template is referenced, not dead. |
| `fixtures` | Under a `Fixtures`/`Stubs` directory *inside a test tree*. Being unreferenced is what makes a fixture a fixture. |
| `keep` | Carries `@api`, `@psalm-api`, `@phpstan-api`, `@phpcpd-keep`, or `@phpcpd-ignore-orphan`. |
| `entrypoint` | Wired reflectively: `#[Route]`, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, `#[Entity]`, `#[Attribute]` and more, a `*Test` class, or a class declared in a `Database\Seeders` / `Database\Factories` namespace, which the framework instantiates by convention. |
| `discovery` | Selected by a sibling file that **globs its own directory and instantiates by filename** — see below. |
| `convention` | A **companion class** a trait names by appending a literal suffix to its consumer's own class name — see below. |
| `planned` | Carries `@phpcpd-planned`. |

#### Class names the code *constructs* instead of writing

Reference detection asks whether a name is mentioned. That question has no answer for a class whose
name is never written down — assembled at runtime from a filename, or from a base class plus a
suffix. The scan correctly finds no mention and incorrectly concludes the class is dead. On a live
Laravel monolith audit this family accounted for **64 of 94** reported dead symbols: 68% false
positives, every one of which would have deleted wired code.

The fix is not to widen reference detection, which costs precision everywhere. It is to recognise
the **wiring** and cite it, so a suppression is something you can check and disagree with.

**`discovery` — a directory scan that instantiates by filename.** A base class globs a directory,
derives a fully-qualified name from each filename, checks `class_exists` and instantiates the
result; a front controller dispatches through the map. Nothing names the discovered classes, so all
of them read as dead — 52 in one directory on the audited project.

All **three** token signals are required, because each alone is ordinary code:

1. a directory read — `glob` / `scandir` / a `DirectoryIterator` — whose argument is anchored at `__DIR__`;
2. an instantiation through a variable (`new $class` or `$class::`);
3. a `class_exists(` guard.

The loop's **glob pattern** is then applied to filenames, so only the classes the loop actually
reaches are spared. A class sitting beside them that the pattern does not select stays reported —
that is the difference between a rule that spares live endpoints and one that only makes the number
go down.

```text
Discovered by a directory scan (instantiated from its filename) — 3

  - Class Neutral\Handlers\AlphaHandler
    src/Handlers/AlphaHandler.php:4
    → discovered by a directory scan — instantiated from its filename behind class_exists
    ⤷ discovered by the loop at src/Handlers/HandlerRegistry.php:10
```

**Boundary — the `__DIR__` anchor is load-bearing.** A glob over a path that is *not* built from
`__DIR__` (a configured directory, a path argument, an absolute string) is deliberately out of
scope: without the anchor nothing in the token stream says which directory on disk the loop reads,
so the rule would be suppressing a directory it cannot name. Such a loop is left unrecognised and
the classes it discovers stay reported.

**`convention` — a companion class named by suffix (12 of the 64).** A trait resolves a companion
class by concatenating `static::class` (or `class_basename(static::class)`) with a literal suffix —
the translatable idiom, where a model `Article` is paired with an `ArticleTranslation`. The
companion's name is never written, so every companion read as dead.

The suffix is registered with the type that declared it, and **both** halves of the claim are then
required before `<X><Suffix>` is spared:

1. a class `<X>` exists in the scanned set, and
2. that `<X>` actually `use`s the trait that declared the suffix.

Drop either and the rule becomes "any class ending in `Translation`", which would spare the dead
ones too. A companion whose base does not exist, or whose base does not use the trait, stays
reported.

```text
Companion class named by convention (suffix declared by a trait) — 1

  - Class Neutral\Content\ArticleTranslation
    src/Content/ArticleTranslation.php:3
    → companion class — Neutral\Content\Article uses Neutral\Support\Translatable, which resolves this name by suffix at runtime
    ⤷ suffix declared at src/Support/Translatable.php:8
```

**Boundary — the suffix must come from a literal.** Both shapes the field data showed are read:
`static::class . 'Translation'` and `. config('x.suffix', 'Translation')`, where the literal is the
`config()` default. A suffix with *no* literal anywhere — `config('x.suffix')` resolved entirely at
runtime — is out of scope, because inventing one would mean suppressing by class-name pattern,
which is the one thing no rule here does.

Both of these are suppression rules, not excludes, on purpose. Excluding the directory would hide
the genuinely dead code sitting next to the wired code — and the audit found exactly that.

#### `config/*.php` is configuration, not just source

Laravel's native config format is PHP: `config/*.php` returns an array of `Provider::class` entries
and quoted fully-qualified names. With `.php` absent from the config sweep's suffix list, none of
that wiring was visible — measured on the audited monolith, the `config` rule suppressed **exactly
one** symbol repo-wide, while the classes it should have accounted for sat in those arrays. And
because a scan is normally pointed at `src/`, `config/` is outside the scanned source, so the
`::class` entries are not code references the collector ever sees either.

Both citation styles resolve, since the sweep matches on name *shape* rather than on syntax:

```text
Registered in a config file — 2

  - Class Neutral\Services\QueueService     ⤷ named in config/services.php:8   ('Neutral\Services\QueueService')
  - Class Neutral\Services\ReportService    ⤷ named in config/services.php:7   (\Neutral\Services\ReportService::class)
```

This moves those symbols from **possible** (a name that showed up in a string somewhere) to
**suppressed** with a config citation — which is the tier split people actually act on.

**Boundary — only directories named `config/`.** Arbitrary `.php` files are *not* swept as
configuration, and must not be: every `.php` file in the project is already read as source, where a
class name is scored as a reference or as the weak string-mention signal. Reading them a second time
as configuration would promote every string literal anywhere to a config registration — a
suppression rule that fires on everything. A `config` path segment is a structural statement about
the file's role, which is the standard every other rule here meets.

**Suppressed does not mean hidden.** The count is always printed, broken down by rule; `--explain`
lists the symbols. That is deliberate — a rule that starts over-firing shows up as a number that
moved, whereas a silent suppression would quietly turn a real orphan into no output at all.

```text
Suppressed (30): conditional 12 · fixtures 9 · config 8 · namespace 1
  → --explain to list them
```

Turn any rule off by name to audit it — the symbols are then judged normally rather than skipped:

```bash
phpcpd --orphans --no-suppress=fixtures,config src/
phpcpd --orphans --no-suppress=all src/          # raw, unfiltered
```

### Clone scanning and reference scanning read different files

`*.blade.php` sits in the Laravel preset's excludes and belongs there — templates are repetitive by
nature and would flood a clone report. Carrying that exclude into *reference* detection does the
opposite of its purpose: it hides the call sites, so a class used only from a view is reported dead.

The two file sets are therefore separate. Clone detection keeps its excludes; reference scanning
additionally reads templates for symbol mentions, without ever treating them as scannable source for
clones. An exclude is dropped from the reference scan only when it blinds templates *specifically* —
it matches a template filename but not a plain `.php` one — so `vendor` and `demo` still prune, and
only patterns like `*.blade.php` step aside.

### The config and template trees are read only if something asks

The `config` and `template` rules are the **last** questions the decision tree asks: a
symbol reaches them only after a code reference, an author tag, `composer.json`, the
namespace rule and the fixture rule have all declined. So the two trees are swept lazily —
on the first symbol that gets that far, once per run, and never at all on a scan where
nothing does. A project with no unreferenced symbols never opens a `.yaml` or a
`.blade.php` file; a project with a hundred of them still reads each tree once.

That laziness is also what pays for the `config/*.php` pass: finding those files means a second walk
over the source tree, which is affordable precisely because it happens at most once per run and only
when a symbol actually reaches the config rule.

This changes only *when* the files are read. The roots and excludes are fixed when the
scan resolves its scope, so a deferred sweep sees exactly the tree the run was pointed
at, and the report is identical either way.

### Orphan detection needs to see the whole project

A symbol is called dead when **nothing** references it, and "nothing" is a claim about the entire
project. A scan narrower than the autoload roots cannot support that claim, so it says so:

```text
Warning: --orphans is scanning below the autoload roots of /home/you/app/composer.json.
  A symbol is called dead when *nothing* references it, which is a claim about the
  whole project. Code outside this scan can still reference what is reported here —
  scan the project root for a result worth acting on.
```

Measured on a modular monolith: scanning one controller directory reported nine live controllers as
"whole file is unwired". Every one of them was imported from a route file in a different package,
outside the scanned path. The warning fires only when *every* scan root sits below every directory
`composer.json` maps — `phpcpd --orphans src/` in an ordinary package stays quiet, because `src/` is
what the manifest maps.

### Tags

Two docblock tags, making opposite claims — do not use one for the other:

```php
/** @phpcpd-keep Registered in phpstan/extension.neon */   // "this IS reachable, you just can't see it"
/** @phpcpd-planned Wired by the console rework. */        // "this is NOT wired yet, and that's known"
```

Both take a free-text reason, which is printed next to the symbol so the next reader learns *why*
without re-deriving it — and so a suppression whose stated reason has gone stale becomes reviewable.
A `@phpcpd-planned` symbol that later *does* get referenced is reported as a possible finding, since
the tag has served its purpose and should be deleted; `@phpcpd-keep` can never give that prompt.

Tags must start a docblock line. Prose such as `Unlike @api classes, this one is internal` does not
suppress anything.

**Scope, honestly.** Orphan detection stops at the type/function level — the "unreferenced file" case.
Method- and property-level dead code needs whole-program type inference (*which* class does
`$this->handle()` resolve to under inheritance and a DI container?); that is PHPStan + Psalm's job, and
this token-based tool deliberately does not guess at it. What it does do — decide whether a *named*
type or function is ever mentioned at all — it does safely: reference detection is generous by design,
so it prefers to stay silent over flagging something that is used. Point it at a whole project
(including `bin/`, entry scripts, and config) so legitimate roots are seen as referenced.

**Laravel and other convention-driven frameworks.** Treat orphan output as *review candidates, not a
delete list*. Laravel reaches many classes with no by-name reference, and they fall into three buckets:
`[Controller::class, 'method']` routes, `$listen`/`$subscribe` arrays and `app(Foo::class)` all use
`::class`, which counts as a **real reference**; string-based references (string route actions, class
names in `config/`, container bindings) are demoted to **possible** — *as long as you scan those files
too* (`routes/`, `config/`); but **convention/auto-discovery** (policies, Livewire/Filament components,
commands loaded via `load()`, model observers) leaves classes with no textual mention at all, and those
**will false-positive**. This is exactly why orphans are advisory in the default run — scan the whole
app, lean on the *possible* tier, and reach for `--orphans` (the gating mode) on code you control.

Embed it the same way as clone detection:

```php
use LucianoPereira\PhpcpdNext\Orphans;

$result = Orphans::detect('src');

if ($result->hasDefiniteOrphans()) {
    foreach ($result->definite() as $orphan) {
        echo $orphan->symbol->fqn, ' — ', $orphan->reason, PHP_EOL;
    }
}

// Accounted-for symbols are kept, not dropped — inspect or audit them:
foreach ($result->suppressed() as $entry) {
    echo $entry->symbol->fqn, ' suppressed by ', $entry->rule, PHP_EOL;
}

foreach ($result->planned() as $entry) {
    echo $entry->symbol->fqn, ' — ', $entry->reason, PHP_EOL;   // the staged-work backlog
}

// Turn a rule off to see what it was accounting for.
$audited = Orphans::detect('src', noSuppress: ['fixtures']);
```
