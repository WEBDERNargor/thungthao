<?php
function pre_r($value)
{
    echo "<pre>";
    print_r($value);
    echo "</pre>";
}
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        $value = trim($value, "'\"");

        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function getConfig()
{
    $config = require __DIR__ . '/../config.php';
    return $config;
}

function composables($path)
{
    $file = __DIR__ . "/../composables/" . $path . ".php";
    if (!file_exists($file)) {
        throw new Exception("Composable not found: $path");
    }
    return require($file);
}

function getcookie($name)
{
    return $_COOKIE[$name] ?? null;
}
function setcookies($name, $value, $days)
{
    setcookie($name, $value, time() + $days * 24 * 60 * 60, "/");
}
function deletecookie($name)
{
    setcookie($name, "", time() - 1, "/");
}

function show_error_log($value)
{
    $mode = $_ENV['WEB_MODE'] ?? getenv('WEB_MODE') ?? null;
    if ($mode === 'development') {
        error_log($value);
    }
}

function useParams()
{
    if (!empty($GLOBALS['__VANILLA_BUILD_PARAMS__']) && is_array($GLOBALS['__VANILLA_BUILD_PARAMS__'])) {
        return $GLOBALS['__VANILLA_BUILD_PARAMS__'];
    }

    global $router;

    if (isset($router) && method_exists($router, 'getParams')) {
        return $router->getParams();
    }

    return $_GET ?? [];
}
