<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Whether this machine can deliver a wall-clock number worth recording.
 *
 * The rule was already written down — "state the machine and its power state
 * beside any wall-clock number, or do not publish the number" — and nothing
 * enforced it, so it was obeyed by memory. It failed the obvious way: a
 * benchmark run on battery reported a 0.35x ratio and a FAIL, and the only
 * thing that had changed was the CPU clock. A gate that reports a failure the
 * hardware caused is worse than no gate, because it spends the reader's trust
 * on noise.
 *
 * Four things make a timing meaningless here, and each is readable before a
 * single measurement is taken:
 *
 *   - the machine is on battery. Under intel_pstate that is not itself the
 *     problem; it is what it implies about the next line.
 *   - energy_performance_preference is `power` or `balance_power`. This, not
 *     the governor name, is what moved this ThinkPad between 800 MHz and
 *     4.1 GHz — `powersave` with EPP `performance` measures fine, which is why
 *     reading the governor alone would pass a machine that cannot deliver.
 *   - the current clock is far below what the CPU advertises, which catches
 *     thermal capping that no policy file mentions.
 *   - something else is already running. A second process is a co-tenant on
 *     the same cores, and interleaving arms does not cancel a load that is not
 *     constant across the run.
 *
 * The verdict is advisory to the caller, never silent: a gate asks, and either
 * measures or declines out loud. Refusing to measure is a legitimate outcome.
 * Reporting a number the machine could not produce is not.
 */

/**
 * Read one sysfs value, or null when the file is absent or unreadable.
 */
function bcb_sysfs(string $path): ?string
{
    if (!is_readable($path)) {
        return null;
    }

    $raw = @file_get_contents($path);

    return $raw === false ? null : trim($raw);
}

/**
 * The current frequency of the fastest core, in kHz, or null when the tree is
 * not readable.
 *
 * One core's reading is a coin flip; the maximum across them is the machine's
 * answer to "can you run fast right now", which is the only thing the caller
 * wants to know.
 */
function bcb_fastest_core(): ?int
{
    $best = null;

    foreach (glob('/sys/devices/system/cpu/cpu[0-9]*/cpufreq/scaling_cur_freq') ?: [] as $path) {
        $value = bcb_sysfs($path);

        if ($value === null) {
            continue;
        }

        $khz = (int) $value;

        if ($best === null || $khz > $best) {
            $best = $khz;
        }
    }

    return $best === null ? null : $best;
}

/**
 * Everything about this machine that bears on a wall-clock number.
 *
 * @return array{governor: ?string, epp: ?string, on_battery: ?bool, mhz: ?float, max_mhz: ?float, load: ?float, cpus: int}
 */
function bcb_power_state(): array
{
    $governor = bcb_sysfs('/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor');
    $epp      = bcb_sysfs('/sys/devices/system/cpu/cpu0/cpufreq/energy_performance_preference');

    $battery = null;

    foreach (glob('/sys/class/power_supply/A*/online') ?: [] as $online) {
        $value = bcb_sysfs($online);

        if ($value !== null) {
            $battery = $value === '0';

            break;
        }
    }

    // The fastest core, not cpu0.
    //
    // `scaling_cur_freq` is an instantaneous reading of ONE core, and a core
    // parked while its siblings work reads at the policy minimum. Sampled five
    // times on an idle machine this file's own laptop gave 4151, 4197, 400,
    // 400, 3974 MHz — and 400 MHz twice while a busy loop was running. Gating
    // on cpu0 alone therefore fails at random, which it did: it threw away a
    // sound symfony-console measurement for a 400 MHz reading taken while the
    // work had already finished.
    //
    // What the question actually asks is whether this machine is *able* to run
    // fast right now, so the answer is the fastest core it has, not whichever
    // one happened to be sampled.
    $khz    = bcb_fastest_core();
    $maxKhz = bcb_sysfs('/sys/devices/system/cpu/cpu0/cpufreq/cpuinfo_max_freq');

    $load = null;

    if (is_readable('/proc/loadavg')) {
        $raw = (string) @file_get_contents('/proc/loadavg');

        if (preg_match('/^([0-9.]+)/', $raw, $m) === 1) {
            $load = (float) $m[1];
        }
    }

    $cpus = 1;

    if (is_readable('/proc/cpuinfo')) {
        $count = preg_match_all('/^processor\s*:/m', (string) @file_get_contents('/proc/cpuinfo'));
        $cpus  = $count > 0 ? $count : 1;
    }

    return [
        'governor'   => $governor,
        'epp'        => $epp,
        'on_battery' => $battery,
        'mhz'        => $khz === null ? null : (float) $khz / 1000.0,
        'max_mhz'    => $maxKhz === null ? null : (float) $maxKhz / 1000.0,
        'load'       => $load,
        'cpus'       => $cpus,
    ];
}

