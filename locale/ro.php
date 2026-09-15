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
 * Traducere în limba română (Romanian translation).
 */

return [
    'frame' => [
        'error'   => 'EROARE: :message',
        'warning' => 'AVERTISMENT: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'În :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Fișierul de configurare nu a fost găsit: :path',
        ],
        'unparsable' => [
            'config' => 'Eșec la analiza fișierului de configurare: :path',
        ],
        'unknown' => [
            'option'  => 'Opțiune necunoscută :flag.',
            'setting' => 'Setare necunoscută ":name" în :file',
        ],
        'needsValue' => [
            'option'  => 'Opțiunea :flag necesită o valoare.',
            'setting' => 'Setarea ":name" din :file necesită o valoare.',
        ],
        'takesNoValue' => [
            'option' => 'Opțiunea :flag nu acceptă nicio valoare.',
        ],
        'invalidValue' => [
            'option' => 'Valoare nevalidă ":value" pentru opțiunea :flag (permis: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Motorul unitar (unified engine) necesită :flag de cel puțin :floor (furnizat: :given). Sub aceasta, fereastra winnow scade sub 4 și indexul încetează să mai fie un eșantion, astfel încât motorul refuză în loc să se degradeze silențios la o scanare completă.',
        ],
        'unwired' => [
            'option' => 'Opțiunea :flag nu are nicio legătură de setare.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Refuz scanarea rădăcinii sistemului de fișiere (:path). Ați vrut să spuneți "./"?',
            'aboveProject'   => 'Refuz scanarea :path: se află deasupra rădăcinii proiectului :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nu s-au găsit fișiere de scanat.',
            'afterTriage' => 'Nu au rămas fișiere de scanat după triaj.',
        ],
        'missingArgument' => [
            'directory' => 'Nu a fost specificat niciun director.',
        ],
        'writeFailed' => [
            'report' => 'Eșec la scrierea raportului în :path: :detail',
        ],
        'writePartial' => [
            'report' => 'S-au scris doar din (:written) totalul de bytes (:total) ai raportului în :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'preconfigurarea ":preset" specifică căi de scanare (:declared), lipsesc (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => 'Opțiunea --orphans nu poate vedea întregul proiect.',
            'belowRoots'     => '  Fiecare rădăcină de scanare se află sub rădăcinile de autoload ale :manifest.',
            'uncoveredRoots' => '  căi de autoload declarate în :manifest dar care nu au fost niciodată deschise (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detectat — preconfigurare aplicată (--no-preset pentru dezactivare)',
        ],
        'cache' => [
            'hit' => '(hit cache)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag este depreciat; folosiți --algorithm=unified — care încă nu raportează fiecare locație raportată de token bag, așa că rămâne opțional)',
        ],
        'incremental' => [
            'combined'    => '(--incremental a fost ignorat în modul combinat)',
            'unsupported' => '(--incremental a fost ignorat: doar algoritmii rabin-karp și unified au index incremental)',
            'index'       => '(index incremental: :reused reutilizate, :scanned scanate)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nu s-au găsit clone de cod.',
            'heading'        => 'S-au găsit clone de cod (:clones):gapped:reordered, linii duplicate (:lines), fișiere (:files):',
            'gapped'         => ', inconsecvente (:count)',
            'reorderedCount' => ', reordonate (:count)',
            'reordered'      => '[reordonat]',
            'unreadable'     => 'fișiere necitibile (:count) — în niciun total:',
            'strataAsserted' => 'Revendicate :asserted, 0 retrogradate.',
            'strataSplit'    => 'Revendicate :asserted, retrogradate :demoted (:detail).',
            'settled'         => 'citiri eliminate ca fiind deja descrise (:count).',
            'unfounded'      => 'rezultate eliminate ca neverificate (:count).',
            'hiddenLine'      => ':count din :total rezultate ascunse sub încrederea :threshold — --hidden le listează.',
            'hiddenHeading'   => 'Ascunse sub încrederea :threshold (:count):',
            'coverage'         => ':percentage din liniile scanate (:lines) este cod duplicat.',
            'literals'         => '[literalii diferă (:count)]',
            'confidence'       => 'încredere :score (:terms)',
            'functions'        => 'în :names',
            'sizes'          => 'Linii per clonă: medie (:average), cea mai mare (:largest).',
        ],
        'ledger' => [
            'line'      => 'Recunoscute: din (:acknowledged) descoperiri (:total) au fost retrogradate de registrul general; depășite (:stale).',
            'staleNote' => 'depășită (codul pe care l-a recunoscut s-a schimbat): :note',
            'wrote'     => 'înregistrări de recunoaștere scrise (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (lipsește)',
            'source' => [
                'default'     => 'implicit',
                'commandLine' => 'linie de comandă',
                'builtIn'     => '    valori implicite încorporate',
            ],
            'fallback'    => ' revenire la valoarea implicită încorporată',
            'layers'      => '  Straturi, prioritate mai mică prima:',
        ],
        'run' => [
            'throughput' => ' — fișiere (:count) la :rate/s',
            'files'    => ' — fișiere (:count)',
            'usage'  => 'Timp: :duration, Memorie: :memory MB',
            'banner' => 'phpcpd :version de :author — după :origin de :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Rădăcină scanare: :root',
            'roots'              => 'Rădăcini de scanare:',
            'count' => [
                'file'       => 'fișiere (:count)',
                'directory'  => 'rădăcini (:count)',
                'pattern'    => 'excluderi (:count)',
                'unreadable' => 'necitibile (:count)',
                'generated'  => 'generate (:count)',
            ],
            'counts'             => 'Scanate :counts',
        ],
        'triage' => [
            'nothing'  => 'Triaj: nimic marcat; fiecare fișier este text program.',
            'removed'  => 'Triaj: fișiere (:total), eliminate (:removed)',
            'labelled' => 'Triaj: fișiere (:total), marcate (:labelled), nimic eliminat',
        ],
        'orphan' => [
            'none'         => 'Nu s-au găsit simboluri orfane (simboluri (:symbols) în fișiere (:files)).',
            'found'        => 'simboluri orfane (:count):',
            'possible'     => 'posibil orfane (:count) — verificați înainte de eliminare:',
            'advisory'     => 'simboluri orfane (:count) — orientative, nu afectează codul de ieșire:',
            'notShown'     => 'descoperiri orfane suplimentare nu sunt afișate (:count) — rulați --orphans pentru verificare.',
            'suppressed'   => 'Suprimate (:count): :census',
            'explainHint'  => '  → --explain pentru a le afișa',
            'wholeFile'    => '    ⤷ întregul fișier este neconectat — niciun simbol declarat aici nu este referit',
            'supersededBy' => '    ⤷ seamănă cu o copie înlocuită a :name',
            'summary'      => 'Scanate simboluri (:symbols) în fișiere (:files); orfane (:orphaned), posibile (:possible), suprimate (:suppressed), planificate (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'în afara setului de fișiere lăsat de excluderile implicite ale acestui proiect — un arbore generat care poate să nu ateste conectarea',
            'declaredHere'  => 'simboluri declarate aici (:count), niciunul dintre ele nefiind referit nicăieri în proiect',
            'foreignNs'     => 'declară :namespaces — un namespace pe care niciun composer.json de deasupra nu îl declară, în afara oricărui director pe care îl leagă',
        ],
        'role' => [
            'noStatements' => 'nicio declarație de nivel superior după introducere',
            'coupled'      => ':registrations din :statements declarații de nivel superior sunt expresii de înregistrare, dar împart o variabilă',
            'independent'  => ':registrations din :statements declarații de nivel superior sunt expresii de înregistrare independente de fluxul de date',
        ],
        'orphan' => [
            'guard'          => 'declarat în interiorul unei protecții de existență — polyfill sau punte de compatibilitate',
            'entrypoint'     => 'declarat în :namespace — apelat prin convenția framework-ului',
            'partialProject' => '  Un simbol este considerat mort atunci când *nimic* nu îl referă, ceea ce reprezintă o afirmație despre
  întregul proiect. Codul din afara acestei scanări se poate referi în continuare la ceea ce este raportat aici.',
            'evidence' => [
                'nameAt'   => 'numele apare în',
                'loopAt'   => 'detectat de buclă în',
                'suffixAt' => 'sufix declarat în',
                'namedIn'  => 'numit în',
            ],
            'plannedServed'  => 'referit acum — @phpcpd-planned și-a îndeplinit scopul și poate fi eliminat',
            'manifest'       => 'declarat într-un punct de intrare composer autoload.files',
            'foreignNs'      => 'declarat în afara propriilor namespace-uri ale proiectului (punte de compatibilitate)',
            'fixture'        => 'fixture de test — încărcat prin cale sau numit ca șir, niciodată referit',
            'discovery'      => 'descoperit prin scanarea muchiilor — instanțiat din numele său de fișier în spatele class_exists',
            'convention'     => 'clasă însoțitoare — :base folosește trait-ul :trait, care rezolvă acest nume cu un sufix la rulare',
            'interface'      => 'niciodată referit (interfață — poate fi implementată în afara setului scanat)',
            'trait'          => 'niciodată referit (trait — poate fi utilizat de clase din afara setului scanat)',
            'abstract'       => 'niciodată referit (abstractă — poate fi extinsă în afara setului scanat)',
            'inString'       => 'niciodată referit în cod; numele apare într-un literal șir (utilizare dinamică posibilă)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Declarat condiționat (polyfill / punte de compatibilitate)',
            'fixtures'    => 'Fixture-uri de test (încărcate după cale sau nume)',
            'config'      => 'Înregistrat într-un fișier de configurare',
            'template'    => 'Referit dintr-un șablon (blade / twig / latte)',
            'manifest'    => 'Referit din composer.json',
            'namespace'   => 'Declarat în afara propriilor namespace-uri ale proiectului (punte de compatibilitate)',
            'keep'        => 'Marcat pentru păstrare (@api / @phpcpd-keep)',
            'entrypoint'  => 'Puncte de intrare ale framework-ului (atribut / clasă de test)',
            'discovery'   => 'Descoperit prin scanarea muchiilor (instanțiat din numele său de fișier)',
            'convention'  => 'Clasă însoțitoare numită prin convenție (sufix declarat de trait)',
            'planned'     => 'Planificat, neconectat încă',
            'none'        => 'Nicio referință găsită',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Adăugați --allow-root-scan dacă ați intenționat într-adevăr întregul sistem de fișiere.',
            'allowOutside' => 'Adăugați --allow-root-scan pentru a scana în afara proiectului oricum.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Scanați rădăcina proiectului pentru un rezultat asupra căruia merită să acționați.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard acționează pe baza etichetelor; --no-triage omite faza)',
            'explain' => '  (--explain listează fiecare fișier și dovezile pro sau contra acestuia)',
        ],
        'clone' => [
            'gapped'  => 'Clonă de potrivire parțială — luați în considerare parametrizarea porțiunii divergente sau alinierea ambelor copii.',
            'demoted' => 'Retrogradat ca :stratum — aceasta este forma pe care o descrie acest strat, așa că eliminați-l doar dacă repetarea nu este scopul.',
            'extract' => 'Luați în considerare extragerea liniilor comune într-o metodă, clasă sau trait reutilizabil.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Utilizare:',
            'invocation' => '  phpcpd [options] <directory>',
        ],
        'group' => [
            'selecting' => 'Opțiuni de selecție a fișierelor',
            'orphans'   => 'Identificarea simbolurilor orfane (cod mort)',
            'analysing' => 'Opțiuni de analiză a fișierelor',
            'general'   => 'Opțiuni generale',
            'reporting' => 'Opțiuni de generare a rapoartelor',
            'ci'        => 'Opțiuni de integrare CI',
        ],
        'option' => [
            'suffix'              => 'Include fișierele ale căror nume se termină în <suffix> (implicit: :default; repetabil)',
            'exclude'             => 'Exclude fișierele care conțin <path> în calea lor (repetabil)',
            'preset'              => 'Aplică preconfigurarea framework-ului (de ex. laravel): setează căi rezonabile, sufixe și excluderi',
            'triage'              => 'Rulează triajul corpusului din faza 0 înainte de detectare (activat implicit; o cere în mod explicit)',
            'no_triage'           => 'Omite faza 0 în întregime: niciun fișier nu este etichetat ca neconectat, umbrit, furnizor sau derivat',
            'triage_posture'      => 'Ce face triajul cu un fișier pe care îl etichetează: îl elimină din scanare (implicit) sau îl etichetează și nu face altceva',
            'no_preset'           => 'Nu aplica automat preconfigurarea framework-ului când este detectată (detecția anunță singură; --preset= suprascrie acest lucru)',
            'no_default_excludes' => 'Scanează și arborii generați și de cache (vendor, node_modules, .phpstan.cache, build, ...), care sunt omiși în mod implicit',
            'allow_root_scan'     => 'Permite rădăcina de scanare la / sau rădăcina de deasupra celui mai apropiat composer.json (interzis implicit: `phpcpd /` este aproape întotdeauna o greșeală de tastare pentru `phpcpd ./`)',
            'orphans'             => 'Detectează simboluri orfane (clase, interfețe, traits, enums, funcții neasociate) în loc de clone',
            'no_suppress'         => 'Dezactivează regulile de supresie după nume, separate prin virgulă, sau "all" (:rules)',
            'fail_on'             => 'Niveluri de rezultat care determină încheierea execuției cu un cod diferit de zero, separate prin virgulă (implicit: :default)',
            'explain'             => 'Listează fiecare simbol suprimat și regula care l-a suprimat, în loc de o simplă numărătoare',
            'rk'                  => 'Doar Rabin-Karp (clone exacte/tip 1; mai rapid, fără detectarea reordonării). Execuția implicită rulează atât Rabin-Karp, cât și TokenBag.',
            'min_lines'           => 'Număr minim de linii identice (implicit: :default)',
            'min_tokens'          => 'Număr minim de tokeni identici (implicit: :default)',
            'language'            => 'Limba raportului (implicit: :default)',
            'verbose'             => 'Imprimă codul duplicat pentru fiecare clonă',
            'algorithm'           => 'Suprascrie algoritmul unic (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Prag de suprapunere TokenBag (implicit: :default)',
            'raw'                   => 'Compară textul brut: și identificatorii trebuie să coincidă (dezactivează normalizarea implicită)',
            'fuzzy'                 => 'Normalizare oarbă la nume: ca cea implicită, dar fără ancorarea tipurilor (cercetare; E2 a măsurat-o ca dominată)',
            'type_anchored'         => 'Păstrează concrete cuvintele-cheie de tip la normalizare (activ implicit; --fuzzy îl dezactivează)',
            'min_confidence'        => 'Listează doar rezultatele pe care modelul le notează la <log-odds> sau peste; restul sunt numărate, lizibile cu --hidden, niciodată eliminate și continuă să determine --fail-on',
            'hidden'                => 'Listează rezultatele reținute de --min-confidence',
            'cache'                 => 'Pune rezultatele în cache în \'.phpcpd-cache/\' — o potrivire cere fiecare fișier neschimbat, deci servește reluarea unui commit, nu următorul',
            'acknowledged'        => 'Citește registrul general de recunoaștere din <file>: duplicarea listată este retrogradată, nu este ascunsă niciodată, iar înregistrările al căror cod s-a schimbat expiră și sunt raportate',
            'write_acknowledged'  => 'Scrie descoperirile acestei execuții în <file> ca registru general de recunoaștere, pentru revizuire și commit',
            'log_pmd'             => 'Scrie jurnalul în format PMD-CPD XML în <file>',
            'log_json'            => 'Scrie jurnalul în format JSON în <file>',
            'log_sarif'           => 'Scrie jurnalul în format SARIF 2.1.0 în <file> (pentru GitHub Code Scanning)',
            'cache_dir'           => 'Citește/scrie cache-ul din <path> (implică --cache; suprascrie directorul implicit)',
            'incremental'           => 'Index incremental per fișier: retokenizează doar fișierele modificate (rabin-karp sau unified, nu modul implicit combinat; folosește directorul cache)',
            'config'              => 'Citește setările din <file> (implicit: ./phpcpd.ini dacă există; cheile sunt numele lungi ale opțiunilor)',
            'show_config'         => 'Imprimă setările valide, de unde provine fiecare și ieșire',
            'no_config'           => 'Ignoră ./phpcpd.ini',
            'help'                => 'Imprimă acest ajutor',
            'version'             => 'Imprimă informații despre versiune',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'registrul general de recunoaștere phpcpd-next',
            'what'  => 'Fiecare rând înregistrează o duplicare pe care acest proiect a examinat-o și a decis să o accepte. O descoperire recunoscută este RETROGRADATĂ, nu este ascunsă niciodată: este totuși raportată, este totuși numărată și totuși controlează codul de ieșire.',
            'key'   => 'Cheia este conținutul fiecărei părți a duplicării, hash-uit — nici calea, nici numărul de rând. Prin urmare, editarea oricărei copii face ca înregistrarea să fie depășită, iar descoperirea este solicitată din nou, în timp ce mutarea codului nu schimbă nimic. O înregistrare care nu se mai potrivește cu nimic este raportată ca fiind depășită, astfel încât să poată fi eliminată.',
            'note'  => 'Textul de după tab este o notă umană. Nu se face niciodată o potrivire cu acesta.',
        ],
    ],
];
