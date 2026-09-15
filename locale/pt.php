<?php

declare(strict_types=1);
/*
 * Este arquivo é parte de PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Para obter informações completas sobre direitos autorais e licença,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 */
/* Tradução para o português.
 *
 * Chaves, marcadores de posição, contagens e o que pertence aqui em geral:
 * consulte docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'ERRO: :message',
        'warning' => 'AVISO: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'Em :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Arquivo de configuração não encontrado: :path',
        ],
        'unparsable' => [
            'config' => 'Não foi possível analisar o arquivo de configuração: :path',
        ],
        'unknown' => [
            'option'  => 'Opção desconhecida :flag.',
            'setting' => 'Configuração desconhecida ":name" em :file',
        ],
        'needsValue' => [
            'option'  => 'A opção :flag precisa de um valor.',
            'setting' => 'A configuração ":name" em :file precisa de um valor.',
        ],
        'takesNoValue' => [
            'option' => 'A opção :flag não aceita valor.',
        ],
        'invalidValue' => [
            'option' => 'Valor inválido ":value" para :flag (permitido: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'O mecanismo unificado precisa de :flag de pelo menos :floor (fornecido: :given). Abaixo disso, a janela de filtragem cai abaixo de 4 e o índice deixa de ser uma amostra, portanto o mecanismo recusa em vez de degradar silenciosamente para uma varredura exaustiva.',
        ],
        'unwired' => [
            'option' => 'A opção :flag não tem associação de configuração.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Recusando escanear a raiz do sistema de arquivos (:path). Você quis dizer "./"?',
            'aboveProject'   => 'Recusando escanear :path: está acima da raiz do projeto :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nenhum arquivo encontrado para escanear.',
            'afterTriage' => 'Nenhum arquivo restante para escanear após a triagem.',
        ],
        'missingArgument' => [
            'directory' => 'Nenhum diretório especificado.',
        ],
        'writeFailed' => [
            'report' => 'Não foi possível gravar o relatório em :path:detail',
        ],
        'writePartial' => [
            'report' => 'Foram gravados apenas de (:written) bytes (:total) do relatório em :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'o preset ":preset" declara caminhos de varredura (:declared), faltando (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans não pode ver todo o projeto.',
            'belowRoots'     => '  Cada raiz de varredura está abaixo das raízes de autoload de :manifest.',
            'uncoveredRoots' => '  caminhos de autoload declarados em :manifest mas nunca abertos (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detectado — preset aplicado (--no-preset para desativar)',
        ],
        'cache' => [
            'hit' => '(acerto de cache)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag está obsoleto; use --algorithm=unified — o qual ainda não relata todos os locais que o token bag relata, por isso permanece selecionável)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignorado no modo combinado)',
            'unsupported' => '(--incremental ignorado: apenas os algoritmos rabin-karp e unified possuem um índice incremental)',
            'index'       => '(índice incremental: :reused reutilizados, :scanned escaneados)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nenhum clone de código encontrado.',
            'heading'        => 'Encontrados clones de código (:clones):gapped:reordered, linhas duplicadas (:lines), arquivos (:files):',
            'gapped'         => ', inconsistentes (:count)',
            'reorderedCount' => ', reordenados (:count)',
            'reordered'      => '[reordenado]',
            'unreadable'     => 'arquivos ilegíveis (:count) — em nenhum dos totais:',
            'strataAsserted' => ':asserted afirmados, 0 rebaixados.',
            'strataSplit'    => ':asserted afirmados, :demoted rebaixados (:detail).',
            'settled'         => 'leituras descartadas por já estarem descritas (:count).',
            'unfounded'      => 'achados descartados por não verificados (:count).',
            'hiddenLine'      => ':count de :total achados ocultos abaixo da confiança :threshold — --hidden lista-os.',
            'hiddenHeading'   => 'Ocultos abaixo da confiança :threshold (:count):',
            'coverage'         => ':percentage das linhas analisadas (:lines) é código duplicado.',
            'literals'         => '[os literais diferem (:count)]',
            'confidence'       => 'confiança :score (:terms)',
            'functions'        => 'em :names',
            'sizes'          => 'Linhas por clone: média (:average), maior (:largest).',
        ],
        'ledger' => [
            'line'      => 'Reconhecidos: de (:acknowledged) achados (:total) rebaixados pelo ledger; obsoletos (:stale).',
            'staleNote' => 'obsoleto (o código que reconheceu mudou): :note',
            'wrote'     => 'reconhecimentos gravados (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (ausente)',
            'source' => [
                'default'     => 'padrão',
                'commandLine' => 'linha de comando',
                'builtIn'     => '    padrões integrados',
            ],
            'fallback'    => ' retornando ao padrão integrado',
            'layers'      => '  Camadas, menor precedência primeiro:',
        ],
        'run' => [
            'throughput' => ' — arquivos (:count) a :rate/s',
            'files'    => ' — arquivos (:count)',
            'usage'  => 'Tempo: :duration, Memória: :memory MB',
            'banner' => 'phpcpd :version por :author — baseado em :origin por :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Raiz de varredura: :root',
            'roots'              => 'Raízes de varredura:',
            'count' => [
                'file'       => 'arquivos (:count)',
                'directory'  => 'raízes (:count)',
                'pattern'    => 'exclusões (:count)',
                'unreadable' => 'ilegíveis (:count)',
                'generated'  => 'gerados (:count)',
            ],
            'counts'             => 'Escaneados :counts',
        ],
        'triage' => [
            'nothing'  => 'Triagem: nada rotulado; cada arquivo é texto de programa.',
            'removed'  => 'Triagem: arquivos (:total), removidos (:removed)',
            'labelled' => 'Triagem: arquivos (:total), rotulados (:labelled), nenhum removido',
        ],
        'orphan' => [
            'none'         => 'Nenhum símbolo órfão encontrado (símbolos (:symbols) em arquivos (:files)).',
            'found'        => 'símbolos órfãos (:count):',
            'possible'     => 'possíveis órfãos (:count) — revise antes de remover:',
            'advisory'     => 'símbolos órfãos (:count) — consultivo, não afeta o código de saída:',
            'notShown'     => 'mais achados de órfãos não mostrados (:count) — execute --orphans para revisar.',
            'suppressed'   => 'Suprimidos (:count): :census',
            'explainHint'  => '  → --explain para listar',
            'wholeFile'    => '    ⤷ todo o arquivo está desconectado — nenhum símbolo declarado aqui é referenciado',
            'supersededBy' => '    ⤷ parece uma cópia substituída de :name',
            'summary'      => 'símbolos (:symbols) escaneados em arquivos (:files); órfãos (:orphaned), possíveis (:possible), suprimidos (:suppressed), planejados (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'fora do conjunto de arquivos que as próprias exclusões padrão deste projeto deixam — uma árvore gerada, que pode não atestar a vinculação (wiring)',
            'declaredHere'  => 'símbolos declarados aqui (:count), nenhum referenciado em nenhuma parte do projeto',
            'foreignNs'     => 'declara :namespaces — um namespace que nenhum composer.json acima dele declara, fora de cada diretório que vinculam',
        ],
        'role' => [
            'noStatements' => 'nenhuma instrução de nível superior após o preâmbulo',
            'coupled'      => ':registrations de :statements instruções de nível superior são expressões de registro, mas compartilham uma variável',
            'independent'  => ':registrations de :statements instruções de nível superior são expressões de registro independentes do fluxo de dados',
        ],
        'orphan' => [
            'guard'          => 'declarado dentro de uma guarda de existência — polyfill ou shim de compatibilidade',
            'entrypoint'     => 'declarado em :namespace — invocado por convenção do framework',
            'partialProject' => '  Um símbolo é chamado de morto quando *nada* o referencia, o que é uma afirmação sobre todo o
  projeto. Código fora desta varredura ainda pode referenciar o que é relatado aqui.',
            'evidence' => [
                'nameAt'   => 'o nome aparece em',
                'loopAt'   => 'descoberto pelo loop em',
                'suffixAt' => 'sufixo declarado em',
                'namedIn'  => 'nomeado em',
            ],
            'plannedServed'  => 'referenciado agora — @phpcpd-planned cumpriu seu propósito e pode ser removido',
            'manifest'       => 'declarado em um ponto de entrada autoload.files do composer',
            'foreignNs'      => 'declarado fora dos namespaces próprios do projeto (shim de compatibilidade)',
            'fixture'        => 'fixture de teste — carregado por caminho ou nomeado como uma string, nunca referenciado',
            'discovery'      => 'descoberto por uma varredura de diretório — instanciado a partir de seu nome de arquivo atrás de class_exists',
            'convention'     => 'classe complementar — :base usa :trait, que resolve este nome por sufixo em tempo de execução',
            'interface'      => 'nunca referenciado (interface — pode ser implementado fora do conjunto escaneado)',
            'trait'          => 'nunca referenciado (trait — pode ser usado por classes fora do conjunto escaneado)',
            'abstract'       => 'nunca referenciado (abstrata — pode ser estendido fora do conjunto escaneado)',
            'inString'       => 'nunca referenciado no código; o nome aparece em um literal de string (possível uso dinâmico)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Declarado condicionalmente (polyfill / shim de compatibilidade)',
            'fixtures'    => 'Fixtures de teste (carregados por caminho ou por nome)',
            'config'      => 'Registrado em um arquivo de configuração',
            'template'    => 'Referenciado a partir de um template (blade / twig / latte)',
            'manifest'    => 'Referenciado a partir de composer.json',
            'namespace'   => 'Declarado fora dos namespaces próprios do projeto (shim de compatibilidade)',
            'keep'        => 'Marcado como mantido (@api / @phpcpd-keep)',
            'entrypoint'  => 'Pontos de entrada do framework (atributo / classe de teste)',
            'discovery'   => 'Descoberto por uma varredura de diretório (instanciado a partir de seu nome de arquivo)',
            'convention'  => 'Classe complementar nomeada por convenção (sufixo declarado por um trait)',
            'planned'     => 'Planejado, ainda não vinculado',
            'none'        => 'Nenhuma referência encontrada',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Passe --allow-root-scan se você realmente quis dizer todo o sistema de arquivos.',
            'allowOutside' => 'Passe --allow-root-scan para escanear fora do projeto de qualquer maneira.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Escanear a raiz do projeto para obter um resultado sobre o qual valha a pena agir.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard age nas etiquetas; --no-triage pula o estágio)',
            'explain' => '  (--explain lista cada arquivo e a evidência a favor ou contra)',
        ],
        'clone' => [
            'gapped'  => 'Clone quase exato — considere parametrizar a parte divergente ou alinhar ambas as cópias.',
            'demoted' => 'Rebaixado como :stratum — esta é a forma que esse estrato descreve, então extraia-o apenas se a repetição não for o ponto.',
            'extract' => 'Considere extrair as linhas compartilhadas em um método, classe ou trait reutilizável.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Uso:',
            'invocation' => '  phpcpd [opções] <diretório>',
        ],
        'group' => [
            'selecting' => 'Opções para seleção de arquivos',
            'orphans'   => 'Detecção de órfãos (código morto)',
            'analysing' => 'Opções para análise de arquivos',
            'general'   => 'Opções gerais',
            'reporting' => 'Opções para geração de relatórios',
            'ci'        => 'Opções para integração com CI',
        ],
        'option' => [
            'suffix'              => 'Incluir arquivos com nomes terminados em <suffix> (padrão: :default; repetível)',
            'exclude'             => 'Excluir arquivos com <path> em seu caminho (repetível)',
            'preset'              => 'Aplicar um preset de framework (ex. laravel): define caminhos, sufixos e exclusões sensatos',
            'triage'              => 'Executar a Triagem de Corpus do Estágio 0 antes da detecção (ativado por padrão; isto solicita explicitamente)',
            'no_triage'           => 'Pular o Estágio 0 completamente: nenhum arquivo é rotulado como desconectado, sombreado (shadowed), vendored ou derivado',
            'triage_posture'      => 'O que a triagem faz com um arquivo que ela rotula: descartá-lo da varredura (padrão) ou rotulá-lo e nada mais',
            'no_preset'           => 'Não aplicar automaticamente um preset de framework quando um for detectado (a detecção anuncia a si mesma; --preset= substitui isso)',
            'no_default_excludes' => 'Escanear árvores geradas e de cache também (vendor, node_modules, .phpstan.cache, build, ...), que são ignoradas por padrão',
            'allow_root_scan'     => 'Permitir uma raiz de varredura de / ou uma raiz acima do composer.json mais próximo (recusado por padrão: `phpcpd /` é quase sempre um erro de digitação para `phpcpd ./`)',
            'orphans'             => 'Detectar símbolos órfãos (classes, interfaces, traits, enums, funções não referenciadas) em vez de clones',
            'no_suppress'         => 'Desativar regras de supresão por nome, separadas por vírgula, ou "all" (:rules)',
            'fail_on'             => 'Níveis de resultado que fazem a execução sair com código diferente de zero, separados por vírgula (padrão: :default)',
            'explain'             => 'Listar cada símbolo suprimido e a regra que o suprimiu, em vez de apenas contá-los',
            'rk'                  => 'Apenas Rabin-Karp (clones exatos/Tipo-1; mais rápido, sem detecção de reordenação). O padrão executa Rabin-Karp e TokenBag.',
            'min_lines'           => 'Número mínimo de linhas idênticas (padrão: :default)',
            'min_tokens'          => 'Número mínimo de tokens idênticos (padrão: :default)',
            'language'            => 'Idioma para o relatório (padrão: :default)',
            'verbose'             => 'Imprimir o código duplicado para cada clon',
            'algorithm'           => 'Substituição para algoritmo único (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Limiar de sobreposição para TokenBag (padrão: :default)',
            'raw'                   => 'Comparar o texto bruto: os identificadores também têm de coincidir (desliga a normalização predefinida)',
            'fuzzy'                 => 'Normalização cega a nomes: como a predefinida mas sem a âncora de tipos (investigação; E2 mediu-a dominada)',
            'type_anchored'         => 'Manter concretas as palavras-chave de tipo sob normalização (ligado por omissão; --fuzzy desliga-o)',
            'min_confidence'        => 'Listar apenas os achados que o modelo pontua em <log-odds> ou acima; os restantes são contados e legíveis com --hidden, nunca descartados, e continuam a condicionar --fail-on',
            'hidden'                => 'Listar os achados retidos por --min-confidence',
            'cache'                 => 'Guardar resultados em cache em \'.phpcpd-cache/\' — um acerto exige todos os ficheiros inalterados, servindo para repetir um commit e não para o seguinte',
            'acknowledged'        => 'Ler um ledger de reconhecimentos submetido de <file>: a duplicação listada é rebaixada, nunca oculta, e entradas cujo código mudou expiram e são relatadas',
            'write_acknowledged'  => 'Gravar os achados desta execução em <file> como um ledger de reconhecimentos, para revisão e submissão',
            'log_pmd'             => 'Gravar log no formato XML PMD-CPD em <file>',
            'log_json'            => 'Gravar log no formato JSON em <file>',
            'log_sarif'           => 'Gravar log no formato SARIF 2.1.0 em <file> (para GitHub Code Scanning)',
            'cache_dir'           => 'Ler/gravar cache de <path> (implica --cache; substitui o diretório padrão)',
            'incremental'           => 'Índice incremental por arquivo: retokeniza apenas os arquivos alterados (rabin-karp ou unified, não o padrão combinado; usa o diretório de cache)',
            'config'              => 'Ler configurações de <file> (padrão: ./phpcpd.ini quando presente); as chaves são os nomes longos das opções',
            'show_config'         => 'Imprimir as configurações em vigor, de onde cada uma veio e sair',
            'no_config'           => 'Ignorar ./phpcpd.ini',
            'help'                => 'Imprimir esta ajuda',
            'version'             => 'Imprimir informações de versão',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'ledger de reconhecimentos (acknowledgment ledger) do phpcpd-next',
            'what'  => 'Cada linha registra uma duplicação que este projeto analisou e decidiu
manter. Um achado reconhecido é REBAIXADO, nunca oculto: ele ainda
é relatado, ainda é contado e ainda restringe o código de saída.',
            'key'   => 'A chave é o conteúdo de cada lado da duplicação, transformado em hash — não um caminho
e não um número de linha. Portanto, editar qualquer uma das cópias faz com que a entrada expire e o
achado seja afirmado novamente, enquanto mover o código não muda nada. Uma entrada
que não corresponde mais a nada é relatada como obsoleta para que possa ser excluída.',
            'note'  => 'O texto após a tabulação é uma nota humana. Nunca é correspondido.',
        ],
    ],
];
