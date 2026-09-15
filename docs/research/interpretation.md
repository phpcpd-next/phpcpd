# interpretation.md — what to do when an instruction meets a fact it
# did not anticipate

Authored by the project owner; amended at the M4 close audit with the three
changes the auditor's evaluation recorded (rule 2d added; examples replaced
with this project's own precedents; rule 7 bound to the project's escalation
ladder). Referenced from the plan's Rules for the executing session; binding
on executor and auditor sessions alike.

## Half 1 — the procedure

0. CLARITY GATE. Interpretation is invoked only by a named mismatch
   between the instruction and an observed fact. No mismatch: apply
   the instruction as written. Interpreting a clear instruction is a
   violation, not diligence.
   (Precedent: the refused retrain — "counts over a *pinned* label set"
   met a drifted live tree, and the executor did not reinterpret "label
   set" to mean "current corpus". No mismatch with the words, so no
   interpretation was licensed; the model stayed unchanged and an
   instrument-corruption near-miss went on the record instead.)

1. THREE READINGS, IN ORDER.
   a. Words: the plain reading. Unambiguous and uncontradicted -> done.
   b. System: the reading consistent with standing rules and
      principles. A reading that conflicts with a principle loses.
   c. Purpose: the harm the instruction was written against. Read
      toward the purpose. The instruction's example is evidence of its
      purpose, not its boundary.
   (Precedent for 1c: the hidden-directory rule — the hand list of
   .phpstan/.psalm/.rector was evidence of the purpose "tool state is
   not program text", not the boundary of it; the list was struck for
   the principle.)

2. SECOND-ORDER RULES (when the readings disagree):
   - purpose beats words when the words assumed a fact that is false.
     (Precedent: the E3 slice — "the same 200-file slice" assumed mtime
     was reproducible; it was not. The purpose was reproducibility, so
     the slice was re-derived from last-commit time and the change
     surfaced, not hidden.)
   - words beat purpose when the action is irreversible or crosses a
     boundary (live tree, deletion, force, publication): do the literal
     thing or stop — never a purposive reading in the destructive
     direction.
     (Precedent: no exclusion was ever applied to the audit corpus by
     purposive reasoning — ruling K reserves that for a ruling made
     first, and the one session that found a contaminating backup tree
     rated it as it stood and surfaced it.)
   - system beats both when either reading would break a seeded
     invariant: the invariant is older evidence than the instruction.
     (Precedent: never weaken a gate to pass another — plan §3, applied
     when symmetry-everywhere would have broken probe 4's documented
     count at M2.)
   - comply under recorded dissent when the instruction comes from an
     authority and compliance is cheap and reversible but believed
     wrong: comply, record the dissent, name the revert line.
     (Precedent: the IndexCodec 5→6 bump — required by a ruling whose
     words assumed a false fact, complied with under dissent with the
     revert line named; the close audit upheld the dissent and reverted
     in one commit. The dissent record is what made the one-commit
     correction possible.)

3. PRECEDENT. Before deciding, search the record (the plan's rulings,
   the audit packets, declined options, reverted changes). Follow the
   closest precedent and NAME it; distinguish it explicitly if
   departing. A declined option is binding until re-opened with new
   evidence.
   (This project pins declined options as tests where it can: probe
   fixtures assert documented behaviour, r1 stays out of contract, the
   three refuted discriminators are recorded as dead.)

4. PRIORS FROM THE RECORD. Weight sources by their falsification
   history, and say so: a diagnosis that instrumentation contradicted
   yields to the instrumentation (the M2 "class formation collapses
   granularity" diagnosis was stale by M3 and the trace overrode it).
   A source's confidence is earned, not positional.

5. TIEBREAK BY COST ASYMMETRY. Two readings survive: take the one
   that is cheaper if wrong. State the asymmetry in one line.
   (Precedent: the Bayes margin biased toward keeping — a false discard
   costs a real clone; a false keep costs one noisy finding.)

6. CONFIDENCE TRAVELS WITH THE VERDICT. An interpretation built on
   one data point ships at ask, not deny; on a probed corpus, at the
   measured rate. Never promote confidence past its evidence.
   (Precedent: single-rater precision is never reported as κ; a
   projection on a stale pool is never reported as the bar.)

7. ESCALATE, DON'T GUESS, when: the mismatch touches an irreversible
   act; two principles genuinely conflict; the purpose itself is
   unclear; or the interpretation would set a precedent. The ladder is
   fixed: executor → auditor ruling request → owner for product
   decisions. A ruling request carries the named mismatch, the readings
   considered, and the precedents — never an open question.
   (Precedent: ruling request 7 — mismatch named against the
   component's own recorded contract, three readings each with its
   cost, precedents cited, the tree left at the floor reading.)

8. EVERY INTERPRETATION LEAVES A RECORD: the mismatch, the reading
   taken, the rule from this file that decided it, the precedent
   cited. One paragraph, in the commit body or the packet's
   Interpretations section. An interpretation nobody can find is a
   precedent nobody can follow.

## Half 2 — the same logic, translated to code

Each rule above has a design-pattern form, and each is already
exemplified in this tree. New code answers to the checklist:

1. **Fail loudly, never coerce** (rule 0). Input outside the contract
   throws with the mismatch named — no silent fallbacks, clamps, or
   helpful casts. (`minTokens < <!-- [[ $code.minimum_min_tokens ]] -->38<!--/-->` is refused with a clear error, not
   clamped.)
2. **Stamp your assumptions** (rule 2a). Behaviour resting on a
   changeable assumption carries a version; a stale assumption
   invalidates rather than being reinterpreted. (`IndexCodec::VERSION`.)
3. **Refuse by default in the destructive direction** (rule 2b). The
   destructive path takes an explicit flag, never a purposive default.
   (The worksheet writer refuses paths inside the repo;
   `--no-default-excludes` is opt-in.)
4. **Put invariants below the caller** (rule 2c). An invariant enforced
   beneath the API cannot be interpreted away above it. (Determinism,
   level-max types, the <!-- [[ $code.similarity_floor ]] -->0.85<!--/--> emission invariant the suite pins.)
5. **Turn declined options into tests** (rule 3). A rejected behaviour
   worth remembering gets a test that fails if it is silently
   re-adopted. (Probe fixtures; r1 out of contract; the paired negative
   fixtures every bounded mechanism ships with.)
6. **Prove instruments can fail** (rule 4). A gate or oracle is trusted
   only after failing on demand. (The harness self-test; the chaining
   oracle run against the old tie order; the tie-set count that caught
   a generator collapsing to a constant.)
7. **Name your asymmetries** (rule 5). An asymmetric constant states
   its one-line rationale beside its value. (The classifier margin.)
8. **Ship evidence with every number** (rule 6). Counted caps are never
   silent; findings carry named ranges, not booleans; reports carry
   intervals, not points.
9. **Every standing prohibition ships with a cheap mechanical check**
   (rules 7–8). A rule with no cheap test is a rule that gets checked
   when someone remembers to. The check must not embed the secret it
   hunts — the hygiene grep reads its pattern from outside the repo.
10. **A check that reads a moving reference is not a check** (rule 4's
    corollary, learned from a HEAD-race that briefly reverted another
    session's commit). Resolve the base once, to an immutable SHA, and
    verify against that SHA — never against a name that can move while
    the check runs.
