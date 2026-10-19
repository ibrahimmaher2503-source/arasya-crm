import { existsSync, readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);

if (args.length !== 0 && (args.length !== 2 || args[0] !== '--root' || !args[1])) {
    console.error('Usage: node scripts/check-design-system.mjs [--root <directory>]');
    process.exit(2);
}

const root = path.resolve(args[1] ?? projectRoot);
const requiredFiles = [
    'resources/css/design-system.css',
    'resources/css/ui-forms.css',
    'resources/css/ui-data.css',
    'resources/css/ui-feedback.css',
    'resources/views/components/ui/button.blade.php',
    'resources/views/components/ui/input.blade.php',
    'resources/views/components/ui/table.blade.php',
    'resources/views/components/ui/modal.blade.php',
    'resources/views/components/erp/entity-header.blade.php',
    'resources/views/components/ai/assistant-panel.blade.php',
];
const sourceExtensions = new Set(['.css', '.php', '.html', '.svg', '.js', '.mjs', '.cjs', '.jsx', '.ts', '.tsx', '.vue']);
const namedColors = new Set(`aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen`.split(' '));
const issues = [];

function maskComments(source) {
    return source.replace(/\/\*[\s\S]*?\*\/|<!--[\s\S]*?-->|{{--[\s\S]*?--}}/g, (comment) =>
        comment.replace(/[^\r\n]/g, ' '),
    );
}

function withoutVarFunctions(source) {
    let result = '';

    for (let index = 0; index < source.length;) {
        const match = /^var\s*\(/i.exec(source.slice(index));

        if (!match) {
            result += source[index++];
            continue;
        }

        let depth = 0;
        let end = index + match[0].length - 1;

        for (; end < source.length; end++) {
            if (source[end] === '(') depth++;
            if (source[end] === ')' && --depth === 0) {
                end++;
                break;
            }
        }

        index = end;
    }

    return result;
}

function hasColorLiteral(value, includeNamedColors = false) {
    const withoutUrls = value.replace(/\burl\s*\([^)]*\)/gi, '');

    if (/#(?:[\da-f]{3}|[\da-f]{4}|[\da-f]{6}|[\da-f]{8})(?![\da-f])/i.test(withoutUrls)) {
        return true;
    }

    const functions = /\b(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color|device-cmyk)\s*\(/gi;

    for (const match of withoutUrls.matchAll(functions)) {
        const open = match.index + match[0].lastIndexOf('(');
        let depth = 1;
        let end = open + 1;

        for (; end < withoutUrls.length && depth > 0; end++) {
            if (withoutUrls[end] === '(') depth++;
            if (withoutUrls[end] === ')') depth--;
        }

        const channels = withoutUrls.slice(open + 1, end - 1).split('/')[0];

        if (/\d/.test(withoutVarFunctions(channels))) {
            return true;
        }
    }

    if (includeNamedColors) {
        const words = withoutVarFunctions(withoutUrls).match(/[a-z]+/gi) ?? [];

        if (words.some((word) => namedColors.has(word.toLowerCase()))) return true;
    }

    return false;
}

function declarationMatches(source) {
    return [...maskComments(source).matchAll(/(?:^|[;{])\s*([\w-]+)\s*:\s*([^;{}]+)/g)];
}

function lineAt(source, index) {
    return source.slice(0, index).split(/\r?\n/).length;
}

function report(source, file, index, message) {
    issues.push(`${file}:${lineAt(source, index)} ${message}`);
}

function checkCss(source, file, offset = 0, lineSource = source) {
    for (const match of declarationMatches(source)) {
        const declarationStart = match.index + match[0].indexOf(match[1]);
        const colorProperty = /^(?:color|background(?:-color|-image)?|border(?:-(?:top|right|bottom|left))?(?:-color)?|outline(?:-color)?|text-decoration-color|text-emphasis-color|column-rule-color|caret-color|accent-color|fill|stroke|stop-color|flood-color|lighting-color|box-shadow|text-shadow|filter)$/i.test(match[1]);

        if (hasColorLiteral(match[2], colorProperty)) {
            report(lineSource, file, offset + declarationStart, 'contains a hardcoded theme color; use a design-system token.');
        }

        if (file !== 'resources/css/design-system.css'
            && /^--(?:app|content|container|layout|main-content|page|shell|workspace)(?:-[\w-]+)*(?:width|height|size)$/i.test(match[1])) {
            report(lineSource, file, offset + declarationStart, 'defines a layout sizing token outside design-system.css.');
        }
    }
}

function checkMarkup(source, file) {
    const uncommented = maskComments(source);

    for (const match of uncommented.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style\s*>/gi)) {
        const content = match[1];
        checkCss(content, file, match.index + match[0].indexOf(content), source);
    }

    for (const match of uncommented.matchAll(/(?:^|\s)(style|fill|stroke|stop-color|flood-color|lighting-color)\s*=\s*(?:"([^"]*)"|'([^']*)')/gi)) {
        const value = match[2] ?? match[3];
        const valueStart = match.index + match[0].lastIndexOf(value);

        if (match[1].toLowerCase() === 'style') {
            checkCss(value, file, valueStart, source);
        } else if (hasColorLiteral(value)) {
            report(source, file, valueStart, 'contains a hardcoded theme color; use a design-system token.');
        }
    }

    for (const match of uncommented.matchAll(/@style\s*\(([^)]*)\)/gi)) {
        if (hasColorLiteral(match[1])) {
            report(source, file, match.index, 'contains a hardcoded theme color; use a design-system token.');
        }
    }
}

