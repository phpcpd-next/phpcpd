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
 * Svensk översättning (Swedish translation).
 */

return [
    'frame' => [
        'error'   => 'FEL: :message',
        'warning' => 'VARNING: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'I :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Konfigurationsfilen hittades inte: :path',
        ],
        'unparsable' => [
            'config' => 'Konfigurationsfilen kunde inte tolkas: :path',
        ],
        'unknown' => [
            'option'  => 'Okänt alternativ :flag.',
            'setting' => 'Okänd inställning ":name" i :file',
        ],
        'needsValue' => [
            'option'  => 'Alternativet :flag behöver ett värde.',
            'setting' => 'Inställningen ":name" i :file behöver ett värde.',
        ],
        'takesNoValue' => [
            'option' => 'Alternativet :flag tar inget värde.',
        ],
        'invalidValue' => [
            'option' => 'Ogiltigt värde ":value" för :flag (tillåtet: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Den enklade motorn (unified engine) behöver :flag på minst :floor (angivet: :given). Under det sjunker winnow-fönstret under 4 och indexet slutar vara ett stickprov, så motorn vägrar istället för att tyst degradera till en fullständig sökning.',
        ],
        'unwired' => [
            'option' => 'Alternativet :flag saknar inställningskoppling.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Vägrar att skanna filsystemets rot (:path). Menade du "./"?',
            'aboveProject'   => 'Vägrar att skanna :path: den är ovanför projektroten :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Inga filer hittades att skanna.',
            'afterTriage' => 'Inga filer kvar att skanna efter triagering.',
        ],
        'missingArgument' => [
            'directory' => 'Ingen katalog angavs.',
        ],
        'writeFailed' => [
            'report' => 'Kunde inte skriva rapporten till :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Skrev endast av (:written) totalt byte (:total) av rapporten till :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'förinställningen ":preset" anger skanningsvägar (:declared), saknade (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans kan inte se hela projektet.',
            'belowRoots'     => '  Varje skanningsrot är under autoload-rötterna för :manifest.',
            'uncoveredRoots' => '  autoload-vägar som deklareras i :manifest men aldrig öppnades (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset upptäckt — förinställning tillämpad (--no-preset för att inaktivera)',
        ],
        'cache' => [
            'hit' => '(cache-träff)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag är föråldrad; använd --algorithm=unified — som ännu inte rapporterar varje plats som token bag gör, så den förblir valbar)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignoreras i kombinerat läge)',
            'unsupported' => '(--incremental ignoreras: endast rabin-karp- och unified-algoritmerna har ett inkrementellt index)',
            'index'       => '(inkrementellt index: :reused återanvända, :scanned skannade)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Inga kodkloner hittades.',
            'heading'        => 'Hittade kodkloner (:clones):gapped:reordered, duplicerade rader (:lines), filer (:files):',
            'gapped'         => ', inkonsekventa (:count)',
            'reorderedCount' => ', omordnade (:count)',
            'reordered'      => '[omordnad]',
            'unreadable'     => 'oläsbara filer (:count) — i ingendera totalen:',
            'strataAsserted' => ':asserted hävdade, 0 degraderade.',
            'strataSplit'    => ':asserted hävdade, :demoted degraderade (:detail).',
            'settled'         => 'läsningar förkastade som redan beskrivna (:count).',
            'unfounded'      => 'fynd förkastade som overifierade (:count).',
            'hiddenLine'      => ':count av :total fynd dolda under konfidensen :threshold — --hidden listar dem.',
            'hiddenHeading'   => 'Dolda under konfidensen :threshold (:count):',
            'coverage'         => ':percentage av de skannade raderna (:lines) är duplicerad kod.',
            'literals'         => '[literaler skiljer sig (:count)]',
            'confidence'       => 'tillförlitlighet :score (:terms)',
            'functions'        => 'i :names',
            'sizes'          => 'Rader per klon: genomsnitt (:average), störst (:largest).',
        ],
        'ledger' => [
            'line'      => 'Erkända: av (:acknowledged) fynd (:total) degraderade av huvudboken; föråldrade (:stale).',
            'staleNote' => 'föråldrade (koden den erkände har ändrats): :note',
            'wrote'     => 'erkännanden skrivna (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (saknas)',
            'source' => [
                'default'     => 'standard',
                'commandLine' => 'kommandorad',
                'builtIn'     => '    inbyggda standardvärden',
            ],
            'fallback'    => ' återgår till inbyggd standard',
            'layers'      => '  Lager, lägsta prioritet först:',
        ],
        'run' => [
            'throughput' => ' — filer (:count) med :rate/s',
            'files'    => ' — filer (:count)',
            'usage'  => 'Tid: :duration, Minne: :memory MB',
            'banner' => 'phpcpd :version av :author — efter :origin av :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Skanningsrot: :root',
            'roots'              => 'Skanningsrötter:',
            'count' => [
                'file'       => 'filer (:count)',
                'directory'  => 'rötter (:count)',
                'pattern'    => 'exkluderingar (:count)',
                'unreadable' => 'oläsbara (:count)',
                'generated'  => 'genererade (:count)',
            ],
            'counts'             => 'Skannade :counts',
        ],
        'triage' => [
            'nothing'  => 'Triagering: inget märkt; varje fil är programtext.',
            'removed'  => 'Triagering: filer (:total), borttagna (:removed)',
            'labelled' => 'Triagering: filer (:total), märkta (:labelled), inget borttaget',
        ],
        'orphan' => [
            'none'         => 'Inga övergivna symboler hittades (symboler (:symbols) i filer (:files)).',
            'found'        => 'övergivna symboler (:count):',
            'possible'     => 'möjliga övergivna (:count) — granska innan borttagning:',
            'advisory'     => 'övergivna symboler (:count) — rådgivande, påverkar inte utgångskoden:',
            'notShown'     => 'ytterligare övergivna fynd visas inte (:count) — kör --orphans för att granska.',
            'suppressed'   => 'Undertryckta (:count): :census',
            'explainHint'  => '  → --explain för att lista dem',
            'wholeFile'    => '    ⤷ hela filen är okopplad — ingen symbol som deklareras här refereras',
            'supersededBy' => '    ⤷ ser ut som en ersatt kopia av :name',
            'summary'      => 'Skannade symboler (:symbols) i filer (:files); övergivna (:orphaned), möjliga (:possible), undertryckta (:suppressed), planerade (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'utanför den filmängd som detta projekts egna standardexkluderingar lämnar — ett genererat träd som kanske inte vittnar om kopplingar',
            'declaredHere'  => 'symboler deklarerade här (:count), ingen av dem refereras någonstans i projektet',
            'foreignNs'     => 'deklarerar :namespaces — en namnrymd som ingen composer.json ovanför den deklarerar, utanför varje katalog de kopplar',
        ],
        'role' => [
            'noStatements' => 'inga påståenden på toppnivå efter ingressen',
            'coupled'      => ':registrations av :statements påståenden på toppnivå är registreringsuttryck, men de delar en variabel',
            'independent'  => ':registrations av :statements påståenden på toppnivå är dataflödesoberoende registreringsuttryck',
        ],
        'orphan' => [
            'guard'          => 'deklarerad inom ett existensskydd — polyfill eller kompabilitetsbrygga',
            'entrypoint'     => 'deklarerad i :namespace — anropad av ramverkets konvention',
            'partialProject' => '  En symbol kallas död när *ingenting* refererar till den, vilket är ett påstående om
  hela projektet. Kod utanför denna skanning kan fortfarande referera till det som rapporteras här.',
            'evidence' => [
                'nameAt'   => 'namnet visas vid',
                'loopAt'   => 'upptäckt av loopen vid',
                'suffixAt' => 'suffix deklarerat vid',
                'namedIn'  => 'namngiven i',
            ],
            'plannedServed'  => 'refereras nu — @phpcpd-planned har fyllt sitt syfte och kan tas bort',
            'manifest'       => 'deklarerad i en composer autoload.files startpunkt',
            'foreignNs'      => 'deklarerad utanför projektets egna namnrymder (kompatibilitetsbrygga)',
            'fixture'        => 'test-fixture — laddad via sökväg eller namngiven som en sträng, aldrig refererad',
            'discovery'      => 'upptäckt av en kantskanning — instansierad från sitt filnamn bakom class_exists',
            'convention'     => 'följeslagarklass — :base använder :trait, som löser detta namn med suffix vid körtid',
            'interface'      => 'aldrig refererad (gränssnitt — kan implementeras utanför den skannade mängden)',
            'trait'          => 'aldrig refererad (trait — kan användas av klasser utanför den skannade mängden)',
            'abstract'       => 'aldrig refererad (abstrakt — kan utökas utanför den skannade mängden)',
            'inString'       => 'aldrig refererad i kod; namnet visas i en strängliteral (möjlig dynamisk användning)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Villkorligt deklarerad (polyfill / kompatibilitetsbrygga)',
            'fixtures'    => 'Test-fixtures (laddade via sökväg eller namn)',
            'config'      => 'Registrerad i en konfigurationsfil',
            'template'    => 'Refererad från en mall (blade / twig / latte)',
            'manifest'    => 'Refererad från composer.json',
            'namespace'   => 'Deklarerad utanför projektets egna namnrymder (kompatibilitetsbrygga)',
            'keep'        => 'Markerad som behållen (@api / @phpcpd-keep)',
            'entrypoint'  => 'Ramverkets startpunkter (attribut / testklass)',
            'discovery'   => 'Upptäckt av en kantskanning (instansierad från sitt filnamn)',
            'convention'  => 'Följeslagarklass namngiven efter konvention (suffix deklarerat av en trait)',
            'planned'     => 'Planerad, ännu inte kopplad',
            'none'        => 'Ingen referens hittades',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Skicka med --allow-root-scan om du verkligen menade hela filsystemet.',
            'allowOutside' => 'Skicka med --allow-root-scan för att skanna utanför projektet ändå.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Skanna projektroten för ett resultat värt att agera på.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard agerar på etiketterna; --no-triage hoppar över fasen)',
            'explain' => '  (--explain listar varje fil och bevisen för eller emot den)',
        ],
        'clone' => [
            'gapped'  => 'Nästan-träff-klon — överväg att parametrisera den avvikande delen eller anpassa båda kopiorna.',
            'demoted' => 'Degraderad som :stratum — detta är den form som det lagret beskriver, så extrahera den endast om upprepningen inte är poängen.',
            'extract' => 'Överväg att extrahera de delade raderna till en återanvändbar metod, klass eller trait.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Användning:',
            'invocation' => '  phpcpd [alternativ] <katalog>',
        ],
        'group' => [
            'selecting' => 'Alternativ för filval',
            'orphans'   => 'Identifiering av övergivna symboler (död kod)',
            'analysing' => 'Alternativ för filanalys',
            'general'   => 'Allmänna alternativ',
            'reporting' => 'Alternativ för rapportgenerering',
            'ci'        => 'Alternativ för CI-integration',
        ],
        'option' => [
            'suffix'              => 'Inkludera filer med namn som slutar på <suffix> (standard: :default; repeterbar)',
            'exclude'             => 'Exkludera filer med <path> i sökvägen (repeterbar)',
            'preset'              => 'Tillämpa en ramverksförinställning (t.ex. laravel): sätter rimliga sökvägar, suffix och exkluderingar',
            'triage'              => 'Kör fas 0-korpustriagering före detektering (på som standard; detta begär det explicit)',
            'no_triage'           => 'Hoppa över fas 0 helt: ingen fil märks som okopplad, skuggad, från leverantör eller härledd',
            'triage_posture'      => 'Vad triagering gör med en fil den märker: kastar bort den från skanningen (standard), eller märker den och inget annat',
            'no_preset'           => 'Tillämpa inte automatiskt en ramverksförinställning när en upptäcks (detektering meddelar sig själv; --preset= åsidosätter detta)',
            'no_default_excludes' => 'Skanna genererade och cache-träd också (vendor, node_modules, .phpstan.cache, build, ...), vilka hoppas över som standard',
            'allow_root_scan'     => 'Tillåt en skanningsrot på / eller en rot ovanför närmaste composer.json (vägras som standard: `phpcpd /` är nästan alltid ett skrivfel för `phpcpd ./`)',
            'orphans'             => 'Upptäck övergivna symboler (oanserade klasser, gränssnitt, traits, enums, funktioner) istället för kloner',
            'no_suppress'         => 'Stäng av undertryckregler efter namn, kommaseparerade, eller "all" (:rules)',
            'fail_on'             => 'Resultatnivåer som gör att körningen avslutas med nollskild kod, kommaseparerade (standard: :default)',
            'explain'             => 'Lista varje undertryckt symbol och regeln som undertryckte den, istället för att bara räkna dem',
            'rk'                  => 'Endast Rabin-Karp (exakta/typ 1-kloner; snabbare, ingen omordningsdetektering). Standardkörningen kör både Rabin-Karp och TokenBag.',
            'min_lines'           => 'Minsta antal identiska rader (standard: :default)',
            'min_tokens'          => 'Minsta antal identiska tokens (standard: :default)',
            'language'            => 'Språk för rapporten (standard: :default)',
            'verbose'             => 'Skriv ut den duplicerade koden för varje klon',
            'algorithm'           => 'Överskrivning av enskild algoritm (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'TokenBag överlappningströskel (standard: :default)',
            'raw'                   => 'Jämför rå text: även identifierare måste stämma (stänger av standardnormaliseringen)',
            'fuzzy'                 => 'Namnblind normalisering: som standard men utan typankaret (forskning; E2 mätte den som dominerad)',
            'type_anchored'         => 'Behåll typnyckelord konkreta vid normalisering (på som standard; --fuzzy stänger av det)',
            'min_confidence'        => 'Lista bara fynd som modellen poängsätter till <log-odds> eller högre; övriga räknas, kan läsas med --hidden, förkastas aldrig och styr fortfarande --fail-on',
            'hidden'                => 'Lista de fynd som --min-confidence höll tillbaka',
            'cache'                 => 'Cacha resultat i \'.phpcpd-cache/\' — en träff kräver varje fil oförändrad, så den tjänar en omkörning av en commit snarare än nästa',
            'acknowledged'        => 'Läs en incheckad erkännandehuvudbok från <file>: listad duplikering degraderas, döljs aldrig, och poster vars kod har ändrats löper ut och rapporteras',
            'write_acknowledged'  => 'Skriv denna körnings fynd till <file> som en erkännandehuvudbok, för granskning och incheckning',
            'log_pmd'             => 'Skriv logg i PMD-CPD XML-format till <file>',
            'log_json'            => 'Skriv logg i JSON-format till <file>',
            'log_sarif'           => 'Skriv logg i SARIF 2.1.0-format till <file> (för GitHub Code Scanning)',
            'cache_dir'           => 'Läs/skriv cache från <path> (innebär --cache; åsidosätter standardkatalogen)',
            'incremental'           => 'Inkrementellt index per fil: tokeniserar bara ändrade filer på nytt (rabin-karp eller unified, inte det kombinerade standardläget; använder cachekatalogen)',
            'config'              => 'Läs inställningar från <file> (standard: ./phpcpd.ini när den finns; nycklarna är de långa alternativnamnen)',
            'show_config'         => 'Skriv ut gällande inställningar, varifrån var och en kom, och avsluta',
            'no_config'           => 'Ignorera ./phpcpd.ini',
            'help'                => 'Skriv ut denna hjälp',
            'version'             => 'Skriv ut versionsinformation',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next erkännandehuvudbok',
            'what'  => 'Varje rad registrerar en duplicering som detta projekt har granskat och bestämt sig för att leva med. Ett erkänt fynd är DEGRADERAT, döljs aldrig: det rapporteras fortfarande, räknas fortfarande och styr fortfarande utgångskoden.',
            'key'   => 'Nyckeln är innehållet i varje sida av dupliceringen, hashuerat — inte en sökväg och inte ett radnummer. Så redigering av antingen kopia gör posten föråldrad och fyndet hävdas igen, medan flyttning av koden ändrar ingenting. En post som inte längre matchar något rapporteras som föråldrad så att den kan tas bort.',
            'note'  => 'Texten efter tabulatortecknet är en mänsklig anteckning. Den matchas aldrig mot.',
        ],
    ],
];
