# Provenance and the inherited surface

phpcpd-next began as a fork of **PHP Copy/Paste Detector (PHPCPD)**, ©
2011–2023 Sebastian Bergmann, BSD-3-Clause. Attribution alone satisfied that
licence, and nothing ever had to change for the package to be correct.

This document exists for a different reason. The project's own plan (M4
ruling S) set an endgame: **a tree with no inherited surface left,
relicensable MIT in one commit, with this inventory as its evidence.** That
was a goal about ownership and about how much of the tool its authors can
answer for, not a licensing repair.

**It is done.** The inventory is at zero, phpcpd-next is MIT, and what
follows is the record of how it got there rather than a plan for getting
there.

## The unit, and why it is files rather than lines

A file's header names its ancestry. It does not say how much of the file is
still the ancestor's — several of the files below have been rewritten in
place while keeping the attribution, which is the right thing to do under
BSD-3-Clause and the wrong thing to draw a percentage from.

Measuring surviving *lines* would mean diffing against upstream, and ruling
S's replacement standard is that a file is rewritten **without opening the
inherited implementation during the rewrite**. A running line-level diff
would defeat the one discipline the standard actually enforces. So the unit
here is *files that still carry the attribution*, which is also exactly the
unit that has to reach zero before the licence can change.

## The inventory

**Measured, not hand-maintained — and this section no longer reproduces the
measurement.** `php bench/check-provenance.php` reads the header block of
every `src/` file, counts the copyright holders it names, and exits non-zero
while any file still carries an inherited one. So the inventory being at zero
is a command's verdict, not a sentence here:

```
  ORIGINAL-WORK SHARE: 100.0 % of files, 100.0 % of lines

  INVENTORY AT ZERO — no file in src/ carries an inherited attribution,
  which is what LICENSE (MIT) asserts.
```

A table of counts used to sit here and it went stale twice. It claimed 19
attributed files against a tree holding 18, was corrected, and then claimed 9
against a tree holding none — while the paragraph three lines below it
already said Group B was empty. A number that has to be retyped is a number
that drifts, and the script answers the question the table was trying to
answer. Run it.

**How it reached zero, in two moves.** 2.0.0 removed the ConQAT-derived
suffix tree — nine attributed files, and the only Apache-2.0 code in the
package — so `NOTICE` lost its second licence section and `LICENSE` its
Apache-2.0 clause in the same commit. That was a removal rather than a
rewrite, and it left the ten files below. Those were then rewritten one at a
time, each proved byte-identical before its header moved, and the last of
them took the inventory to zero and unlocked the licence change.

**Group A is empty.** It held the suffix tree.

**Group B is empty.** It held these ten, rewritten one at a time, each
proved to produce byte-identical output before its header was changed:

```
  CodeClone.php                              the finding value object
  CodeCloneMap.php                           the finding collection
  CodeCloneMapIterator.php                   iteration over the collection
  CLI/Application.php                        the command line entry point
  Detector/Detector.php                      the scan loop
  Detector/Strategy/AbstractStrategy.php     the strategy base and its ignore list
  Detector/Strategy/DefaultStrategy.php      the Rabin-Karp strategy and the tokenizer
  Log/AbstractXmlLogger.php                  XML reporter scaffolding
  Log/PMD.php                                the PMD-CPD reporter
  Log/Text.php                               the text reporter
```

### What the inventory looked like before the last ten

```
  CodeClone.php                              264    the finding value object
  Detector/Strategy/DefaultStrategy.php      223    the Rabin–Karp strategy and the tokenizer
  Log/Text.php                               208    the text reporter
  CodeCloneMap.php                           188    the finding collection
  Log/PMD.php                                 75    the PMD-CPD reporter
  CodeCloneMapIterator.php                    66    iteration over the collection
  Detector/Detector.php                       63    the scan loop
  Detector/Strategy/AbstractStrategy.php      61    the strategy base and its ignore list
  Log/AbstractXmlLogger.php                   60    XML reporter scaffolding
```

These were what ruling S predicted would remain, and it predicted them
correctly: the tokenizer behind Stage A, the Rabin-Karp strategy, parts of
the finding model, and reporter scaffolding.

### The replacement standard, and its honest caveat

Each file is replaced under one standard: **written from the PHP manual and
this project's own specifications, without opening the inherited
implementation during the rewrite, and proven behaviour-identical by the
gates** — the equivalence protocol for Rabin-Karp, the superset and
determinism gates, and the full suite.

The caveat is recorded rather than glossed. BSD-3-Clause already permits
derived work with attribution, and sessions across this project's milestones
have read these files. So the enforceable standard is the no-open discipline
plus gate-proven equivalence, recorded per file as it is done — **not** a
legal clean-room claim, and this document does not make one.

Relicensing to MIT happened in one commit, when the inventory reached zero,
with the final inventory as its evidence and `LICENSE`, `NOTICE` and the
changelog changing together. `bench/check-provenance.php` exits non-zero
while the inventory is above zero, so the precondition was a command rather
than a reading of this document.

`LICENSE` is MIT, and no BSD text ships beside it, because no code under that
licence does. Keeping one looked like free caution and is not: a licence file
covering nothing asserts an encumbrance the package does not carry, and every
scanner and every downstream consumer would read it as real.

The closest thing to an exception is acknowledged rather than licensed — the
membership of the ignored-token set in
`Detector/Strategy/AbstractStrategy.php`, which nine of PHP's token types
carry no program. That is a fact about the language rather than a way of
writing one, and a fact is not somebody's to license.

### What a diff says, now that one is allowed

The no-open discipline forbids consulting upstream *during* a rewrite. Every
rewrite being finished, the diff it forbade became available, and it was
taken: of upstream's 393 meaningful source lines, 76 still appear verbatim
somewhere in `src/`. All of them are method signatures, one-line accessor
bodies, the attributed token list, constants the XML and PMD-CPD specs
dictate, and short call statements that follow from the API's own names. No
algorithm survives — not the window hashing, the chaining, the clone
construction, nor the report assembly.

One user-facing sentence did survive and was reworded, because a sentence
somebody chose the words for is the one category here that is neither
interface nor specification. After that, the shared prose is empty.

This measurement is recorded for the same reason as the caveat above: it is
evidence, not a legal conclusion, and it is stronger than the claim this
document is willing to make on its own.

## The technical diff against upstream

Behavioural changes are recorded one entry per change, each in its own commit,
in [CHANGELOG.md](../CHANGELOG.md) — and at length, with the measurements each
entry rests on, in [release-notes.md](release-notes.md).
The design work behind the unified engine, its constants and their
derivations is in `docs/research/` and in the paper.
