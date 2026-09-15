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
 * Magyar fordítás (Hungarian translation).
 */

return [
    'frame' => [
        'error'   => 'HIBA: :message',
        'warning' => 'FIGYELMEZTETÉS: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'A(z) :file fájlban: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'A konfigurációs fájl nem található: :path',
        ],
        'unparsable' => [
            'config' => 'A konfigurációs fájl elemzése nem sikerült: :path',
        ],
        'unknown' => [
            'option'  => 'Ismeretlen opció: :flag.',
            'setting' => 'Ismeretlen beállítás ":name" a(z) :file fájlban',
        ],
        'needsValue' => [
            'option'  => 'A(z) :flag opció értéket igényel.',
            'setting' => 'A(z) ":name" beállítás a(z) :file fájlban értéket igényel.',
        ],
        'takesNoValue' => [
            'option' => 'A(z) :flag opció nem fogad el értéket.',
        ],
        'invalidValue' => [
            'option' => 'Érvénytelen érték ":value" a(z) :flag opcióhoz (engedélyezett: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Az egységes motor (unified engine) a(z) :flag opcióhoz legalább :floor értéket igényel (megadva: :given). Ez alatt a winnow ablak 4 alá süllyed, és az index megszűnik mintának lenni, így a motor a teljes vizsgálatra való csendes visszalépés helyett elutasítja a futtatást.',
        ],
        'unwired' => [
            'option' => 'A(z) :flag opcióhoz nincs beállítás-összerendelés.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'A fájlrendszer gyökerének skannelése elutasítva (:path). A "./"-re gondoltál?',
            'aboveProject'   => 'A(z) :path skannelése elutasítva: ez a projekt gyökere (:project) felett van.',
        ],
        'nothingToScan' => [
            'files'       => 'Nem találtam skannelhető fájlokat.',
            'afterTriage' => 'A triázs után nem maradt skannelhető fájl.',
        ],
        'missingArgument' => [
            'directory' => 'Nincs megadva könyvtár.',
        ],
        'writeFailed' => [
            'report' => 'Nem sikerült a jelentést ide írni: :path: :detail',
        ],
        'writePartial' => [
            'report' => 'A jelentésből csak bájtot (:written) írtam ki a(z) bájtból (:total) ide: :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'a(z) ":preset" előbeállítás meghatározza a skannelési útvonalakat (:declared), hiányoznak (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => 'A --orphans nem látja az egész projektet.',
            'belowRoots'     => '  Minden skannelési gyökér a(z) :manifest automatikus betöltési gyökerei alatt van.',
            'uncoveredRoots' => '  automatikus betöltési útvonalak, amelyek deklarálva vannak a(z) :manifest fájlban, but soha nem lettek megnyitva (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset észlelve — előbeállítás alkalmazva (--no-preset a letiltáshoz)',
        ],
        'cache' => [
            'hit' => '(gyorsítótár-találat)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag elavult; használja a --algorithm=unified kapcsolót — amely még nem jelenti az összes olyan helyet, amit a token bag jelentett, ezért ez választható marad)',
        ],
        'incremental' => [
            'combined'    => '(--incremental kihagyva kombinált módban)',
            'unsupported' => '(--incremental kihagyva: csak a rabin-karp és az unified algoritmusok rendelkeznek inkrementális indexszel)',
            'index'       => '(inkrementális index: :reused újrafelhasznált, :scanned skannelt)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nem találtam kóduplikációt.',
            'heading'        => 'Találat: kóduplikációk (:clones):gapped:reordered, duplikált sorok (:lines), fájlok (:files):',
            'gapped'         => ', hézagos (:count)',
            'reorderedCount' => ', átrendezett (:count)',
            'reordered'      => '[átrendezett]',
            'unreadable'     => 'olvashatatlan fájlok (:count) — egyik végösszegben sem szerepelnek:',
            'strataAsserted' => ':asserted igazolva, 0 lefokozva.',
            'strataSplit'    => ':asserted igazolva, :demoted lefokozva (:detail).',
            'settled'         => 'olvasatok elvetve, mert már le vannak írva (:count).',
            'unfounded'      => 'találatok elvetve, mert nem igazolhatók (:count).',
            'hiddenLine'      => ':total találatból :count elrejtve a :threshold megbízhatóság alatt — a --hidden felsorolja őket.',
            'hiddenHeading'   => 'Elrejtve a :threshold megbízhatóság alatt (:count):',
            'coverage'         => 'A vizsgált sorok (:lines) :percentage része duplikált kód.',
            'literals'         => '[a literálok eltérnek (:count)]',
            'confidence'       => 'megbízhatóság :score (:terms)',
            'functions'        => 'itt: :names',
            'sizes'          => 'Sorok klónonként: átlag (:average), legnagyobb (:largest).',
        ],
        'ledger' => [
            'line'      => 'Elismert: / (:acknowledged) találat (:total) lefokozva a főkönyv által; elavult (:stale).',
            'staleNote' => 'elavult (az általa elismert kód megváltozott): :note',
            'wrote'     => 'elismerések kiírva (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (hiányzik)',
            'source' => [
                'default'     => 'alapértelmezett',
                'commandLine' => 'parancssor',
                'builtIn'     => '    beépített alapértelmezések',
            ],
            'fallback'    => ' visszatérés a beépített alapértelmezéshez',
            'layers'      => '  Rétegek, a legalacsonyabb prioritással kezdve:',
        ],
        'run' => [
            'throughput' => ' — fájlok (:count) :rate/s sebességgel',
            'files'    => ' — fájlok (:count)',
            'usage'  => 'Idő: :duration, Memória: :memory MB',
            'banner' => 'phpcpd :version, készítő: :author — az :origin után, készítő: :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Skannelési gyökér: :root',
            'roots'              => 'Skannelési gyökerek:',
            'count' => [
                'file'       => 'fájlok (:count)',
                'directory'  => 'gyökerek (:count)',
                'pattern'    => 'kizárások (:count)',
                'unreadable' => 'olvashatatlanok (:count)',
                'generated'  => 'generáltak (:count)',
            ],
            'counts'             => 'Skannelve: :counts',
        ],
        'triage' => [
            'nothing'  => 'Triázs: semmi sem lett megjelölve; minden fájl programszöveg.',
            'removed'  => 'Triázs: fájlok (:total), eltávolítva (:removed)',
            'labelled' => 'Triázs: fájlok (:total), megjelölve (:labelled), semmi sem lett eltávolítva',
        ],
        'orphan' => [
            'none'         => 'Nem találtam árva szimbólumot (szimbólum (:symbols) fájlban (:files)).',
            'found'        => 'árva szimbólumok (:count):',
            'possible'     => 'lehetséges árvák (:count) — ellenőrizze eltávolítás előtt:',
            'advisory'     => 'árva szimbólumok (:count) — tanácsadó jellegűek, nem befolyásolják a kilépési kódot:',
            'notShown'     => 'további árva találatok nem jelennek meg (:count) — futtassa a --orphans kapcsolót az ellenőrzéshez.',
            'suppressed'   => 'Elnyomva (:count): :census',
            'explainHint'  => '  → --explain a listázásukhoz',
            'wholeFile'    => '    ⤷ az egész fájl összeköttetés nélküli — az itt deklarált szimbólumokra semmi sem hivatkozik',
            'supersededBy' => '    ⤷ úgy néz ki, mint a(z) :name helyettesített másolata',
            'summary'      => 'Skannelve szimbólum (:symbols) fájlban (:files); árva (:orphaned), lehetséges (:possible), elnyomott (:suppressed), tervezett (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'kívül esik azon a fájlhalmazon, amelyet a projekt saját alapértelmezett kizárásai hagynak — generált fa, amely nem feltétlenül bizonyítja a vezetékezést',
            'declaredHere'  => 'az itt deklarált szimbólumok (:count), amelyekre senki sem hivatkozik a projektben',
            'foreignNs'     => ':namespaces névteret deklarál — olyan névteret, amelyet egyetlen fenti composer.json sem deklarál, az általuk összekötött könyvtárakon kívül',
        ],
        'role' => [
            'noStatements' => 'nincsenek felső szintű utasítások az előszó után',
            'coupled'      => 'a felső szintű utasítások közül :registrations / :statements regisztrációs kifejezés, but osztoznak egy változón',
            'independent'  => 'a felső szintű utasítások közül :registrations / :statements adatfolyamtól független regisztrációs kifejezés',
        ],
        'orphan' => [
            'guard'          => 'létvédelmi korlátn belül deklarálva — polyfill vagy kompatibilitási híd',
            'entrypoint'     => 'a(z) :namespace névterben deklarálva — a keretrendszer konvenciója hívja meg',
            'partialProject' => '  Egy szimbólumot akkor nevezünk halottnak, amikor *semmi* sem hivatkozik rá, ami egy állítás az
  egész projektről. A skannelésen kívüli kód még hivatkozhat arra, ami itt jelentve van.',
            'evidence' => [
                'nameAt'   => 'a név megjelenik itt:',
                'loopAt'   => 'a ciklus által észlelve itt:',
                'suffixAt' => 'utótag deklarálva itt:',
                'namedIn'  => 'elnevezve itt:',
            ],
            'plannedServed'  => 'most már hivatkoznak rá — a @phpcpd-planned betöltötte a szerepét, és eltávolítható',
            'manifest'       => 'egy composer autoload.files belépési pontban deklarálva',
            'foreignNs'      => 'a projekt saját névterein kívül deklarálva (kompatibilitási híd)',
            'fixture'        => 'teszt-fixture — útvonalon keresztül betöltve vagy sztringként elnevezve, soha nem hivatkoztak rá',
            'discovery'      => 'könyvtárszűréssel felfedezve — fájlnevéből példányosítva a class_exists mögött',
            'convention'     => 'társosztály — a :base a :trait traitet használja, amely futásidőben utótaggal oldja fel ezt a nevet',
            'interface'      => 'soha nem hivatkoztak rá (interfész — lehet, hogy a skannelt halmazon kívül implementálják)',
            'trait'          => 'soha nem hivatkoztak rá (trait — lehet, hogy a skannelt halmazon kívül eső osztályok használják)',
            'abstract'       => 'soha nem hivatkoztak rá (absztrakt — lehet, hogy a skannelt halmazon kívül kiterjesztik)',
            'inString'       => 'soha nem hivatkoztak rá a kódban; a név sztringliterálban szerepel (lehetséges dinamikus használat)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Feltételesen deklarálva (polyfill / kompatibilitási híd)',
            'fixtures'    => 'Teszt-fixture-k (útvonal vagy név alapján betöltve)',
            'config'      => 'Konfigurációs fájlban regisztrálva',
            'template'    => 'Sablonból hivatkozva (blade / twig / latte)',
            'manifest'    => 'A composer.json fájlból hivatkozva',
            'namespace'   => 'A projekt saját névterein kívül deklarálva (kompatibilitási híd)',
            'keep'        => 'Megőrzésre megjelölve (@api / @phpcpd-keep)',
            'entrypoint'  => 'Keretrendszer belépési pontjai (attribútum / tesztosztály)',
            'discovery'   => 'Könyvtárszűréssel felfedezve (fájlnevéből példányosítva)',
            'convention'  => 'Konvenció szerint elnevezett társosztály (trait által deklarált utótag)',
            'planned'     => 'Tervezett, még nincs összekötve',
            'none'        => 'Nem található hivatkozás',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Add meg a --allow-root-scan kapcsolót, ha valóban az egész fájlrendszert akartad.',
            'allowOutside' => 'Add meg a --allow-root-scan kapcsolót a projekten kívüli skanneléshez mindenesetre.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skanneld a projekt gyökerét egy olyan eredményért, amellyel érdemes dolgozni.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard a címkék alapján cselekszik; --no-triage kihagyja a fázist)',
            'explain' => '  (--explain listázza az összes fájlt és a mellette vagy ellene szóló bizonyítékokat)',
        ],
        'clone' => [
            'gapped'  => 'Közel-találat klón — fontold meg az eltérő rész parametrizálását vagy a két másolat egységesítését.',
            'demoted' => ':stratum néven lefokozva — ez az a forma, amelyet az adott réteg leír, úgyhogy csak akkor távolítsd el, ha az ismétlés nem a cél.',
            'extract' => 'Fontold meg a megosztott sorok kinyerését egy újrafelhasználható metódussá, osztállyá vagy traitté.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Használat:',
            'invocation' => '  phpcpd [opciók] <könyvtár>',
        ],
        'group' => [
            'selecting' => 'Fájlválasztási opciók',
            'orphans'   => 'Árva szimbólumok azonosítása (halott kód)',
            'analysing' => 'Fájlelemzési opciók',
            'general'   => 'Általános opciók',
            'reporting' => 'Jelentéskészítési opciók',
            'ci'        => 'CI-integrációs opciók',
        ],
        'option' => [
            'suffix'              => 'Fájlok belefoglalása, amelyek neve <suffix> utótagra végződik (alapértelmezett: :default; ismételhető)',
            'exclude'             => 'Fájlok kizárása, amelyek útvonala tartalmazza a következőt: <path> (ismételhető)',
            'preset'              => 'Keretrendszer-előbeállítás alkalmazása (pl. laravel): beállítja az ésszerű útvonalakat, utótagokat és kizárásokat',
            'triage'              => 'A 0. fázisú korpustriázs futtatása az észlelés előtt (alapértelmezés szerint bekapcsolva; ez kifejezetten kéri)',
            'no_triage'           => 'A 0. fázis teljes kihagyása: egyetlen fájl sem lesz megjelölve összeköttetés nélkülinek, árnyékoltnak, szállítói vagy származtatott fájlnak',
            'triage_posture'      => 'Mit tegyen a triázs egy általa megjelölt fájllal: eldobja a skannelésből (alapértelmezett), vagy megjelöli és semmi mást nem csinál',
            'no_preset'           => 'Ne alkalmazza automatikusan a keretrendszer-előbeállítást, ha észlelésre kerül (az észlelés jelzi magát; a --preset= felülbírálja ezt)',
            'no_default_excludes' => 'A generált és gyorsítótár-fák skannelése is (vendor, node_modules, .phpstan.cache, build, ...), amelyek alapértelmezés szerint ki vannak hagyva',
            'allow_root_scan'     => 'Skannelési gyökér engedélyezése a / vagy a legközelebbi composer.json feletti gyökéren (alapértelmezés szerint tiltva: a `phpcpd /` szinte mindig elírás a `phpcpd ./` helyett)',
            'orphans'             => 'Árva szimbólumok észlelése (hivatkozás nélküli osztályok, interfészek, traitek, enumok, függvények) klónok helyett',
            'no_suppress'         => 'Elnyomási szabályok kikapcsolása név szerint, vesszővel elválasztva, vagy "all" (:rules)',
            'fail_on'             => 'Eredményszintek, amelyek miatt a futás nem nulla kóddal lép ki, vesszővel elválasztva (alapértelmezett: :default)',
            'explain'             => 'Minden elnyomott szimbólum és az azt elnyomó szabály listázása a csupán számlálás helyett',
            'rk'                  => 'Csak Rabin-Karp (pontos / 1. típusú klónok; gyorsabb, nincs átrendezés-felismerés). Az alapértelmezett futás mind a Rabin-Karp, mind a TokenBag algoritmust lefuttatja.',
            'min_lines'           => 'Azonos sorok minimális száma (alapértelmezett: :default)',
            'min_tokens'          => 'Azonos tokenek minimális száma (alapértelmezett: :default)',
            'language'            => 'A jelentés nyelve (alapértelmezett: :default)',
            'verbose'             => 'A duplikált kód nyomtatása minden klónhoz',
            'algorithm'           => 'Egyetlen algoritmus felülbírálása (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag átfedési küszöb (alapértelmezett: :default)',
            'raw'                   => 'Nyers szöveg összevetése: az azonosítóknak is egyezniük kell (kikapcsolja az alapértelmezett normalizálást)',
            'fuzzy'                 => 'Névvak normalizálás: mint az alapértelmezett, de típushorgony nélkül (kutatás; az E2 dominálnak mérte)',
            'type_anchored'         => 'A típuskulcsszavak konkrétan tartása normalizáláskor (alapértelmezés szerint be; a --fuzzy kikapcsolja)',
            'min_confidence'        => 'Csak azokat a találatokat sorolja fel, amelyeket a modell <log-odds> vagy afölött pontoz; a többi számít, a --hidden megmutatja, sosem vész el, és továbbra is befolyásolja a --fail-on beállítást',
            'hidden'                => 'A --min-confidence által visszatartott találatok felsorolása',
            'cache'                 => 'Eredmények gyorsítótárazása a \'.phpcpd-cache/\' könyvtárba — a találat minden fájl változatlanságát igényli, így egy commit újrafuttatását szolgálja, nem a következőt',
            'acknowledged'        => 'Lehívott elismerési főkönyv olvasása a <file> fájlból: a felsorolt duplikáció le lesz fokozva, soha nem lesz elrejtve, és azok a bejegyzések, amelyek kódja megváltozott, elavulnak és jelentésre kerülnek',
            'write_acknowledged'  => 'Ezen futás találatainak kiírása a <file> fájlba elismerési főkönyvként, ellenőrzéshez és commitoláshoz',
            'log_pmd'             => 'Napló írása PMD-CPD XML formátumban a <file> fájlba',
            'log_json'            => 'Napló írása JSON formátumban a <file> fájlba',
            'log_sarif'           => 'Napló írása SARIF 2.1.0 formátumban a <file> fájlba (GitHub Code Scanninghez)',
            'cache_dir'           => 'Gyorsítótár olvasása/írása a <path> útvonalról (implikálja a --cache kapcsolót; felülbírálja az alapértelmezett mappát)',
            'incremental'           => 'Fájlonkénti növekményes index: csak a módosult fájlokat tokenizálja újra (rabin-karp vagy unified, nem a kombinált alapértelmezés; a gyorsítótár könyvtárát használja)',
            'config'              => 'Beállítások olvasása a <file> fájrból (alapértelmezett: ./phpcpd.ini, ha létezik; a kulcsok a hosszú opciónevek)',
            'show_config'         => 'Az érvényes beállítások, azok forrásának kiírása, majd kilépés',
            'no_config'           => 'A ./phpcpd.ini figyelmen kívül hagyása',
            'help'                => 'Ezen súgó nyomtatása',
            'version'             => 'Verzióinformációk nyomtatása',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next elismerési főkönyv',
            'what'  => 'Minden egyes sor rögzít egy olyan duplikációt, amelyet ez a projekt átvizsgált, és úgy döntött, hogy együtt él vele. Az elismert találat LEFOKOZOTT, soha nincs elrejtve: továbbra is jelentésre kerül, továbbra is bekerül a számlálásba, és továbbra is irányítja a kilépési kódot.',
            'key'   => 'A kulcs a duplikáció mindkét oldalának hash-elt tartalma — nem útvonal és nem sorszám. Így bármelyik másolat szerkesztése elavulttá teszi a bejegyzést, és a találatot újra követelni fogja, míg a kód áthelyezése semmit sem változtat. Az a bejegyzés, amely már semminek sem felel meg, elavultként lesz jelentve, hogy eltávolítható legyen.',
            'note'  => 'A tabulátor utáni szöveg egy emberi megjegyzés. A rendszer soha nem hasonlítja össze vele a kódot.',
        ],
    ],
];
