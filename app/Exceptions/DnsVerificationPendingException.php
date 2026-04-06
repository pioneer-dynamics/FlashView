<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class DnsVerificationPendingException extends Exception implements ShouldntReport {}
