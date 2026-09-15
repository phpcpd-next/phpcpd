<?php

declare(strict_types=1);
/*
 * See fail on PhpcpdNext osa.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Täieliku autoriõiguse ja litsentsi teabe saamiseks vaadake faili LICENSE
 * mis jagati selle lähtekoodiga.
 */
/*
 * Eestikeelne tõlge.
 *
 * Võtmed, kohatäited, loendurid ja mis siia üldiselt kuulub: vt
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'VIGA: :message',
        'warning' => 'HOIATUS: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'Failis :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfiguratsioonifaili ei leitud: :path',
        ],
        'unparsable' => [
            'config' => 'Konfiguratsioonifaili ei saanud parssida: :path',
        ],
        'unknown' => [
            'option'  => 'Tundmatu valik :flag.',
            'setting' => 'Tundmatu seadistus ":name" failis :file',
        ],
        'needsValue' => [
            'option'  => 'Valik :flag vajab väärtust.',
            'setting' => 'Seadistus ":name" failis :file vajab väärtust.',
        ],
        'takesNoValue' => [
            'option' => 'Valik :flag ei aktsepteeri väärtust.',
        ],
        'invalidValue' => [
            'option' => 'Vigane väärtus ":value" valikule :flag (lubatud: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Ühendatud mootor (unified engine) vajab valikut :flag väärtusega vähemalt :floor (antud: :given). Sellest madalamal langeb winnow-aken alla 4 ja indeks lakkab olemast proov, nii et mootor keeldub töötamast, selle asemel et vaikselt degradeeruda täielikuks skannimiseks.',
        ],
        'unwired' => [
            'option' => 'Valikul :flag ei ole seadistuste seost.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Keeldub skannimast failisüsteemi juurkausta (:path). Kas pidasite silmas "./"?',
            'aboveProject'   => 'Keeldub skannimast teed :path: see asub projektijuure :project kohal.',
        ],
        'nothingToScan' => [
            'files'       => 'Skannitavaid faile ei leitud.',
            'afterTriage' => 'Triaaži järel ei jäänud skannimiseks ühtegi faili.',
        ],
        'missingArgument' => [
            'directory' => 'Kataloogi pole määratud.',
        ],
        'writeFailed' => [
            'report' => 'Aruande kirjutamine teele :path ebaõnnestus: :detail',
        ],
        'writePartial' => [
            'report' => 'Aruandest kirjutati teele :path ainult baiti (:written) baidist (:total)',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'Eelseadistus ":preset" deklareerib skannimise teed (:declared), puuduvad (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans ei näe kogu projekti.',
            'belowRoots'     => '  Iga skannimise juurkaust asub manifesti :manifest automaatlaadimise juurte all.',
            'uncoveredRoots' => '  manifestis :manifest deklareeritud automaatlaadimise teed, mida pole kunagi avatud (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => 'Tuvastatud :preset — eelseadistus rakendatud (--no-preset keelamiseks)',
        ],
        'cache' => [
            'hit' => '(vahemälu tabamus)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag on aegunud; kasutage --algorithm=unified — mis ei raporteeri veel kõiki kohti, mida tokenbag raporteerib, seega jääb see valitavaks)',
        ],
        'incremental' => [
            'combined'    => '(--incremental eiratud kombineeritud režiimis)',
            'unsupported' => '(--incremental eiratud: ainult rabin-karp ja unified algoritmid omavad inkrementaalset indeksit)',
            'index'       => '(inkrementaalne indeks: :reused taaskasutatud, :scanned skannitud)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Koodikloone ei leitud.',
            'heading'        => 'Leitud koodikloonid (:clones):gapped:reordered, dubleeritud read (:lines), failid (:files):',
            'gapped'         => ', vastuolulisi (:count)',
            'reorderedCount' => ', ümberjärjestatud (:count)',
            'reordered'      => '[ümberjärjestatud]',
            'unreadable'     => 'loetamatud failid (:count) — kummaski kogus ei ole kaasatud:',
            'strataAsserted' => 'nõutud :asserted, 0 degradeeritud.',
            'strataSplit'    => 'nõutud :asserted, degradeeritud :demoted (:detail).',
            'settled'         => 'lugemised kõrvale heidetud, sest need on juba kirjeldatud (:count).',
            'unfounded'      => 'leiud kõrvale heidetud kontrollimatuna (:count).',
            'hiddenLine'      => ':count leidu :total-st peidetud allapoole usaldusväärsust :threshold — --hidden loetleb need.',
            'hiddenHeading'   => 'Peidetud allapoole usaldusväärsust :threshold (:count):',
            'coverage'         => ':percentage skannitud ridadest (:lines) on dubleeritud kood.',
            'literals'         => '[literaalid erinevad (:count)]',
            'confidence'       => 'kindlus :score (:terms)',
            'functions'        => ':names sees',
            'sizes'          => 'Ridu klooni kohta: keskmine (:average), suurim (:largest).',
        ],
        'ledger' => [
            'line'      => 'Tunnustatud: :acknowledged tulemusest (:total) on pearaamatu poolt degradeeritud; aegunud (:stale).',
            'staleNote' => 'aegunud (tunnustatud kood on muutunud): :note',
            'wrote'     => 'tunnustused kirjutatud (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (puudub)',
            'source' => [
                'default'     => 'vaikimisi',
                'commandLine' => 'käsurea',
                'builtIn'     => '    sisseehitatud vaikimisi väärtused',
            ],
            'fallback'    => ' tagasipöördumine sisseehitatud vaikeväärtuse juurde',
            'layers'      => '  Kihid, madalaima prioriteediga eespool:',
        ],
        'run' => [
            'throughput' => ' — failid (:count) kiirusel :rate/s',
            'files'    => ' — failid (:count)',
            'usage'  => 'Aeg: :duration, Mälu: :memory MB',
            'banner' => 'phpcpd :version - autor :author — pärast :origin - autor :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Skannimise juur: :root',
            'roots'              => 'Skannimise juured:',
            'count' => [
                'file'       => 'faile (:count)',
                'directory'  => 'juuri (:count)',
                'pattern'    => 'välistusi (:count)',
                'unreadable' => 'loetamatuid (:count)',
                'generated'  => 'genereeritud (:count)',
            ],
            'counts'             => 'Skannitud :counts',
        ],
        'triage' => [
            'nothing'  => 'Triaaž: midagi pole märgistatud; iga fail on programmitekst.',
            'removed'  => 'Triaaž: faile (:total), eemaldatud (:removed)',
            'labelled' => 'Triaaž: faile (:total), märgistatud (:labelled), midagi ei eemaldatud',
        ],
        'orphan' => [
            'none'         => 'Orvust sümboleid ei leitud (sümbolit (:symbols) failis (:files)).',
            'found'        => 'orvust sümbolid (:count):',
            'possible'     => 'võimalikud orvud (:count) — kontrollige enne eemaldamist:',
            'advisory'     => 'orvust sümbolid (:count) — soovituslikud, ei mõjuta väljumiskoodi:',
            'notShown'     => 'muid orvustulemusi ei kuvata (:count) — käivitage --orphans ülevaatamiseks.',
            'suppressed'   => 'Summutatud (:count): :census',
            'explainHint'  => '  → --explain nende loetlemiseks',
            'wholeFile'    => '    ⤷ kogu fail on ühendamata — ühtegi siin deklareeritud sümbolit ei viidata',
            'supersededBy' => '    ⤷ näeb välja nagu :name asendatud koopia',
            'summary'      => 'Skannitud sümbolit (:symbols) failis (:files); orvust (:orphaned), võimalikud (:possible), summutatud (:suppressed), plaanitud (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'väljaspool failikomplekti, mille selle projekti enda vaikevälistused jätavad — genereeritud puu, mis ei pruugi kaabeldust tõendada',
            'declaredHere'  => 'siin deklareeritud sümbolid (:count), millest ühelegi ei viidata kuskil projektis',
            'foreignNs'     => 'deklareerib :namespaces — nimeruum, mida ükski selle kohal olev composer.json ei deklareeri, väljaspool ühtegi kataloogi, mida nad ühendavad',
        ],
        'role' => [
            'noStatements' => 'pärast preambulat pole ülataseme lauseid',
            'coupled'      => ':registrations :statements ülataseme lausest on registreerimisavaldised, kuid nad jagavad muutujat',
            'independent'  => ':registrations :statements ülataseme lausest on andmevoost sõltumatud registreerimisavaldised',
        ],
        'orphan' => [
            'guard'          => 'deklareeritud eksistentsivalvuri sees — polüfill või ühilduvuskilp',
            'entrypoint'     => 'deklareeritud nimeruumis :namespace — raamistiku konventsiooni poolt välja kutsutud',
            'partialProject' => '  Sümbolit nimetatakse surnuks, kui *ükski asi* sellele ei viita, mis on väide
  kogu projekti kohta. Kood väljaspool seda skannimist võib siiski viidata sellele, mida siin raporteeritakse.',
            'evidence' => [
                'nameAt'   => 'nimi ilmub asukohas',
                'loopAt'   => 'tuvastatud tsükli poolt asukohas',
                'suffixAt' => 'sufiks deklareeritud asukohas',
                'namedIn'  => 'nimetatud asukohas',
            ],
            'plannedServed'  => 'nüüd viidatud — @phpcpd-planned täitis oma eesmärgi ja seda saab eemaldada',
            'manifest'       => 'deklareeritud composer autoload.files sisenemispunktis',
            'foreignNs'      => 'deklareeritud projekti oma nimeruumidest väljaspool (ühilduvuskilp)',
            'fixture'        => 'testifixtuur — laaditud tee kaudu või nimetatud stringina, mitte kunagi viidatud',
            'discovery'      => 'avastatud kataloogiskannimisega — initsialiseeritud oma failinimest class_exists taga',
            'convention'     => 'kaaslas klass — :base kasutab traiti :trait, mis lahendab selle nime sufiksi abil käitusajal',
            'interface'      => 'mitte kunagi viidatud (liides — võib olla rakendatud skannitud komplektist väljaspool)',
            'trait'          => 'mitte kunagi viidatud (trait — võib olla kasutatud klasside poolt skannitud komplektist väljaspool)',
            'abstract'       => 'mitte kunagi viidatud (abstraktne — võib olla laiendatud skannitud komplektist väljaspool)',
            'inString'       => 'koodis mitte kunagi viidatud; nimi ilmub stringiliteralis (võimalik dünaamiline kasutus)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Tingimuslikult deklareeritud (polüfill / ühilduvuskilp)',
            'fixtures'    => 'Testifixtuurid (laaditud tee või nime kaudu)',
            'config'      => 'Registreeritud konfiguratsioonifailis',
            'template'    => 'Viidatud mallist (blade / twig / latte)',
            'manifest'    => 'Viidatud failist composer.json',
            'namespace'   => 'Deklareeritud projekti oma nimeruumidest väljaspool (ühilduvuskilp)',
            'keep'        => 'Märgitud säilitatavaks (@api / @phpcpd-keep)',
            'entrypoint'  => 'Raamistiku sisenemispunktid (atribuut / testklass)',
            'discovery'   => 'Avastatud kataloogiskannimisega (initsialiseeritud failinimest)',
            'convention'  => 'Konventsiooni järgi nimetatud kaaslasklass (traidi poolt deklareeritud sufiks)',
            'planned'     => 'Plaanitud, veel ühendamata',
            'none'        => 'Viidet ei leitud',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Edastage --allow-root-scan, kui pidasite tõesti silmas kogu failisüsteemi.',
            'allowOutside' => 'Edastage --allow-root-scan, et skannida projektist väljaspool igal juhul.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skannige projektijuurt tulemuse saamiseks, mille alusel tasub tegutseda.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard tegutseb siltide järgi; --no-triage jätab staadiumi vahele)',
            'explain' => '  (--explain loetleb iga faili ja seda toetavad või ümber lükkavad tõendid)',
        ],
        'clone' => [
            'gapped'  => 'Peaaegu-tabamus kloon — kaaluge erineva osa parameetrilisteks muutmist või mõlema koopia ühtlustamist.',
            'demoted' => 'Degradeeritud kui :stratum — see on vorm, mida see kiht kirjeldab, seega ekstraheerige see ainult siis, kui kordus pole eesmärk.',
            'extract' => 'Kaaluge jagatud ridade ekstraheerimist korduskasutatavaks meetodiks, klassiks või traidiks.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Kasutamine:',
            'invocation' => '  phpcpd [valikud] <kataloog>',
        ],
        'group' => [
            'selecting' => 'Failide valimise valikud',
            'orphans'   => 'Orvust sümbolite tuvastamine (surnud kood)',
            'analysing' => 'Failide analüüsimise valikud',
            'general'   => 'Üldised valikud',
            'reporting' => 'Aruannete genereerimise valikud',
            'ci'        => 'CI integreerimise valikud',
        ],
        'option' => [
            'suffix'              => 'Kaasake failid, mille nimed lõpevad sümboliga <suffix> (vaikimisi: :default; korratav)',
            'exclude'             => 'Jätke välja failid, mille tees on <path> (korratav)',
            'preset'              => 'Rakendage raamistiku eelseadistus (nt laravel): seab mõistlikud teed, sufiksid ja välistused',
            'triage'              => 'Käivitage Staadium 0 korpuse triaaž enne tuvastamist (lubatud vaikimisi; nõuab seda sõnaselgelt)',
            'no_triage'           => 'Jätke Staadium 0 täielikult vahele: ühtegi faili ei märgistata ühendamata, varjutatud, müüja või tuletatud failina',
            'triage_posture'      => 'Mida triaaž teeb failiga, mida see märgistab: viskab skannimisest välja (vaikimisi) või märgistab ega tee midagi muud',
            'no_preset'           => 'Ärge rakendage automaatselt raamistiku eelseadistust selle tuvastamisel (tuvastamine annab endast märku; --preset= kirjutab selle üle)',
            'no_default_excludes' => 'Skannige ka genereeritud ja vahemälu puid (vendor, node_modules, .phpstan.cache, build, ...), mis vaikimisi jäetakse vahele',
            'allow_root_scan'     => 'Lubage skannimise juurkaust / või juurkaust lähima composer.json kohal (keeldutud vaikimisi: `phpcpd /` on peaaegu alati trükiviga `phpcpd ./` jaoks)',
            'orphans'             => 'Tuvastage orvust sümbolid (viitamata klassid, liidesed, traidid, enumid, funktsioonid) kloonide asemel',
            'no_suppress'         => 'Lülitage summutusreeglid nime järgi välja, komadega eraldatud või "all" (:rules)',
            'fail_on'             => 'Tulemuste tasemed, mis panevad käivituse väljuma nullist erineva koodiga, komadega eraldatud (vaikimisi: :default)',
            'explain'             => 'Loetlege iga summutatud sümbol ja reegel, mis selle summutas, lihtsalt loendamise asemel',
            'rk'                  => 'Ainult Rabin-Karp (täpsed/tüüp 1 kloonid; kiirem, ümberjärjestamise tuvastamist pole). Vaikimisi käivitab nii Rabin-Karp kui ka TokenBag.',
            'min_lines'           => 'Identsete ridade miinimumarv (vaikimisi: :default)',
            'min_tokens'          => 'Identsete tokenite miinimumarv (vaikimisi: :default)',
            'language'            => 'Aruande keel (vaikimisi: :default)',
            'verbose'             => 'Trüki iga klooni dubleeritud kood',
            'algorithm'           => 'Ühe algoritmi ülekirjutamine (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag kattumislävi (vaikimisi: :default)',
            'raw'                   => 'Võrdle toorteksti: ka identifikaatorid peavad kokku langema (lülitab vaikimisi normaliseerimise välja)',
            'fuzzy'                 => 'Nimepime normaliseerimine: nagu vaikimisi, kuid ilma tüübiankruta (uurimuslik; E2 mõõtis selle domineerituks)',
            'type_anchored'         => 'Hoia tüübi võtmesõnad normaliseerimisel konkreetsena (vaikimisi sees; --fuzzy lülitab välja)',
            'min_confidence'        => 'Loetle ainult leiud, mille mudel hindab <log-odds> või kõrgemaks; ülejäänud loetakse kokku, on loetavad --hidden abil, neid ei visata kunagi ära ja need mõjutavad endiselt --fail-on',
            'hidden'                => 'Loetle leiud, mille --min-confidence kinni pidas',
            'cache'                 => 'Puhverda tulemused kataloogi \'.phpcpd-cache/\' — tabamus nõuab iga faili muutumatuna, seega teenib see ühe commiti kordusjooksu, mitte järgmist',
            'acknowledged'        => 'Lugege sisse antud tunnustuse pearaamat <file>-st: loetletud dubleerimine degradeeritakse, seda ei peideta kunagi ja kanded, mille kood on muutunud, aeguvad ning neid raporteeritakse',
            'write_acknowledged'  => 'Kirjutage selle käivituse tulemused <file>-sse tunnustuse pearaamatuna ülevaatamiseks ja commit\'imiseks',
            'log_pmd'             => 'Kirjutage logi PMD-CPD XML-vormingus <file>-sse',
            'log_json'            => 'Kirjutage logi JSON-vormingus <file>-sse',
            'log_sarif'           => 'Kirjutage logi SARIF 2.1.0 vormingus <file>-sse (GitHub Code Scanningu jaoks)',
            'cache_dir'           => 'Lugege/kirjutage vahemälu <path>-st (hõlmab --cache; kirjutab üle vaikekataloogi)',
            'incremental'           => 'Failipõhine inkrementaalne indeks: tokeniseerib uuesti ainult muutunud failid (rabin-karp või unified, mitte kombineeritud vaikerežiim; kasutab vahemälukataloogi)',
            'config'              => 'Lugege seadistusi <file>-st (vaikimisi: ./phpcpd.ini, kui see on olemas; võtmed on pikkade valikute nimed)',
            'show_config'         => 'Trüki kehtivad seadistused, kust igaüks neist pärineb, ja välju',
            'no_config'           => 'Eira ./phpcpd.ini',
            'help'                => 'Trüki see abi',
            'version'             => 'Trüki versiooniteave',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next tunnustuse pearaamat',
            'what'  => 'Iga rida registreerib ühe dubleeringu, mida see projekt on vaadelnud ja otsustanud sellega koos elada. Tunnustatud tulemus on DEGRADEERITUD, seda ei peideta kunagi: seda ikka raporteeritakse, ikka loendatakse ja see juhib endiselt väljumiskoodi.',
            'key'   => 'Võti on dubleeringu kummagi külje sisu, räsitud — mitte tee ja mitte reanumber. Seega muudab kummagi koopia muutmine kande aegunuks ja tulemus nõutakse uuesti, samas kui koodi liigutamine ei muuda midagi. Kanne, mis ei vasta enam millelegi, raporteeritakse aegununa, et seda saaks eemaldada.',
            'note'  => 'Tabulaatorile järgnev tekst on inimlik märkus. Sellega ei sobitata kunagi.',
        ],
    ],
];
