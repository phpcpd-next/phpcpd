<?php

declare(strict_types=1);
/*
 * Diese Datei ist Teil von PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Vollständige Informationen zu Copyright und Lizenz finden Sie in der
 * Datei LICENSE, die mit diesem Quellcode verteilt wurde.
 */
/* Deutsche Übersetzung.
 *
 * Schlüssel, Platzhalter, Zählungen und was hier im Allgemeinen
 * hineingehört: siehe docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'FEHLER: :message',
        'warning' => 'WARNUNG: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'In :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfigurationsdatei nicht gefunden: :path',
        ],
        'unparsable' => [
            'config' => 'Konfigurationsdatei konnte nicht geparst werden: :path',
        ],
        'unknown' => [
            'option'  => 'Unbekannte Option :flag.',
            'setting' => 'Unbekannte Einstellung ":name" in :file',
        ],
        'needsValue' => [
            'option'  => 'Option :flag benötigt einen Wert.',
            'setting' => 'Einstellung ":name" in :file benötigt einen Wert.',
        ],
        'takesNoValue' => [
            'option' => 'Option :flag nimmt keinen Wert an.',
        ],
        'invalidValue' => [
            'option' => 'Ungültiger Wert ":value" für :flag (erlaubt: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Die einheitliche Engine benötigt :flag von mindestens :floor (gegeben: :given). Darunter fällt das Filterfenster unter 4 und der Index hört auf, eine Stichprobe zu sein, daher verweigert die Engine den Dienst, anstatt stillschweigend auf einen erschöpfenden Scan zurückzufallen.',
        ],
        'unwired' => [
            'option' => 'Option :flag hat keine Einstellungsbindung.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Das Scannen der Dateisystemwurzel (:path) wird verweigert. Meinten Sie "./"?',
            'aboveProject'   => 'Das Scannen von :path wird verweigert: Es liegt oberhalb der Projektwurzel :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Keine Dateien zum Scannen gefunden.',
            'afterTriage' => 'Nach dem Triage keine Dateien zum Scannen übrig.',
        ],
        'missingArgument' => [
            'directory' => 'Kein Verzeichnis angegeben.',
        ],
        'writeFailed' => [
            'report' => 'Bericht konnte nicht nach :path geschrieben werden: :detail',
        ],
        'writePartial' => [
            'report' => 'Es wurden nur von (:written) Bytes (:total) des Berichts nach :path geschrieben',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'Das Preset ":preset" deklariert Scan-Pfade (:declared), fehlen (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans kann nicht das gesamte Projekt sehen.',
            'belowRoots'     => '  Jede Scan-Wurzel liegt unterhalb der Autoload-Wurzeln von :manifest.',
            'uncoveredRoots' => '  Autoload-Pfade in :manifest deklariert, aber nie geöffnet (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset erkannt — Preset angewendet (--no-preset zum Deaktivieren)',
        ],
        'cache' => [
            'hit' => '(Cache-Treffer)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag ist veraltet; verwenden Sie --algorithm=unified — das noch nicht jeden Ort meldet, den das Token-Bag meldet, daher bleibt dies auswählbar)',
        ],
        'incremental' => [
            'combined'    => '(--incremental im kombinierten Modus ignoriert)',
            'unsupported' => '(--incremental ignoriert: nur die Algorithmen rabin-karp und unified haben einen inkrementellen Index)',
            'index'       => '(inkrementeller Index: :reused wiederverwendet, :scanned gescannt)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Keine Code-Klone gefunden.',
            'heading'        => 'Gefunden: Code-Klone (:clones):gapped:reordered, duplizierte Zeilen (:lines), Dateien (:files):',
            'gapped'         => ', inkonsistent (:count)',
            'reorderedCount' => ', umgeordnet (:count)',
            'reordered'      => '[umgeordnet]',
            'unreadable'     => 'unlesbare Dateien (:count) — in keinem der Totale:',
            'strataAsserted' => ':asserted behauptet, 0 herabgestuft.',
            'strataSplit'    => ':asserted behauptet, :demoted herabgestuft (:detail).',
            'settled'         => 'Lesarten verworfen, weil bereits beschrieben (:count).',
            'unfounded'      => 'Funde verworfen, weil unbestätigt (:count).',
            'hiddenLine'      => ':count von :total Funden unterhalb der Konfidenz :threshold ausgeblendet — --hidden listet sie.',
            'hiddenHeading'   => 'Ausgeblendet unterhalb der Konfidenz :threshold (:count):',
            'coverage'         => ':percentage der gescannten Zeilen (:lines) sind duplizierter Code.',
            'literals'         => '[Literale unterscheiden sich (:count)]',
            'confidence'       => 'Konfidenz :score (:terms)',
            'functions'        => 'in :names',
            'sizes'          => 'Zeilen pro Klon: Durchschnitt (:average), größter (:largest).',
        ],
        'ledger' => [
            'line'      => 'Anerkannt: von (:acknowledged) Funden (:total) durch das Ledger herabgestuft; veraltet (:stale).',
            'staleNote' => 'veraltet (der anerkannte Code hat sich geändert): :note',
            'wrote'     => 'Anerkennungen geschrieben (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (fehlt)',
            'source' => [
                'default'     => 'Standard',
                'commandLine' => 'Befehlszeile',
                'builtIn'     => '    eingebaute Standards',
            ],
            'fallback'    => ' Rückfall auf den eingebauten Standard',
            'layers'      => '  Schichten, niedrigste Priorität zuerst:',
        ],
        'run' => [
            'throughput' => ' — Dateien (:count) mit :rate/s',
            'files'    => ' — Dateien (:count)',
            'usage'  => 'Zeit: :duration, Speicher: :memory MB',
            'banner' => 'phpcpd :version von :author — basierend auf :origin von :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Scan-Wurzel: :root',
            'roots'              => 'Scan-Wurzeln:',
            'count' => [
                'file'       => 'Dateien (:count)',
                'directory'  => 'Wurzeln (:count)',
                'pattern'    => 'Ausschlüsse (:count)',
                'unreadable' => 'unlesbar (:count)',
                'generated'  => 'generiert (:count)',
            ],
            'counts'             => 'Gescannt :counts',
        ],
        'triage' => [
            'nothing'  => 'Triage: nichts beschriftet; jede Datei ist Programmtext.',
            'removed'  => 'Triage: Dateien (:total), entfernt (:removed)',
            'labelled' => 'Triage: Dateien (:total), beschriftet (:labelled), keine entfernt',
        ],
        'orphan' => [
            'none'         => 'Keine verwaisten Symbole gefunden (Symbole (:symbols) in Dateien (:files)).',
            'found'        => 'verwaiste Symbole (:count):',
            'possible'     => 'mögliche Verwaiste (:count) — vor dem Entfernen prüfen:',
            'advisory'     => 'verwaiste Symbole (:count) — beratend, beeinflusst den Exit-Code nicht:',
            'notShown'     => 'weitere Verwaisten-Funde werden nicht angezeigt (:count) — führen Sie --orphans zum Überprüfen aus.',
            'suppressed'   => 'Unterdrückt (:count): :census',
            'explainHint'  => '  → --explain zum Auflisten',
            'wholeFile'    => '    ⤷ die gesamte Datei ist unverbunden — kein hier deklariertes Symbol wird referenziert',
            'supersededBy' => '    ⤷ sieht aus wie eine ersetzte Kopie von :name',
            'summary'      => 'Symbole (:symbols) in Dateien (:files) gescannt; verwaist (:orphaned), möglich (:possible), unterdrückt (:suppressed), geplant (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'außerhalb des Dateisatzes, den die eigenen Standardschlüsse dieses Projekts übrig lassen — ein generierter Baum, der möglicherweise keine Verkabelung bezeugt',
            'declaredHere'  => 'hier deklarierte Symbole (:count), keines davon im Projekt referenziert',
            'foreignNs'     => 'deklariert :namespaces — einen Namespace, den kein übergeordnetes composer.json deklariert, außerhalb jedes von ihnen verkabelten Verzeichnisses',
        ],
        'role' => [
            'noStatements' => 'keine Top-Level-Anweisungen nach dem Vorspann',
            'coupled'      => ':registrations von :statements Top-Level-Anweisungen sind Registrierungsausdrücke, teilen sich jedoch eine Variable',
            'independent'  => ':registrations von :statements Top-Level-Anweisungen sind datenstromunabhängige Registrierungsausdrücke',
        ],
        'orphan' => [
            'guard'          => 'innerhalb einer Existenzwache deklariert — Polyfill oder Kompatibilitäts-Shim',
            'entrypoint'     => 'in :namespace deklariert — durch Framework-Konvention aufgerufen',
            'partialProject' => '  Ein Symbol wird als tot bezeichnet, wenn *nichts* es referenziert, was eine Aussage über das
  gesamte Projekt ist. Code außerhalb dieses Scans kann das hier Gemeldete immer noch referenzieren.',
            'evidence' => [
                'nameAt'   => 'Name erscheint bei',
                'loopAt'   => 'entdeckt durch Schleife bei',
                'suffixAt' => 'Suffix deklariert bei',
                'namedIn'  => 'benannt in',
            ],
            'plannedServed'  => 'jetzt referenziert — @phpcpd-planned hat seinen Zweck erfüllt und kann entfernt werden',
            'manifest'       => 'in einem composer autoload.files Einstiegspunkt deklariert',
            'foreignNs'      => 'außerhalb der projekteigenen Namespaces deklariert (Kompatibilitäts-Shim)',
            'fixture'        => 'Test-Fixture — über Pfad geladen oder als String benannt, nie referenziert',
            'discovery'      => 'durch Verzeichnis-Scan entdeckt — hinter class_exists aus seinem Dateinamen instanziiert',
            'convention'     => 'Begleitklasse — :base verwendet :trait, das diesen Namen zur Laufzeit über ein Suffix auflöst',
            'interface'      => 'nie referenziert (Interface — kann außerhalb des gescannten Satzes implementiert werden)',
            'trait'          => 'nie referenziert (Trait — kann von Klassen außerhalb des gescannten Satzes verwendet werden)',
            'abstract'       => 'nie referenziert (abstrakt — kann außerhalb des gescannten Satzes erweitert werden)',
            'inString'       => 'im Code nie referenziert; Name erscheint in einem String-Literal (mögliche dynamische Verwendung)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Bedingt deklariert (Polyfill / Kompatibilitäts-Shim)',
            'fixtures'    => 'Test-Fixtures (nach Pfad oder Name geladen)',
            'config'      => 'In einer Konfigurationsdatei registriert',
            'template'    => 'Aus einem Template referenziert (blade / twig / latte)',
            'manifest'    => 'Aus composer.json referenziert',
            'namespace'   => 'Außerhalb der projekteigenen Namespaces deklariert (Kompatibilitäts-Shim)',
            'keep'        => 'Als behalten markiert (@api / @phpcpd-keep)',
            'entrypoint'  => 'Framework-Einstiegspunkte (Attribut / Testklasse)',
            'discovery'   => 'Durch Verzeichnis-Scan entdeckt (aus Dateinamen instanziiert)',
            'convention'  => 'Begleitklasse nach Konvention benannt (durch ein Trait deklariertes Suffix)',
            'planned'     => 'Geplant, noch nicht verkabelt',
            'none'        => 'Keine Referenz gefunden',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Geben Sie --allow-root-scan an, wenn Sie wirklich das gesamte Dateisystem meinten.',
            'allowOutside' => 'Geben Sie --allow-root-scan an, um ohnehin außerhalb des Projekts zu scannen.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Projektwurzel scannen, um ein Ergebnis zu erhalten, auf das es sich zu handeln lohnt.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard wirkt auf die Labels; --no-triage überspringt die Stufe)',
            'explain' => '  (--explain listet jede Datei und die Belege dafür oder dagegen auf)',
        ],
        'clone' => [
            'gapped'  => 'Beinahe-Treffer-Klon — erwägen Sie, den abweichenden Teil zu parametrisieren oder beide Kopien anzugleichen.',
            'demoted' => 'Als :stratum herabgestuft — dies ist die Form, die dieses Stratum beschreibt, extrahieren Sie es also nur, wenn die Wiederholung nicht der Punkt ist.',
            'extract' => 'Erwägen Sie, die gemeinsamen Zeilen in eine wiederverwendbare Methode, Klasse oder ein Trait zu extrahieren.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Verwendung:',
            'invocation' => '  phpcpd [Optionen] <Verzeichnis>',
        ],
        'group' => [
            'selecting' => 'Optionen zur Dateiauswahl',
            'orphans'   => 'Erkennung von Verwaisten (toter Code)',
            'analysing' => 'Optionen zur Code-Analyse',
            'general'   => 'Allgemeine Optionen',
            'reporting' => 'Optionen zur Berichterstellung',
            'ci'        => 'Optionen für die CI-Integration',
        ],
        'option' => [
            'suffix'              => 'Dateien einschließen, deren Namen auf <suffix> enden (Standard: :default; wiederholbar)',
            'exclude'             => 'Dateien mit <path> in ihrem Pfad ausschließen (wiederholbar)',
            'preset'              => 'Ein Framework-Preset anwenden (z. B. laravel): setzt sinnvolle Pfade, Suffixe und Ausschlüsse',
            'triage'              => 'Stufe-0-Corpus-Triage vor der Erkennung ausführen (standardmäßig aktiv; fordert dies explizit an)',
            'no_triage'           => 'Stufe 0 komplett überspringen: Keine Datei wird als unverbunden, verschattet (shadowed), vendored oder abgeleitet markiert',
            'triage_posture'      => 'Was das Triage mit einer markierten Datei macht: aus dem Scan verwerfen (Standard) oder markieren und sonst nichts',
            'no_preset'           => 'Ein Framework-Preset nicht automatisch anwenden, wenn eines erkannt wird (Erkennung kündigt sich an; --preset= überschreibt es)',
            'no_default_excludes' => 'Auch generierte und Cache-Bäume scannen (vendor, node_modules, .phpstan.cache, build, ...), die standardmäßig übersprungen werden',
            'allow_root_scan'     => 'Eine Scan-Wurzel von / oder eine Wurzel oberhalb des nächsten composer.json erlauben (standardmäßig verweigert: `phpcpd /` ist fast immer ein Tippfehler für `phpcpd ./`)',
            'orphans'             => 'Verwaiste Symbole (nicht referenzierte Klassen, Interfaces, Traits, Enums, Funktionen) statt Klone erkennen',
            'no_suppress'         => 'Unterdrückungsregeln nach Name deaktivieren, kommasepariert, oder "all" (:rules)',
            'fail_on'             => 'Ergebnisstufen, die dazu führen, dass der Lauf mit einem Code ungleich Null beendet wird, kommasepariert (Standard: :default)',
            'explain'             => 'Jedes unterdrückte Symbol und die Regel, die es unterdrückt hat, auflisten, anstatt sie nur zu zählen',
            'rk'                  => 'Nur Rabin-Karp (exakte/Typ-1-Klone; schneller, keine Reorder-Erkennung). Führt standardmäßig sowohl Rabin-Karp als auch TokenBag aus.',
            'min_lines'           => 'Mindestanzahl identischer Zeilen (Standard: :default)',
            'min_tokens'          => 'Mindestanzahl identischer Tokens (Standard: :default)',
            'language'            => 'Sprache für den Bericht (Standard: :default)',
            'verbose'             => 'Den duplizierten Code für jeden Klon ausgeben',
            'algorithm'           => 'Überschreibung für einzelnen Algorithmus (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag-Überlappungsschwelle (Standard: :default)',
            'raw'                   => 'Rohtext vergleichen: auch Bezeichner müssen übereinstimmen (schaltet die Standardnormalisierung ab)',
            'fuzzy'                 => 'Namensblinde Normalisierung: wie der Standard, aber ohne Typanker (Forschung; E2 maß sie als dominiert)',
            'type_anchored'         => 'Typ-Schlüsselwörter unter Normalisierung konkret halten (standardmäßig an; --fuzzy schaltet es ab)',
            'min_confidence'        => 'Nur Funde auflisten, die das Modell mit <log-odds> oder höher bewertet; der Rest wird gezählt, ist mit --hidden lesbar, wird nie verworfen und steuert weiterhin --fail-on',
            'hidden'                => 'Die von --min-confidence zurückgehaltenen Funde auflisten',
            'cache'                 => 'Ergebnisse in \'.phpcpd-cache/\' zwischenspeichern — ein Treffer verlangt jede Datei unverändert, dient also dem erneuten Lauf eines Commits statt dem nächsten',
            'acknowledged'        => 'Ein committetes Anerkennungs-Ledger aus <file> lesen: aufgelistete Duplikate werden herabgestuft, nie verborgen, und Einträge, deren Code sich geändert hat, laufen ab und werden gemeldet',
            'write_acknowledged'  => 'Die Funde dieses Laufs als Anerkennungs-Ledger zur Überprüfung und zum Commit in <file> schreiben',
            'log_pmd'             => 'Protokoll im PMD-CPD-XML-Format nach <file> schreiben',
            'log_json'            => 'Protokoll im JSON-Format nach <file> schreiben',
            'log_sarif'           => 'Protokoll im SARIF-2.1.0-Format nach <file> schreiben (für GitHub Code Scanning)',
            'cache_dir'           => 'Cache von <path> lesen/schreiben (impliziert --cache; überschreibt Standardverzeichnis)',
            'incremental'           => 'Inkrementeller Index pro Datei: nur geänderte Dateien neu tokenisieren (rabin-karp oder unified, nicht die kombinierte Voreinstellung; nutzt das Cache-Verzeichnis)',
            'config'              => 'Einstellungen aus <file> lesen (Standard: ./phpcpd.ini falls vorhanden); Schlüssel sind die Namen der langen Optionen',
            'show_config'         => 'Die gültigen Einstellungen ausgeben, woher sie stammten, und beenden',
            'no_config'           => 'Ignoriert ./phpcpd.ini',
            'help'                => 'Diese Hilfe ausgeben',
            'version'             => 'Versionsinformationen ausgeben',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'Anerkennungs-Ledger (acknowledgment ledger) von phpcpd-next',
            'what'  => 'Jede Zeile verzeichnet eine Duplizierung, die dieses Projekt überprüft hat und beschlossen hat,
zu behalten. Ein anerkannter Fund ist HERABGESTUFT, nie verborgen: er wird
weiterhin gemeldet, weiterhin gezählt und schränkt weiterhin den Exit-Code ein.',
            'key'   => 'Der Schlüssel ist der Inhalt jeder Seite der Duplizierung, gehasht — kein Pfad
und keine Zeilennummer. Das Bearbeiten einer der Kopien lässt den Eintrag ablaufen, und der
Fund wird erneut geltend gemacht, während das Verschieben des Codes nichts ändert. Ein Eintrag,
der mit nichts mehr übereinstimmt, wird als veraltet gemeldet, damit er gelöscht werden kann.',
            'note'  => 'Der Text nach dem Tabulator ist ein menschlicher Hinweis. Er wird niemals für den Abgleich verwendet.',
        ],
    ],
];
