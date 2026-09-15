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
 * Norsk bokmål-oversettelse (Norwegian Bokmål translation).
 */

return [
    'frame' => [
        'error'   => 'FEIL: :message',
        'warning' => 'ADVARSEL: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'I :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfigurasjonsfilen ble ikke funnet: :path',
        ],
        'unparsable' => [
            'config' => 'Konfigurasjonsfilen kunne ikke parses: :path',
        ],
        'unknown' => [
            'option'  => 'Ukjent alternativ :flag.',
            'setting' => 'Ukjent innstilling ":name" i :file',
        ],
        'needsValue' => [
            'option'  => 'Alternativet :flag trenger en verdi.',
            'setting' => 'Innstillingen ":name" i :file trenger en verdi.',
        ],
        'takesNoValue' => [
            'option' => 'Alternativet :flag tar ingen verdi.',
        ],
        'invalidValue' => [
            'option' => 'Ugyldig verdi ":value" for :flag (tillatt: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Den forenklede motoren (unified engine) trenger :flag på minst :floor (gitt: :given). Under det synker winnow-vinduet under 4 og indeksen slutter å være et utvalg, så motoren nekter i stedet for å gradvis og stille falle tilbake til en fullstendig skanning.',
        ],
        'unwired' => [
            'option' => 'Alternativet :flag har ingen innstillingsbinding.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Nekter å skanne filsystemets rot (:path). Mente du "./"?',
            'aboveProject'   => 'Nekter å skanne :path: den er over prosjektroten :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Ingen filer funnet å skanne.',
            'afterTriage' => 'Ingen filer igjen å skanne etter triagering.',
        ],
        'missingArgument' => [
            'directory' => 'Ingen katalog spesifisert.',
        ],
        'writeFailed' => [
            'report' => 'Kunne ikke skrive rapporten til :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Skrev bare av (:written) totalt byte (:total) av rapporten til :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'forhåndsinnstillingen ":preset" angir skanningsveier (:declared), manglende (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans kan ikke se hele prosjektet.',
            'belowRoots'     => '  Hver skanningsrot er under autolaste-røttene til :manifest.',
            'uncoveredRoots' => '  autolaste-veier deklarert i :manifest men aldri åpnet (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset oppdaget — forhåndsinnstilling anvendt (--no-preset for å deaktivere)',
        ],
        'cache' => [
            'hit' => '(mellomlagringstreff)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag er utdatert; bruk --algorithm=unified — som ennå ikke rapporterer hver plass token bag gjør, så denne forblir valgbar)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignorert i kombinert modus)',
            'unsupported' => '(--incremental ignorert: bare rabin-karp- og unified-algoritmene har en inkrementell indeks)',
            'index'       => '(inkrementell indeks: :reused gjenbrukt, :scanned skannet)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Ingen kodekloner funnet.',
            'heading'        => 'Fant kodekloner (:clones):gapped:reordered, dupliserte linjer (:lines), filer (:files):',
            'gapped'         => ', inkonsekvente (:count)',
            'reorderedCount' => ', omorganiserte (:count)',
            'reordered'      => '[omorganisert]',
            'unreadable'     => 'uleselige filer (:count) — i ingen av totalene:',
            'strataAsserted' => ':asserted hevdet, 0 degradert.',
            'strataSplit'    => ':asserted hevdet, :demoted degradert (:detail).',
            'settled'         => 'lesninger forkastet som allerede beskrevet (:count).',
            'unfounded'      => 'funn forkastet som ubekreftede (:count).',
            'hiddenLine'      => ':count av :total funn skjult under konfidensen :threshold — --hidden viser dem.',
            'hiddenHeading'   => 'Skjult under konfidensen :threshold (:count):',
            'coverage'         => ':percentage av de skannede linjene (:lines) er duplisert kode.',
            'literals'         => '[literaler avviker (:count)]',
            'confidence'       => 'tillit :score (:terms)',
            'functions'        => 'i :names',
            'sizes'          => 'Linjer per klon: gjennomsnitt (:average), størst (:largest).',
        ],
        'ledger' => [
            'line'      => 'Erkjent: av (:acknowledged) funn (:total) degradert av hovedboken; foreldet (:stale).',
            'staleNote' => 'foreldet (koden den erkjente har endret seg): :note',
            'wrote'     => 'erkjennelser skrevet (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (mangler)',
            'source' => [
                'default'     => 'standard',
                'commandLine' => 'kommandolinje',
                'builtIn'     => '    innebygde standarder',
            ],
            'fallback'    => ' faller tilbake til den innebygde standarden',
            'layers'      => '  Lag, laveste prioritet først:',
        ],
        'run' => [
            'throughput' => ' — filer (:count) med :rate/s',
            'files'    => ' — filer (:count)',
            'usage'  => 'Tid: :duration, Minne: :memory MB',
            'banner' => 'phpcpd :version av :author — etter :origin av :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Skanningsrot: :root',
            'roots'              => 'Skanningsrøtter:',
            'count' => [
                'file'       => 'filer (:count)',
                'directory'  => 'røtter (:count)',
                'pattern'    => 'ekskluderinger (:count)',
                'unreadable' => 'uleselige (:count)',
                'generated'  => 'genererte (:count)',
            ],
            'counts'             => 'Skannet :counts',
        ],
        'triage' => [
            'nothing'  => 'Triagering: ingenting merket; hver fil er programtekst.',
            'removed'  => 'Triagering: filer (:total), fjernet (:removed)',
            'labelled' => 'Triagering: filer (:total), merket (:labelled), ingenting fjernet',
        ],
        'orphan' => [
            'none'         => 'Ingen foreldreløse symboler funnet (symboler (:symbols) i filer (:files)).',
            'found'        => 'foreldreløse symboler (:count):',
            'possible'     => 'mulige foreldreløse (:count) — gjennomgå før fjerning:',
            'advisory'     => 'foreldreløse symboler (:count) — rådgivende, påvirker ikke utgangskoden:',
            'notShown'     => 'flere foreldreløse funn vises ikke (:count) — kjør --orphans for å gjennomgå.',
            'suppressed'   => 'Undertrykt (:count): :census',
            'explainHint'  => '  → --explain for å liste dem',
            'wholeFile'    => '    ⤷ hele filen er ukopplet — ingen symbol deklarert her blir referert',
            'supersededBy' => '    ⤷ ser ut som en erstattet kopi av :name',
            'summary'      => 'Skannet symboler (:symbols) i filer (:files); foreldreløse (:orphaned), mulige (:possible), undertrykte (:suppressed), planlagte (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'utenfor filsettet som dette prosjektets egne standardekskluderinger etterlater — et generert tre, som kanskje ikke vitner om tilkobling',
            'declaredHere'  => 'symboler deklarert her (:count), ingen av dem referert noe sted i prosjektet',
            'foreignNs'     => 'deklarerer :namespaces — et navnerom som ingen composer.json over det deklarerer, utenfor hver katalog de kobler til',
        ],
        'role' => [
            'noStatements' => 'ingen utsagn på toppnivå etter innledningen',
            'coupled'      => ':registrations av :statements utsagn på toppnivå er registreringsuttrykk, men de deler en variabel',
            'independent'  => ':registrations av :statements utsagn på toppnivå er dataflytuavhengige registreringsuttrykk',
        ],
        'orphan' => [
            'guard'          => 'deklarert inne i en eksistensvakt — polyfill eller kompatibilitetsomvei',
            'entrypoint'     => 'deklarert i :namespace — kalt av rammeverkets konvensjon',
            'partialProject' => '  Et symbol kalles dødt når *ingenting* refererer til det, noe som er en påstand om
  hele prosjektet. Kod utenfor denne skanningen kan fremdeles referere til det som rapporteres her.',
            'evidence' => [
                'nameAt'   => 'navnet vises ved',
                'loopAt'   => 'oppdaget av løkken ved',
                'suffixAt' => 'suffiks deklarert ved',
                'namedIn'  => 'navngitt i',
            ],
            'plannedServed'  => 'referert nå — @phpcpd-planned har utført sin hensikt og kan fjernes',
            'manifest'       => 'deklarert i et composer autoload.files-startpunkt',
            'foreignNs'      => 'deklarert utenfor prosjektets egne navnerom (kompatibilitetsomvei)',
            'fixture'        => 'test-fixture — lastet etter bane eller navngitt som en streng, aldri referert',
            'discovery'      => 'oppdaget av en kantskanning — instansiert fra filnavnet sitt bak class_exists',
            'convention'     => 'ledsagerklasse — :base bruker :trait, som løser dette navnet etter suffiks ved kjøretid',
            'interface'      => 'aldri referert (grensesnitt — kan implementeres utenfor det skannede settet)',
            'trait'          => 'aldri referert (trait — kan brukes av klasser utenfor det skannede settet)',
            'abstract'       => 'aldri referert (abstrakt — kan utvides utenfor det skannede settet)',
            'inString'       => 'aldri referert i kode; navnet vises i en strengliteral (mulig dynamisk bruk)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Betinget deklarert (polyfill / kompatibilitetsomvei)',
            'fixtures'    => 'Test-fixtures (lastet etter bane eller navn)',
            'config'      => 'Registrert i en konfigurasjonsfil',
            'template'    => 'Referert fra en mal (blade / twig / latte)',
            'manifest'    => 'Referert fra composer.json',
            'namespace'   => 'Deklarert utenfor prosjektets egne navnerom (kompatibilitetsomvei)',
            'keep'        => 'Merket som beholdt (@api / @phpcpd-keep)',
            'entrypoint'  => 'Rammeverkets startpunkter (attributt / testklasse)',
            'discovery'   => 'Oppdaget av en kantskanning (instansiert fra filnavnet sitt)',
            'convention'  => 'Ledsagerklasse navngitt etter konvensjon (suffiks deklarert av en trait)',
            'planned'     => 'Planlagt, ennå ikke tilkoblet',
            'none'        => 'Ingen referanse funnet',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Send med --allow-root-scan hvis du virkelig mente hele filsystemet.',
            'allowOutside' => 'Send med --allow-root-scan for å skanne utenfor prosjektet uansett.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skann prosjektroten for et resultat verdt å handle på.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard handler etter etikettene; --no-triage hopper over fasen)',
            'explain' => '  (--explain lister opp hver fil og bevisene for eller imot den)',
        ],
        'clone' => [
            'gapped'  => 'Nesten-treff-klon — vurder å parametrisere den avvikende delen eller justere begge kopiene.',
            'demoted' => 'Degradert som :stratum — dette er formen det laget beskriver, så ekstraher den bare hvis repetisjonen ikke er poenget.',
            'extract' => 'Vurder å ekstrahere de delte linjene til en gjenbrukbar metode, klasse eller trait.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Bruk:',
            'invocation' => '  phpcpd [alternativer] <katalog>',
        ],
        'group' => [
            'selecting' => 'Alternativer for valg av filer',
            'orphans'   => 'Oppdagelse av foreldreløse symboler (død kode)',
            'analysing' => 'Alternativer for analysering av filer',
            'general'   => 'Generelle alternativer',
            'reporting' => 'Alternativer for rapportgenerering',
            'ci'        => 'Alternativer for CI-integrasjon',
        ],
        'option' => [
            'suffix'              => 'Inkluder filer med navn som slutter på <suffix> (standard: :default; repeterbar)',
            'exclude'             => 'Ekskluder filer med <path> i banen deres (repeterbar)',
            'preset'              => 'Bruk en rammeverksforhåndsinnstilling (f.eks. laravel): setter fornuftige veier, suffikser og ekskluderinger',
            'triage'              => 'Kjør fase 0-korpustriagering før detektering (på som standard; dette ber om det eksplisitt)',
            'no_triage'           => 'Hopp over fase 0 fullstendig: ingen fil merkes som ukopplet, skyggelagt, fra leverandør eller avledet',
            'triage_posture'      => 'Hva triagering gjør med en fil den merker: kaster den ut fra skanningen (standard), eller merker den og ingenting annet',
            'no_preset'           => 'Ikke bruk en rammeverksforhåndsinnstilling automatisk når en oppdages (detektering kunngjør seg selv; --preset= overstyrer dette)',
            'no_default_excludes' => 'Skann genererte trær og mellomlagringstrær også (vendor, node_modules, .phpstan.cache, build, ...), som hoppes over som standard',
            'allow_root_scan'     => 'Tillat en skanningsrot på / eller en rot over nærmeste composer.json (nektet som standard: `phpcpd /` er nesten alltid en skrivefeil for `phpcpd ./`)',
            'orphans'             => 'Oppdag foreldreløse symboler (uhenviste klasser, grensesnitt, traits, enums, funksjoner) i stedet for kloner',
            'no_suppress'         => 'Slå av undertrykkelsesregler etter navn, kommadelt, eller "all" (:rules)',
            'fail_on'             => 'Resultatnivåer som gjør at kjøringen avslutter med nullskilt kode, kommadelt (standard: :default)',
            'explain'             => 'List opp hvert undertrykt symbol og regelen som undertrykte det, i stedet for bare å telle dem',
            'rk'                  => 'Bare Rabin-Karp (nøyaktige/type 1-kloner; raskere, ingen omordningsdetektering). Standardkjøringen kjører både Rabin-Karp og TokenBag.',
            'min_lines'           => 'Minimum antall identiske linjer (standard: :default)',
            'min_tokens'          => 'Minimum antall identiske token (standard: :default)',
            'language'            => 'Språk for rapporten (standard: :default)',
            'verbose'             => 'Skriv ut den dupliserte koden for hver klon',
            'algorithm'           => 'Overstyring av enkeltalgoritme (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag overlappende terskel (standard: :default)',
            'raw'                   => 'Sammenlign rå tekst: identifikatorer må også stemme (slår av standardnormaliseringen)',
            'fuzzy'                 => 'Navneblind normalisering: som standard men uten typeankeret (forskning; E2 målte den som dominert)',
            'type_anchored'         => 'Hold typenøkkelord konkrete under normalisering (på som standard; --fuzzy slår det av)',
            'min_confidence'        => 'List bare funn som modellen scorer til <log-odds> eller høyere; resten telles, kan leses med --hidden, forkastes aldri og styrer fortsatt --fail-on',
            'hidden'                => 'List funnene som --min-confidence holdt tilbake',
            'cache'                 => 'Mellomlagre resultater i \'.phpcpd-cache/\' — et treff krever hver fil uendret, så det tjener en ny kjøring av én commit heller enn den neste',
            'acknowledged'        => 'Les en innsjekket erkjennelseshovedbok fra <file>: listet duplisering degraderes, skjules aldri, og oppføringer hvis kode har endret seg utløper og rapporteres',
            'write_acknowledged'  => 'Skriv denne kjøringens funn til <file> som en erkjennelseshovedbok, for gjennomgang og innsjekking',
            'log_pmd'             => 'Skriv logg i PMD-CPD XML-format til <file>',
            'log_json'            => 'Skriv logg i JSON-format til <file>',
            'log_sarif'           => 'Skriv logg i SARIF 2.1.0-format til <file> (for GitHub Code Scanning)',
            'cache_dir'           => 'Les/skriv mellomlagring fra <path> (innebærer --cache; overstyrer standardkatalogen)',
            'incremental'           => 'Inkrementell indeks per fil: retokeniserer bare endrede filer (rabin-karp eller unified, ikke den kombinerte standarden; bruker hurtigbufferkatalogen)',
            'config'              => 'Les innstillinger fra <file> (standard: ./phpcpd.ini når tilstede; nøklene er de lange alternativnavnene)',
            'show_config'         => 'Skriv ut gjeldende innstillinger, hvor hver kom fra, og avslutt',
            'no_config'           => 'Ignorer ./phpcpd.ini',
            'help'                => 'Skriv ut denne hjelpen',
            'version'             => 'Skriv ut versjonsinformasjon',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next erkjennelseshovedbok',
            'what'  => 'Hver linje registrerer én duplisering som dette prosjektet har sett på og bestemt seg for å leve med. Et erkjent funn er DEGRADERT, aldri skjult: det rapporteres fremdeles, telles fremdeles og styrer fremdeles utgangskoden.',
            'key'   => 'Nøkkelen er innholdet på hver side av dupliseringene, hashet — ikke en bane og ikke et linjenummer. Så redigering av hvilken som helst kopi utløper oppføringen og funnet hevdes på ny, mens flytting av koden endrer ingenting. En oppføring som ikke lenger samsvarer med noe, rapporteres som foreldret slik at den kan slettes.',
            'note'  => 'Teksten etter tabulatoren er en menneskelig merknad. Det matches aldri mot.',
        ],
    ],
];
