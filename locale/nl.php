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
 * Nederlandse vertaling.
 *
 * Sleutels, placeholders, tellers en wat hier in thuis hoort: zie
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'FOUT: :message',
        'warning' => 'WAARSCHUWING: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'In :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Configuratiebestand niet gevonden: :path',
        ],
        'unparsable' => [
            'config' => 'Configuratiebestand kon niet worden geparseerd: :path',
        ],
        'unknown' => [
            'option'  => 'Onbekende optie :flag.',
            'setting' => 'Onbekende instelling ":name" in :file',
        ],
        'needsValue' => [
            'option'  => 'Optie :flag heeft een waarde nodig.',
            'setting' => 'Instelling ":name" in :file heeft een waarde nodig.',
        ],
        'takesNoValue' => [
            'option' => 'Optie :flag accepteert geen waarde.',
        ],
        'invalidValue' => [
            'option' => 'Ongeldige waarde ":value" voor :flag (toegestaan: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'De unified-engine heeft een :flag nodig van ten minste :floor (opgegeven: :given). Daaronder daalt het winnow-venster onder de 4 en stopt de index een steekproef te zijn, dus weigert de engine in plaats van stilletjes te degraderen naar een uitputtende scan.',
        ],
        'unwired' => [
            'option' => 'Optie :flag heeft geen instellingen-binding.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Weigert de bestandssysteemroot te scannen (:path). Bedoelde u "./"?',
            'aboveProject'   => 'Weigert :path te scannen: het bevindt zich boven de projectroot :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Geen bestanden gevonden om te scannen.',
            'afterTriage' => 'Geen bestanden over om te scannen na triage.',
        ],
        'missingArgument' => [
            'directory' => 'Geen map opgegeven.',
        ],
        'writeFailed' => [
            'report' => 'Kon het rapport niet naar :path schrijven: :detail',
        ],
        'writePartial' => [
            'report' => 'Slechts van (:written) bytes (:total) van het rapport geschreven naar :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'preset ":preset" declareert scan-paden (:declared), ontbrekend (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans kan het hele project niet zien.',
            'belowRoots'     => '  Elke scanroot bevindt zich onder de autoload-roots van :manifest.',
            'uncoveredRoots' => '  autoload-paden gedeclareerd in :manifest maar nooit geopend (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset gedetecteerd — preset toegepast (--no-preset om uit te schakelen)',
        ],
        'cache' => [
            'hit' => '(cache hit)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag is verouderd; gebruik --algorithm=unified — die nog niet elke locatie rapporteert die de tokenbag rapporteert, dus dit blijft selecteerbaar)',
        ],
        'incremental' => [
            'combined'    => '(--incremental genegeerd in gecombineerde modus)',
            'unsupported' => '(--incremental genegeerd: alleen de rabin-karp- en unified-algoritmen hebben een incrementele index)',
            'index'       => '(incrementele index: :reused hergebruikt, :scanned gescand)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Geen codeklonen gevonden.',
            'heading'        => 'Gevonden codeklonen (:clones):gapped:reordered, gedupliceerde regels (:lines), bestanden (:files):',
            'gapped'         => ', inconsistent (:count)',
            'reorderedCount' => ', herordend (:count)',
            'reordered'      => '[herordend]',
            'unreadable'     => 'onleesbare bestanden (:count) — in geen van beide totaal:',
            'strataAsserted' => ':asserted geëist, 0 gedegradeerd.',
            'strataSplit'    => ':asserted geëist, :demoted gedegradeerd (:detail).',
            'settled'         => 'lezingen verworpen omdat ze al beschreven zijn (:count).',
            'unfounded'      => 'bevindingen verworpen als niet-geverifieerd (:count).',
            'hiddenLine'      => ':count van :total bevindingen verborgen onder vertrouwen :threshold — --hidden toont ze.',
            'hiddenHeading'   => 'Verborgen onder vertrouwen :threshold (:count):',
            'coverage'         => ':percentage van de gescande regels (:lines) is gedupliceerde code.',
            'literals'         => '[literals verschillen (:count)]',
            'confidence'       => 'vertrouwen :score (:terms)',
            'functions'        => 'in :names',
            'sizes'          => 'Regels per kloon: gemiddelde (:average), grootste (:largest).',
        ],
        'ledger' => [
            'line'      => 'Erkend: van (:acknowledged) bevindingen (:total) gedegradeerd door het grootboek; verouderd (:stale).',
            'staleNote' => 'verouderd (de code die het erkende is veranderd): :note',
            'wrote'     => 'acknowledgments geschreven (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (ontbreekt)',
            'source' => [
                'default'     => 'standaard',
                'commandLine' => 'opdrachtregel',
                'builtIn'     => '    ingebouwde standaarden',
            ],
            'fallback'    => ' terugvallen op de ingebouwde standaard',
            'layers'      => '  Lagen, laagste prioriteit eerst:',
        ],
        'run' => [
            'throughput' => ' — bestanden (:count) met :rate/s',
            'files'    => ' — bestanden (:count)',
            'usage'  => 'Tijd: :duration, Geheugen: :memory MB',
            'banner' => 'phpcpd :version door :author — na :origin door :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Scanroot: :root',
            'roots'              => 'Scanroots:',
            'count' => [
                'file'       => 'bestanden (:count)',
                'directory'  => 'roots (:count)',
                'pattern'    => 'uitsluitingen (:count)',
                'unreadable' => 'onleesbaar (:count)',
                'generated'  => 'gegenereerd (:count)',
            ],
            'counts'             => 'Gescand :counts',
        ],
        'triage' => [
            'nothing'  => 'Triëntatie: niets gelabeld; elk bestand is programmatekst.',
            'removed'  => 'Triëntatie: bestanden (:total), verwijderd (:removed)',
            'labelled' => 'Triëntatie: bestanden (:total), gelabeld (:labelled), niets verwijderd',
        ],
        'orphan' => [
            'none'         => 'Geen wees-symbolen gevonden (symbolen (:symbols) in bestanden (:files)).',
            'found'        => 'wees-symbolen (:count):',
            'possible'     => 'mogelijke wezen (:count) — controleren voor verwijdering:',
            'advisory'     => 'wees-symbolen (:count) — advies, heeft geen invloed op afsluitcode:',
            'notShown'     => 'verdere weesbevindingen niet getoond (:count) — voer --orphans uit om te controleren.',
            'suppressed'   => 'Onderdrukt (:count): :census',
            'explainHint'  => '  → --explain om ze op te sommen',
            'wholeFile'    => '    ⤷ hele bestand is niet-bekabeld — geen enkel hier verklaard symbool wordt gerefereerd',
            'supersededBy' => '    ⤷ lijkt een vervangen kopie van :name',
            'summary'      => 'symbolen (:symbols) gescand in bestanden (:files); wees (:orphaned), mogelijk (:possible), onderdrukt (:suppressed), gepland (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'buiten de bestandset die de eigen standaard uitsluitingen van dit project overlaten — een gegenereerde boomstructuur, die mogelijk geen bekabeling vertoont',
            'declaredHere'  => 'symbolen hier verklaard (:count), geen enkele wordt ergens in het project gerefereerd',
            'foreignNs'     => 'verklaart :namespaces — een namespace die geen composer.json erboven verklaart, buiten elke map die ze bekabelen',
        ],
        'role' => [
            'noStatements' => 'geen top-level statements na de preambule',
            'coupled'      => ':registrations van :statements top-level statements zijn registratie-expressies, maar ze delen een variabele',
            'independent'  => ':registrations van :statements top-level statements zijn datastroom-onafhankelijke registratie-expressies',
        ],
        'orphan' => [
            'guard'          => 'verklaard binnen een existentiebewaker — polyfill of compatibiliteitsshim',
            'entrypoint'     => 'verklaard in :namespace — aangeroepen door frameworkconventie',
            'partialProject' => '  Een symbool wordt Dood genoemd wanneer *niets* er naar verwijst, wat een bewering is over
  het hele project. Code buiten deze scan kan nog steeds verwijzen naar wat hier wordt gerapporteerd.',
            'evidence' => [
                'nameAt'   => 'naam verschijnt op',
                'loopAt'   => 'ontdekt door de lus op',
                'suffixAt' => 'achtervoegsel verklaard op',
                'namedIn'  => 'genoemd in',
            ],
            'plannedServed'  => 'nu gerefereerd — @phpcpd-planned heeft zijn doel gediend en kan worden verwijderd',
            'manifest'       => 'verklaard in een composer autoload.files entrypoint',
            'foreignNs'      => 'verklaard buiten de eigen namespaces van het project (compatibiliteitsshim)',
            'fixture'        => 'testfixture — geladen via pad of benoemd als string, nooit gerefereerd',
            'discovery'      => 'ontdekt door een mapscan — geïnstantieerd vanuit zijn bestandsnaam achter class_exists',
            'convention'     => 'metgezelklasse — :base gebruikt :trait, wat deze naam oplost bij runtime via achtervoegsel',
            'interface'      => 'nooit gerefereerd (interface — kan buiten de gescande set worden geïmplementeerd)',
            'trait'          => 'nooit gerefereerd (trait — kan worden gebruikt door klassen buiten de gescande set)',
            'abstract'       => 'nooit gerefereerd (abstract — kan worden uitgebreid buiten de gescande set)',
            'inString'       => 'nooit gerefereerd in code; naam verschijnt in een stringliteral (mogelijk dynamisch gebruik)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Voorwaardelijk verklaard (polyfill / compatibiliteitsshim)',
            'fixtures'    => 'Testfixtures (geladen via pad of naam)',
            'config'      => 'Geregistreerd in een configuratiebestand',
            'template'    => 'Gerefereerd vanuit een sjabloon (blade / twig / latte)',
            'manifest'    => 'Gerefereerd vanuit composer.json',
            'namespace'   => 'Verklaard buiten de eigen namespaces van het project (compatibiliteitsshim)',
            'keep'        => 'Gemarkeerd als behouden (@api / @phpcpd-keep)',
            'entrypoint'  => 'Framework entrypoints (attribuut / testklasse)',
            'discovery'   => 'Ontdekt door een mapscan (geïnstantieerd vanuit bestandsnaam)',
            'convention'  => 'Metgezelklasse vernoemd volgens conventie (achtervoegsel verklaard door trait)',
            'planned'     => 'Gepland, nog niet bekabeld',
            'none'        => 'Geen referentie gevonden',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Geef --allow-root-scan op als u echt het hele bestandssysteem bedoelde.',
            'allowOutside' => 'Geef --allow-root-scan op om hoe dan ook buiten het project te scannen.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Scan de projectroot voor een resultaat dat de moeite waard is om naar te handelen.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard handelt naar de labels; --no-triage slaat het stadium over)',
            'explain' => '  (--explain somt elk bestand en het bewijs voor of tegen op)',
        ],
        'clone' => [
            'gapped'  => 'Bijna-misser klon — overweeg het afwijkende deel te parameteriseren of beide kopieën op één lijn te brengen.',
            'demoted' => 'Gedegradeerd als :stratum — dit is de vorm die dat stratum beschrijft, dus extraheer het alleen als de herhaling niet het punt is.',
            'extract' => 'Overweeg de gedeelde regels te extraheren naar een herbruikbare methode, klasse of trait.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Gebruik:',
            'invocation' => '  phpcpd [opties] <map>',
        ],
        'group' => [
            'selecting' => 'Opties voor het selecteren van bestanden',
            'orphans'   => 'Wees-detectie (dode code)',
            'analysing' => 'Opties voor het analyseren van bestanden',
            'general'   => 'Algemene opties',
            'reporting' => 'Opties voor het genereren van rapporten',
            'ci'        => 'Opties voor CI-integratie',
        ],
        'option' => [
            'suffix'              => 'Sluit bestanden in met namen die eindigen op <suffix> (standaard: :default; herhaalbaar)',
            'exclude'             => 'Sluit bestanden uit met <path> in hun pad (herhaalbaar)',
            'preset'              => 'Pas een framework-preset toe (bijv. laravel): stelt logische paden, achtervoegsels en uitsluitingen in',
            'triage'              => 'Voer Stage 0 corpus triëntatie uit vóór detectie (standaard aan; vraagt hier expliciet om)',
            'no_triage'           => 'Sla Stage 0 volledig over: geen enkel bestand wordt gelabeld als niet-bekabeld, geschaduwd, geverandert of afgeleid',
            'triage_posture'      => 'Wat triëntatie doet met een bestand dat het labelt: weggooien uit de scan (standaard), of labelen en niets anders',
            'no_preset'           => 'Pas niet automatisch een framework-preset toe wanneer er een wordt gedetecteerd (detectie kondigt zichzelf aan; --preset= overschrijft dit)',
            'no_default_excludes' => 'Scan ook gegenereerde en cachebomen (vendor, node_modules, .phpstan.cache, build, ...), die standaard worden overgeslagen',
            'allow_root_scan'     => 'Sta een scanroot van / toe of een root boven de dichtstbijzijnde composer.json (standaard geweigerd: `phpcpd /` is bijna altijd een typo voor `phpcpd ./`)',
            'orphans'             => 'Detecteer wees-symbolen (niet-gerereerde klassen, interfaces, traits, enums, functies) in plaats van klonen',
            'no_suppress'         => 'Schakel onderdrukkingsregels uit op naam, door komma\'s gescheiden, of "all" (:rules)',
            'fail_on'             => 'Resultaatlagen waardoor de run niet-nul afdrukt, gescheiden door komma\'s (standaard: :default)',
            'explain'             => 'Som elk onderdrukt symbool op en de regel die het onderdrukte, in plaats van ze alleen te tellen',
            'rk'                  => 'Alleen Rabin-Karp (exacte/Type-1 klonen; sneller, geen herordeningdetectie). Standaard voert zowel Rabin-Karp als TokenBag uit.',
            'min_lines'           => 'Minimale aantal identieke regels (standaard: :default)',
            'min_tokens'          => 'Minimale aantal identieke tokens (standaard: :default)',
            'language'            => 'Taal voor het rapport (standaard: :default)',
            'verbose'             => 'Print de gedupliceerde code voor elke klon',
            'algorithm'           => 'Enkele algoritme-override (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag overlapdrempel (standaard: :default)',
            'raw'                   => 'Ruwe tekst vergelijken: ook identifiers moeten overeenkomen (schakelt de standaardnormalisatie uit)',
            'fuzzy'                 => 'Naamblinde normalisatie: als de standaard maar zonder het typeanker (onderzoek; E2 mat haar als gedomineerd)',
            'type_anchored'         => 'Typesleutelwoorden concreet houden onder normalisatie (standaard aan; --fuzzy schakelt het uit)',
            'min_confidence'        => 'Alleen bevindingen tonen die het model op <log-odds> of hoger scoort; de rest wordt geteld en is leesbaar met --hidden, wordt nooit weggegooid en bepaalt nog steeds --fail-on',
            'hidden'                => 'De bevindingen tonen die --min-confidence achterhield',
            'cache'                 => 'Resultaten cachen in \'.phpcpd-cache/\' — een treffer vereist elk bestand ongewijzigd, dus dient het een herhaling van één commit en niet de volgende',
            'acknowledged'        => 'Lees een ingediend acknowledgment-grootboek van <file>: vermelde duplicatie wordt gedegradeerd, nooit verborgen, en vermeldingen waarvan de code is gewijzigd verlopen en worden gerapporteerd',
            'write_acknowledged'  => 'Schrijf de bevindingen van deze run naar <file> als acknowledgment-grootboek, ter beoordeling en commit',
            'log_pmd'             => 'Schrijf logboek in PMD-CPD XML-indeling naar <file>',
            'log_json'            => 'Schrijf logboek in JSON-indeling naar <file>',
            'log_sarif'           => 'Schrijf logboek in SARIF 2.1.0-indeling naar <file> (voor GitHub Code Scanning)',
            'cache_dir'           => 'Lees/schrijf cache van <path> (impliceert --cache; overschrijft standaardmap)',
            'incremental'           => 'Incrementele index per bestand: hertokeniseert alleen gewijzigde bestanden (rabin-karp of unified, niet de gecombineerde standaard; gebruikt de cachemap)',
            'config'              => 'Lees instellingen van <file> (standaard: ./phpcpd.ini indien aanwezig); sleutels zijn de lange optienamen',
            'show_config'         => 'Print de geldende instellingen, waar elk vandaan kwam, en sluit af',
            'no_config'           => 'Negeer ./phpcpd.ini',
            'help'                => 'Print deze hulp',
            'version'             => 'Print versie-informatie',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next acknowledgment-grootboek',
            'what'  => 'Elke regel registreert één duplicatie die dit project heeft bekeken en heeft besloten mee te leven. Een erkende bevinding is GEDegradeerd, nooit verborgen: het wordt nog steeds gerapporteerd, nog steeds geteld en stuurt nog steeds de afsluitcode aan.',
            'key'   => 'De sleutel is de inhoud van elke kant van de duplicatie, gehasht — geen pad en geen regelnummer. Dus het bewerken van beide kopieën laat de invoer verlopen en de bevinding wordt opnieuw geëist, terwijl het verplaatsen van de code niets verandert. Een invoer die nergens meer mee overeenkomt, wordt gerapporteerd als verouderd zodat deze kan worden verwijderd.',
            'note'  => 'De tekst na de tab is een menselijke notitie. Er wordt nooit op gematcht.',
        ],
    ],
];