function checkJavaScript(source, file) {
    const uncommented = maskComments(source).replace(/^[\t ]*\/\/[^\r\n]*/gm, (comment) => comment.replace(/[^\r\n]/g, ' '));
    const styleValues = /\.style(?:\.[\w$-]+)?\s*=\s*(["'`])([^"'`]*?)\1|\.style\.setProperty\s*\(\s*["'`][^"'`]+["'`]\s*,\s*(["'`])([^"'`]*?)\3/gi;
    const themeValues = /["']?[\w$-]*(?:color|theme|palette|background|fill|stroke|primary|secondary|success|warning|danger|error|info|surface|text|border|focus|accent)[\w$-]*["']?\s*[:=]\s*(["'`])([^"'`]*?)\1/gi;
    const reported = new Set();

    for (const [pattern, valueIndex] of [[styleValues, 2], [themeValues, 2]]) {
        for (const match of uncommented.matchAll(pattern)) {
            const value = match[valueIndex] ?? match[valueIndex + 2];

            const line = lineAt(source, match.index);

            if (hasColorLiteral(value, pattern === themeValues) && !reported.has(line)) {
                report(source, file, match.index, 'contains a hardcoded theme color; use a design-system token.');
                reported.add(line);
            }
        }
    }
}

function collectSources(directory) {
    const absolute = path.join(root, directory);

    if (!existsSync(absolute)) return [];

    const files = [];
    const visit = (current) => {
        for (const entry of readdirSync(current, { withFileTypes: true })) {
            const candidate = path.join(current, entry.name);

            if (entry.isDirectory()) visit(candidate);
            else if (entry.isFile() && sourceExtensions.has(path.extname(entry.name).toLowerCase())) files.push(candidate);
        }
    };

    visit(absolute);

    return files;
}

for (const required of requiredFiles) {
    if (!existsSync(path.join(root, required))) issues.push(`Missing required design-system file: ${required}`);
}

for (const directory of ['resources/views', 'resources/css', 'resources/js']) {
    for (const absolute of collectSources(directory)) {
        const file = path.relative(root, absolute).split(path.sep).join('/');

        if (file === 'resources/css/design-system.css') continue;

        const source = readFileSync(absolute, 'utf8');

        if (file.endsWith('.css')) checkCss(source, file);
        if (/\.(?:blade\.php|php|html|svg|js|mjs|cjs|jsx|ts|tsx|vue)$/i.test(file)) checkMarkup(source, file);
        if (/\.(?:js|mjs|cjs|jsx|ts|tsx|vue)$/i.test(file)) checkJavaScript(source, file);
    }
}

if (issues.length > 0) {
    console.error(`Design-system check failed:\n${issues.map((issue) => `- ${issue}`).join('\n')}`);
    process.exitCode = 1;
} else {
    console.log('Design-system check passed.');
}
