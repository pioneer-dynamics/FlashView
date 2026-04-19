<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

class WebhookDeliveryFailedException extends \RuntimeException implements ShouldntReport {}
