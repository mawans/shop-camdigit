<?php
/**
 * WHMCS runtime stubs — LOCAL ONLY. Do NOT upload to server.
 *
 * These declarations exist only so IDEs (Intelephense, etc.) can resolve
 * WHMCS core functions that are defined at runtime and not visible statically.
 */

/**
 * Register a WHMCS hook callback.
 *
 * @param string   $hookPoint The hook point name.
 * @param int      $priority  Execution priority (lower = earlier).
 * @param callable $callback  The callback to invoke.
 * @return mixed
 */
function add_hook(string $hookPoint, int $priority, callable $callback): mixed
{
    return null;
}
