# Localization

Every sentence phpcpd-next prints lives in `locale/`, one file per language,
keyed by what the message does. Twenty-eight ship. `locale/en.php` is the
fallback, so a translation may be partial and still be useful.

This document is the guidance. The locale files carry the strings and nothing
else, so that a translator opening `locale/fr.php` reads French rather than a
rationale they have already read here.

`php bench/check-locales.php` reports every translation's coverage, and fails on
the two ways a locale file is always wrong: a key English does not have, and a
`:placeholder` that was renamed or dropped. A *missing* key is not a failure —
it is the number a translator wants, and it falls back.

## Adding a language

Copy `locale/en.php` to `locale/xx.php`, translate the leaves, keep the keys.
Nothing else is registered: `Catalogue::available()` lists the directory, so the
new code becomes legal in `--language` and as `language =` in `phpcpd.ini` the
moment the file exists.

Missing keys fall back to English, one key at a time. A key that `en` does not
have raises `MissingStringException` — that is a bug in the caller, not a gap in
a translation, and it should not be quiet.

## Placeholders are `:name`, never `%s`

```php
'aboveProject' => 'Refusing to scan :path: it is above the project root :project.',
```

`sprintf` numbers its arguments by position, so a language that wants the
project before the path has to reach for `%2$s` and count. A named placeholder
can be moved anywhere in the sentence, dropped if the language does not need it,
or repeated. Substitution is `strtr` over exactly the parameters the call site
passed, so an unpassed `:name` survives visibly rather than becoming an empty
string.

## A key holds a whole sentence

Never a fragment the code will join to another fragment. `:subject :verb :object`
is not a pattern, it is English word order plus an assumption that nothing
agrees with anything. The moment the code owns the grammar, the translation
cannot fix it.

Frames are the one exception, and only because their slot holds a whole message:

```php
'frame' => [
    'error'   => 'ERROR: :message',
    'hint'    => ":message\n:hint",
    'inFile'  => 'In :file: :message',
],
```

A severity marker is a channel marker rather than grammar — it never inflects
and never reorders with the sentence after it — so defining it once is safe
where composing a sentence from words would not be. It also carries what colour
and stream cannot: `NO_COLOR`, a pipe, or `2>&1` in a CI log all erase the
difference between a failure and ordinary output. A word does not erase.

## Counts are written label-first

```php
'file' => 'files (:count)',
```

`Scanned files (1), roots (1), excludes (13)` rather than
`Scanned 1 file(s), 1 path(s)`. Putting the count after the label removes the
agreement problem instead of papering over it: `files (1)` and `files (105)` are
both correct, and so is the translation in a language where `(s)` means nothing
and where the plural rule has six branches rather than two. This is why the
catalogue has no plural mechanism at all — it had two, and retiring them cost
nothing but this convention.

## Keyed by act, then by result

The first segment says what the message *does* to its reader:

```
refuse  warn  notice  report  explain  label  advise  help  document
```

Not by the class that prints it. The emitting class is an implementation detail
a translator has no reason to know, and keying by it hid a real problem: a dozen
refusals lived under six groups and had drifted into four ways of saying
"unknown value". They only look inconsistent when they sit together.

Inside `refuse`, the second segment is the **result** and the third is its
subject — `refuse.needsValue.option` and `refuse.needsValue.setting`, not
`refuse.option.needsValue` and `refuse.config.settingNeedsValue`. Same reason,
one level down: subject-first put those two lines forty lines apart, and one of
them said "requires a value" while the other said "needs a value" for years.

## What belongs in the catalogue, and what does not

A string that is **printed** goes in the catalogue. A string that is **compared,
keyed on, or matched** stays inline, because moving it makes a translation able
to break the program.

`CloneClassifier` builds a `reason` that reads like prose, and
`UnifiedStrategy` tests it with `=== 'below thresholds'`. It looks like a
message. It is a protocol value, and it stays where it is. The same goes for
SARIF rule ids, log format names, and setting keys.

The drift guard is `tests/HelpTextTest.php::everyOptionHasTextInTheCatalogue`:
a new option with no catalogue entry fails the suite rather than printing an
empty description.
