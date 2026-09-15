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
 * Esperanta traduko.
 *
 * Ŝlosiloj, rezervitaj vortoj, nombriloj kaj kio ĝenerale apartenas ĉi tien: vidu
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'ERARO: :message',
        'warning' => 'AVERTO: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'En :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Agorda dosiero ne troviĝis: :path',
        ],
        'unparsable' => [
            'config' => 'Ne eblis analizi la agordan dosieron: :path',
        ],
        'unknown' => [
            'option'  => 'Nekonata opcio :flag.',
            'setting' => 'Nekonata agordo ":name" en :file',
        ],
        'needsValue' => [
            'option'  => 'La opcio :flag bezonas valoron.',
            'setting' => 'La agordo ":name" en :file bezonas valoron.',
        ],
        'takesNoValue' => [
            'option' => 'La opcio :flag ne akceptas valoron.',
        ],
        'invalidValue' => [
            'option' => 'Malvalida valoro ":value" por :flag (permesata: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'La unuigita motoro (unified engine) bezonas :flag de almenaŭ :floor (donita: :given). Sub tio, la winnow-fenestro falas sub 4 kaj la indekso ĉesas esti specimeno, do la motoro rifuzas anstataŭ silentgrade malsupreniri al kompleta skanado.',
        ],
        'unwired' => [
            'option' => 'La opcio :flag havas neniun agordan ligon.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Rifuzas skani la dosiersisteman radikon (:path). Ĉu vi celis "./"?',
            'aboveProject'   => 'Rifuzas skani :path: ĝi troviĝas super la projekta radiko :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Neniuj dosieroj troviĝis por skani.',
            'afterTriage' => 'Neniuj dosieroj restas por skani post triagado.',
        ],
        'missingArgument' => [
            'directory' => 'Neniu dosierujo specifita.',
        ],
        'writeFailed' => [
            'report' => 'Ne eblis skribi la raporton al :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Nur el (:written) entute bajtoj (:total) de la raporto skribitaj al :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'Antaŭgordo ":preset" deklaras skanvojojn (:declared), mankas (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans ne povas vidi la tutan projekton.',
            'belowRoots'     => '  Ĉiu skanradiko troviĝas sub la aŭtomataj ŝargaj radikoj de :manifest.',
            'uncoveredRoots' => '  aŭtomataj ŝargaj vojoj deklaritaj en :manifest sed neniam malfermitaj (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detektita — antaŭgordo aplikiĝis (--no-preset por malŝalti)',
        ],
        'cache' => [
            'hit' => '(kaŝmemora trafuto)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag estas malnoviĝinta; uzu --algorithm=unified — kiu ankoraŭ ne raportas ĉiun lokon kiun raportas tokenbag, do ĉi tio restas elektebla)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignorita en kombinita reĝimo)',
            'unsupported' => '(--incremental ignorita: nur la algoritmoj rabin-karp kaj unified havas pliigan indekson)',
            'index'       => '(pliiga indekso: :reused reuzitaj, :scanned skanitaj)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Niuj kodoklonoj troviĝis.',
            'heading'        => 'Trovitaj kodoklonoj (:clones):gapped:reordered, duplikitaj linioj (:lines), dosieroj (:files):',
            'gapped'         => ', malkongruaj (:count)',
            'reorderedCount' => ', reordigitaj (:count)',
            'reordered'      => '[reordigita]',
            'unreadable'     => 'nelegeblaj dosieroj (:count) — ne inkluzivataj en la sumo:',
            'strataAsserted' => 'asertitaj :asserted, 0 malaltigitaj.',
            'strataSplit'    => 'asertitaj :asserted, malaltigitaj :demoted (:detail).',
            'settled'         => 'legadoj forĵetitaj kiel jam priskribitaj (:count).',
            'unfounded'      => 'trovoj forĵetitaj kiel nekontrolitaj (:count).',
            'hiddenLine'      => ':count el :total trovoj kaŝitaj sub la fido :threshold — --hidden listigas ilin.',
            'hiddenHeading'   => 'Kaŝitaj sub la fido :threshold (:count):',
            'coverage'         => ':percentage de la skanitaj linioj (:lines) estas duplikata kodo.',
            'literals'         => '[literaloj malsamas (:count)]',
            'confidence'       => 'fido :score (:terms)',
            'functions'        => 'en :names',
            'sizes'          => 'Linioj po klono: averaĝe (:average), plej granda (:largest).',
        ],
        'ledger' => [
            'line'      => 'Agnoskitaj: el (:acknowledged) trovoj (:total) malaltigitaj per la libro; malaktualaj (:stale).',
            'staleNote' => 'malaktuala (la kodo agnoskita ŝanĝiĝis): :note',
            'wrote'     => 'skribitaj agnoskoj (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (mankanta)',
            'source' => [
                'default'     => 'defaŭlta',
                'commandLine' => 'komandlinio',
                'builtIn'     => '    enkonstruitaj defaŭltoj',
            ],
            'fallback'    => ' reveno al la enkonstruita defaŭlto',
            'layers'      => '  Tavoloj, plej malalta prioritato unue:',
        ],
        'run' => [
            'throughput' => ' — dosieroj (:count) je :rate/s',
            'files'    => ' — dosieroj (:count)',
            'usage'  => 'Tempo: :duration, Memoro: :memory MB',
            'banner' => 'phpcpd :version de :author — post :origin de :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Skanradiko: :root',
            'roots'              => 'Skanradikoj:',
            'count' => [
                'file'       => 'dosieroj (:count)',
                'directory'  => 'radikoj (:count)',
                'pattern'    => 'ekskludoj (:count)',
                'unreadable' => 'nelegeblaj (:count)',
                'generated'  => 'generitaj (:count)',
            ],
            'counts'             => 'Skanitaj :counts',
        ],
        'triage' => [
            'nothing'  => 'Triagado: nenio etikedita; ĉiu dosiereto estas programteksto.',
            'removed'  => 'Triagado: dosieroj (:total), forigitaj (:removed)',
            'labelled' => 'Triagado: dosieroj (:total), etikeditaj (:labelled), nenio forigita',
        ],
        'orphan' => [
            'none'         => 'Niuj orfaj simboloj troviĝis (simboloj (:symbols) en dosieroj (:files)).',
            'found'        => 'orfaj simboloj (:count):',
            'possible'     => 'eblaj orfoj (:count) — kontrolu antaŭ forigo:',
            'advisory'     => 'orfaj simboloj (:count) — konsilaj, ne influas elirkodon:',
            'notShown'     => 'pliaj orfaj trovoj ne montrataj (:count) — rulu --orphans por revizii.',
            'suppressed'   => 'Subpremitaj (:count): :census',
            'explainHint'  => '  → --explain por listigi ilin',
            'wholeFile'    => '    ⤷ la tuta dosiero estas neligita — neniu ĉi tie deklarita simbolo estas referenceita',
            'supersededBy' => '    ⤷ ŝajnas anstataŭigita kopio de :name',
            'summary'      => 'Skanitaj simboloj (:symbols) en dosieroj (:files); orfaj (:orphaned), eblaj (:possible), subpremitaj (:suppressed), planitaj (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'ekster la dosieraro kiun la propraj defaŭltaj ekskludoj de ĉi tiu projekto postlasas — generita arbo, eble ne atestas pri drataro',
            'declaredHere'  => 'simboloj deklaritaj ĉi tie (:count), neniu el ili estas referenceita ie ajn en la projekto',
            'foreignNs'     => 'deklaras :namespaces — nomspaco kiun neniu composer.json super ĝi deklaras, ekster ĉiu dosierujo kiun ili dratas',
        ],
        'role' => [
            'noStatements' => 'neniuj ĉefnivelaj instrukcioj post la preambulo',
            'coupled'      => ':registrations el :statements ĉefnivelaj instrukcioj estas registraj esprimoj, sed ili dividas variablon',
            'independent'  => ':registrations el :statements ĉefnivelaj instrukcioj estas datenflu-sendependaj registraj esprimoj',
        ],
        'orphan' => [
            'guard'          => 'deklarita ene de ekzistogardilo — polifilo aŭ kongrueceto',
            'entrypoint'     => 'deklarita en :namespace — vokata de frama konvencio',
            'partialProject' => '  Simbolo estas nomata morta kiam *nenio* referencas ĝin, kio estas aserto pri
  la tuta projekto. Kodo ekster ĉi tiu skanado ankoraŭ povas referenci tion kio estas raportita ĉi tie.',
            'evidence' => [
                'nameAt'   => 'nomo aperas ĉe',
                'loopAt'   => 'detektita de la bucle ĉe',
                'suffixAt' => 'sufikso deklarita ĉe',
                'namedIn'  => 'nomita en',
            ],
            'plannedServed'  => 'nun referenceita — @phpcpd-planned plenumis sian celon kaj povas esti forigita',
            'manifest'       => 'deklarita en composer autoload.files enirejo',
            'foreignNs'      => 'deklarita ekster la propraj nomspacoj de la projekto (kongrueceto)',
            'fixture'        => 'testfiksaĵo — ŝargita per vojo aŭ nomita kiel signoĉeno, neniam referenceita',
            'discovery'      => 'detektita per dosierujoskanado — instanciita el sia dosiernomo malantaŭ class_exists',
            'convention'     => 'kunula klaso — :base uzas :trait, kiu solvas ĉi tiun nomon per sufikso dum rultempo',
            'interface'      => 'neniam referenceita (interfaco — eble efektivigita ekster la skanita aro)',
            'trait'          => 'neniam referenceita (trajto — eble uzata de klasoj ekster la skanita aro)',
            'abstract'       => 'neniam referenceita (abstrakta — eble etendita ekster la skanita aro)',
            'inString'       => 'neniam referenceita en kodo; nomo aperas en signoĉena literalo (ebla dinamika uzo)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Kondiĉe deklarita (polifilo / kongrueceto)',
            'fixtures'    => 'Testfiksaĵoj (ŝargitaj per vojo aŭ nomo)',
            'config'      => 'Registrita en agorda dosiero',
            'template'    => 'Referenceita el ŝablono (blade / twig / latte)',
            'manifest'    => 'Referenceita el composer.json',
            'namespace'   => 'Deklarita ekster la propraj nomspacoj de la projekto (kongrueceto)',
            'keep'        => 'Markita kiel tenita (@api / @phpcpd-keep)',
            'entrypoint'  => 'Framaj enirejoj (atributo / testklaso)',
            'discovery'   => 'Detektita per dosierujoskanado (instanciita el dosiernomo)',
            'convention'  => 'Kunula klaso nomita laŭ konvencio (sufikso deklarita per trajto)',
            'planned'     => 'Planita, ankoraŭ nedratita',
            'none'        => 'Neniu referenco troviĝis',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Pasigu --allow-root-scan se vi vere celis la tutan dosiersistemon.',
            'allowOutside' => 'Pasigu --allow-root-scan por skani ekster la projekto ĉiuokaze.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skanu la projektradikon por rezulto inda agi laŭ ĝi.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard agas laŭ la etikedoj; --no-triage preterpasas la stadion)',
            'explain' => '  (--explain listigas ĉiun dosieron kaj la pruvojn por aŭ kontraŭ ĝi)',
        ],
        'clone' => [
            'gapped'  => 'Preskaŭ-sukcesa klono — konsideru parametrigi la malsamantan parton aŭ vicigi ambaŭ kopiojn.',
            'demoted' => 'Malaltigita kiel :stratum — ĉi tio estas la formo kiun tiu tavolo priskribas, do elprenu ĝin nur se la ripeto ne estas la intenco.',
            'extract' => 'Konsideru elpreni la komunajn liniojn al reuzebla metodo, klaso aŭ trajto.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Uzo:',
            'invocation' => '  phpcpd [opcioj] <dosierujo>',
        ],
        'group' => [
            'selecting' => 'Opcioj por elekti dosierojn',
            'orphans'   => 'Detekto de orfaj elementoj (morta kodo)',
            'analysing' => 'Opcioj por analizi dosierojn',
            'general'   => 'Ĝeneralaj opcioj',
            'reporting' => 'Opcioj por generi raportojn',
            'ci'        => 'Opcioj por CI-integriĝo',
        ],
        'option' => [
            'suffix'              => 'Inkluzivi dosierojn kies nomoj finiĝas per <suffix> (defaŭlte: :default; ripetebla)',
            'exclude'             => 'Ekskludi dosierojn enhavantajn <path> en sia vojo (ripetebla)',
            'preset'              => 'Apliki framan antaŭgordon (ekz. laravel): agordas prudentajn vojojn, sufiksojn kaj ekskludojn',
            'triage'              => 'Ruli korpusan triagadon de Stadio 0 antaŭ detekto (ŝaltita defaŭlte; petas tion eksplicite)',
            'no_triage'           => 'Plene preterpasi Stadion 0: neniu dosiero estas etikedita kiel neligita, ombrata, vendista aŭ deriva',
            'triage_posture'      => 'Kion triagado faras kun dosiero kiun ĝi etikedas: forĵeti ĝin el la skanado (defaŭlte), aŭ etikedi ĝin kaj fari nenion alian',
            'no_preset'           => 'Ne aŭtomate apliki framan antaŭgordon kiam ĝi estas detektita (la detekto anoncas sin; --preset= superregas ĉi tion)',
            'no_default_excludes' => 'Skani ankaŭ generitajn kaj kaŝmemorajn arbojn (vendor, node_modules, .phpstan.cache, build, ...), kiuj estas preterpasataj defaŭlte',
            'allow_root_scan'     => 'Permesi skanradikon de / aŭ radikon super la plej proksima composer.json (rifuzita defaŭlte: `phpcpd /` estas preskaŭ ĉiam tajperaro por `phpcpd ./`)',
            'orphans'             => 'Detekti orfajn simbolojn (nevicigitajn klasojn, interfacojn, trajtojn, enumojn, funkciojn) anstataŭ klonojn',
            'no_suppress'         => 'Malŝalti subpremajn regulojn laŭ nomo, apartigitaj per komoj, aŭ "all" (:rules)',
            'fail_on'             => 'Rezultatniveloj kiuj igas la rulumon eliri kun ne-nula kodo, apartigitaj per komoj (defaŭlte: :default)',
            'explain'             => 'Listigi ĉiun subpremitan simbolon kaj la regulon kiu subpremis ĝin, anstataŭ nur nombri ilin',
            'rk'                  => 'Nur Rabin-Karp (ekzakta/Tip-1 klonoj; pli rapida, neniu reordiga detekto). La defaŭlta rulumo rulas kaj Rabin-Karp kaj TokenBag.',
            'min_lines'           => 'Minimuma nombro de identaj linioj (defaŭlte: :default)',
            'min_tokens'          => 'Minimuma nombro de identaj signetoj (defaŭlte: :default)',
            'language'            => 'Lingvo de la raporto (defaŭlte: :default)',
            'verbose'             => 'Presi la duplikatan kodon por ĉiu klono',
            'algorithm'           => 'Superregi unuopan algoritmon (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Sojlo de TokenBag-interkovro (defaŭlte: :default)',
            'raw'                   => 'Kompari krudan tekston: ankaŭ identigiloj devas kongrui (malŝaltas la defaŭltan normaligon)',
            'fuzzy'                 => 'Nomblinda normaligo: kiel la defaŭlta sed sen la tipankro (esplora; E2 mezuris ĝin dominata)',
            'type_anchored'         => 'Teni tipvortojn konkretaj dum normaligo (defaŭlte ŝaltita; --fuzzy malŝaltas ĝin)',
            'min_confidence'        => 'Listigi nur trovojn kiujn la modelo taksas je <log-odds> aŭ pli; la ceteraj estas kalkulataj, legeblaj per --hidden, neniam forĵetataj, kaj plu determinas --fail-on',
            'hidden'                => 'Listigi la trovojn kiujn --min-confidence retenis',
            'cache'                 => 'Kaŝmemori rezultojn en \'.phpcpd-cache/\' — trafo postulas ĉiun dosieron neŝanĝita, do ĝi servas al ripeto de unu enmeto anstataŭ la sekva',
            'acknowledged'        => 'Legi kommititan agnoskoflibron el <file>: listigita duplikato estas malaltigita, neniam kaŝita, kaj eniroj kies kodo ŝanĝiĝis eksvalidiĝas kaj estas raportitaj',
            'write_acknowledged'  => 'Skribi la trovojn de ĉi tiu rulumo al <file> kiel agnoskoflibron, por revizio kaj kommit',
            'log_pmd'             => 'Skribi protokolon laŭ la formato PMD-CPD XML al <file>',
            'log_json'            => 'Skribi protokolon laŭ la formato JSON al <file>',
            'log_sarif'           => 'Skribi protokolon laŭ la formato SARIF 2.1.0 al <file> (por GitHub Code Scanning)',
            'cache_dir'           => 'Legi/skribi kaŝmemoron el <path> (implikas --cache; superregas la defaŭltan dosierujon)',
            'incremental'           => 'Alkrementa indekso po dosiero: retokenigi nur ŝanĝitajn dosierojn (rabin-karp aŭ unified, ne la kombinita defaŭlto; uzas la kaŝmemoran dosierujon)',
            'config'              => 'Legi agordojn el <file> (defaŭlte: ./phpcpd.ini kiam ĝi ekzistas; la ŝlosiloj estas la longaj opciaj nomoj)',
            'show_config'         => 'Presi la validajn agordojn, de kie ĉiu venis, kaj eliri',
            'no_config'           => 'Ignori ./phpcpd.ini',
            'help'                => 'Presi ĉi tiun helpon',
            'version'             => 'Presi informojn pri versio',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next agnoskoflibro',
            'what'  => 'Ĉiu linio registras unu duplikaton kiun ĉi tiu projekto rigardis kaj decidis kunvivi kun ĝi. Agnoskita trovo estas MALALTIGITA, neniam kaŝita: ĝi ankoraŭ estas raportita, ankoraŭ kalkulita, kaj ankoraŭ stiras la elirkodon.',
            'key'   => 'La ŝlosilo estas la enhavo de ĉiu flanko de la duplikato, hakita — ne vojo kaj ne linionumero. Do redakti iun ajn el ambaŭ kopioj igas la eniron eksvalida kaj la trovo denove estas asertita, dum movi la kodon nenion ŝanĝas. Eniro kiu jam ne kongruas kun io ajn estas raportita kiel malaktuala por ke ĝi povu esti forigita.',
            'note'  => 'La teksto post la tabulatoro estas homa noto. Ĝi neniam estas kongruigata.',
        ],
    ],
];
