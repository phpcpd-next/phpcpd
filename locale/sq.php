<?php

declare(strict_types=1);
/*
 * Ky skedar është pjesë e PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Për informacionin e plotë mbi të drejtat e autorit dhe licencën, ju lutemi shikoni skedarin LICENSE
 * që u shpërnda me këtë kod buror.
 */
/*
 * Përkthimi në shqip.
 *
 * Çelësat, vendmbajtësit, numëruesit dhe çfarë i përket përgjithësisht këtu: shih
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'GABIM: :message',
        'warning' => 'PARALAJMËRIM: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'Në :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Skedari i konfigurimit nuk u gjet: :path',
        ],
        'unparsable' => [
            'config' => 'Skedari i konfigurimit nuk mund të analizohej: :path',
        ],
        'unknown' => [
            'option'  => 'Opsion i panjohur :flag.',
            'setting' => 'Cilësim i panjohur ":name" në :file',
        ],
        'needsValue' => [
            'option'  => 'Opsioni :flag kërkon një vlerë.',
            'setting' => 'Cilësimi ":name" në :file kërkon një vlerë.',
        ],
        'takesNoValue' => [
            'option' => 'Opsioni :flag nuk pranon vlerë.',
        ],
        'invalidValue' => [
            'option' => 'Vlerë e pavlefshme ":value" për :flag (e lejuar: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Motori i unifikuar (unified engine) kërkon një :flag prej të paktën :floor (u dha: :given). Nën këtë vlerë, dritarja e fitimit bie nën 4 dhe indeksi ndalon së qeni një mostër, kështu që motori refuzon në vend që të degradojë qetësisht në një skanim të plotë.',
        ],
        'unwired' => [
            'option' => 'Opsioni :flag nuk ka lidhje me cilësimet.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Refuzon skanimin e rrënjës së sistemit të skedarëve (:path). Mos kishe parasysh "./"?',
            'aboveProject'   => 'Refuzon skanimin e :path: ndodhet mbi rrënjën e projektit :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nuk u gjetën skedarë për t\'u skanuar.',
            'afterTriage' => 'Nuk mbetën skedarë për t\'u skanuar pas triazhit.',
        ],
        'missingArgument' => [
            'directory' => 'Nuk u specifikua asnjë dosje.',
        ],
        'writeFailed' => [
            'report' => 'Nuk mund të shkruhej raporti te :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Shkrim i paplotë te :path: bajtë (:written) nga (:total).',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'Paracaktimi (preset) ":preset" deklaron shtigjet e skanimit (:declared), mungojnë (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans nuk mund ta shohë të gjithë projektin.',
            'belowRoots'     => '  Secila rrënjë skanimi ndodhet nën rrënjët e ngarkimit automatik (autoload) të :manifest.',
            'uncoveredRoots' => '  Shtigjet e ngarkimit automatik të deklaruara në :manifest por që nuk u hapën kurrë (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => 'U zbulua :preset — u aplikua paracaktimi (--no-preset për ta çaktivizuar)',
        ],
        'cache' => [
            'hit' => '(gjetje në cache)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag është vjetëruar; përdorni --algorithm=unified — i cili ende nuk raporton çdo vend që e raporton tokenbag, kështu që kjo mbetet e zgjedhshme)',
        ],
        'incremental' => [
            'combined'    => '(--incremental u anashkalua në modalitetin e kombinuar)',
            'unsupported' => '(--incremental u anashkalua: vetëm algoritmet rabin-karp dhe unified kanë një indeks shtues)',
            'index'       => '(indeksi shtues: :reused të ripërdorura, :scanned të skanuara)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nuk u gjetën klone kodi.',
            'heading'        => 'U gjetën klone kodi (:clones):gapped:reordered, rreshta të dyfishuar (:lines), skedarë (:files):',
            'gapped'         => ', jo konsistente (:count)',
            'reorderedCount' => ', të rirenditura (:count)',
            'reordered'      => '[rirenditur]',
            'unreadable'     => 'skedarë të palexueshëm (:count) — nuk përfshihen në total:',
            'strataAsserted' => 'të pohuara :asserted, 0 të ulura në rang.',
            'strataSplit'    => 'të pohuara :asserted, të ulura në rang :demoted (:detail).',
            'settled'         => 'lexime të hedhura poshtë si tashmë të përshkruara (:count).',
            'unfounded'      => 'gjetje të hedhura poshtë si të paverifikuara (:count).',
            'hiddenLine'      => ':count nga :total gjetje të fshehura nën besueshmërinë :threshold — --hidden i rendit.',
            'hiddenHeading'   => 'Të fshehura nën besueshmërinë :threshold (:count):',
            'coverage'         => ':percentage e rreshtave të skanuar (:lines) është kod i dyfishuar.',
            'literals'         => '[literalët ndryshojnë (:count)]',
            'confidence'       => 'besueshmëria :score (:terms)',
            'functions'        => 'në :names',
            'sizes'          => 'Rreshta për klon: mesatarja (:average), më i madhi (:largest).',
        ],
        'ledger' => [
            'line'      => 'Të pranuara nga regjistri: të ulura në rang (:acknowledged) nga gjetje (:total), të vjetruara (:stale).',
            'staleNote' => 'të vjetruara (kodi që u pranua ka ndryshuar): :note',
            'wrote'     => 'u shkruan pranimet (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (mungon)',
            'source' => [
                'default'     => 'parazgjedhje',
                'commandLine' => 'rreshti i komandave',
                'builtIn'     => '    parazgjedhjet e integruara',
            ],
            'fallback'    => ' kthehet te parazgjedhja e integruar',
            'layers'      => '  Shtresat, prioriteti më i ulët i pari:',
        ],
        'run' => [
            'throughput' => ' — skedarë (:count) me :rate/s',
            'files'    => ' — skedarë (:count)',
            'usage'  => 'Koha: :duration, Memoria: :memory MB',
            'banner' => 'phpcpd :version nga :author — pas :origin nga :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Rrënjë e skanimit: :root',
            'roots'              => 'Rrënjët e skanimit:',
            'count' => [
                'file'       => 'skedarë (:count)',
                'directory'  => 'rrënjë (:count)',
                'pattern'    => 'përjashtime (:count)',
                'unreadable' => 'të palexueshëm (:count)',
                'generated'  => 'të gjeneruar (:count)',
            ],
            'counts'             => 'U skanuan :counts',
        ],
        'triage' => [
            'nothing'  => 'Triazhi: asgjë nuk u etiketua; çdo skedar është tekst programi.',
            'removed'  => 'Triazhi: skedarë (:total), u hoqën (:removed)',
            'labelled' => 'Triazhi: skedarë (:total), u etiketuan (:labelled), asgjë nuk u hoq',
        ],
        'orphan' => [
            'none'         => 'Nuk u gjetën simbole jetime — simbole (:symbols), skedarë (:files).',
            'found'        => 'simbole jetime (:count):',
            'possible'     => 'simbole jetime të mundshme (:count) — kontrolloni para se t\'i hiqni:',
            'advisory'     => 'simbole jetime (:count) — këshilluese, nuk ndikojnë në kodin e daljes:',
            'notShown'     => 'gjetje të tjera jetime nuk shfaqen (:count) — ekzekutoni --orphans për t\'i shqyrtuar.',
            'suppressed'   => 'Të shtypura (:count): :census',
            'explainHint'  => '  → --explain për t\'i listuar',
            'wholeFile'    => '    ⤷ i gjithë skedari është i palidhur — asnjë simbol i deklaruar këtu nuk referohet',
            'supersededBy' => '    ⤷ duket si një kopje e zëvendësuar e :name',
            'summary'      => 'U skanuan simbole (:symbols) në skedarë (:files); jetime (:orphaned), të mundshme (:possible), të shtypura (:suppressed), të planifikuara (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'jashtë grupit të skedarëve që lënë pas përjashtimet e parazgjedhura të këtij projekti — një pemë e gjeneruar, e cila mund të mos dëshmojë për lidhjet',
            'declaredHere'  => 'simbole të deklaruara këtu (:count), asnjëra prej tyre nuk referohet diku tjetër në projekt',
            'foreignNs'     => 'deklaron :namespaces — një hapësirë emrash që asnjë composer.json sipër saj nuk e deklaron, jashtë çdo dosjeje që ata lidhin',
        ],
        'role' => [
            'noStatements' => 'nuk ka deklarata të nivelit të lartë pas parathënies',
            'coupled'      => ':registrations nga :statements deklarata të nivelit të lartë janë shprehje regjistrimi, por ato ndajnë një variabël',
            'independent'  => ':registrations nga :statements deklarata të nivelit të lartë janë shprehje regjistrimi të pavarura nga rrjedha e të dhënave',
        ],
        'orphan' => [
            'guard'          => 'e deklaruar brenda një roje ekzistence — një polyfill ose shtresë përputhshmërie',
            'entrypoint'     => 'e deklaruar në :namespace — e thirrur nga konvencioni i kornizës',
            'partialProject' => '  Një simbol quhet i vdekur kur *asgjë* nuk referohet tek ai, gjë që është një pohim rreth
  të gjithë projektit. Kodi jashtë këtij skanimi mund të vazhdojë t\'i referohet asaj që raportohet këtu.',
            'evidence' => [
                'nameAt'   => 'emri shfaqet në',
                'loopAt'   => 'u zbulua nga unaza në',
                'suffixAt' => 'prapashtesa u deklarua në',
                'namedIn'  => 'u emërtua në',
            ],
            'plannedServed'  => 'tani i referencuar — @phpcpd-planned e ka kryer qëllimin e tij dhe mund të hiqet',
            'manifest'       => 'e deklaruar në pikën e hyrjes composer autoload.files',
            'foreignNs'      => 'e deklaruar jashtë hapësirave të emrave të vetë projektit (shtresë përputhshmërie)',
            'fixture'        => 'fiksurë testimi — e ngarkuar përmes shtegut ose e emërtuar si varg, kurrë e referencuar',
            'discovery'      => 'e zbuluar përmes skanimit të dosjeve — e instancuar nga emri i skedarit pas class_exists',
            'convention'     => 'klasë shoqëruese — :base përdor :trait, e cila e zgjidh këtë emër me prapashtesë gjatë kohës së ekzekutimit',
            'interface'      => 'kurrë e referencuar (ndërfaqe — mund të zbatohet jashtë grupit të skanuar)',
            'trait'          => 'kurrë e referencuar (tipar — mund të përdoret nga klasa jashtë grupit të skanuar)',
            'abstract'       => 'kurrë e referencuar (abstrakte — mund të zgjerohet jashtë grupit të skanuar)',
            'inString'       => 'kurrë e referencuar në kod; emri shfaqet në një fjalëpërfjalshme vargu (përdorim i mundshëm dinamik)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'E deklaruar me kusht (polyfill / shtresë përputhshmërie)',
            'fixtures'    => 'Fiksurë testimi (të ngarkuara me shteg ose emër)',
            'config'      => 'E regjistruar në një skedar konfigurimi',
            'template'    => 'E referencuar nga një shabllon (blade / twig / latte)',
            'manifest'    => 'E referencuar nga composer.json',
            'namespace'   => 'E deklaruar jashtë hapësirave të emrave të vetë projektit (shtresë përputhshmërie)',
            'keep'        => 'E shënuar si e ruajtur (@api / @phpcpd-keep)',
            'entrypoint'  => 'Pikat e hyrjes së kornizës (atribut / klasë testimi)',
            'discovery'   => 'E zbuluar përmes skanimit të dosjeve (e instancuar nga emri i skedarit)',
            'convention'  => 'Klasë shoqëruese e emërtuar sipas konvencionit (prapashtesë e deklaruar nga trait)',
            'planned'     => 'E planifikuar, ende e nelidhur',
            'none'        => 'Nuk u gjet asnjë referencë',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Kalon --allow-root-scan nëse vërtet kishit për qëllim të gjithë sistemin e skedarëve.',
            'allowOutside' => 'Kalon --allow-root-scan për të skanuar jashtë projektit gjithsesi.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skanoni rrënjën e projektit për të marrë një rezultat për të cilin vlen të veprohet.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard vepron sipas etiketave; --no-triage anashkalon fazën)',
            'explain' => '  (--explain liston çdo skedar dhe provat në favor ose kundër tij)',
        ],
        'clone' => [
            'gapped'  => 'Kloni gati i saktë — konsideroni parametrizimin e pjesës ndryshuese ose njësimin e dy kopjeve.',
            'demoted' => 'E ulur në rang si :stratum — kjo është forma që përshkruan ajo shtresë, ndaj nxirreni vetëm nëse përsëritja nuk është qëllimi.',
            'extract' => 'Konsideroni nxjerrjen e rreshtave të përbashkët në një metodë, klasë ose trait të ripërdorshëm.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Përdorimi:',
            'invocation' => '  phpcpd [opsionet] <dosier>',
        ],
        'group' => [
            'selecting' => 'Opsionet e zgjedhjes së skedarëve',
            'orphans'   => 'Zbulimi i simboleve jetime (kodi i vdekur)',
            'analysing' => 'Opsionet e analizës së skedarëve',
            'general'   => 'Opsionet e përgjithshme',
            'reporting' => 'Opsionet e gjenerimit të raporteve',
            'ci'        => 'Opsionet e integrimit CI',
        ],
        'option' => [
            'suffix'              => 'Përfshi skedarët emrat e të cilëve mbarojnë me <suffix> (parazgjedhje: :default; e përsëritshme)',
            'exclude'             => 'Përjashto skedarët që përmbajnë <path> në shtegun e tyre (e përsëritshme)',
            'preset'              => 'Apliko një paracaktim kornize (p.sh. laravel): vendos shtigje, prapashtesa dhe përjashtime të arsyeshme',
            'triage'              => 'Ekzekuto triazhin e korpusit të Fazës 0 para zbulimit (aktivizuar si parazgjedhje; kërkon këtë shprehimisht)',
            'no_triage'           => 'Anashkalo plotësisht Fazën 0: asnjë skedar nuk etiketohet si i nelidhur, i hijezuar, nga shitësi ose derivat',
            'triage_posture'      => 'Çfarë bën triazhi me një skedar që etiketon: e hedh poshtë nga skanimi (parazgjedhje), ose e etiketon dhe nuk bën asgjë tjetër',
            'no_preset'           => 'Mos apliko automatikisht një paracaktim kornize kur zbulohet (zbulimi njofton veten; --preset= e mbishkruan këtë)',
            'no_default_excludes' => 'Skano gjithashtu pemët e gjeneruara dhe të cache-it (vendor, node_modules, .phpstan.cache, build, ...), të cilat anashkalohen si parazgjedhje',
            'allow_root_scan'     => 'Lejo një rrënjë skanimi prej / ose një rrënjë sipër composer.json më të afërt (refuzuar si parazgjedhje: `phpcpd /` është pothuajse gjithmonë një gabim shtypjeje për `phpcpd ./`)',
            'orphans'             => 'Zbulo simbolet jetime (klasa, ndërfaqe, trafte, enume, funksione të nereferencuara) në vend të kloneve',
            'no_suppress'         => 'Çaktivizo rregullat e shtypjes sipas emrit, të ndara me presje, ose "all" (:rules)',
            'fail_on'             => 'Nivelet e rezultateve që bëjnë që ekzekutimi të dalë me kod jo-zero, të ndara me presje (parazgjedhje: :default)',
            'explain'             => 'Listo çdo simbol të shtypur dhe rregullin që e shtypi, në vend që thjesht t\'i numërosh',
            'rk'                  => 'Vetëm Rabin-Karp (klone të sakta/Tipi 1; më e shpejtë, pa zbulim rirenditjeje). Ekzekutimi i parazgjedhur ekzekuton si Rabin-Karp ashtu edhe TokenBag.',
            'min_lines'           => 'Numri minimal i rreshtave identikë (parazgjedhje: :default)',
            'min_tokens'          => 'Numri minimal i tokeneve identikë (parazgjedhje: :default)',
            'language'            => 'Gjuha e raportit (parazgjedhje: :default)',
            'verbose'             => 'Printo kodin e dyfishuar për secilin klon',
            'algorithm'           => 'Mbishkrim i një algoritmi të vetëm (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Pragu i mbivendosjes së TokenBag (parazgjedhje: :default)',
            'raw'                   => 'Krahaso tekstin e papërpunuar: edhe identifikuesit duhet të përputhen (çaktivizon normalizimin e parazgjedhur)',
            'fuzzy'                 => 'Normalizim i verbër ndaj emrave: si i parazgjedhuri por pa spirancën e tipave (kërkim; E2 e mati si të dominuar)',
            'type_anchored'         => 'Mbaj konkrete fjalëkyçet e tipave gjatë normalizimit (aktiv si parazgjedhje; --fuzzy e çaktivizon)',
            'min_confidence'        => 'Rendit vetëm gjetjet që modeli i vlerëson në <log-odds> ose më lart; të tjerat numërohen, lexohen me --hidden, nuk hidhen kurrë poshtë dhe vazhdojnë të përcaktojnë --fail-on',
            'hidden'                => 'Rendit gjetjet që --min-confidence mbajti mbrapa',
            'cache'                 => 'Ruaj rezultatet në \'.phpcpd-cache/\' — një përputhje kërkon çdo skedar të pandryshuar, ndaj i shërben rinisjes së një commit-i e jo atij pasues',
            'acknowledged'        => 'Lexo një regjistër pranimi të angazhuar nga <file>: dyfishimi i listuar ulet në rang, nuk fshihet kurrë, dhe hyrjet kodi i të cilave ka ndryshuar skadojnë dhe raportohen',
            'write_acknowledged'  => 'Shkruaj gjetjet e këtij ekzekutimi te <file> si një regjistër pranimi, për shqyrtim dhe angazhim',
            'log_pmd'             => 'Shkruaj regjistrin në formatin PMD-CPD XML te <file>',
            'log_json'            => 'Shkruaj regjistrin në formatin JSON te <file>',
            'log_sarif'           => 'Shkruaj regjistrin në formatin SARIF 2.1.0 te <file> (për Skanimin e Kodit në GitHub)',
            'cache_dir'           => 'Lexo/shkruaj cache nga <path> (nënkupton --cache; mbishkruan dosjen e parazgjedhur)',
            'incremental'           => 'Indeks inkremental për skedar: ritokenizon vetëm skedarët e ndryshuar (rabin-karp ose unified, jo parazgjedhja e kombinuar; përdor direktorinë e cache-it)',
            'config'              => 'Lexo cilësimet nga <file> (parazgjedhje: ./phpcpd.ini kur është i pranishëm); çelësat janë emrat e opsioneve të gjata',
            'show_config'         => 'Printo cilësimet në fuqi, nga erdhi secila, dhe dil',
            'no_config'           => 'Injoro ./phpcpd.ini',
            'help'                => 'Printo këtë ndihmë',
            'version'             => 'Printo informacionin e versionit',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'regjistri i pranimit phpcpd-next',
            'what'  => 'Secila rresht regjistron një dyfishim që ky projekt e ka shqyrtuar dhe ka vendosur të bashkëjetojë me të. Një gjetje e pranuar ULET NË RANG, nuk fshihet kurrë: ajo ende raportohet, ende llogaritet dhe ende kontrollon kodin e daljes.',
            'key'   => 'Çelësi është përmbajtja e secilës anë të dyfishimit, e hashuar — jo një shteg dhe jo një numër rreshti. Kështu që modifikimi i njërës prej kopjeve e bën hyrjen të skaduar dhe gjetja kërkohet përsëri, ndërsa lëvizja e kodit nuk ndryshon asgjë. Një hyrje që nuk përputhet më me asgjë raportohet si e vjetruar në mënyrë që të mund të fshihet.',
            'note'  => 'Teksti pas tabulacionit është një shënim njerëzor. Nuk përputhet kurrë.',
        ],
    ],
];
