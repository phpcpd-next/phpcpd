# Documentation

Everything here is prose to read. Executable things live elsewhere: `src/` is
the tool, `tests/` proves it, `bench/` measures it, `bin/` builds things.

## For people using phpcpd-next

| | |
|---|---|
| [`../README.md`](../README.md) | What the tool does, every option, and how to run it |
| [`orphans.md`](orphans.md) | Dead-code detection: what it claims, what it cannot see |
| [`localization.md`](localization.md) | Translating what the tool says, and the rules the string catalogue keeps |
| [`../CHANGELOG.md`](../CHANGELOG.md) | One line per change, per release |

## For people asking why it does that

| | |
|---|---|
| [`paper/`](paper/) | The method and its evaluation, written for an outside reader — build with `bash bin/build-paper.sh` |
| [`MODERNIZATION.md`](MODERNIZATION.md) | What was inherited from `sebastianbergmann/phpcpd` and what replaced it |
| [`release-notes.md`](release-notes.md) | The reasoning behind every changelog entry, with the measurements it rests on |
| [`research/`](research/) | The working record: the unified engine's plan, the deferred work, and the dated audit reports M0–M6 |

The research directory is a lab notebook, not a narrative. Its entries were
written when the work happened and are not revised afterwards — that is what
makes them evidence rather than recollection. The paper is the claim; the
audit reports are what the claim rests on.

## For whoever ships it

[`internal/`](internal/) is the maintainer's own working material — a release
checklist, and a standing list of what is known to be untrue and shipping
anyway. Nothing in it is a promise to a user.
