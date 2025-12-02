<?php
// Build vanilla PHP project into /build from file-based pages

$rootDir = __DIR__;
$pagesDir = $rootDir . '/pages';
$buildDir = $rootDir . '/build';

if (!is_dir($pagesDir)) {
    fwrite(STDERR, "[vanilla] pages directory not found: {$pagesDir}\n");
    exit(1);
}

// Bootstrap application environment so we can render pages to static HTML
require_once $rootDir . '/vendor/autoload.php';
require_once $rootDir . '/includes/CoreFunction.php';
require_once $rootDir . '/includes/Database.php';
require_once $rootDir . '/includes/Layout.php';
require_once $rootDir . '/global.php';

session_start();
$config = getConfig();
if (!defined('URL')) {
    define('URL', $config['web']['url']);
}

// Global param container used when rendering dynamic pages during build
$GLOBALS['__VANILLA_BUILD_PARAMS__'] = [];

if (!function_exists('useParams')) {
    function useParams()
    {
        return $GLOBALS['__VANILLA_BUILD_PARAMS__'] ?? [];
    }
}

function rrmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function rcopy($src, $dst)
{
    if (!is_dir($src)) {
        return;
    }
    if (!is_dir($dst)) {
        mkdir($dst, 0777, true);
    }
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($srcPath)) {
            rcopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

// Fresh build directory
if (is_dir($buildDir)) {
    rrmdir($buildDir);
}
mkdir($buildDir, 0777, true);

// Copy core folders/files needed for a standalone vanilla project
$dirsToCopy = [
    'controllers',
    'components',
    'composables',
    'includes',
    'layouts',
    'api',
    'protect',
    'public',
    'vendor',
    'pages',
];

foreach ($dirsToCopy as $dir) {
    $src = $rootDir . DIRECTORY_SEPARATOR . $dir;
    $dst = $buildDir . DIRECTORY_SEPARATOR . $dir;
    if (is_dir($src)) {
        rcopy($src, $dst);
        echo "[vanilla] copied directory {$dir}\n";
    }
}

$filesToCopy = [
    'composer.json',
    'config.php',
    'global.php',
];

foreach ($filesToCopy as $file) {
    $src = $rootDir . DIRECTORY_SEPARATOR . $file;
    $dst = $buildDir . DIRECTORY_SEPARATOR . $file;
    if (file_exists($src)) {
        copy($src, $dst);
        echo "[vanilla] copied file {$file}\n";
    }
}

// Optionally copy .env if present at project root
$envSrc = $rootDir . DIRECTORY_SEPARATOR . '.env';
$envDst = $buildDir . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envSrc)) {
    copy($envSrc, $envDst);
    echo "[vanilla] copied .env\n";
}

/**
 * Render a page (from pages/) to full HTML using the Layout system.
 * Used for non-dynamic routes so the output file has ready-to-serve HTML.
 */
function renderStaticPage($rootDir, $pageRelativePath)
{
    // Ensure forward slashes
    $pageRelativePath = str_replace('\\', '/', $pageRelativePath);

    // Reload config for fresh layout defaults
    $config = getConfig();

    $layout = \App\includes\Layout::getInstance();
    $db = \App\includes\Database::getInstance();

    // Reset layout state for this page
    if (isset($config['layout']['default_head'])) {
        $layout->setHead($config['layout']['default_head']);
    } else {
        $layout->setHead('');
    }
    $layout->setContent('');

    // Helper used by pages to set <head> content
    $setHead = function ($headContent) use ($layout) {
        $layout->setHead($headContent);
    };

    // Render page content into layout
    ob_start();
    include $rootDir . '/pages/' . $pageRelativePath;
    $content = ob_get_clean();
    $layout->setContent($content);

    return $layout->render();
}

/**
 * Render a dynamic page ([param] routes) into a static template file
 * that contains full HTML and only minimal PHP for reading query params.
 */
