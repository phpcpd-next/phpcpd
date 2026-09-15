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
 * Tradución ao galego (Galician translation).
 */

return [
    'frame' => [
        'error'   => 'ERRO: :message',
        'warning' => 'AVISO: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'En :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Non se atopou o ficheiro de configuración: :path',
        ],
        'unparsable' => [
            'config' => 'Produciuse un erro ao analizar o ficheiro de configuración: :path',
        ],
        'unknown' => [
            'option'  => 'Opción descoñecida :flag.',
            'setting' => 'Configuración descoñecida ":name" en :file',
        ],
        'needsValue' => [
            'option'  => 'A opción :flag require un valor.',
            'setting' => 'A configuración ":name" en :file require un valor.',
        ],
        'takesNoValue' => [
            'option' => 'A opción :flag non acepta ningún valor.',
        ],
        'invalidValue' => [
            'option' => 'Valor non válido ":value" para a opción :flag (permitido: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'O motor unificado (unified engine) require que a opción :flag sexa polo menos :floor (fornecido: :given). Por debaixo diso, a xanela winnow cae por abaixo de 4 e o índice deixa de ser unha mostra, polo que o motor rexeita a execución en vez de degradarse silenciosamente a unha exploración completa.',
        ],
        'unwired' => [
            'option' => 'A opción :flag non ten ningunha asociación de configuración.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Rexeitouse a exploración da raíz do sistema de ficheiros (:path). Quixeches dicir "./"?',
            'aboveProject'   => 'Rexeitouse a exploración de :path: está por enriba da raíz do proxecto (:project).',
        ],
        'nothingToScan' => [
            'files'       => 'Non se atoparon ficheiros para explorar.',
            'afterTriage' => 'Non quedaron ficheiros para explorar despois da triaxe.',
        ],
        'missingArgument' => [
            'directory' => 'Non se especificou ningún directorio.',
        ],
        'writeFailed' => [
            'report' => 'Produciuse un erro ao escribir o informe en :path: :detail',
        ],
        'writePartial' => [
            'report' => 'Só se escribiron de (:written) bytes (:total) do informe en :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'a preconfiguración ":preset" especifica rutas de exploración (:declared), faltan (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => 'A opción --orphans non pode ver todo o proxecto.',
            'belowRoots'     => '  Cada raíz de exploración está baixo as raíces de auto-carga de :manifest.',
            'uncoveredRoots' => '  rutas de auto-carga declaradas en :manifest pero que nunca se abriron (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detectado — preconfiguración aplicada (--no-preset para desactivala)',
        ],
        'cache' => [
            'hit' => '(acerto na caché)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag está obsoleto; use --algorithm=unified — que aínda non informa de cada localización que informaba o token bag, polo que permanece opcional)',
        ],
        'incremental' => [
            'combined'    => '(--incremental omitido en modo combinado)',
            'unsupported' => '(--incremental omitido: só os algoritmos rabin-karp e unified teñen índice incremental)',
            'index'       => '(índice incremental: :reused reutilizados, :scanned explorados)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Non se atoparon clons de código.',
            'heading'        => 'Atopáronse clons de código (:clones):gapped:reordered, liñas duplicadas (:lines), ficheiros (:files):',
            'gapped'         => ', con ocos (:count)',
            'reorderedCount' => ', reordenados (:count)',
            'reordered'      => '[reordenado]',
            'unreadable'     => 'ficheiros ilexibles (:count) — non se inclúen en ningún total:',
            'strataAsserted' => 'Reivindicados :asserted, 0 degradados.',
            'strataSplit'    => 'Reivindicados :asserted, degradados :demoted (:detail).',
            'settled'         => 'lecturas descartadas por xa estaren descritas (:count).',
            'unfounded'      => 'achados descartados por non verificados (:count).',
            'hiddenLine'      => ':count de :total achados agochados baixo a confianza :threshold — --hidden lístaos.',
            'hiddenHeading'   => 'Agochados baixo a confianza :threshold (:count):',
            'coverage'         => 'O :percentage das liñas analizadas (:lines) é código duplicado.',
            'literals'         => '[os literais difiren (:count)]',
            'confidence'       => 'confianza :score (:terms)',
            'functions'        => 'en :names',
            'sizes'          => 'Liñas por clon: media (:average), maior (:largest).',
        ],
        'ledger' => [
            'line'      => 'Recoñecidos: de (:acknowledged) descubrimentos (:total) degradados polo libro maior; caducados (:stale).',
            'staleNote' => 'caducado (o código que recoñecía cambiou): :note',
            'wrote'     => 'rexistros de recoñecemento escritos (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (falta)',
            'source' => [
                'default'     => 'por defecto',
                'commandLine' => 'liña de comandos',
                'builtIn'     => '    valores predeterminados integrados',
            ],
            'fallback'    => ' volta ao valor predeterminado integrado',
            'layers'      => '  Capas, comezando pola menor prioridade:',
        ],
        'run' => [
            'throughput' => ' — ficheiros (:count) a :rate/s',
            'files'    => ' — ficheiros (:count)',
            'usage'  => 'Tempo: :duration, Memoria: :memory MB',
            'banner' => 'phpcpd :version por :author — despois de :origin por :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Raíz de exploración: :root',
            'roots'              => 'Raíces de exploración:',
            'count' => [
                'file'       => 'ficheiros (:count)',
                'directory'  => 'raíces (:count)',
                'pattern'    => 'exclusións (:count)',
                'unreadable' => 'ilexibles (:count)',
                'generated'  => 'xerados (:count)',
            ],
            'counts'             => 'Explorados: :counts',
        ],
        'triage' => [
            'nothing'  => 'Triaxe: nada marcado; cada ficheiro é texto de programa.',
            'removed'  => 'Triaxe: ficheiros (:total), eliminados (:removed)',
            'labelled' => 'Triaxe: ficheiros (:total), marcados (:labelled), non se eliminou nada',
        ],
        'orphan' => [
            'none'         => 'Non se atoparon símbolos orfanos (símbolos (:symbols) en ficheiros (:files)).',
            'found'        => 'símbolos orfanos (:count):',
            'possible'     => 'posibles orfanos (:count) — verifique antes de eliminar:',
            'advisory'     => 'símbolos orfanos (:count) — consultivos, non afectan ao código de saída:',
            'notShown'     => 'non se amosan máis descubrimentos orfanos (:count) — execute --orphans para comprobalos.',
            'suppressed'   => 'Suprimidos (:count): :census',
            'explainHint'  => '  → --explain para listalos',
            'wholeFile'    => '    ⤷ o ficheiro enteiro está desconectado — ningún símbolo declarado aquí ten referencias',
            'supersededBy' => '    ⤷ parece unha copia substituída de :name',
            'summary'      => 'Explorados símbolos (:symbols) en ficheiros (:files); orfanos (:orphaned), posibles (:possible), suprimidos (:suppressed), planificados (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'fóra do conxunto de ficheiros permitido polas exclusións predeterminadas deste proxecto — unha árbore xerada que pode non probar a conexión',
            'declaredHere'  => 'símbolos declarados aquí (:count), aos que ninguén fai referencia no proxecto',
            'foreignNs'     => 'declara :namespaces — un espazo de nomes que ningún composer.json de riba declara, fóra de calquera directorio que conecte',
        ],
        'role' => [
            'noStatements' => 'non hai sentenzas de nivel superior despois da introdución',
            'coupled'      => ':registrations de :statements sentenzas de nivel superior son expresións de rexistro, pero comparten unha variábel',
            'independent'  => ':registrations de :statements sentenzas de nivel superior son expresións de rexistro independentes do fluxo de datos',
        ],
        'orphan' => [
            'guard'          => 'declarado dentro dunha garda de existencia — polyfill ou ponte de compatibilidade',
            'entrypoint'     => 'declarado en :namespace — chamado pola convención do framework',
            'partialProject' => '  Un símbolo considérase morto cando *nada* apunta a el, o cal é unha afirmación sobre
  o proxecto enteiro. O código fóra desta exploración aínda pode facer referencia ao que se informa aquí.',
            'evidence' => [
                'nameAt'   => 'o nome aparece en',
                'loopAt'   => 'detectado polo bucle en',
                'suffixAt' => 'sufixo declarado en',
                'namedIn'  => 'nomeado en',
            ],
            'plannedServed'  => 'agora ten referencias — @phpcpd-planned cumpriu o seu propósito e pódese eliminar',
            'manifest'       => 'declarado nun punto de entrada composer autoload.files',
            'foreignNs'      => 'declarado fóra dos propios espazos de nomes do proxecto (ponte de compatibilidade)',
            'fixture'        => 'fixture de proba — cargado por ruta ou nomeado como cadea, nunca referenciado',
            'discovery'      => 'descuberto mediante exploración de bordos — instanciado a partir do seu nome de ficheiro detrás de class_exists',
            'convention'     => 'clase compaña — :base usa o trait :trait, que resolve este nome cun sufixo en tempo de execución',
            'interface'      => 'nunca referenciado (interface — pode implementarse fóra do conxunto explorado)',
            'trait'          => 'nunca referenciado (trait — pode ser usado por clases fóra do conxunto explorado)',
            'abstract'       => 'nunca referenciado (abstracto — pode estenderse fóra do conxunto explorado)',
            'inString'       => 'nunca referenciado no código; o nome aparece nun literal de cadea (posible uso dinámico)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Declarado condicionalmente (polyfill / ponte de compatibilidade)',
            'fixtures'    => 'Fixture de proba (cargados por ruta ou nome)',
            'config'      => 'Rexistrado nun ficheiro de configuración',
            'template'    => 'Referenciado desde un modelo (blade / twig / latte)',
            'manifest'    => 'Referenciado desde composer.json',
            'namespace'   => 'Declarado fóra dos propios espazos de nomes do proxecto (ponte de compatibilidade)',
            'keep'        => 'Marcado para conservar (@api / @phpcpd-keep)',
            'entrypoint'  => 'Puntos de entrada do framework (atributo / clase de proba)',
            'discovery'   => 'Descuberto mediante exploración de bordos (instanciado desde o seu nome de ficheiro)',
            'convention'  => 'Clase compaña nomeada por convención (sufixo declarado por trait)',
            'planned'     => 'Planificado, aínda non conectado',
            'none'        => 'Non se atopou ningunha referencia',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Engada a opción --allow-root-scan se realmente pretendía todo o sistema de ficheiros.',
            'allowOutside' => 'Engada --allow-root-scan para explorar fóra do proxecto en calquera caso.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Explore a raíz do proxecto para obter un resultado co que paga a pena traballar.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard actúa segundo as etiquetas; --no-triage omite a fase)',
            'explain' => '  (--explain lista cada ficheiro e as probas a favor ou en contra)',
        ],
        'clone' => [
            'gapped'  => 'Clon de coincidencia achegada — considere parametrizar a parte diverxente ou aliñar ambas as copias.',
            'demoted' => 'Degradado como :stratum — esta é a forma que describe esta capa, polo tanto elimínea só se a repetición non é o obxectivo.',
            'extract' => 'Considere extraer as liñas comúns nun método, clase ou trait reutilizábel.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Uso:',
            'invocation' => '  phpcpd [opcións] <directorio>',
        ],
        'group' => [
            'selecting' => 'Opcións de selección de ficheiros',
            'orphans'   => 'Identificación de símbolos orfanos (código morto)',
            'analysing' => 'Opcións de análise de ficheiros',
            'general'   => 'Opcións xerais',
            'reporting' => 'Opcións de informes',
            'ci'        => 'Opcións de integración CI',
        ],
        'option' => [
            'suffix'              => 'Inclúe ficheiros cuxos nomes rematan en <suffix> (por defecto: :default; repetíbel)',
            'exclude'             => 'Excluír ficheiros cuxa ruta conteña <path> (repetíbel)',
            'preset'              => 'Aplica a preconfiguración do framework (p. ex., laravel): establece rutas razoables, sufixos e exclusións',
            'triage'              => 'Executa a triaxe do corpus da fase 0 antes da detección (activado por defecto; isto pídeo explicitamente)',
            'no_triage'           => 'Omite a fase 0 por completo: ningún ficheiro se marca como desconectado, sombreado, provedor ou xerado',
            'triage_posture'      => 'O que fai a triaxe cun ficheiro que etiqueta: descártao da exploración (por defecto) ou etíquetao e non fai nada máis',
            'no_preset'           => 'Non aplique automaticamente a preconfiguración do framework cando se detecte (--preset= anula isto)',
            'no_default_excludes' => 'Explore tamén árbores xeradas e de caché (vendor, node_modules, .phpstan.cache, build, ...), que se omiten por defecto',
            'allow_root_scan'     => 'Permite a raíz de exploración en / ou na raíz por enriba do composer.json máis achegado (prohibido por defecto: `phpcpd /` é case sempre un erro de dixitación para `phpcpd ./`)',
            'orphans'             => 'Detecta símbolos orfanos (clases, interfaces, traits, enums, funcións desconectadas) en lugar de clons',
            'no_suppress'         => 'Desactiva as regras de supresión por nome, separadas por comas, ou "all" (:rules)',
            'fail_on'             => 'Niveis de resultado que fan que a execución remate cun código distinto de cero, separados por comas (por defecto: :default)',
            'explain'             => 'Lista cada símbolo suprimido e a regra que o suprimiu en lugar dun simple reconto',
            'rk'                  => 'Só Rabin-Karp (clons exactos / tipo 1; máis rápido, sen detección de reordenamento). A execución predeterminada executa tanto Rabin-Karp coma TokenBag.',
            'min_lines'           => 'Número mínimo de liñas idénticas (por defecto: :default)',
            'min_tokens'          => 'Número mínimo de tokens idénticos (por defecto: :default)',
            'language'            => 'Idioma do informe (por defecto: :default)',
            'verbose'             => 'Imprime o código duplicado para cada clon',
            'algorithm'           => 'Anula o algoritmo único (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Limiar de superposición de TokenBag (por defecto: :default)',
            'raw'                   => 'Comparar o texto en bruto: os identificadores tamén deben coincidir (desactiva a normalización predeterminada)',
            'fuzzy'                 => 'Normalización cega aos nomes: como a predeterminada pero sen a ancoraxe de tipos (investigación; E2 mediuna dominada)',
            'type_anchored'         => 'Manter concretas as palabras clave de tipo baixo normalización (activo por defecto; --fuzzy desactívao)',
            'min_confidence'        => 'Listar só os achados que o modelo puntúa en <log-odds> ou por riba; os demais cóntanse, lense con --hidden, nunca se descartan e seguen condicionando --fail-on',
            'hidden'                => 'Listar os achados retidos por --min-confidence',
            'cache'                 => 'Gardar resultados na caché \'.phpcpd-cache/\' — un acerto esixe todos os ficheiros sen cambios, así que serve para repetir un commit e non para o seguinte',
            'acknowledged'        => 'Lec o libro maior de recoñecemento de <file>: a duplicación listada degrádese, nunca se agocha, e as entradas cuxo código cambiou caducan e infórmanse',
            'write_acknowledged'  => 'Escribe os descubrimentos desta execución en <file> como libro maior de recoñecemento, para revisión e commit',
            'log_pmd'             => 'Escribe o rexistro en formato PMD-CPD XML en <file>',
            'log_json'            => 'Escribe o rexistro en formato JSON en <file>',
            'log_sarif'           => 'Escribe o rexistro en formato SARIF 2.1.0 en <file> (para GitHub Code Scanning)',
            'cache_dir'           => 'Lec/escrebe a caché desde a ruta <path> (implica --cache; anula o directorio predeterminado)',
            'incremental'           => 'Índice incremental por ficheiro: só volve tokenizar os ficheiros modificados (rabin-karp ou unified, non o predeterminado combinado; usa o directorio de caché)',
            'config'              => 'Lec as configuracións de <file> (por defecto: ./phpcpd.ini se existe; as claves son os nomes longos de opcións)',
            'show_config'         => 'Imprime as configuracións válidas, a súa orixe e sae',
            'no_config'           => 'Ignora ./phpcpd.ini',
            'help'                => 'Imprime esta axuda',
            'version'             => 'Imprime información sobre a versión',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'libro maior de recoñecemento de phpcpd-next',
            'what'  => 'Cada ringleira rexistra unha duplicación que este proxecto revisou e decidiu aceptar. Un descubrimento recoñecido DEGRÁDESE, nunca se agocha: infórmase igualmente, cóntase igualmente e segue controlando o código de saída.',
            'key'   => 'A chave é o contido resumido (hash) de ambas as partes da duplicación — nin ruta nin número de liña. Polo tanto, editar calquera copia fai que a entrada quede caducada e o descubrimento volva ser requirido, mentres que mover o código non cambia nada. Unha entrada que xa non coincide con nada informarase como caducada para que poida ser eliminada.',
            'note'  => 'O texto despois do tabulador é unha nota humana. O sistema nunca o compara co código.',
        ],
    ],
];
