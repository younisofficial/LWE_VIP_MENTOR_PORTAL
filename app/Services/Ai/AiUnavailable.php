<?php

namespace App\Services\Ai;

use RuntimeException;

/** AI is switched off, has no API key, hit the daily limit, or the API failed. Message is safe to show admins. */
class AiUnavailable extends RuntimeException {}
