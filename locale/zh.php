<?php

declare(strict_types=1);
/*
 * 此文件是 PhpcpdNext 的一部分。
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * 有关完整的版权和许可信息，请查阅随此源代码分发的 LICENSE 文件。
 */
/*
 * 中文翻译。
 *
 * 键、占位符、计数以及通常应包含在此处的内容：请参阅
 * docs/localization.md。
 */

return [
    'frame' => [
        'error'   => '错误：:message',
        'warning' => '警告：:message',
        'hint'    => ":message\n:hint",
        'inFile'  => '在 :file 中：:message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => '未找到配置文件：:path',
        ],
        'unparsable' => [
            'config' => '无法解析配置文件：:path',
        ],
        'unknown' => [
            'option'  => '未知选项 :flag。',
            'setting' => ':file 中存在未知的设置“:name”',
        ],
        'needsValue' => [
            'option'  => '选项 :flag 需要一个值。',
            'setting' => ':file 中的设置“:name”需要一个值。',
        ],
        'takesNoValue' => [
            'option' => '选项 :flag 不接受任何值。',
        ],
        'invalidValue' => [
            'option' => ':flag 的值“:value”无效（允许的值：:allowed）。',
        ],
        'belowFloor' => [
            'minTokens' => '统一引擎（unified engine）要求 :flag 至少为 :floor（给定值：:given）。低于此值时，过滤窗口将小于 4 且索引不再是样本，因此引擎将拒绝服务，而不是默默退回到穷举扫描。',
        ],
        'unwired' => [
            'option' => '选项 :flag 没有关联的设置。',
        ],
        'outOfScope' => [
            'filesystemRoot' => '拒绝扫描文件系统根目录 (:path)。您的意思是 "./" 吗？',
            'aboveProject'   => '拒绝扫描 :path：它位于项目根目录 :project 之上。',
        ],
        'nothingToScan' => [
            'files'       => '未找到要扫描的文件。',
            'afterTriage' => '分类后没有剩余要扫描的文件。',
        ],
        'missingArgument' => [
            'directory' => '未指定目录。',
        ],
        'writeFailed' => [
            'report' => '无法将报告写入 :path：:detail',
        ],
        'writePartial' => [
            'report' => '写入 :path 不完整：字节（:written）／（:total）。',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => '预设“:preset”声明了扫描路径 (:declared)，但缺少 (:missing)：:paths。',
        ],
        'orphan' => [
            'partialProject' => '--orphans 无法查看整个项目。',
            'belowRoots'     => '  每个扫描根目录都位于 :manifest 的 autoload 根目录之下。',
            'uncoveredRoots' => '  在 :manifest 中声明但从未打开的 autoload 路径 (:count)：',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => '检测到 :preset — 已应用预设（使用 --no-preset 禁用）',
        ],
        'cache' => [
            'hit' => '（缓存命中）',
        ],
        'engine' => [
            'tokenbagDeprecated' => '（--algorithm=tokenbag 已弃用；请使用 --algorithm=unified — 它尚未报告 token bag 报告的所有位置，因此仍可选择）',
        ],
        'incremental' => [
            'combined'    => '（在组合模式下忽略 --incremental）',
            'unsupported' => '（忽略 --incremental：只有 rabin-karp 和 unified 算法具有增量索引）',
            'index'       => '（增量索引：复用了 :reused，扫描了 :scanned）',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => '未找到代码克隆。',
            'heading'        => '发现代码克隆（:clones）:gapped:reordered、重复行（:lines）、文件（:files）：',
            'gapped'         => '、不一致（:count）',
            'reorderedCount' => '，重排 (:count)',
            'reordered'      => '[已重排]',
            'unreadable'     => '不可读的文件 (:count) — 不计入总数：',
            'strataAsserted' => '断言 :asserted 个，降级 0 个。',
            'strataSplit'    => '断言 :asserted 个，降级 :demoted 个（:detail）。',
            'settled'         => '因已被描述而丢弃的读取 (:count)。',
            'unfounded'      => '因未获验证而丢弃的结果 (:count)。',
            'hiddenLine'      => '在置信度 :threshold 以下隐藏了 :total 项中的 :count 项 — 用 --hidden 列出。',
            'hiddenHeading'   => '在置信度 :threshold 以下隐藏 (:count):',
            'coverage'         => '扫描行数（:lines）中 :percentage 为重复代码。',
            'literals'         => '[字面量不同（:count）]',
            'confidence'       => '置信度 :score（:terms）',
            'functions'        => '位于 :names',
            'sizes'          => '克隆行数：平均（:average）、最大（:largest）。',
        ],
        'ledger' => [
            'line'      => '账本已确认：降级（:acknowledged）／发现（:total），过期（:stale）。',
            'staleNote' => '已过期（已确认的代码已更改）：:note',
            'wrote'     => '已写入确认信息 (:count)：:path',
        ],
        'config' => [
            'missingPath' => ':path（缺失）',
            'source' => [
                'default'     => '默认',
                'commandLine' => '命令行',
                'builtIn'     => '    内置默认值',
            ],
            'fallback'    => ' 回退到内置默认值',
            'layers'      => '  层级，优先级从低到高：',
        ],
        'run' => [
            'throughput' => ' — 文件（:count），每秒 :rate 个',
            'files'    => ' — 文件（:count）',
            'usage'  => '耗时：:duration，内存：:memory MB',
            'banner' => 'phpcpd :version，作者 :author — 基于 :origin，原作者 :originAuthor。',
        ],
        'scan' => [
            'root'               => '扫描根目录：:root',
            'roots'              => '扫描根目录：',
            'count' => [
                'file'       => '文件 (:count)',
                'directory'  => '根目录 (:count)',
                'pattern'    => '排除项 (:count)',
                'unreadable' => '不可读 (:count)',
                'generated'  => '已生成 (:count)',
            ],
            'counts'             => '已扫描 :counts',
        ],
        'triage' => [
            'nothing'  => '分类：未标记任何文件；每个文件都是程序文本。',
            'removed'  => '分类：文件（:total）、已移除（:removed）',
            'labelled' => '分类：文件（:total）、已标记（:labelled）、未移除',
        ],
        'orphan' => [
            'none'         => '未发现孤立符号 — 符号（:symbols）、文件（:files）。',
            'found'        => '孤立符号 (:count)：',
            'possible'     => '可能的孤立符号 (:count) — 请在移除前检查：',
            'advisory'     => '孤立符号 (:count) — 建议性质，不影响退出码：',
            'notShown'     => '未显示更多孤立符号发现 (:count) — 请运行 --orphans 以进行检查。',
            'suppressed'   => '已压制 (:count)：:census',
            'explainHint'  => '  → 使用 --explain 列出',
            'wholeFile'    => '    ⤷ 整个文件均未连接 — 此处声明的任何符号均未被引用',
            'supersededBy' => '    ⤷ 看起来像是 :name 的替代副本',
            'summary'      => '已扫描符号（:symbols）、文件（:files）；孤立（:orphaned）、可能（:possible）、已压制（:suppressed）、计划中（:planned）。',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => '在项目自身默认排除项保留的文件集之外 — 这是一个生成的树，可能无法证明其连线性（wiring）',
            'declaredHere'  => '在此处声明的符号 (:count)，项目中没有任何地方引用它们',
            'foreignNs'     => '声明了 :namespaces — 这是一个在其上方的任何 composer.json 中均未声明的命名空间，位于它们所连接的任何目录之外',
        ],
        'role' => [
            'noStatements' => '前导码之后没有顶层语句',
            'coupled'      => ':statements 个顶层语句中有 :registrations 个是注册表达式，但它们共享一个变量',
            'independent'  => ':statements 个顶层语句中有 :registrations 个是不依赖数据流的注册表达式',
        ],
        'orphan' => [
            'guard'          => '在存在性保护内声明 — polyfill 或兼容性 shim',
            'entrypoint'     => '在 :namespace 中声明 — 通过框架约定调用',
            'partialProject' => '  当*没有任何东西*引用一个符号时，该符号被称为死代码，这是对整个
  项目的一项断言。此扫描范围之外的代码仍然可能引用此处报告的内容。',
            'evidence' => [
                'nameAt'   => '名称出现在',
                'loopAt'   => '通过循环发现在',
                'suffixAt' => '声明后缀在',
                'namedIn'  => '命名于',
            ],
            'plannedServed'  => '现已被引用 — @phpcpd-planned 已完成其使命，可以移除',
            'manifest'       => '在 composer 的 autoload.files 入口点中声明',
            'foreignNs'      => '在项目自身的命名空间之外声明（兼容性 shim）',
            'fixture'        => '测试夹具（fixture） — 通过路径加载或作为字符串命名，从未被直接引用',
            'discovery'      => '通过目录扫描发现 — 从其文件名在 class_exists 后面实例化',
            'convention'     => '伴随类 — :base 使用了 :trait，后者在运行时通过后缀解析此名称',
            'interface'      => '从未被引用（接口 — 可以在扫描集之外实现）',
            'trait'          => '从未被引用（Trait — 可以被扫描集之外的类使用）',
            'abstract'       => '从未被引用（抽象类 — 可以在扫描集之外扩展）',
            'inString'       => '代码中从未被引用；名称出现在字符串字面量中（可能存在动态使用）',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => '条件声明（polyfill / 兼容性 shim）',
            'fixtures'    => '测试夹具（通过路径或名称加载）',
            'config'      => '在配置文件中注册',
            'template'    => '从模板中引用（blade / twig / latte）',
            'manifest'    => '从 composer.json 中引用',
            'namespace'   => '在项目自身的命名空间之外声明（兼容性 shim）',
            'keep'        => '标记为保留（@api / @phpcpd-keep）',
            'entrypoint'  => '框架入口点（属性 / 测试类）',
            'discovery'   => '通过目录扫描发现（从文件名实例化）',
            'convention'  => '按约定命名的伴随类（由 trait 声明的后缀）',
            'planned'     => '计划中，尚未连接',
            'none'        => '未找到引用',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => '如果您确实是指整个文件系统，请传递 --allow-root-scan。',
            'allowOutside' => '传递 --allow-root-scan 以在项目外部进行扫描。',
        ],
        'orphan' => [
            'scanProjectRoot' => '  扫描项目根目录以获取值得采取行动的结果。',
        ],
        'triage' => [
            'posture' => '  （--triage-posture=discard 作用于标签；--no-triage 跳过此阶段）',
            'explain' => '  （--explain 列出每个文件及其支持或反对的证据）',
        ],
        'clone' => [
            'gapped'  => '近似克隆 — 考虑将分叉的部分参数化或对齐两个副本。',
            'demoted' => '已降级为 :stratum — 这是该层级描述的形式，因此仅在重复不是重点时才提取它。',
            'extract' => '考虑将共享的行提取到可重用的方法、类或 trait 中。',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => '用法：',
            'invocation' => '  phpcpd [选项] <目录>',
        ],
        'group' => [
            'selecting' => '文件选择选项',
            'orphans'   => '孤立代码（死代码）检测',
            'analysing' => '代码分析选项',
            'general'   => '通用选项',
            'reporting' => '报告生成选项',
            'ci'        => 'CI 集成选项',
        ],
        'option' => [
            'suffix'              => '包含名称以 <suffix> 结尾的文件（默认：:default；可重复）',
            'exclude'             => '排除路径中包含 <path> 的文件（可重复）',
            'preset'              => '应用框架预设（例如 laravel）：设置合理的路径、后缀和排除项',
            'triage'              => '在检测前执行第 0 阶段语料库分类（默认启用；此选项显式请求它）',
            'no_triage'           => '完全跳过第 0 阶段：不将任何文件标记为断开连接、被屏蔽（shadowed）、第三方（vendored）或派生的',
            'triage_posture'      => '分类对标记文件的处理方式：从扫描中丢弃（默认）或仅标记而不做其他处理',
            'no_preset'           => '检测到框架预设时不自动应用（检测会自行通告；--preset= 会覆盖它）',
            'no_default_excludes' => '同时扫描默认跳过的生成树和缓存树（vendor、node_modules、.phpstan.cache、build 等）',
            'allow_root_scan'     => '允许从 / 或最近的 composer.json 之上的根目录进行扫描（默认拒绝：`phpcpd /` 通常是 `phpcpd ./` 的拼写错误）',
            'orphans'             => '检测孤立符号（未引用的类、接口、trait、enum、函数）而不是克隆',
            'no_suppress'         => '按名称禁用压制规则，逗号分隔，或使用 "all"（:rules）',
            'fail_on'             => '导致运行以非零代码退出的结果级别，逗号分隔（默认：:default）',
            'explain'             => '列出每个被压制的符号及压制它的规则，而不仅仅是统计数量',
            'rk'                  => '仅 Rabin-Karp（精确/类型 1 克隆；速度更快，无重排检测）。默认同时运行 Rabin-Karp 和 TokenBag。',
            'min_lines'           => '相同行的最小数量（默认：:default）',
            'min_tokens'          => '相同 token 的最小数量（默认：:default）',
            'language'            => '报告语言（默认：:default）',
            'verbose'             => '输出每个克隆的重复代码',
            'algorithm'           => '单算法覆盖（rabin-karp | tokenbag | unified）',
            'min_similarity'      => 'TokenBag 重叠阈值（默认：:default）',
            'raw'                   => '比对原始文本：标识符也必须一致（关闭默认的归一化）',
            'fuzzy'                 => '对名称不敏感的归一化：与默认相同但没有类型锚定（研究用；E2 测得其被支配）',
            'type_anchored'         => '归一化时保持类型关键字具体（默认开启；--fuzzy 会关闭）',
            'min_confidence'        => '仅列出模型评分达到 <log-odds> 及以上的结果；其余仍被计数、可用 --hidden 查看、绝不丢弃，并仍决定 --fail-on',
            'hidden'                => '列出被 --min-confidence 拦下的结果',
            'cache'                 => '将结果缓存到 \'.phpcpd-cache/\' — 命中要求每个文件都未改动，因此它服务于同一提交的重跑而非下一个提交',
            'acknowledged'        => '从 <file> 读取已提交的确认账本：列出的重复项被降级但绝不隐藏，代码更改的条目将过期并被报告',
            'write_acknowledged'  => '将本次运行的发现写入 <file> 作为确认账本，以供审查和提交',
            'log_pmd'             => '以 PMD-CPD XML 格式将日志写入 <file>',
            'log_json'            => '以 JSON 格式将日志写入 <file>',
            'log_sarif'           => '以 SARIF 2.1.0 格式将日志写入 <file>（用于 GitHub Code Scanning）',
            'cache_dir'           => '从 <path> 读取/写入缓存（暗示 --cache；覆盖默认目录）',
            'incremental'           => '按文件增量索引：仅重新分词已更改的文件（rabin-karp 或 unified，不支持组合默认模式；使用缓存目录）',
            'config'              => '从 <file> 读取配置（默认：存在时为 ./phpcpd.ini）；键为长选项名称',
            'show_config'         => '打印生效的配置及其来源并退出',
            'no_config'           => '忽略 ./phpcpd.ini',
            'help'                => '打印此帮助信息',
            'version'             => '打印版本信息',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next 的确认账本（acknowledgment ledger）',
            'what'  => '每一行记录了一处该项目已检查并决定保留的重复代码。
已确认的发现会被降级，绝不隐藏：它仍然会被报告、会被统计，并且仍然会限制退出码。',
            'key'   => '键是重复代码两侧内容的哈希值 — 不包含路径，也不包含行号。
因此，编辑任一副本都会导致条目过期并使发现重新生效，而移动代码则不会产生任何影响。
不再与任何内容匹配的条目将被报告为过期，以便将其删除。',
            'note'  => '制表符后面的文本是人工备注。它绝不用于匹配。',
        ],
    ],
];
