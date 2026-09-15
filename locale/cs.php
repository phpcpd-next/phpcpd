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
 * Český překlad (Czech translation).
 */

return [
    'frame' => [
        'error'   => 'CHYBA: :message',
        'warning' => 'VAROVÁNÍ: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'V souboru :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfigurační soubor nebyl nalezen: :path',
        ],
        'unparsable' => [
            'config' => 'Konfigurační soubor se nepodařilo zpracovat: :path',
        ],
        'unknown' => [
            'option'  => 'Neznámá volba :flag.',
            'setting' => 'Neznámé nastavení ":name" v :file',
        ],
        'needsValue' => [
            'option'  => 'Volba :flag vyžaduje hodnotu.',
            'setting' => 'Nastavení ":name" v :file vyžaduje hodnotu.',
        ],
        'takesNoValue' => [
            'option' => 'Volba :flag nepřijímá žádnou hodnotu.',
        ],
        'invalidValue' => [
            'option' => 'Neplatná hodnota ":value" pro volbu :flag (povoleno: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Jednotný motor (unified engine) vyžaduje volbu :flag alespoň :floor (zadáno: :given). Pod tuto hodnotu klesá okno winnow pod 4 a index přestává být vzorkem, takže motor odmítá spustit místo tichého degradování na úplné skenování.',
        ],
        'unwired' => [
            'option' => 'Volba :flag nemá vazbu na nastavení.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Odmítá skenovat kořen souborového systému (:path). Mysleli jste "./"?',
            'aboveProject'   => 'Odmítá skenovat :path: je nad kořenem projektu :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nebyly nalezeny žádné soubory ke skenování.',
            'afterTriage' => 'Po triáži nezůstaly žádné soubory ke skenování.',
        ],
        'missingArgument' => [
            'directory' => 'Nebyl specifikován žádný adresář.',
        ],
        'writeFailed' => [
            'report' => 'Nepodařilo se zapsat zprávu do :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Zapsáno pouze z (:written) celkových bajtů (:total) zprávy do :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'přednastavení ":preset" určuje cesty skenování (:declared), chybějící (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans nemůže vidět celý projekt.',
            'belowRoots'     => '  Každý kořen skenování je pod kořeny automatického načítání pro :manifest.',
            'uncoveredRoots' => '  cesty automatického načítání deklarované v :manifest, které nebyly nikdy otevřeny (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detekován — přednastavení aplikováno (--no-preset pro vypnutí)',
        ],
        'cache' => [
            'hit' => '(vyrovnávací paměť - hit)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag je zastaralý; použijte --algorithm=unified — který zatím nehlásí každé místo, které hlásí token bag, takže zůstává volitelný)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignorováno v kombinovaném režimu)',
            'unsupported' => '(--incremental ignorováno: pouze algoritmy rabin-karp a unified mají přírůstkový index)',
            'index'       => '(přírůstkový index: :reused znovu použito, :scanned skenováno)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nebyly nalezeny žádné klony kódu.',
            'heading'        => 'Nalezeno klonů kódu (:clones):gapped:reordered, duplicitních řádků (:lines), souborů (:files):',
            'gapped'         => ', nekonzistentních (:count)',
            'reorderedCount' => ', přeuspořádané (:count)',
            'reordered'      => '[přeuspořádáno]',
            'unreadable'     => 'nečitelné soubory (:count) — v žádném součtu:',
            'strataAsserted' => 'Nárokováno :asserted, 0 degradováno.',
            'strataSplit'    => 'Nárokováno :asserted, degradováno :demoted (:detail).',
            'settled'         => 'čtení zahozena, protože už jsou popsána (:count).',
            'unfounded'      => 'nálezy zahozeny jako neověřené (:count).',
            'hiddenLine'      => ':count z :total nálezů skryto pod spolehlivostí :threshold — --hidden je vypíše.',
            'hiddenHeading'   => 'Skryto pod spolehlivostí :threshold (:count):',
            'coverage'         => ':percentage prohledaných řádků (:lines) je duplicitní kód.',
            'literals'         => '[literály se liší (:count)]',
            'confidence'       => 'jistota :score (:terms)',
            'functions'        => 'v :names',
            'sizes'          => 'Řádků na klon: průměr (:average), nejvíce (:largest).',
        ],
        'ledger' => [
            'line'      => 'Uznáno: z (:acknowledged) nálezů (:total) degradováno hlavní knihou; zastaralé (:stale).',
            'staleNote' => 'zastaralé (kód, který uznal, se změnil): :note',
            'wrote'     => 'uznání zapsána (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (chybí)',
            'source' => [
                'default'     => 'výchozí',
                'commandLine' => 'příkazový řádek',
                'builtIn'     => '    vestavěné výchozí hodnoty',
            ],
            'fallback'    => ' vrací se k vestavěné výchozí hodnotě',
            'layers'      => '  Vrstvy, nejnižší priorita první:',
        ],
        'run' => [
            'throughput' => ' — soubory (:count) rychlostí :rate/s',
            'files'    => ' — soubory (:count)',
            'usage'  => 'Čas: :duration, Paměť: :memory MB',
            'banner' => 'phpcpd :version od :author — po :origin od :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Kořen skenování: :root',
            'roots'              => 'Kořeny skenování:',
            'count' => [
                'file'       => 'soubory (:count)',
                'directory'  => 'kořeny (:count)',
                'pattern'    => 'vyloučení (:count)',
                'unreadable' => 'nečitelné (:count)',
                'generated'  => 'generované (:count)',
            ],
            'counts'             => 'Skenováno :counts',
        ],
        'triage' => [
            'nothing'  => 'Triáž: nic označeno; každý soubor je programový text.',
            'removed'  => 'Triáž: souborů (:total), odstraněno (:removed)',
            'labelled' => 'Triáž: souborů (:total), označeno (:labelled), nic neodstraněno',
        ],
        'orphan' => [
            'none'         => 'Nebyly nalezeny žádné osiřelé symboly (symbolů (:symbols) v souborech (:files)).',
            'found'        => 'osiřelé symboly (:count):',
            'possible'     => 'možné osiřelé (:count) — zkontrolujte před odstraněním:',
            'advisory'     => 'osiřelé symboly (:count) — informativní, neovlivňují návratový kód:',
            'notShown'     => 'další osiřelé nálezy se nezobrazují (:count) — spusťte --orphans pro kontrolu.',
            'suppressed'   => 'Potlačené (:count): :census',
            'explainHint'  => '  → --explain pro jejich zobrazení',
            'wholeFile'    => '    ⤷ celý soubor je nepropojený — žádný symbol zde deklarovaný není odkazován',
            'supersededBy' => '    ⤷ vypadá jako nahrazená kopie :name',
            'summary'      => 'Skenováno symbolů (:symbols) v souborech (:files); osiřelých (:orphaned), možných (:possible), potlačených (:suppressed), plánovaných (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'mimo množinu souborů, kterou opouštějí vlastní výchozí vyloučení tohoto projektu — generovaný strom, který nemusí svědčit o propojení',
            'declaredHere'  => 'symboly deklarované zde (:count), z nichž žádný není odkazován kdekoli v projektu',
            'foreignNs'     => 'deklaruje :namespaces — jmenný prostor, který žádný composer.json nad ním nedeklaruje, mimo každý adresář, který propojují',
        ],
        'role' => [
            'noStatements' => 'žádné příkazy na nejvyšší úrovni po úvodu',
            'coupled'      => ':registrations z :statements příkazů na nejvyšší úrovni jsou registrační výrazy, ale sdílejí proměnnou',
            'independent'  => ':registrations z :statements příkazů na nejvyšší úrovni jsou registrační výrazy nezávislé na datovém toku',
        ],
        'orphan' => [
            'guard'          => 'deklarováno uvnitř ochrany existence — polyfill nebo most kompatibility',
            'entrypoint'     => 'deklarováno v :namespace — voláno konvencí frameworku',
            'partialProject' => '  Symbol se nazývá mrtvý, když ho *nic* neodkazuje, což je tvrzení o
  celém projektu. Kód mimo toto skenování může stále odkazovat na to, co je zde hlášeno.',
            'evidence' => [
                'nameAt'   => 'název se objeví v',
                'loopAt'   => 'zjištěno smyčkou v',
                'suffixAt' => 'přípona deklarována v',
                'namedIn'  => 'pojmenováno v',
            ],
            'plannedServed'  => 'nyní odkazováno — @phpcpd-planned splnilo svůj účel a lze jej odstranit',
            'manifest'       => 'deklarováno ve vstupním bodě composer autoload.files',
            'foreignNs'      => 'deklarováno mimo vlastní jmenné prostory projektu (most kompatibility)',
            'fixture'        => 'testovací fixture — načteno cestou nebo pojmenováno jako řetězec, nikdy neodkazováno',
            'discovery'      => 'zjištěno skenováním okrajů — instancováno ze svého názvu souboru za class_exists',
            'convention'     => 'doprovodná třída — :base používá trait :trait, který řeší tento název s příponou za běhu',
            'interface'      => 'nikdy neodkazováno (rozhraní — může být implementováno mimo skenovanou množinu)',
            'trait'          => 'nikdy neodkazováno (trait — může být používán třídami mimo skenovanou množinu)',
            'abstract'       => 'nikdy neodkazováno (abstraktní — může být rozšiřováno mimo skenovanou množinu)',
            'inString'       => 'nikdy neodkazováno v kódu; název se objeví v řetězcovém literálu (možné dynamické použití)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Podmíněně deklarováno (polyfill / most kompatibility)',
            'fixtures'    => 'Testovací fixture (načtené cestou nebo názvem)',
            'config'      => 'Registrováno v konfiguračním souboru',
            'template'    => 'Odkazováno ze šablony (blade / twig / latte)',
            'manifest'    => 'Odkazováno z composer.json',
            'namespace'   => 'Deklarováno mimo vlastní jmenné prostory projektu (most kompatibility)',
            'keep'        => 'Označeno k zachování (@api / @phpcpd-keep)',
            'entrypoint'  => 'Vstupní body frameworku (atribut / testovací třída)',
            'discovery'   => 'Zjištěno skenováním okrajů (instancováno ze svého názvu souboru)',
            'convention'  => 'Doprovodná třída pojmenovaná podle konvence (přípona deklarovaná traitem)',
            'planned'     => 'Plánováno, zatím nepropojeno',
            'none'        => 'Odkaz nebyl nalezen',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Přidejte --allow-root-scan, pokud jste opravdu mysleli celý souborový systém.',
            'allowOutside' => 'Přidejte --allow-root-scan pro skenování mimo projekt tak či onak.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skenujte kořen projektu pro výsledek, na který stojí za to reagovat.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard jedná podle štítků; --no-triage přeskočí fázi)',
            'explain' => '  (--explain vypíše každý soubor a důkazy pro nebo proti němu)',
        ],
        'clone' => [
            'gapped'  => 'Klon téměř-shody — zvažte parametrizaci odlišné části nebo sjednocení obou kopií.',
            'demoted' => 'Degradováno jako :stratum — toto je forma, kterou tato vrstva popisuje, takže ji odstraňte pouze tehdy, pokud opakování není účelem.',
            'extract' => 'Zvažte vyjmutí sdílených řádků do opakovaně použitelné metody, třídy nebo traitu.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Použití:',
            'invocation' => '  phpcpd [volby] <adresář>',
        ],
        'group' => [
            'selecting' => 'Volby pro výběr souborů',
            'orphans'   => 'Identifikace osiřelých symbolů (mrtvý kód)',
            'analysing' => 'Volby pro analýzu souborů',
            'general'   => 'Obecné volby',
            'reporting' => 'Volby pro generování zpráv',
            'ci'        => 'Volby pro integraci CI',
        ],
        'option' => [
            'suffix'              => 'Zahrnout soubory s názvy končícími na <suffix> (výchozí: :default; opakovatelné)',
            'exclude'             => 'Vyloučit soubory obsahující <path> ve své cestě (opakovatelné)',
            'preset'              => 'Použít přednastavení frameworku (např. laravel): nastavuje rozumné cesty, přípony a vyloučení',
            'triage'              => 'Spustit triáž korpusu fáze 0 před detekcí (zapnuto výchozí; toto to výslovně vyžaduje)',
            'no_triage'           => 'Zcela přeskočit fázi 0: žádný soubor není označen jako nepropojený, zastíněný, dodavatelský nebo odvozený',
            'triage_posture'      => 'Co triáž provede se souborem, který označí: zahodí ho ze skenování (výchozí), nebo ho označí a nic jiného',
            'no_preset'           => 'Neaplikovat automaticky přednastavení frameworku, když je detekováno (detekce to oznámí sama; --preset= toto přepisuje)',
            'no_default_excludes' => 'Skenovat také generované stromy a stromy mezipaměti (vendor, node_modules, .phpstan.cache, build, ...), které se standardně vynechávají',
            'allow_root_scan'     => 'Povolit kořen skenování na / nebo kořen nad nejbližším composer.json (standardně zakázáno: `phpcpd /` je téměř vždy překlep pro `phpcpd ./`)',
            'orphans'             => 'Detekovat osiřelé symboly (neodkazované třídy, rozhraní, traity, enumy, funkce) místo klonů',
            'no_suppress'         => 'Vypnout pravidla potlačení podle názvu, oddělená čárkou, nebo "all" (:rules)',
            'fail_on'             => 'Úrovně výsledků, které způsobí, že běh skončí nenulovým kódem, oddělené čárkou (výchozí: :default)',
            'explain'             => 'Vypsat každý potlačený symbol a pravidlo, které jej potlačilo, namísto pouhého počítání',
            'rk'                  => 'Pouze Rabin-Karp (přesné/typ 1 klony; rychlejší, bez detekce přeuspořádání). Výchozí běh spouští Rabin-Karp i TokenBag.',
            'min_lines'           => 'Minimální počet identických řádků (výchozí: :default)',
            'min_tokens'          => 'Minimální počet identických tokenů (výchozí: :default)',
            'language'            => 'Jazyk zprávy (výchozí: :default)',
            'verbose'             => 'Vypsat duplikovaný kód pro každý klon',
            'algorithm'           => 'Přepsání jednoho algoritmu (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Práh překrytí TokenBag (výchozí: :default)',
            'raw'                   => 'Porovnávat holý text: shodovat se musí i identifikátory (vypne výchozí normalizaci)',
            'fuzzy'                 => 'Normalizace slepá ke jménům: jako výchozí, ale bez ukotvení typů (výzkum; E2 ji naměřila jako dominovanou)',
            'type_anchored'         => 'Ponechat klíčová slova typů konkrétní při normalizaci (výchozí zapnuto; --fuzzy to vypne)',
            'min_confidence'        => 'Vypsat jen nálezy, které model hodnotí na <log-odds> a výše; ostatní se počítají, jsou čitelné s --hidden, nikdy se nezahazují a stále ovlivňují --fail-on',
            'hidden'                => 'Vypsat nálezy, které --min-confidence zadržel',
            'cache'                 => 'Ukládat výsledky do \'.phpcpd-cache/\' — zásah vyžaduje každý soubor beze změny, slouží tedy k opakování jednoho commitu, ne k dalšímu',
            'acknowledged'        => 'Přečíst zaznamenanou hlavní knihu uznání ze souboru <file>: uvedená duplikace je degradována, nikdy se neskrývá a položky, jejichž kód se změnil, vyprší a jsou hlášeny',
            'write_acknowledged'  => 'Zapsat nálezy tohoto běhu do <file> jako hlavní knihu uznání, pro kontrolu a commit',
            'log_pmd'             => 'Zapsat protokol ve formátu PMD-CPD XML do <file>',
            'log_json'            => 'Zapsat protokol ve formátu JSON do <file>',
            'log_sarif'           => 'Zapsat protokol ve formátu SARIF 2.1.0 do <file> (pro GitHub Code Scanning)',
            'cache_dir'           => 'Číst/zapisovat mezipaměť z <path> (implikuje --cache; přepisuje výchozí adresář)',
            'incremental'           => 'Přírůstkový index po souborech: znovu tokenizuje jen změněné soubory (rabin-karp nebo unified, ne kombinovaný výchozí režim; používá adresář cache)',
            'config'              => 'Číst nastavení z <file> (výchozí: ./phpcpd.ini, pokud existuje; klíče jsou dlouhé názvy voleb)',
            'show_config'         => 'Vypsat platná nastavení, odkud každé pochází, a skončit',
            'no_config'           => 'Ignorovat ./phpcpd.ini',
            'help'                => 'Vypsat tuto nápovědu',
            'version'             => 'Vypsat informace o verzi',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'hlavní kniha uznání phpcpd-next',
            'what'  => 'Každý řádek zaznamenává jednu duplikaci, kterou tento projekt zkoumal a rozhodl se s ní žít. Uznaný nález je DEGRADOVANÝ, nikdy se neskrývá: stále se hlásí, stále se počítá a stále řídí návratový kód.',
            'key'   => 'Klíč je obsah každé strany duplikace, zahashovaný — nikoliv cesta a nikoliv číslo řádku. Úprava jakékoliv kopie tedy činí záznam zastaralým a nález je znovu vyžadován, zatímco přesun kódu nemění nic. Záznam, který již ničemu neodpovídá, je hlášen jako zastaralý, aby mohl být odstraněn.',
            'note'  => 'Text za tabulátorem je lidská poznámka. Nikdy se k ní nepřiřazuje.',
        ],
    ],
];