/**
 * Clock floor, as a fraction of what the CPU advertises, below which a timing
 * is not worth taking. Generous: the point is to catch 800 MHz against 4.1 GHz,
 * not to insist on a boost clock no sustained run holds anyway.
 */
const BCB_CLOCK_FLOOR = 0.55;

/**
 * Load ceiling, as a fraction of the core count. One busy core out of eight is
 * tolerated; a machine already at half its capacity is measuring someone else.
 */
const BCB_LOAD_CEILING = 0.5;

/**
 * Why a machine in this state cannot produce a publishable timing — empty when
 * it can.
 *
 * Pure, and separate from {@see bcb_power_state()} on purpose: a test for this
 * rule must not depend on the machine the test runs on. Asserting against live
 * sysfs would be the same mistake the rule exists to prevent, one level up.
 *
 * @param array{governor?: ?string, epp?: ?string, on_battery?: ?bool, mhz?: ?float, max_mhz?: ?float, load?: ?float, cpus?: int} $state
 * @return list<string>
 */
function bcb_timing_reasons(array $state): array
{
    $state += ['governor' => null, 'epp' => null, 'on_battery' => null, 'mhz' => null, 'max_mhz' => null, 'load' => null, 'cpus' => 1];

    $reasons = [];

    if ($state['epp'] !== null && in_array($state['epp'], ['power', 'balance_power'], true)) {
        $reasons[] = sprintf('energy_performance_preference is "%s"', $state['epp']);
    }

    if ($state['on_battery'] === true) {
        $reasons[] = 'running on battery';
    }

    if ($state['mhz'] !== null && $state['max_mhz'] !== null && $state['max_mhz'] > 0.0) {
        $share = $state['mhz'] / $state['max_mhz'];

        if ($share < BCB_CLOCK_FLOOR) {
            $reasons[] = sprintf(
                'clock is %.0f MHz against a %.0f MHz maximum (%.0f%%)',
                $state['mhz'],
                $state['max_mhz'],
                $share * 100,
            );
        }
    }

    if ($state['load'] !== null && $state['load'] > $state['cpus'] * BCB_LOAD_CEILING) {
        $reasons[] = sprintf('load average is %.2f across %d cpus — something else is running', $state['load'], $state['cpus']);
    }

    return $reasons;
}

/**
 * Can a wall-clock number taken on THIS machine right now be published?
 *
 * @return array{ok: bool, reasons: list<string>, state: array{governor: ?string, epp: ?string, on_battery: ?bool, mhz: ?float, max_mhz: ?float, load: ?float, cpus: int}}
 */
function bcb_timing_verdict(): array
{
    $state   = bcb_power_state();
    $reasons = bcb_timing_reasons($state);

    return ['ok' => $reasons === [], 'reasons' => $reasons, 'state' => $state];
}

/**
 * One line naming the machine's state, for printing beside any number taken on
 * it. This is the half of the rule that applies even when the verdict passes.
 *
 * The **model and the maximum clock** are named alongside the current one,
 * because a reader on a different machine cannot otherwise tell what these
 * seconds mean. `clock=4189MHz` on its own says nothing: it could be a laptop
 * at full tilt or a workstation crawling. `4189 of 4200MHz` on a named CPU says
 * both how fast the machine is and that it was delivering what it advertises,
 * which is the pair of facts a wall-clock number needs beside it. The maximum
 * was already read — {@see bcb_timing_reasons()} judges the ratio against it —
 * and simply never published.
 */
function bcb_power_line(): string
{
    $s = bcb_power_state();

    return sprintf(
        'cpu=%s governor=%s epp=%s power=%s clock=%s load=%s/%d cpus',
        bcb_cpu_model(),
        $s['governor'] ?? '?',
        $s['epp'] ?? '?',
        $s['on_battery'] === null ? '?' : ($s['on_battery'] ? 'battery' : 'AC'),
        $s['mhz'] === null
            ? '?'
            : sprintf(
                '%.0f/%sMHz',
                $s['mhz'],
                $s['max_mhz'] === null ? '?' : sprintf('%.0f', $s['max_mhz']),
            ),
        $s['load'] === null ? '?' : sprintf('%.2f', $s['load']),
        $s['cpus'],
    );
}

/**
 * The CPU this machine reports, normalised to one line.
 *
 * Read from `/proc/cpuinfo`, and `?` when it cannot be read — a number is still
 * publishable without it, it just carries less for anyone comparing machines.
 */
function bcb_cpu_model(): string
{
    $raw = @file_get_contents('/proc/cpuinfo');

    if (!is_string($raw)) {
        return '?';
    }

    foreach (explode("\n", $raw) as $line) {
        if (str_starts_with($line, 'model name')) {
            $at = strpos($line, ':');

            if ($at !== false) {
                // Vendor strings are padded and full of doubled spaces.
                return trim((string) preg_replace('/\s+/', ' ', substr($line, $at + 1)));
            }
        }
    }

    return '?';
}
