<?php

// Helper functions for vanilla build/runtime URL rewriting.
// These work without Apache/Nginx rewrite rules by mapping
// router-style paths (e.g. /product/123) to built PHP entry files
// (e.g. product.php?id=123).

function vanilla_mapPageToEntry($relativePath)
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
        if (preg_match('/^\[(.+)\]$/', $seg, $m)) {
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

function vanilla_matchRoutePathToPage($requestPath, $pagesDir)
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

function vanilla_makeRelativePath($from, $to)
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

function vanilla_rewriteInternalUrl($url, $pagesDir, $currentDestRelative)
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

    $route = vanilla_matchRoutePathToPage($relativePath, $pagesDir);
    if ($route === null) {
        return $url;
    }

    list($pageRelative, $routeParams) = $route;
    list($destRelative, $paramNames) = vanilla_mapPageToEntry($pageRelative);

    $queryParams = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
    }

    $merged = array_merge($routeParams, $queryParams);
    $qs = http_build_query($merged);

    // Make URL relative to the current built file so project can live under any base path
    $newPath = vanilla_makeRelativePath($currentDestRelative, $destRelative);
    if ($qs !== '') {
        $newPath .= '?' . $qs;
    }
    if (!empty($parsed['fragment'])) {
        $newPath .= '#' . $parsed['fragment'];
    }

    return $newPath;
}

function vanilla_rewriteHtmlUrls($html, $pagesDir, $currentDestRelative)
{
    $pattern = '/\b(href|action)\s*=\s*([\'\"])([^\'\"]*)([\'\"])/i';
    $callback = function ($matches) use ($pagesDir, $currentDestRelative) {
        $attr = $matches[1];
        $quote = $matches[2];
        $url = $matches[3];
        $newUrl = vanilla_rewriteInternalUrl($url, $pagesDir, $currentDestRelative);
        return $attr . '=' . $quote . $newUrl . $quote;
    };

    return preg_replace_callback($pattern, $callback, $html);
}