function renderDynamicPageToStatic($rootDir, $pageRelativePath, array $paramNames, $currentDestRelative)
{
    $placeholders = [];
    foreach ($paramNames as $name) {
        $upper = strtoupper($name);
        $placeholders[$name] = "__VANILLA_PARAM_{$upper}__";
    }

    $GLOBALS['__VANILLA_BUILD_PARAMS__'] = $placeholders;
    foreach ($placeholders as $name => $value) {
        $_GET[$name] = $value;
    }

    $html = renderStaticPage($rootDir, $pageRelativePath);
    global $pagesDir;
    $html = rewriteHtmlUrls($html, $pagesDir, $currentDestRelative);

    $header = "<?php\n";
    foreach ($paramNames as $name) {
        $header .= "\$" . $name . " = \\filter_input(INPUT_GET, '" . $name . "');\n";
    }
    $header .= "?>\n";

    $search = [];
    $replace = [];
    foreach ($paramNames as $name) {
        $ph = $placeholders[$name];
        $expr = "<?= htmlspecialchars(\$" . $name . " ?? '') ?>";
        $search[] = $ph;
        $replace[] = $expr;
    }

    $body = str_replace($search, $replace, $html);

    return $header . $body;
}

function findGetParamsInFile($filePath)
{
    $code = @file_get_contents($filePath);
    if ($code === false) {
        return [];
    }

    $names = [];
    if (preg_match_all('/\$_GET\s*\[\s*[\'\"]([a-zA-Z0-9_]+)[\'\"]\s*\]/', $code, $matches)) {
        foreach ($matches[1] as $name) {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function mapPageToVanilla($relativePath)
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if (substr($relativePath, -4) === '.php') {
        $withoutExt = substr($relativePath, 0, -4);
    } else {
        $withoutExt = $relativePath;
    }

    $segments = $withoutExt === '' ? [] : explode('/', $withoutExt);
    $paramNames = [];
    $staticSegments = [];

    foreach ($segments as $seg) {
        if (preg_match('/^\\[(.+)\\]$/', $seg, $m)) {
            $paramNames[] = $m[1];
        } else {
            $staticSegments[] = $seg;
        }
    }

    $hasDynamic = !empty($paramNames);

    if (!$hasDynamic) {
        // No dynamic segment: keep structure as-is
        $dest = $relativePath;
        return [$dest, []];
    }

    // Dynamic route mapping rules based on user's examples:
    // - pages/product/[id].php        -> build/product.php
    // - pages/type/[name]/[id].php    -> build/type/index.php

    if (empty($staticSegments)) {
        $dest = 'index.php';
    } elseif (count($staticSegments) === 1 && count($paramNames) === 1) {
        // Single static + single param -> flatten to product.php
        $dest = $staticSegments[0] . '.php';
    } else {
        // Multiple params or deeper nesting -> type/index.php style
        $dest = implode('/', $staticSegments) . '/index.php';
    }

    return [$dest, $paramNames];
}

function matchRoutePathToPage($requestPath, $pagesDir)
{
    $requestPath = trim($requestPath, '/');
    $segments = $requestPath === '' ? ['index'] : explode('/', $requestPath);
    $currentPath = $pagesDir;
    $paramValues = [];

    if (count($segments) === 1 && $segments[0] === 'index') {
        $indexFile = $currentPath . '/index.php';
        if (file_exists($indexFile)) {
            $relative = substr($indexFile, strlen($pagesDir) + 1);
            return [$relative, $paramValues];
        }
    }

    $paramPattern = '/^\[(.*?)\]$/';
    $segmentCount = count($segments);

    for ($i = 0; $i < $segmentCount; $i++) {
        $segment = $segments[$i];
        $isLast = ($i === $segmentCount - 1);
        $found = false;

        if ($isLast) {
            $filePath = $currentPath . '/' . $segment . '.php';
            if (file_exists($filePath)) {
                $relative = substr($filePath, strlen($pagesDir) + 1);
                return [$relative, $paramValues];
            }

            $indexPath = $currentPath . '/' . $segment . '/index.php';
            if (file_exists($indexPath)) {
                $relative = substr($indexPath, strlen($pagesDir) + 1);
                return [$relative, $paramValues];
            }
        }

        $dirPath = $currentPath . '/' . $segment;
        if (is_dir($dirPath)) {
            $currentPath = $dirPath;
            continue;
        }

        $items = scandir($currentPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemName = pathinfo($item, PATHINFO_FILENAME);
            if (preg_match($paramPattern, $itemName, $matches)) {
                $paramName = $matches[1];
                $paramValues[$paramName] = $segment;

                if ($isLast && substr($item, -4) === '.php') {
                    $filePath = $currentPath . '/' . $item;
                    $relative = substr($filePath, strlen($pagesDir) + 1);
                    return [$relative, $paramValues];
                }

                $paramPath = $currentPath . '/' . $item;
                if (!$isLast && is_dir($paramPath)) {
                    $currentPath = $paramPath;
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            return null;
        }
    }

    $indexFile = $currentPath . '/index.php';
    if (file_exists($indexFile)) {
        $relative = substr($indexFile, strlen($pagesDir) + 1);
        return [$relative, $paramValues];
    }

    return null;
}

function makeRelativePath($from, $to)
{
    $from = str_replace('\\', '/', trim($from, '/'));
    $to = str_replace('\\', '/', trim($to, '/'));

    $fromParts = $from === '' ? [] : explode('/', $from);
    $toParts = $to === '' ? [] : explode('/', $to);

    // Remove filename from source path
    if (!empty($fromParts)) {
        array_pop($fromParts);
    }

    $length = min(count($fromParts), count($toParts));
    $common = 0;
    for ($i = 0; $i < $length; $i++) {
        if ($fromParts[$i] !== $toParts[$i]) {
            break;
        }
        $common++;
    }

    $up = array_fill(0, count($fromParts) - $common, '..');
    $down = array_slice($toParts, $common);

    $relativeParts = array_merge($up, $down);
    $rel = implode('/', $relativeParts);

    // If same directory/file, just return basename
    if ($rel === '') {
        return basename($to);
    }

    return $rel;
}

function rewriteInternalUrl($url, $pagesDir, $currentDestRelative)
{
    $trimmed = trim($url);
    if ($trimmed === '' || $trimmed[0] === '#') {
        return $url;
    }
    if (preg_match('~^(https?:)?//~i', $trimmed)) {
        return $url;
    }
    if (preg_match('~^(mailto:|tel:|javascript:)~i', $trimmed)) {
        return $url;
    }

    $parsed = parse_url($trimmed);
    if ($parsed === false) {
        return $url;
    }

    $path = $parsed['path'] ?? '';
    if ($path === '' || $path === '/') {
        return $url;
    }

    // Route matching always uses a path relative to pages/ root
    $relativePath = ltrim($path, '/');

    $route = matchRoutePathToPage($relativePath, $pagesDir);
    if ($route === null) {
        return $url;
    }

    list($pageRelative, $routeParams) = $route;
    list($destRelative, $paramNames) = mapPageToVanilla($pageRelative);

    $queryParams = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
    }

    $merged = array_merge($routeParams, $queryParams);
    $qs = http_build_query($merged);

    // Make URL relative to the current built file so project can live under any base path
    $newPath = makeRelativePath($currentDestRelative, $destRelative);
    if ($qs !== '') {
        $newPath .= '?' . $qs;
    }
    if (!empty($parsed['fragment'])) {
        $newPath .= '#' . $parsed['fragment'];
    }

    return $newPath;
}

function rewriteHtmlUrls($html, $pagesDir, $currentDestRelative)
{
    $pattern = '/\b(href|action)\s*=\s*([\'\"])([^\'\"]*)([\'\"])/i';
    $callback = function ($matches) use ($pagesDir, $currentDestRelative) {
        $attr = $matches[1];
        $quote = $matches[2];
        $url = $matches[3];
        $newUrl = rewriteInternalUrl($url, $pagesDir, $currentDestRelative);
        return $attr . '=' . $quote . $newUrl . $quote;
    };

    return preg_replace_callback($pattern, $callback, $html);
}

function generateEntrypoint($destPath, $pageRelativePath, array $paramNames)
{
    $destPath = str_replace('\\', '/', $destPath);
    $pageRelativePath = str_replace('\\', '/', $pageRelativePath);

    $depth = substr_count($destPath, '/');

    $code = "<?php\n";
    $code .= "\$root = __DIR__;\n";
    if ($depth > 0) {
        $code .= "\$root = dirname(\$root, {$depth});\n";
    }
    $code .= "require_once \$root . '/vendor/autoload.php';\n";
    $code .= "require_once \$root . '/config.php';\n";
    $code .= "require_once \$root . '/includes/CoreFunction.php';\n";
    $code .= "require_once \$root . '/includes/Database.php';\n";
    $code .= "require_once \$root . '/includes/Layout.php';\n";
    $code .= "require_once \$root . '/includes/VanillaUrl.php';\n";
    $code .= "require_once \$root . '/global.php';\n";
    $code .= "\n";
    $code .= "use App\\includes\\Layout;\n";
    $code .= "use App\\includes\\Database;\n";
    $code .= "\n";
    $code .= "session_start();\n";
    $code .= "\$config = getConfig();\n";
    $code .= "if (!defined('URL')) {\n";
    $code .= "    define('URL', \$config['web']['url']);\n";
    $code .= "}\n";
    $code .= "\n";
    $code .= "\$layout = Layout::getInstance();\n";
    $code .= "\$db = Database::getInstance();\n";
    $code .= "\n";
    $code .= "\$setHead = function (\$headContent) use (\$layout) {\n";
    $code .= "    \$layout->setHead(\$headContent);\n";
    $code .= "};\n";
    $code .= "\n";

    if (!empty($paramNames)) {
        $export = var_export(array_values($paramNames), true);
        $code .= "if (!function_exists('useParams')) {\n";
        $code .= "    function useParams() {\n";
        $code .= "        \$allowed = {$export};\n";
        $code .= "        \$params = [];\n";
        $code .= "        foreach (\$allowed as \$key) {\n";
        $code .= "            if (isset(\$_GET[\$key])) {\n";
        $code .= "                \$params[\$key] = \\filter_input(INPUT_GET, \$key, FILTER_SANITIZE_STRING);\n";
        $code .= "            }\n";
        $code .= "        }\n";
        $code .= "        return \$params;\n";
        $code .= "    }\n";
        $code .= "}\n\n";
    }

    $code .= "ob_start();\n";
    $code .= "include \$root . '/pages/{$pageRelativePath}';\n";
    $code .= "\$content = ob_get_clean();\n";
    $code .= "\$layout->setContent(\$content);\n";
    $code .= "\$html = \$layout->render();\n";
    $code .= "\$html = vanilla_rewriteHtmlUrls(\$html, \$root . '/pages', '" . addslashes($destPath) . "');\n";
    $code .= "echo \$html;\n";
    $code .= "";

    return $code;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isDir()) {
        continue;
    }
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $fullPath = $file->getPathname();
    $relative = str_replace($pagesDir . DIRECTORY_SEPARATOR, '', $fullPath);

    list($destRelative, $paramNames) = mapPageToVanilla($relative);

    $pageGetParams = findGetParamsInFile($fullPath);
    $allParamNames = array_values(array_unique(array_merge($paramNames, $pageGetParams)));

    $destFull = $buildDir . DIRECTORY_SEPARATOR . $destRelative;
    $destDir = dirname($destFull);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }

    // Generate a PHP entrypoint so original page/layout PHP (including DB access)
    // still runs at runtime instead of being baked into static HTML.
    $entryCode = generateEntrypoint($destRelative, $relative, $allParamNames);
    file_put_contents($destFull, $entryCode);
    echo "[vanilla] built entry {$relative} -> build/{$destRelative}\n";
}

echo "[vanilla] build completed in {$buildDir}\n";
