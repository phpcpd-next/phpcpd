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
 * Suomenkielinen käännös (Finnish translation).
 */

return [
    'frame' => [
        'error'   => 'VIRHE: :message',
        'warning' => 'VAROITUS: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'Tiedostossa :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfiguraatiotiedostoa ei löytynyt: :path',
        ],
        'unparsable' => [
            'config' => 'Konfiguraatiotiedoston jäsennys epäonnistui: :path',
        ],
        'unknown' => [
            'option'  => 'Tuntematon valinta :flag.',
            'setting' => 'Tuntematon asetus ":name" tiedostossa :file',
        ],
        'needsValue' => [
            'option'  => 'Valinta :flag vaatii arvon.',
            'setting' => 'Asetus ":name" tiedostossa :file vaatii arvon.',
        ],
        'takesNoValue' => [
            'option' => 'Valinta :flag ei ota vastaan arvoa.',
        ],
        'invalidValue' => [
            'option' => 'Virheellinen arvo ":value" valinnalle :flag (sallittu: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Yhdistetty moottori (unified engine) vaatii arvon :flag, joka on vähintään :floor (annettu: :given). Sen alapuolella winnow-ikkuna laskee alle neljän ja indeksi lakkaa olemasta näyte, joten moottori kieltäytyy suorittamasta pelkkää täydellistä skannausta.',
        ],
        'unwired' => [
            'option' => 'Valinnalla :flag ei ole asetusliitosta.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Kieltäydytään skannaamasta tiedostojärjestelmän juurta (:path). Tarkoitetko "./"?',
            'aboveProject'   => 'Kieltäydytään skannaamasta polkua :path: se on projektin juuren :project yläpuolella.',
        ],
        'nothingToScan' => [
            'files'       => 'Skannattavia tiedostoja ei löytynyt.',
            'afterTriage' => 'Triaasin jälkeen ei jäänyt skannattavia tiedostoja.',
        ],
        'missingArgument' => [
            'directory' => 'Kansiota ei ole määritetty.',
        ],
        'writeFailed' => [
            'report' => 'Raportin kirjoittaminen polkuun :path epäonnistui: :detail',
        ],
        'writePartial' => [
            'report' => 'Raportista kirjoitettiin polkuun :path vain / (:written) tavua (:total)',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'esiasetus ":preset" määrittää skannauspolut (:declared), puuttuvat (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans ei näe koko projektia.',
            'belowRoots'     => '  Jokainen skannausjuuri on manifestin :manifest automaattilatausjuurien alapuolella.',
            'uncoveredRoots' => '  automaattilatauspolut, jotka on määritetty manifestissa :manifest, mutta joita ei ole koskaan avattu (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset havaittu — esiasetus otettu käyttöön (--no-preset poistaa käytöstä)',
        ],
        'cache' => [
            'hit' => '(välimuistiosuma)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag on poistumassa käytöstä; käytä --algorithm=unified — joka ei vielä raportoi kaikkia token bagin raportoimia kohteita, joten tämä pysyy valittavana)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ohitettu yhdistetyssä tilassa)',
            'unsupported' => '(--incremental ohitettu: vain rabin-karp- ja unified-algoritmeilla on inkrementaalinen indeksi)',
            'index'       => '(inkrementaalinen indeksi: :reused uudelleenkäytetty, :scanned skannattu)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Koodiklooneja ei löytynyt.',
            'heading'        => 'Löytyi koodikloonit (:clones):gapped:reordered, kahdennetut rivit (:lines), tiedostot (:files):',
            'gapped'         => ', epäyhtenäisiä (:count)',
            'reorderedCount' => ', uudelleenjärjestettyjä (:count)',
            'reordered'      => '[uudelleenjärjestetty]',
            'unreadable'     => 'lukukelvottomat tiedostot (:count) — eivät kummassakaan kokonaismäärässä:',
            'strataAsserted' => ':asserted vaadittu, 0 alennettu.',
            'strataSplit'    => ':asserted vaadittu, :demoted alennettu (:detail).',
            'settled'         => 'luentoja hylätty, koska ne on jo kuvattu (:count).',
            'unfounded'      => 'löydöksiä hylätty vahvistamattomina (:count).',
            'hiddenLine'      => ':count/:total löydöstä piilotettu luottamuksen :threshold alle — --hidden listaa ne.',
            'hiddenHeading'   => 'Piilotettu luottamuksen :threshold alle (:count):',
            'coverage'         => ':percentage skannatuista riveistä (:lines) on monistettua koodia.',
            'literals'         => '[literaalit eroavat (:count)]',
            'confidence'       => 'luottamus :score (:terms)',
            'functions'        => 'kohteessa :names',
            'sizes'          => 'Rivejä kloonia kohti: keskiarvo (:average), suurin (:largest).',
        ],
        'ledger' => [
            'line'      => 'Tunnustettu: / (:acknowledged) löydöstä (:total) alennettu pääkirjan toimesta; vanhentuneita (:stale).',
            'staleNote' => 'vanhentunut (sen tunnustama koodi on muuttunut): :note',
            'wrote'     => 'tunnustukset kirjoitettu (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (puuttuu)',
            'source' => [
                'default'     => 'oletus',
                'commandLine' => 'komentorivi',
                'builtIn'     => '    sisäänrakennetut oletusarvot',
            ],
            'fallback'    => ' palataan sisäänrakennettuun oletukseen',
            'layers'      => '  Kerrokset, matalin prioriteetti ensin:',
        ],
        'run' => [
            'throughput' => ' — tiedostot (:count) nopeudella :rate/s',
            'files'    => ' — tiedostot (:count)',
            'usage'  => 'Aika: :duration, Muisti: :memory MB',
            'banner' => 'phpcpd :version, tekijä :author — ohjelman :origin jälkeen, tekijä :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Skannauksen juuri: :root',
            'roots'              => 'Skannauksen juuret:',
            'count' => [
                'file'       => 'tiedostot (:count)',
                'directory'  => 'juuret (:count)',
                'pattern'    => 'poissulkemiset (:count)',
                'unreadable' => 'lukukelvottomat (:count)',
                'generated'  => 'generoidut (:count)',
            ],
            'counts'             => 'Skannattu :counts',
        ],
        'triage' => [
            'nothing'  => 'Triaasi: mitään ei merkitty; jokainen tiedosto on ohjelmatekstiä.',
            'removed'  => 'Triaasi: tiedostot (:total), poistettu (:removed)',
            'labelled' => 'Triaasi: tiedostot (:total), merkitty (:labelled), mitään ei poistettu',
        ],
        'orphan' => [
            'none'         => 'Orpoja symboleja ei löytynyt (symbolia (:symbols) tiedostossa (:files)).',
            'found'        => 'orvot symbolit (:count):',
            'possible'     => 'mahdolliset orvot (:count) — tarkista ennen poistamista:',
            'advisory'     => 'orvot symbolit (:count) — suositusluonteisia, eivät vaikuta poistumiskoodiin:',
            'notShown'     => 'muita orpolöydöksiä ei näytetä (:count) — suorita --orphans tarkistusta varten.',
            'suppressed'   => 'Vaimennetut (:count): :census',
            'explainHint'  => '  → --explain listataksesi ne',
            'wholeFile'    => '    ⤷ koko tiedosto on yhdistämätön — yhtään täällä määritettyä symbolia ei viitata',
            'supersededBy' => '    ⤷ näyttää :name korvatulta kopiolta',
            'summary'      => 'Skannattu symbolia (:symbols) tiedostossa (:files); orpoja (:orphaned), mahdollisia (:possible), vaimennettuja (:suppressed), suunniteltuja (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'sen tiedostojoukon ulkopuolella, jonka tämän projektin omat oletuspoissulkemiset jättävät — generoitu puu, joka ei ehkä todista johdotusta',
            'declaredHere'  => 'täällä määritetyt symbolit (:count), joihin ei viitata missään päin projektia',
            'foreignNs'     => 'määrittää :namespaces — nimiavaruus, jota mikään sen yläpuolella oleva composer.json ei määritä, jokaisen niiden yhdistämän kansion ulkopuolella',
        ],
        'role' => [
            'noStatements' => 'ei ylätason lauseita esipuheen jälkeen',
            'coupled'      => ':registrations / :statements ylätason lauseesta ovat rekisteröintilausekkeita, mutta ne jakavat muuttujan',
            'independent'  => ':registrations / :statements ylätason lauseesta ovat datavirrasta riippumattomia rekisteröintilausekkeita',
        ],
        'orphan' => [
            'guard'          => 'määritetty olemassaolosuojan sisällä — polyfill tai yhteensopivuussuoja',
            'entrypoint'     => 'määritetty nimiavaruudessa :namespace — kehyksen konvention kutsuma',
            'partialProject' => '  Symbolia sanotaan kuolleeksi, kun *mikään* ei viittaa siihen, mikä on väite
  koko projektista. Koodi tämän skannauksen ulkopuolella voi silti viitata siihen, mitä täällä raportoidaan.',
            'evidence' => [
                'nameAt'   => 'nimi näkyy kohteessa',
                'loopAt'   => 'silmukan havaitsema kohteessa',
                'suffixAt' => 'pääte määritetty kohteessa',
                'namedIn'  => 'nimetty kohteessa',
            ],
            'plannedServed'  => 'viitataan nyt — @phpcpd-planned on täyttänyt tarkoituksensa ja voidaan poistaa',
            'manifest'       => 'määritetty composer autoload.files -sisääntulopisteessä',
            'foreignNs'      => 'määritetty projektin omien nimiavaruuksien ulkopuolella (yhteensopivuussuoja)',
            'fixture'        => 'testifixtuuri — ladattu polun kautta tai nimetty merkkijonona, ei koskaan viitattu',
            'discovery'      => 'havaittu kansioskannauksella — alustettu tiedostonimestään class_existsin takana',
            'convention'     => 'kumppaniluokka — :base käyttää traitiä :trait, joka ratkaisee tämän nimen päätteellä ajoaikana',
            'interface'      => 'ei koskaan viitattu (rajapinta — saatetaan toteuttaa skannatun joukon ulkopuolella)',
            'trait'          => 'ei koskaan viitattu (trait — saattaa olla luokkien käyttämä skannatun joukon ulkopuolella)',
            'abstract'       => 'ei koskaan viitattu (abstrakti — saatetaan laajentaa skannatun joukon ulkopuolella)',
            'inString'       => 'ei koskaan viitattu koodissa; nimi näkyy merkkijonoliteraalissa (mahdollinen dynaaminen käyttö)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Ehdoillisesti määritetty (polyfill / yhteensopivuussuoja)',
            'fixtures'    => 'Testifixtuurit (ladattu polun tai nimen mukaan)',
            'config'      => 'Rekisteröity konfiguraatiotiedostoon',
            'template'    => 'Viitattu mallista (blade / twig / latte)',
            'manifest'    => 'Viitattu tiedostosta composer.json',
            'namespace'   => 'Määritetty projektin omien nimiavaruuksien ulkopuolella (yhteensopivuussuoja)',
            'keep'        => 'Merkitty säilytettäväksi (@api / @phpcpd-keep)',
            'entrypoint'  => 'Kehyksen sisääntulopisteet (attribuutti / testiluokka)',
            'discovery'   => 'Havaittu kansioskannauksella (alustettu tiedostonimestään)',
            'convention'  => 'Konvention mukaan nimetty kumppaniluokka (traitin määrittämä pääte)',
            'planned'     => 'Suunniteltu, ei vielä yhdistetty',
            'none'        => 'Viitettä ei löytynyt',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Anna --allow-root-scan, jos tarkoitit todella koko tiedostojärjestelmää.',
            'allowOutside' => 'Anna --allow-root-scan skannataksesi projektin ulkopuolelta joka tapauksessa.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skannaa projektin juuri saadaksesi tuloksen, jonka pohjalta kannattaa toimia.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard toimii tunnisteiden mukaan; --no-triage ohittaa vaiheen)',
            'explain' => '  (--explain listaa jokaisen tiedoston ja sitä puoltavat tai vastustavat todisteet)',
        ],
        'clone' => [
            'gapped'  => 'Lähes-osuma-klooni — harkitse poikkeavan osan parametrisointia tai molempien kopioiden yhdenmukaistamista.',
            'demoted' => 'Alennettu nimellä :stratum — tämä on muoto, jota kyseinen kerros kuvaa, joten poista se vain, jos toisto ei ole tarkoitus.',
            'extract' => 'Harkitse jaettujen rivien eriyttämistä uudelleenkäytettäväksi metodiksi, luokaksi tai traitiksi.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Käyttö:',
            'invocation' => '  phpcpd [valinnat] <kansio>',
        ],
        'group' => [
            'selecting' => 'Tiedostojen valintavalinnat',
            'orphans'   => 'Orpojen symbolien tunnistus (kuollut koodi)',
            'analysing' => 'Tiedostojen analysointivalinnat',
            'general'   => 'Yleiset valinnat',
            'reporting' => 'Raportin generointivalinnat',
            'ci'        => 'CI-integraatiovalinnat',
        ],
        'option' => [
            'suffix'              => 'Sisällytä tiedostot, joiden nimet päättyvät päätteeseen <suffix> (oletus: :default; toistettava)',
            'exclude'             => 'Jätä pois tiedostot, joiden polussa on <path> (toistettava)',
            'preset'              => 'Käytä kehyksen esiasetusta (esim. laravel): asettaa järkevät polut, päätteet ja poissulkemiset',
            'triage'              => 'Suorita vaiheen 0 korpustriaasi ennen tunnistusta (käytössä oletuksena; tämä pyytää sitä nimenomaisesti)',
            'no_triage'           => 'Ohita vaihe 0 kokonaan: yhtään tiedostoa ei merkitä yhdistämättömäksi, varjostetuksi, toimittajan tai johdetuksi tiedostoksi',
            'triage_posture'      => 'Mitä triaasi tekee tiedostolle, jonka se merkitsee: hylkää sen skannauksesta (oletus) tai merkitsee sen eikä tee mitään muuta',
            'no_preset'           => 'Älä ota automaattisesti käyttöön kehyksen esiasetusta, kun sellainen havaitaan (tunnistus ilmoittaa itsestään; --preset= ohittaa tämän)',
            'no_default_excludes' => 'Skannaa myös generoidut ja välimuistipuut (vendor, node_modules, .phpstan.cache, build, ...), jotka ohitetaan oletuksena',
            'allow_root_scan'     => 'Salli skannausjuureksi / tai lähimmän composer.json-tiedoston yläpuolinen juuri (kielletty oletuksena: `phpcpd /` on lähes aina kirjoitusvirhe komennosta `phpcpd ./`)',
            'orphans'             => 'Tunnista orvot symbolit (viittaamattomat luokat, rajapinnat, traitit, enumit, funktiot) kloonien sijasta',
            'no_suppress'         => 'Poista vaimennussäännöt nimellä, pilkuilla erotettuna, tai "all" (:rules)',
            'fail_on'             => 'Tulosetasot, jotka saavat ajon poistumaan nollasta poikkeavalla koodilla, pilkuilla erotettuna (oletus: :default)',
            'explain'             => 'Listaa jokainen vaimennettu symboli ja sen vaimentanut sääntö pelkän laskemisen sijaan',
            'rk'                  => 'Vain Rabin-Karp (tarkat/tyypin 1 kloonit; nopeampi, ei uudelleenjärjestelyn tunnistusta). Oletusajo suorittaa sekä Rabin-Karpin että TokenBagin.',
            'min_lines'           => 'Identtisten rivien vähimmäismäärä (oletus: :default)',
            'min_tokens'          => 'Identtisten tokenien vähimmäismäärä (oletus: :default)',
            'language'            => 'Raportin kieli (oletus: :default)',
            'verbose'             => 'Tulosta kahdennettu koodi jokaiselle kloonille',
            'algorithm'           => 'Yhden algoritmin ohitus (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBagin päällekkäisyyskynnys (oletus: :default)',
            'raw'                   => 'Vertaa raakatekstiä: myös tunnisteiden on täsmättävä (poistaa oletusnormalisoinnin käytöstä)',
            'fuzzy'                 => 'Nimisokea normalisointi: kuten oletus mutta ilman tyyppiankkuria (tutkimus; E2 mittasi sen hallituksi)',
            'type_anchored'         => 'Pidä tyyppiavainsanat konkreettisina normalisoinnissa (oletuksena päällä; --fuzzy poistaa käytöstä)',
            'min_confidence'        => 'Listaa vain löydökset, jotka malli pisteyttää vähintään <log-odds>; loput lasketaan, luetaan --hidden-valitsimella, niitä ei koskaan hylätä ja ne ohjaavat yhä --fail-on-valitsinta',
            'hidden'                => 'Listaa löydökset, jotka --min-confidence pidätti',
            'cache'                 => 'Välimuistita tulokset hakemistoon \'.phpcpd-cache/\' — osuma vaatii jokaisen tiedoston muuttumattomana, joten se palvelee yhden commitin toistoa eikä seuraavaa',
            'acknowledged'        => 'Lue sitoutustunnustuskirjanpito tiedostosta <file>: listattu kahdennus alennetaan, sitä ei piiloteta koskaan, ja merkinnät, joiden koodi on muuttunut, vanhentuvat ja niistä raportoidaan',
            'write_acknowledged'  => 'Kirjoita tämän ajon tulokset tiedostoon <file> tunnustuskirjanpitona tarkistusta ja sitouttamista varten',
            'log_pmd'             => 'Kirjoita loki PMD-CPD XML -muodossa tiedostoon <file>',
            'log_json'            => 'Kirjoita loki JSON-muodossa tiedostoon <file>',
            'log_sarif'           => 'Kirjoita loki SARIF 2.1.0 -muodossa tiedostoon <file> (GitHub Code Scanning -toimintoa varten)',
            'cache_dir'           => 'Lue/kirjoita välimuisti polusta <path> (implikoi --cache; ohittaa oletuskansion)',
            'incremental'           => 'Tiedostokohtainen inkrementaalinen indeksi: tokenisoi uudelleen vain muuttuneet tiedostot (rabin-karp tai unified, ei yhdistetty oletus; käyttää välimuistihakemistoa)',
            'config'              => 'Lue asetukset tiedostosta <file> (oletus: ./phpcpd.ini, jos olemassa; avaimet ovat pitkät valintanimet)',
            'show_config'         => 'Tulosta voimassa olevat asetukset, mistä kukin tuli, ja poistu',
            'no_config'           => 'Ohita ./phpcpd.ini',
            'help'                => 'Tulosta tämä ohje',
            'version'             => 'Tulosta versiotiedot',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-nextin tunnustuskirjanpito',
            'what'  => 'Jokainen rivi tallentaa yhden kahdennuksen, jota tämä projekti on tarkastellut ja päättänyt elää sen kanssa. Tunnustettu löydös on ALENNETTU, sitä ei koskaan piiloteta: siitä raportoidaan edelleen, se lasketaan edelleen ja se ohjaa edelleen poistumiskoodia.',
            'key'   => 'Avain on kahdennuksen kummankin puolen sisältö tiivisteenä — ei polku eikä rivinumero. Siksi kumman tahansa kopion muokkaaminen tekee merkinnästä vanhentuneen ja löydös vaaditaan uudelleen, kun taas koodin siirteminen ei muuta mitään. Merkintä, joka ei enää vastaa mitään, raportoidaan vanhentuneena, jotta se voidaan poistaa.',
            'note'  => 'Sarkaimen jälkeinen teksti on ihmisen tekemä huomautus. Siihen ei koskaan verrata.',
        ],
    ],
];
