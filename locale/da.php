<?php

declare(strict_types=1);
/*
 * Denne fil er en del af PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For fuldstændig information om ophavsret og licens, se
 * venligst LICENSE-filen, der fulgte med denne kildekode.
 */
/*
 * Dansk oversættelse.
 *
 * Nøgler, pladsholdere, tællinger og hvad der generelt hører til her: se
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'FEJL: :message',
        'warning' => 'ADVARSEL: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'I :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfigurationsfil ikke fundet: :path',
        ],
        'unparsable' => [
            'config' => 'Konfigurationsfil kunne ikke parses: :path',
        ],
        'unknown' => [
            'option'  => 'Ukendt option :flag.',
            'setting' => 'Ukendt indstilling ":name" i :file',
        ],
        'needsValue' => [
            'option'  => 'Optionen :flag kræver en værdi.',
            'setting' => 'Indstillingen ":name" i :file kræver en værdi.',
        ],
        'takesNoValue' => [
            'option' => 'Optionen :flag tager ikke imod en værdi.',
        ],
        'invalidValue' => [
            'option' => 'Ugyldig værdi ":value" for :flag (tilladt: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Den samlede motor kræver et :flag på mindst :floor (givet: :given). Under dette falder filtreringsvinduet under 4, og indekset ophører med at være et udsnit, så motoren nægter tjenesten i stedet for stille og roligt at falde tilbage til en udtømmende scanning.',
        ],
        'unwired' => [
            'option' => 'Optionen :flag har ingen indstillingsbinding.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Nægter at scanne filsystemets rod (:path). Mente du "./"?',
            'aboveProject'   => 'Nægter at scanne :path: Den er over projektets rod :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Ingen filer fundet til scanning.',
            'afterTriage' => 'Ingen filer tilbage til scanning efter triage.',
        ],
        'missingArgument' => [
            'directory' => 'Ingen mappe angivet.',
        ],
        'writeFailed' => [
            'report' => 'Kunne ikke skrive rapport til :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Kun af (:written) bytes (:total) af rapporten blev skrevet til :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'presetten ":preset" erklærer scanningsstier (:declared), mangler (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans kan ikke se hele projektet.',
            'belowRoots'     => '  Hver scanningsrod ligger under autoload-rødderne for :manifest.',
            'uncoveredRoots' => '  Autoload-stier erklæret i :manifest, men aldrig åbnet (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detekteret — preset anvendt (--no-preset for at deaktivere)',
        ],
        'cache' => [
            'hit' => '(cache-hit)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag er udrangeret; brug --algorithm=unified — som endnu ikke rapporterer hvert sted, som token bag rapporterer, så dette forbliver vælgbar)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignoreret i kombineret tilstand)',
            'unsupported' => '(--incremental ignoreret: kun algoritmerne rabin-karp og unified har et inkrementelt indeks)',
            'index'       => '(inkrementelt indeks: :reused genbrugt, :scanned skannet)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Ingen kodekloner fundet.',
            'heading'        => 'Fandt kodekloner (:clones):gapped:reordered, duplikerede linjer (:lines), filer (:files):',
            'gapped'         => ', inkonsistente (:count)',
            'reorderedCount' => ', omarrangerede (:count)',
            'reordered'      => '[omarrangeret]',
            'unreadable'     => 'ulæselige filer (:count) — i ingen af totalerne:',
            'strataAsserted' => ':asserted hævdet, 0 degraderet.',
            'strataSplit'    => ':asserted hævdet, :demoted degraderet (:detail).',
            'settled'         => 'læsninger kasseret som allerede beskrevet (:count).',
            'unfounded'      => 'fund kasseret som ubekræftede (:count).',
            'hiddenLine'      => ':count af :total fund skjult under konfidensen :threshold — --hidden viser dem.',
            'hiddenHeading'   => 'Skjult under konfidensen :threshold (:count):',
            'coverage'         => ':percentage af de scannede linjer (:lines) er dupliceret kode.',
            'literals'         => '[literaler afviger (:count)]',
            'confidence'       => 'tillid :score (:terms)',
            'functions'        => 'i :names',
            'sizes'          => 'Linjer pr. klon: gennemsnit (:average), størst (:largest).',
        ],
        'ledger' => [
            'line'      => 'Anerkendt: af (:acknowledged) fund (:total) degraderet af ledgeren; forældede (:stale).',
            'staleNote' => 'forældet (den anerkendte kode er ændret): :note',
            'wrote'     => 'anerkendelser skrevet (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (mangler)',
            'source' => [
                'default'     => 'standard',
                'commandLine' => 'kommandolinje',
                'builtIn'     => '    indbyggede standarder',
            ],
            'fallback'    => ' falder tilbage til indbygget standard',
            'layers'      => '  Lag, laveste prioritet først:',
        ],
        'run' => [
            'throughput' => ' — filer (:count) ved :rate/s',
            'files'    => ' — filer (:count)',
            'usage'  => 'Tid: :duration, Hukommelse: :memory MB',
            'banner' => 'phpcpd :version af :author — baseret på :origin af :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Scanningsrod: :root',
            'roots'              => 'Scanningsrødder:',
            'count' => [
                'file'       => 'filer (:count)',
                'directory'  => 'rødder (:count)',
                'pattern'    => 'udeladelser (:count)',
                'unreadable' => 'ulæselige (:count)',
                'generated'  => 'genererede (:count)',
            ],
            'counts'             => 'Skannet :counts',
        ],
        'triage' => [
            'nothing'  => 'Triage: intet mærket; hver fil er programtekst.',
            'removed'  => 'Triage: filer (:total), fjernet (:removed)',
            'labelled' => 'Triage: filer (:total), mærket (:labelled), ingen fjernet',
        ],
        'orphan' => [
            'none'         => 'Ingen forældreløse symboler fundet (symboler (:symbols) i filer (:files)).',
            'found'        => 'forældreløse symboler (:count):',
            'possible'     => 'mulige forældreløse (:count) — gennemgå før fjernelse:',
            'advisory'     => 'forældreløse symboler (:count) — rådgivende, påvirker ikke exit-koden:',
            'notShown'     => 'flere forældreløse fund vises ikke (:count) — kør --orphans for at gennemgå.',
            'suppressed'   => 'Undertrykt (:count): :census',
            'explainHint'  => '  → --explain for at liste',
            'wholeFile'    => '    ⤷ hele filen er afbrudt — intet symbol erklæret her refereres',
            'supersededBy' => '    ⤷ ligner en erstattet kopi af :name',
            'summary'      => 'symboler (:symbols) skannet i filer (:files); forældreløse (:orphaned), mulige (:possible), undertrykte (:suppressed), planlagte (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'uden for det filset, som dette projekts egne standardudeladelser efterlader — et genereret træ, som muligvis ikke vidner om kabelføring (wiring)',
            'declaredHere'  => 'symboler erklæret her (:count), ingen af dem refereret i projektet',
            'foreignNs'     => 'erklærer :namespaces — et namespace som ingen overliggende composer.json erklærer, uden for enhver mappe de forbinder',
        ],
        'role' => [
            'noStatements' => 'ingen top-level sætninger efter forteksten',
            'coupled'      => ':registrations af :statements top-level sætninger er registreringsudtryk, men deler en variabel',
            'independent'  => ':registrations af :statements top-level sætninger er datastrømuafhængige registreringsudtryk',
        ],
        'orphan' => [
            'guard'          => 'erklæret inden for en eksistensvagt — polyfill eller kompatibilitets-shim',
            'entrypoint'     => 'erklæret i :namespace — kaldet af framework-konvention',
            'partialProject' => '  Et symbol kaldes dødt, når *intet* refererer til det, hvilket er en påstand om hele
  projektet. Kode uden for denne scanning kan stadig referere til det, der rapporteres her.',
            'evidence' => [
                'nameAt'   => 'navn optræder ved',
                'loopAt'   => 'opdaget af løkke ved',
                'suffixAt' => 'suffiks erklæret ved',
                'namedIn'  => 'navngivet i',
            ],
            'plannedServed'  => 'refereres nu — @phpcpd-planned har opfyldt sit formål og kan fjernes',
            'manifest'       => 'erklæret i et composer autoload.files startpunkt',
            'foreignNs'      => 'erklæret uden for projektets egne namespaces (kompatibilitets-shim)',
            'fixture'        => 'test-fixture — indlæst via sti eller navngivet som streng, aldrig refereret',
            'discovery'      => 'opdaget af mappescanning — instansieret fra sit filnavn bag class_exists',
            'convention'     => 'ledsagerklasse — :base bruger :trait, som løser dette navn via et suffiks ved køretid',
            'interface'      => 'aldrig refereret (interface — kan implementeres uden for det skannede sæt)',
            'trait'          => 'aldrig refereret (trait — kan bruges af klasser uden for det skannede sæt)',
            'abstract'       => 'aldrig refereret (abstrakt — kan udvides uden for det skannede sæt)',
            'inString'       => 'aldrig refereret i koden; navn optræder i strengliteral (mulig dynamisk brug)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Betinget erklæret (polyfill / kompatibilitets-shim)',
            'fixtures'    => 'Test-fixtures (indlæst via sti eller navn)',
            'config'      => 'Registreret i en konfigurationsfil',
            'template'    => 'Refereret fra en skabelon (blade / twig / latte)',
            'manifest'    => 'Refereret fra composer.json',
            'namespace'   => 'Erklæret uden for projektets egne namespaces (kompatibilitets-shim)',
            'keep'        => 'Markeret som beholdt (@api / @phpcpd-keep)',
            'entrypoint'  => 'Framework-startpunkter (attribut / testklasse)',
            'discovery'   => 'Opdaget ved mappescanning (instansieret fra filnavn)',
            'convention'  => 'Ledsagerklasse navngivet efter konvention (suffiks erklæret af et trait)',
            'planned'     => 'Planlagt, endnu ikke forbundet',
            'none'        => 'Ingen reference fundet',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Angiv --allow-root-scan, hvis du virkelig mente hele filsystemet.',
            'allowOutside' => 'Angiv --allow-root-scan for at scanne uden for projektet alligevel.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Scann projektets rod for at få et resultat, der er værd at handle på.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard virker på mærkerne; --no-triage springer stadiet over)',
            'explain' => '  (--explain lister hver fil og beviserne for eller imod)',
        ],
        'clone' => [
            'gapped'  => 'Næsten-klon — overvej at parameterisere den afvigende del eller justere begge kopier.',
            'demoted' => 'Degraderet som :stratum — dette er den form, denne strata beskærer, så udtræk den kun, hvis gentagelsen ikke er pointen.',
            'extract' => 'Overvej at udtrække de delte linjer til en genbrugelig metode, klasse eller trait.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Anvendelse:',
            'invocation' => '  phpcpd [optioner] <mappe>',
        ],
        'group' => [
            'selecting' => 'Filvalgsoptioner',
            'orphans'   => 'Opdagelse af forældreløse (død kode)',
            'analysing' => 'Kodeanalyseoptioner',
            'general'   => 'Generelle optioner',
            'reporting' => 'Rapporteringsoptioner',
            'ci'        => 'CI-integrationsoptioner',
        ],
        'option' => [
            'suffix'              => 'Inkluder filer hvis navne slutter på <suffix> (standard: :default; kan gentages)',
            'exclude'             => 'Udelad filer med <path> i deres sti (kan gentages)',
            'preset'              => 'Anvend et framework-preset (f.eks. laravel): sætter fornuftige stier, suffikser og udeladelser',
            'triage'              => 'Kør stadium 0 corpus-triage før detektion (aktiveret som standard; anmoder eksplicit om dette)',
            'no_triage'           => 'Spring stadium 0 helt over: ingen fil mærkes som afbrudt, skygget (shadowed), vendored eller afledt',
            'triage_posture'      => 'Hvad triagen gør ved en mærket fil: kasserer den fra scanningen (standard) eller mærker den og intet andet',
            'no_preset'           => 'Anvend ikke automatisk et framework-preset, når et detekteres (detektering annoncerer sig selv; --preset= tilsidesætter det)',
            'no_default_excludes' => 'Scann også genererede og cache-træer (vendor, node_modules, .phpstan.cache, build, ...), der udelades som standard',
            'allow_root_scan'     => 'Tillad en scanningsrod på / eller en rod over den næste composer.json (nægtet som standard: `phpcpd /` er næsten altid en stavefejl for `phpcpd ./`)',
            'orphans'             => 'Detekter forældreløse symboler (uhenførte klasser, interfaces, traits, enums, funktioner) i stedet for kloner',
            'no_suppress'         => 'Deaktiver undertrykkelsesregler efter navn, kommasepareret, eller "all" (:rules)',
            'fail_on'             => 'Resultatniveauer der gør at kørslen afsluttes med en kode forskellig fra nul, kommasepareret (standard: :default)',
            'explain'             => 'List hvert undertrykt symbol og den regel der undertrykte det, i stedet for blot at tælle dem',
            'rk'                  => 'Kun Rabin-Karp (nøjagtige/type-1 kloner; hurtigere, ingen reorder-detektion). Kører som standard både Rabin-Karp og TokenBag.',
            'min_lines'           => 'Minimum antal identiske linjer (standard: :default)',
            'min_tokens'          => 'Minimum antal identiske tokens (standard: :default)',
            'language'            => 'Sprog til rapporten (standard: :default)',
            'verbose'             => 'Udskriv den duplikerede kode for hver klon',
            'algorithm'           => 'Tilsidesættelse for enkeltalgoritme (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag-overlapningstærskel (standard: :default)',
            'raw'                   => 'Sammenlign rå tekst: identifikatorer skal også stemme (slår standardnormaliseringen fra)',
            'fuzzy'                 => 'Navneblind normalisering: som standard men uden typeankeret (forskning; E2 målte den som domineret)',
            'type_anchored'         => 'Hold typenøgleord konkrete under normalisering (slået til som standard; --fuzzy slår det fra)',
            'min_confidence'        => 'Vis kun fund, som modellen scorer til <log-odds> eller højere; resten tælles, kan læses med --hidden, kasseres aldrig og styrer stadig --fail-on',
            'hidden'                => 'Vis de fund, som --min-confidence holdt tilbage',
            'cache'                 => 'Cache resultater i \'.phpcpd-cache/\' — et hit kræver hver fil uændret, så det tjener en gentagelse af én commit frem for den næste',
            'acknowledged'        => 'Læs en indsendt anerkendelses-ledger fra <file>: opstillede duplikater degraderes, skjules aldrig, og poster hvis kode er ændret udløber og rapporteres',
            'write_acknowledged'  => 'Skriv denne kørsels fund som en anerkendelses-ledger til <file> til gennemgang og indsendelse',
            'log_pmd'             => 'Skriv log i PMD-CPD XML-format til <file>',
            'log_json'            => 'Skriv log i JSON-format til <file>',
            'log_sarif'           => 'Skriv log i SARIF 2.1.0-format til <file> (til GitHub Code Scanning)',
            'cache_dir'           => 'Læs/skriv cache fra <path> (indebærer --cache; tilsidesætter standardmappe)',
            'incremental'           => 'Inkrementelt indeks pr. fil: gentokeniser kun ændrede filer (rabin-karp eller unified, ikke den kombinerede standard; bruger cache-mappen)',
            'config'              => 'Læs konfiguration fra <file> (standard: ./phpcpd.ini hvis tilstede); nøgler er navnene på de lange optioner',
            'show_config'         => 'Udskriv de gældende indstillinger, hvorfra de stammede, og afslut',
            'no_config'           => 'Ignorer ./phpcpd.ini',
            'help'                => 'Udskriv denne hjælpetekst',
            'version'             => 'Udskriv versionsinformation',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'anerkendelses-ledger (acknowledgment ledger) fra phpcpd-next',
            'what'  => 'Hver linje registrerer en duplikering, som dette projekt har gennemgået og besluttet at
beholde. Et anerkendt fund er DEGRADERET, aldrig skult: det rapporteres
stadig, tælles stadig og begrænser stadig exit-koden.',
            'key'   => 'Nøglen er indholdet af hver side af duplikeringen, hashet — ingen sti
og intet linjenummer. Redigering af en af kopierne får posten til at udløbe, og
fundet hævdes på ny, mens flytning af koden ændrer intet. En post,
der ikke længere matcher noget, rapporteres som forældet, så den kan slettes.',
            'note'  => 'Teksten efter tabulatoren er en menneskelig note. Den matches aldrig.',
        ],
    ],
];
