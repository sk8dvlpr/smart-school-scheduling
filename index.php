<?php

/**
 * Shared-hosting fallback entry when DocumentRoot cannot be set to /public.
 * Prefer configuring the vhost to use /public instead of this file.
 */
require __DIR__ . '/public/index.php';
