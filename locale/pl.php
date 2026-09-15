<?php

declare(strict_types=1);
/*
 * Ten plik jest częścią PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Pełne informacje o prawach autorskich i licencji można znaleźć w pliku LICENSE
 * rozpowszechnionym wraz z tym kodem źródłowym.
 */
/*
 * Polska lokalizacja.
 *
 * Klucze, symbole zastępcze, liczniki i to, co generalnie tutaj należy: zobacz
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'BŁĄD: :message',
        'warning' => 'OSTRZEŻENIE: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'W :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Nie znaleziono pliku konfiguracyjnego: :path',
        ],
        'unparsable' => [
            'config' => 'Nie udało się przetworzyć pliku konfiguracyjnego: :path',
        ],
        'unknown' => [
            'option'  => 'Nieznana opcja :flag.',
            'setting' => 'Nieznane ustawienie ":name" w :file',
        ],
        'needsValue' => [
            'option'  => 'Opcja :flag wymaga wartości.',
            'setting' => 'Ustawienie ":name" w :file wymaga wartości.',
        ],
        'takesNoValue' => [
            'option' => 'Opcja :flag nie przyjmuje wartości.',
        ],
        'invalidValue' => [
            'option' => 'Nieprawidłowa wartość ":value" dla :flag (dozwolone: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Zunifikowany silnik (unified engine) potrzebuje :flag wynoszącego co najmniej :floor (podano: :given). Poniżej tej wartości okno winnow spada poniżej 4 i indeks przestaje być próbką, więc silnik odmawia działania zamiast po cichu degradować się do pełnego skanowania.',
        ],
        'unwired' => [
            'option' => 'Opcja :flag nie ma powiązania z ustawieniami.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Odmowa skanowania korzenia systemu plików (:path). Czy chodziło Ci o "./"?',
            'aboveProject'   => 'Odmowa skanowania :path: znajduje się powyżej korzenia projektu :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nie znaleziono plików do przeskanowania.',
            'afterTriage' => 'Brak plików do przeskanowania po triażu.',
        ],
        'missingArgument' => [
            'directory' => 'Nie podano katalogu.',
        ],
        'writeFailed' => [
            'report' => 'Nie udało się zapisać raportu do :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Zapisano tylko z (:written) bajtów (:total) raportu do :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'preset ":preset" deklaruje ścieżki skanowania (:declared), brakuje (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans nie widzi całego projektu.',
            'belowRoots'     => '  Każdy korzeń skanowania znajduje się poniżej korzeni autoload :manifest.',
            'uncoveredRoots' => '  ścieżki autoload zadeklarowane w :manifest, ale nigdy nie otwarte (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => 'Wykryto :preset — zastosowano preset (--no-preset aby wyłączyć)',
        ],
        'cache' => [
            'hit' => '(trafienie pamięci podręcznej)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag jest przestarzałe; użyj --algorithm=unified — który nie zgłasza jeszcze każdej lokalizacji, którą zgłasza tokenbag, więc pozostaje to opcjonalne)',
        ],
        'incremental' => [
            'combined'    => '(--incremental zignorowane w trybie połączonym)',
            'unsupported' => '(--incremental zignorowane: tylko algorytmy rabin-karp i unified posiadają indeks przyrostowy)',
            'index'       => '(indeks przyrostowy: :reused ponowne użycie, :scanned przeskanowane)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nie znaleziono klonów kodu.',
            'heading'        => 'Znaleziono klony kodu (:clones):gapped:reordered, zduplikowane linie (:lines), pliki (:files):',
            'gapped'         => ', niespójnych (:count)',
            'reorderedCount' => ', przestawione (:count)',
            'reordered'      => '[przestawione]',
            'unreadable'     => 'nieczytelne pliki (:count) — w żadnej sumie:',
            'strataAsserted' => 'potwierdzone :asserted, 0 zdegradowanych.',
            'strataSplit'    => 'potwierdzone :asserted, zdegradowane :demoted (:detail).',
            'settled'         => 'odczyty odrzucone jako już opisane (:count).',
            'unfounded'      => 'znaleziska odrzucone jako niezweryfikowane (:count).',
            'hiddenLine'      => ':count z :total znalezisk ukrytych poniżej pewności :threshold — --hidden je wypisze.',
            'hiddenHeading'   => 'Ukryte poniżej pewności :threshold (:count):',
            'coverage'         => ':percentage przeskanowanych wierszy (:lines) to zduplikowany kod.',
            'literals'         => '[literały różnią się (:count)]',
            'confidence'       => 'pewność :score (:terms)',
            'functions'        => 'w :names',
            'sizes'          => 'Linii na klon: średnia (:average), największy (:largest).',
        ],
        'ledger' => [
            'line'      => 'Zaakceptowane: z (:acknowledged) ustaleń (:total) zdegradowanych przez rejestr; przestarzałe (:stale).',
            'staleNote' => 'przestarzałe (kod, który został zaakceptowany, uległ zmianie): :note',
            'wrote'     => 'zapisano akceptacje (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (brakujący)',
            'source' => [
                'default'     => 'domyślne',
                'commandLine' => 'wiersz poleceń',
                'builtIn'     => '    wbudowane domyślne',
            ],
            'fallback'    => ' powrót do wbudowanego domyślnego',
            'layers'      => '  Warstwy, najniższy priorytet jako pierwszy:',
        ],
        'run' => [
            'throughput' => ' — pliki (:count) z prędkością :rate/s',
            'files'    => ' — pliki (:count)',
            'usage'  => 'Czas: :duration, Pamięć: :memory MB',
            'banner' => 'phpcpd :version autorstwa :author — po :origin autorstwa :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Korzeń skanowania: :root',
            'roots'              => 'Korzenie skanowania:',
            'count' => [
                'file'       => 'pliki (:count)',
                'directory'  => 'korzenie (:count)',
                'pattern'    => 'wykluczenia (:count)',
                'unreadable' => 'nieczytelne (:count)',
                'generated'  => 'wygenerowane (:count)',
            ],
            'counts'             => 'Przeskanowano :counts',
        ],
        'triage' => [
            'nothing'  => 'Triaż: nic nie oznaczono; każdy plik jest tekstem programu.',
            'removed'  => 'Triaż: pliki (:total), usunięto (:removed)',
            'labelled' => 'Triaż: pliki (:total), oznaczono (:labelled), nic nie usunięto',
        ],
        'orphan' => [
            'none'         => 'Nie znaleziono osieroconych symboli (symboli (:symbols) w plikach (:files)).',
            'found'        => 'osierocone symbole (:count):',
            'possible'     => 'możliwe osierocone (:count) — sprawdź przed usunięciem:',
            'advisory'     => 'osierocone symbole (:count) — doradcze, nie wpływają na kod wyjścia:',
            'notShown'     => 'dalsze osierocone ustalenia nie są pokazywane (:count) — uruchom --orphans, aby przejrzeć.',
            'suppressed'   => 'Stłumione (:count): :census',
            'explainHint'  => '  → --explain aby je wyświetlić',
            'wholeFile'    => '    ⤷ cały plik jest niepołączony — żaden zadeklarowany tutaj symbol nie jest referencjonowany',
            'supersededBy' => '    ⤷ wygląda jak zastąpiona kopia :name',
            'summary'      => 'Przeskanowano symboli (:symbols) w plikach (:files); osieroconych (:orphaned), możliwych (:possible), stłumionych (:suppressed), zaplanowanych (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'poza zestawem plików, które pozostawiają domyślne wykluczenia tego projektu — wygenerowane drzewo, które może nie świadczyć o okablowaniu',
            'declaredHere'   => 'symbole zadeklarowane tutaj (:count), żaden nie jest referencjonowany nigdzie w projekcie',
            'foreignNs'     => 'deklaruje :namespaces — przestrzeń nazw, której żaden composer.json powyżej nie deklaruje, poza każdym katalogiem, który okablowują',
        ],
        'role' => [
            'noStatements' => 'brak instrukcji najwyższego poziomu po preambule',
            'coupled'      => ':registrations z :statements instrukcji najwyższego poziomu to wyrażenia rejestracji, ale dzielą zmienną',
            'independent'  => ':registrations z :statements instrukcji najwyższego poziomu to wyrażenia rejestracji niezależne od przepływu danych',
        ],
        'orphan' => [
            'guard'          => 'zadeklarowane wewnątrz strażnika istnienia — polyfill lub łatka kompatybilności',
            'entrypoint'     => 'zadeklarowane w :namespace — wywoływane przez konwencję frameworka',
            'partialProject' => '  Symbol jest nazywany martwym, gdy *nic* do niego nie odwołuje się, co jest twierdzeniem o
  całym projekcie. Kod poza tym skanowaniem może nadal odwoływać się do tego, co zostało zgłoszone tutaj.',
            'evidence' => [
                'nameAt'   => 'nazwa pojawia się w',
                'loopAt'   => 'odkryte przez pętlę w',
                'suffixAt' => 'przyrostek zadeklarowany w',
                'namedIn'  => 'nazwane w',
            ],
            'plannedServed'  => 'teraz referencjonowane — @phpcpd-planned spełnił swoje zadanie i można go usunąć',
            'manifest'       => 'zadeklarowane w punkcie wejścia composer autoload.files',
            'foreignNs'      => 'zadeklarowane poza własnymi przestrzeniami nazw projektu (łatka kompatybilności)',
            'fixture'        => 'fiskstura testowa — ładowana przez ścieżkę lub nazwana jako ciąg znaków, nigdy nie referencjonowana',
            'discovery'      => 'odkryte przez skanowanie katalogu — zainstancjonowane z nazwy pliku za class_exists',
            'convention'     => 'klasa towarzysząća — :base używa :trait, która rozwiązuje tę nazwę przez przyrostek w czasie wykonywania',
            'interface'      => 'nigdy nie referencjonowane (interfejs — może być implementowany poza przeskanowanym zestawem)',
            'trait'          => 'nigdy nie referencjonowane (cecha — może być używane przez klasy poza przeskanowanym zestawem)',
            'abstract'       => 'nigdy nie referencjonowane (abstrakcyjna — może być rozszerzana poza przeskanowanym zestawem)',
            'inString'       => 'nigdy nie referencjonowane w kodzie; nazwa pojawia się w literale łańcuchowym (możliwe użycie dynamiczne)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Warunkowo zadeklarowane (polyfill / łatka kompatybilności)',
            'fixtures'    => 'Fiskstury testowe (ładowane przez ścieżkę lub nazwę)',
            'config'      => 'Zarejestrowane w pliku konfiguracyjnym',
            'template'    => 'Referencjonowane z szablonu (blade / twig / latte)',
            'manifest'    => 'Referencjonowane z composer.json',
            'namespace'   => 'Zadeklarowane poza własnymi przestrzeniami nazw projektu (łatka kompatybilności)',
            'keep'        => 'Oznaczone jako zachowane (@api / @phpcpd-keep)',
            'entrypoint'  => 'Punkty wejścia frameworka (atrybut / klasa testowa)',
            'discovery'   => 'Odkryte przez skanowanie katalogu (zainstancjonowane z nazwy pliku)',
            'convention'  => 'Klasa towarzysząća nazwana zgodnie z konwencją (przyrostek zadeklarowany przez trait)',
            'planned'     => 'Zaplanowane, jeszcze niepołączone',
            'none'        => 'Nie znaleziono referencji',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Przekaż --allow-root-scan, jeśli naprawdę miałeś na myśli cały system plików.',
            'allowOutside' => 'Przekaż --allow-root-scan, aby i tak skanować poza projektem.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Przeskanuj korzeń projektu w poszukiwaniu wyniku wartiego podjęcia działania.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard działa na etykietach; --no-triage pomija ten etap)',
            'explain' => '  (--explain wypisuje każdy plik i dowody za lub przeciw niemu)',
        ],
        'clone' => [
            'gapped'  => 'Klon o włos — rozważ sparametryzowanie różniącej się części lub wyrównanie obu kopii.',
            'demoted' => 'Zdegradowane jako :stratum — to jest kształt, który opisuje ta strata, więc wyciągnij ją tylko wtedy, gdy powtórzenie nie jest celem.',
            'extract' => 'Rozważ wyciągnięcie wspólnych linii do metody wielokrotnego użytku, klasy lub traitu.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Użycie:',
            'invocation' => '  phpcpd [opcje] <katalog>',
        ],
        'group' => [
            'selecting' => 'Opcje wyboru plików',
            'orphans'   => 'Wykrywanie osieroconych elementów (martwy kod)',
            'analysing' => 'Opcje analizy plików',
            'general'   => 'Opcje ogólne',
            'reporting' => 'Opcje generowania raportów',
            'ci'        => 'Opcje integracji CI',
        ],
        'option' => [
            'suffix'              => 'Uwzględnij pliki o nazwach kończących się na <suffix> (domyślnie: :default; powtarzalne)',
            'exclude'             => 'Wyklucz pliki z <path> w ścieżce (powtarzalne)',
            'preset'              => 'Zastosuj preset frameworka (np. laravel): ustawia rozsądne ścieżki, przyrostki i wykluczenia',
            'triage'              => 'Uruchom triaż korpusu Etapu 0 przed wykrywaniem (domyślnie włączone; to żąda tego wyraźnie)',
            'no_triage'           => 'Całkowicie pomiń Etap 0: żaden plik nie jest oznaczany jako niepołączony, zacieniony, z zewnętrznego dostawcy lub pochodny',
            'triage_posture'      => 'Co triaż robi z plikiem, który oznaczy: odrzuć go ze skanowania (domyślnie), lub oznacz go i nic więcej',
            'no_preset'           => 'Nie stosuj automatycznie presetu frameworka po wykryciu (wykrywanie samo się ogłasza; --preset= je nadpisuje)',
            'no_default_excludes' => 'Skanuj również wygenerowane drzewa i drzewa pamięci podręcznej (vendor, node_modules, .phpstan.cache, build, ...), które są domyślnie pomijane',
            'allow_root_scan'     => 'Zezwól na korzeń skanowania / lub korzeń powyżej najbliższego composer.json (domyślnie odrzucone: `phpcpd /` to prawie zawsze literówka dla `phpcpd ./`)',
            'orphans'             => 'Wykryj osierocone symbole (nieodwołane klasy, interfejsy, traity, enumy, funkcje) zamiast klonów',
            'no_suppress'         => 'Wyłącz reguły tłumienia według nazwy, oddzielone przecinkami lub "all" (:rules)',
            'fail_on'             => 'Poziomy wyników powodujące wyjście z kodu różnego od zera, oddzielone przecinkami (domyślnie: :default)',
            'explain'             => 'Wypisz każdy stłumiony symbol i regułę, która go stłumiła, zamiast je tylko liczyć',
            'rk'                  => 'Tylko Rabin-Karp (dokładne/klony typu 1; szybsze, brak wykrywania zmiany kolejności). Domyślnie uruchamia zarówno Rabin-Karp, jak i TokenBag.',
            'min_lines'           => 'Minimalna liczba identycznych linii (domyślnie: :default)',
            'min_tokens'          => 'Minimalna liczba identycznych tokenów (domyślnie: :default)',
            'language'            => 'Język raportu (domyślnie: :default)',
            'verbose'             => 'Wypisz zduplikowany kod dla każdego klonu',
            'algorithm'           => 'Nadpisanie pojedynczego algorytmu (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Próg nakładania się TokenBag (domyślnie: :default)',
            'raw'                   => 'Porównywać surowy tekst: identyfikatory też muszą się zgadzać (wyłącza domyślną normalizację)',
            'fuzzy'                 => 'Normalizacja ślepa na nazwy: jak domyślna, ale bez zakotwiczenia typów (badawcza; E2 zmierzyła ją jako zdominowaną)',
            'type_anchored'         => 'Zachować konkretne słowa kluczowe typów przy normalizacji (domyślnie włączone; --fuzzy to wyłącza)',
            'min_confidence'        => 'Wypisać tylko znaleziska, które model ocenia na <log-odds> lub wyżej; reszta jest liczona, czytelna przez --hidden, nigdy nie usuwana i nadal wpływa na --fail-on',
            'hidden'                => 'Wypisać znaleziska zatrzymane przez --min-confidence',
            'cache'                 => 'Buforować wyniki w \'.phpcpd-cache/\' — trafienie wymaga każdego pliku bez zmian, więc służy powtórzeniu jednego commitu, a nie następnemu',
            'acknowledged'        => 'Odczytaj zatwierdzony rejestr akceptacji z <file>: wymieniona duplikacja jest degradowna, nigdy nie ukrywana, a wpisy, których kod uległ zmianie, wygasają i są zgłaszane',
            'write_acknowledged'  => 'Zapisz wyniki tego uruchomienia do <file> jako rejestr akceptacji, do przeglądu i zatwierdzenia',
            'log_pmd'             => 'Zapisz log w formacie PMD-CPD XML do <file>',
            'log_json'            => 'Zapisz log w formacie JSON do <file>',
            'log_sarif'           => 'Zapisz log w formacie SARIF 2.1.0 do <file> (dla GitHub Code Scanning)',
            'cache_dir'           => 'Odczytaj/zapisz pamięć podręczną z <path> (implikuje --cache; nadpisuje domyślny katalog)',
            'incremental'           => 'Przyrostowy indeks na plik: ponownie tokenizuje tylko zmienione pliki (rabin-karp lub unified, nie połączony tryb domyślny; używa katalogu pamięci podręcznej)',
            'config'              => 'Odczytaj ustawienia z <file> (domyślnie: ./phpcpd.ini, jeśli jest obecny); klucze to nazwy długich opcji',
            'show_config'         => 'Wypisz obowiązujące ustawienia, skąd każde pochodzi, i wyjdź',
            'no_config'           => 'Zignoruj ./phpcpd.ini',
            'help'                => 'Wypisz tę pomoc',
            'version'             => 'Wypisz informacje o wersji',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'rejestr akceptacji phpcpd-next',
            'what'  => 'Każda linia rejestruje jedną duplikację, którą ten projekt przeanalizował i postanowił z nią żyć. Zaakceptowane ustalenie jest ZDEGRADOWANE, nigdy nie ukrywane: jest nadal zgłaszane, nadal liczone i nadal blokuje kod wyjścia.',
            'key'   => 'Klucz to zawartość każdej strony duplikacji, zahashowana — nie ścieżka i nie numer linii. Więc edycja którejkolwiek kopii powoduje wygaśnięcie wpisu i ponowne potwierdzenie ustalenia, podczas gdy przeniesienie kodu niczego nie zmienia. Wpis, który nie pasuje już do niczego, jest zgłaszany jako przestarzały, aby można go było usunąć.',
            'note'  => 'Tekst po tabulatorze to notatka ludzka. Nigdy nie jest dopasowywany.',
        ],
    ],
];
