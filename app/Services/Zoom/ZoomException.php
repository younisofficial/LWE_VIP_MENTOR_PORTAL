<?php

namespace App\Services\Zoom;

use RuntimeException;

/** Zoom API problem. The message is safe to show to admins. */
class ZoomException extends RuntimeException {}
