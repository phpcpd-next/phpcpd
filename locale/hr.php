<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Za potpune informacije o autorskim pravima i licenci pogledajte datoteku
 * LICENSE koja je isporučena s ovim izvornim kodom.
 */
/*
 * Hrvatski prijevod.
 *
 * Ključevi, rezervirana mjesta, brojevi i što ovdje uopće spada: vidi
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'GREŠKA: :message',
        'warning' => 'UPOZORENJE: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'U :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfiguracijska datoteka nije pronađena: :path',
        ],
        'unparsable' => [
            'config' => 'Konfiguracijsku datoteku nije bilo moguće raščlaniti: :path',
        ],
        'unknown' => [
            'option'  => 'Nepoznata opcija :flag.',
            'setting' => 'Nepoznata postavka ":name" u :file',
        ],
        'needsValue' => [
            'option'  => 'Opcija :flag traži vrijednost.',
            'setting' => 'Postavka ":name" u :file traži vrijednost.',
        ],
        'takesNoValue' => [
            'option' => 'Opcija :flag ne prima vrijednost.',
        ],
        'invalidValue' => [
            'option' => 'Neispravna vrijednost ":value" za :flag (dopušteno: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Objedinjeni mehanizam traži :flag od barem :floor (zadano: :given). Ispod toga prozor prosijavanja pada ispod 4 i indeks prestaje biti uzorak, pa mehanizam odbija umjesto da tiho prijeđe na iscrpno pretraživanje.',
        ],
        'unwired' => [
            'option' => 'Opcija :flag nema vezu s postavkama.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Odbijam pretraživati korijen datotečnog sustava (:path). Jeste li mislili "./"?',
            'aboveProject'   => 'Odbijam pretraživati :path: nalazi se iznad korijena projekta :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nema datoteka za pretraživanje.',
            'afterTriage' => 'Nakon trijaže nije ostala nijedna datoteka za pretraživanje.',
        ],
        'missingArgument' => [
            'directory' => 'Nije naveden direktorij.',
        ],
        'writeFailed' => [
            'report' => 'Nije bilo moguće zapisati izvještaj u :path:detail',
        ],
        'writePartial' => [
            'report' => 'Nepotpun zapis u :path: bajtova (:written) od (:total).',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'predložak ":preset" navodi putanje za pretraživanje (:declared), nedostaje (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans ne može vidjeti cijeli projekt.',
            'belowRoots'     => '  Svaki korijen pretraživanja nalazi se ispod autoload korijena datoteke :manifest.',
            'uncoveredRoots' => '  autoload putanje navedene u :manifest, a nikad otvorene (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset otkriven — predložak primijenjen (--no-preset za isključivanje)',
        ],
        'cache' => [
            'hit' => '(pogodak u predmemoriji)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag je zastario; koristite --algorithm=unified — koji još ne prijavljuje svako mjesto koje vreća tokena prijavljuje, pa ovaj ostaje dostupan)',
        ],
        'incremental' => [
            'combined'    => '(--incremental zanemaren u kombiniranom načinu rada)',
            'unsupported' => '(--incremental zanemaren: samo algoritmi rabin-karp i unified imaju inkrementalni indeks)',
            'index'       => '(inkrementalni indeks: :reused ponovno iskorišteno, :scanned pretraženo)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nisu pronađeni klonovi koda.',
            'heading'        => 'Pronađeno klonova (:clones):gapped:reordered, dupliciranih redaka (:lines), datoteka (:files):',
            'gapped'         => ', nedosljednih (:count)',
            'reorderedCount' => ', preraspoređeni (:count)',
            'reordered'      => '[preraspoređeno]',
            'unreadable'     => 'nečitljivih datoteka (:count) — ni u jednom zbroju:',
            'strataAsserted' => ':asserted potvrđeno, 0 spušteno.',
            'strataSplit'    => ':asserted potvrđeno, :demoted spušteno (:detail).',
            'settled'         => 'čitanja odbačena jer su već opisana (:count).',
            'unfounded'      => 'nalazi odbačeni kao neprovjereni (:count).',
            'hiddenLine'      => ':count od :total nalaza skriveno ispod pouzdanosti :threshold — --hidden ih navodi.',
            'hiddenHeading'   => 'Skriveno ispod pouzdanosti :threshold (:count):',
            'coverage'         => ':percentage skeniranih redaka (:lines) je duplicirani kôd.',
            'literals'         => '[literali se razlikuju (:count)]',
            'functions'        => 'u :names',
            'sizes'          => 'Redaka po klonu: prosječno (:average), najviše (:largest).',
            'confidence'     => 'pouzdanost :score (:terms)',
        ],
        'ledger' => [
            'line'      => 'Prihvaćeno u evidenciji: spušteno (:acknowledged) od nalaza (:total), zastarjelo (:stale).',
            'staleNote' => 'zastarjelo (kod koji je prihvaćen se promijenio): :note',
            'wrote'     => 'zapisanih prihvaćanja (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (nedostaje)',
            'source' => [
                'default'     => 'zadano',
                'commandLine' => 'naredbeni redak',
                'builtIn'     => '    ugrađene zadane vrijednosti',
            ],
            'fallback'    => ' vraćanje na ugrađenu zadanu vrijednost',
            'layers'      => '  Slojevi, od najnižeg prioriteta:',
        ],
        'run' => [
            'usage'      => 'Vrijeme: :duration, Memorija: :memory MB',
            'banner'     => 'phpcpd :version autora :author — prema :origin autora :originAuthor.',
            'throughput' => ' — datoteka (:count) brzinom :rate/s',
            'files'      => ' — datoteka (:count)',
        ],
        'scan' => [
            'root'  => 'Korijen pretraživanja: :root',
            'roots' => 'Korijeni pretraživanja:',
            'count' => [
                'file'       => 'datoteka (:count)',
                'directory'  => 'korijena (:count)',
                'pattern'    => 'izuzeća (:count)',
                'unreadable' => 'nečitljivih (:count)',
                'generated'  => 'generiranih (:count)',
            ],
            'counts' => 'Pretraženo :counts',
        ],
        'triage' => [
            'nothing'  => 'Trijaža: ništa označeno; svaka je datoteka programski tekst.',
            'removed'  => 'Trijaža: datoteka (:total), uklonjeno (:removed)',
            'labelled' => 'Trijaža: datoteka (:total), označeno (:labelled), ništa uklonjeno',
        ],
        'orphan' => [
            'none'         => 'Nisu pronađeni osamljeni simboli — simbola (:symbols), datoteka (:files).',
            'found'        => 'osamljenih simbola (:count):',
            'possible'     => 'mogućih osamljenih (:count) — pregledajte prije uklanjanja:',
            'advisory'     => 'osamljenih simbola (:count) — savjetodavno, ne utječe na izlazni kod:',
            'notShown'     => 'daljnji nalazi o osamljenima nisu prikazani (:count) — pokrenite --orphans za pregled.',
            'suppressed'   => 'Potisnuto (:count): :census',
            'explainHint'  => '  → --explain za popis',
            'wholeFile'    => '    ⤷ cijela datoteka nije povezana — nijedan ovdje deklarirani simbol nije referenciran',
            'supersededBy' => '    ⤷ izgleda kao zamijenjena kopija simbola :name',
            'summary'      => 'Pretraženo simbola (:symbols), datoteka (:files); osamljenih (:orphaned), mogućih (:possible), potisnutih (:suppressed), planiranih (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'izvan skupa datoteka koji ostavljaju vlastita zadana izuzeća ovog projekta — generirano stablo, koje možda ne svjedoči o povezanosti',
            'declaredHere'  => 'ovdje deklariranih simbola (:count), nijedan nije referenciran nigdje u projektu',
            'foreignNs'     => 'deklarira :namespaces — imenski prostor koji nijedan composer.json iznad njega ne deklarira, izvan svakog direktorija koji povezuju',
        ],
        'role' => [
            'noStatements' => 'nema naredbi na najvišoj razini nakon uvoda',
            'coupled'      => ':registrations od :statements naredbi na najvišoj razini su izrazi registracije, ali dijele varijablu',
            'independent'  => ':registrations od :statements naredbi na najvišoj razini su izrazi registracije neovisni o toku podataka',
        ],
        'orphan' => [
            'guard'          => 'deklarirano unutar provjere postojanja — polifil ili sloj za kompatibilnost',
            'entrypoint'     => 'deklarirano u :namespace — poziva se prema konvenciji radnog okvira',
            'partialProject' => "  Simbol se naziva mrtvim kada ga *ništa* ne referencira, što je tvrdnja o\n  cijelom projektu. Kod izvan ovog pretraživanja i dalje može referencirati ono što je ovdje prijavljeno.",
            'evidence' => [
                'nameAt'   => 'ime se pojavljuje na',
                'loopAt'   => 'otkriveno petljom na',
                'suffixAt' => 'sufiks deklariran na',
                'namedIn'  => 'imenovano u',
            ],
            'plannedServed' => 'sada referencirano — @phpcpd-planned je ispunio svrhu i može se ukloniti',
            'manifest'      => 'deklarirano u composer autoload.files ulaznoj točki',
            'foreignNs'     => 'deklarirano izvan vlastitih imenskih prostora projekta (sloj za kompatibilnost)',
            'fixture'       => 'testni podatak — učitan putanjom ili imenovan kao niz znakova, nikad referenciran',
            'discovery'     => 'otkriveno pretraživanjem direktorija — instancirano iz imena datoteke iza class_exists',
            'convention'    => 'prateći razred — :base koristi :trait, koji ovo ime razrješava sufiksom pri izvođenju',
            'interface'     => 'nikad referencirano (sučelje — može biti implementirano izvan pretraženog skupa)',
            'trait'         => 'nikad referencirano (trait — mogu ga koristiti razredi izvan pretraženog skupa)',
            'abstract'      => 'nikad referencirano (apstraktno — može biti naslijeđeno izvan pretraženog skupa)',
            'inString'      => 'nikad referencirano u kodu; ime se pojavljuje u nizu znakova (moguća dinamička uporaba)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Uvjetno deklarirano (polifil / sloj za kompatibilnost)',
            'fixtures'    => 'Testni podaci (učitani putanjom ili po imenu)',
            'config'      => 'Registrirano u konfiguracijskoj datoteci',
            'template'    => 'Referencirano iz predloška (blade / twig / latte)',
            'manifest'    => 'Referencirano iz composer.json',
            'namespace'   => 'Deklarirano izvan vlastitih imenskih prostora projekta (sloj za kompatibilnost)',
            'keep'        => 'Označeno za zadržavanje (@api / @phpcpd-keep)',
            'entrypoint'  => 'Ulazne točke radnog okvira (atribut / testni razred)',
            'discovery'   => 'Otkriveno pretraživanjem direktorija (instancirano iz imena datoteke)',
            'convention'  => 'Prateći razred imenovan po konvenciji (sufiks deklariran traitom)',
            'planned'     => 'Planirano, još nije povezano',
            'none'        => 'Nije pronađena referenca',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Proslijedite --allow-root-scan ako ste doista mislili na cijeli datotečni sustav.',
            'allowOutside' => 'Proslijedite --allow-root-scan za pretraživanje izvan projekta.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Pretražite korijen projekta za rezultat vrijedan djelovanja.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard djeluje na oznake; --no-triage preskače korak)',
            'explain' => '  (--explain navodi svaku datoteku i dokaze za nju ili protiv nje)',
        ],
        'clone' => [
            'gapped'  => 'Klon s malim odstupanjem — razmislite o parametrizaciji dijela koji se razlikuje ili o usklađivanju obiju kopija.',
            'demoted' => 'Spušteno kao :stratum — to je oblik koji taj sloj opisuje, pa ga izdvojite samo ako ponavljanje nije svrha.',
            'extract' => 'Razmislite o izdvajanju zajedničkih redaka u ponovno iskoristivu metodu, razred ili trait.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Uporaba:',
            'invocation' => '  phpcpd [opcije] <direktorij>',
        ],
        'group' => [
            'selecting' => 'Opcije za odabir datoteka',
            'orphans'   => 'Otkrivanje osamljenih simbola (mrtvi kod)',
            'analysing' => 'Opcije za analizu datoteka',
            'general'   => 'Opće opcije',
            'reporting' => 'Opcije za stvaranje izvještaja',
            'ci'        => 'Opcije za integraciju s CI-jem',
        ],
        'option' => [
            'suffix'              => 'Uključi datoteke čija imena završavaju na <suffix> (zadano: :default; može se ponavljati)',
            'exclude'             => 'Izuzmi datoteke koje u putanji sadrže <path> (može se ponavljati)',
            'preset'              => 'Primijeni predložak radnog okvira (npr. laravel): postavlja razumne putanje, sufikse i izuzeća',
            'triage'              => 'Pokreni trijažu korpusa (Stage 0) prije otkrivanja (uključeno po zadanom; ovime se traži izričito)',
            'no_triage'           => 'Potpuno preskoči Stage 0: nijedna datoteka neće biti označena kao nepovezana, zasjenjena, vendorska ili izvedena',
            'triage_posture'      => 'Što trijaža radi s datotekom koju označi: odbacuje je iz pretraživanja (zadano) ili je samo označava',
            'no_preset'           => 'Ne primjenjuj automatski predložak radnog okvira kada je otkriven (otkrivanje se samo najavljuje; --preset= ima prednost)',
            'no_default_excludes' => 'Pretraži i generirana stabla te stabla predmemorije (vendor, node_modules, .phpstan.cache, build, ...), koja se po zadanom preskaču',
            'allow_root_scan'     => 'Dopusti korijen pretraživanja / ili korijen iznad najbližeg composer.json (po zadanom odbijeno: `phpcpd /` gotovo je uvijek tipfeler za `phpcpd ./`)',
            'orphans'             => 'Otkrij osamljene simbole (nereferencirane razrede, sučelja, traitove, enume, funkcije) umjesto klonova',
            'no_suppress'         => 'Isključi pravila potiskivanja po imenu, odvojena zarezom, ili "all" (:rules)',
            'fail_on'             => 'Razine rezultata koje uzrokuju izlaz različit od nule, odvojene zarezom (zadano: :default)',
            'explain'             => 'Navedi svaki potisnuti simbol i pravilo koje ga je potisnulo, umjesto samo brojanja',
            'rk'                  => 'Samo Rabin-Karp (točni klonovi / tip 1; brže, bez otkrivanja preraspoređivanja). Zadano se pokreću i Rabin-Karp i TokenBag.',
            'min_lines'           => 'Najmanji broj istovjetnih redaka (zadano: :default)',
            'min_tokens'          => 'Najmanji broj istovjetnih tokena (zadano: :default)',
            'language'            => 'Jezik izvještaja (zadano: :default)',
            'verbose'             => 'Ispiši duplicirani kod za svaki klon',
            'algorithm'           => 'Odabir jednog algoritma (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Prag preklapanja za TokenBag (zadano: :default)',
            'raw'                   => 'Usporedi sirovi tekst: i identifikatori se moraju podudarati (isključuje zadanu normalizaciju)',
            'fuzzy'                 => 'Normalizacija slijepa na imena: kao zadana, ali bez sidrenja tipova (istraživanje; E2 ju je izmjerio kao dominiranu)',
            'type_anchored'         => 'Zadrži ključne riječi tipova konkretnima pri normalizaciji (zadano uključeno; --fuzzy isključuje)',
            'min_confidence'        => 'Navedi samo nalaze koje model ocjenjuje na <log-odds> ili više; ostali se broje, čitljivi su uz --hidden, nikad se ne odbacuju i i dalje određuju --fail-on',
            'hidden'                => 'Navedi nalaze koje je --min-confidence zadržao',
            'cache'                 => 'Spremi rezultate u predmemoriju \'.phpcpd-cache/\' — pogodak zahtijeva svaku datoteku nepromijenjenu, pa služi ponovnom pokretanju jednog commita, a ne sljedećem',
            'acknowledged'        => 'Pročitaj potvrđenu evidenciju prihvaćanja iz <file>: navedeno dupliciranje se spušta, nikad ne skriva, a stavke čiji se kod promijenio istječu i prijavljuju se',
            'write_acknowledged'  => 'Zapiši nalaze ovog pokretanja u <file> kao evidenciju prihvaćanja, za pregled i potvrdu',
            'log_pmd'             => 'Zapiši zapisnik u PMD-CPD XML formatu u <file>',
            'log_json'            => 'Zapiši zapisnik u JSON formatu u <file>',
            'log_sarif'           => 'Zapiši zapisnik u SARIF 2.1.0 formatu u <file> (za GitHub Code Scanning)',
            'cache_dir'           => 'Čitaj/piši predmemoriju iz <path> (podrazumijeva --cache; ima prednost nad zadanim direktorijem)',
            'incremental'           => 'Inkrementalni indeks po datoteci: ponovno tokenizira samo promijenjene datoteke (rabin-karp ili unified, ne kombinirani zadani; koristi direktorij predmemorije)',
            'config'              => 'Čitaj postavke iz <file> (zadano: ./phpcpd.ini kada postoji); ključevi su duga imena opcija',
            'show_config'         => 'Ispiši postavke na snazi, odakle je svaka došla, i izađi',
            'no_config'           => 'Zanemari ./phpcpd.ini',
            'help'                => 'Ispiši ovu pomoć',
            'version'             => 'Ispiši podatke o verziji',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next evidencija prihvaćanja',
            'what'  => "Svaki redak bilježi jedno dupliciranje koje je ovaj projekt pogledao i odlučio\ns njim živjeti. Prihvaćeni nalaz je SPUŠTEN, nikad skriven: i dalje se\nprijavljuje, i dalje broji, i dalje utječe na izlazni kod.",
            'key'   => "Ključ je sadržaj svake strane dupliciranja, raspršen — ne putanja\ni ne broj retka. Zato uređivanje bilo koje kopije poništava stavku i\nnalaz se ponovno potvrđuje, dok premještanje koda ne mijenja ništa. Stavka\nkoja se više ni s čim ne podudara prijavljuje se kao zastarjela kako bi se mogla obrisati.",
            'note'  => 'Tekst nakon tabulatora je ljudska bilješka. Nikada se ne uspoređuje.',
        ],
    ],
];
